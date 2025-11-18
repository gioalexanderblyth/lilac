<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/config.php';

if (!function_exists('getallheaders')) {
    function getallheaders() {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }
}

$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

if (!$authHeader || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$token = $matches[1];

session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['token']) || $_SESSION['token'] !== $token) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Invalid token']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$awardCategory = $_GET['category'] ?? null;
$awardId = $_GET['award_id'] ?? null;
$listAll = $_GET['list_all'] ?? null;

try {
    $pdo = getDatabaseConnection();

    if ($pdo instanceof FileBasedDatabase) {
        echo json_encode(['success' => false, 'error' => 'Database required']);
        exit();
    }

    if ($method === 'GET' && $listAll === 'true') {
        // Get all award criteria with applicant counts
        $stmt = $pdo->query("
            SELECT
                ac.id,
                ac.category_name,
                ac.award_type,
                ac.description,
                ac.requirements,
                ac.keywords,
                ac.status,
                COUNT(DISTINCT aa.award_id) as total_applicants,
                SUM(CASE WHEN a.status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN a.status = 'approved' THEN 1 ELSE 0 END) as recognized_count,
                SUM(CASE WHEN a.status = 'analyzed' THEN 1 ELSE 0 END) as processed_count
            FROM award_criteria ac
            LEFT JOIN award_analysis aa ON ac.category_name = aa.predicted_category
            LEFT JOIN awards a ON aa.award_id = a.id
            WHERE ac.status = 'active'
            GROUP BY ac.id
            ORDER BY ac.category_name
        ");
        $allCriteria = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Parse requirements JSON
        foreach ($allCriteria as &$criteria) {
            if ($criteria['requirements']) {
                $reqs = json_decode($criteria['requirements'], true);
                $criteria['requirements_array'] = is_array($reqs) ? $reqs : [];
                $criteria['requirements_count'] = is_array($reqs) ? count($reqs) : 0;
            } else {
                $criteria['requirements_array'] = [];
                $criteria['requirements_count'] = 0;
            }
        }

        echo json_encode([
            'success' => true,
            'criteria' => $allCriteria
        ]);
        exit();

    } elseif ($method === 'GET' && $awardCategory) {
        // Get all users who applied for this award category
        $stmt = $pdo->prepare("
            SELECT
                a.id as award_id,
                a.user_id,
                a.title as submission_title,
                a.description,
                a.file_name,
                a.file_path,
                a.file_type,
                a.status as award_status,
                a.created_at,
                u.username,
                u.email,
                aa.predicted_category,
                aa.match_percentage,
                aa.all_matches,
                aa.status as analysis_status
            FROM awards a
            LEFT JOIN users u ON a.user_id = u.id
            LEFT JOIN award_analysis aa ON a.id = aa.award_id
            WHERE aa.predicted_category = ?
            ORDER BY aa.match_percentage DESC, a.created_at DESC
        ");
        $stmt->execute([$awardCategory]);
        $applicants = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Process each applicant
        $processedApplicants = [];
        $stats = [
            'total' => count($applicants),
            'pending' => 0,
            'recognized' => 0,
            'processed' => 0,
            'under_review' => 0
        ];

        foreach ($applicants as $applicant) {
            // Parse analysis data
            if ($applicant['all_matches']) {
                $matches = json_decode($applicant['all_matches'], true);
                if ($matches && is_array($matches)) {
                    // Handle two data formats:
                    // Format 1 (new): {'matched': [...], 'missing': [...], 'match_count': N, 'total_keywords': N}
                    // Format 2 (old): [{'met_criteria': [...], 'unmet_criteria': [...], 'criteria_met': N, ...}]

                    if (isset($matches['matched'])) {
                        // New format
                        $applicant['matched_criteria'] = $matches['matched'] ?? [];
                        $applicant['unmatched_criteria'] = $matches['missing'] ?? [];
                        $applicant['criteria_met'] = $matches['match_count'] ?? 0;
                        $applicant['criteria_total'] = $matches['total_keywords'] ?? 0;
                    } elseif (isset($matches[0])) {
                        // Old format (array with first element)
                        $firstMatch = $matches[0];
                        $applicant['matched_criteria'] = $firstMatch['met_criteria'] ?? [];
                        $applicant['unmatched_criteria'] = $firstMatch['unmet_criteria'] ?? [];
                        $applicant['criteria_met'] = $firstMatch['criteria_met'] ?? 0;
                        $applicant['criteria_total'] = $firstMatch['criteria_total'] ?? 0;
                    }

                    $applicant['similarity_score'] = $applicant['match_percentage'] / 100;
                }
            }

            // Count status (map ENUM values to display names)
            $status = strtolower($applicant['award_status'] ?? 'pending');
            if ($status === 'approved') {
                $stats['recognized']++;
            } elseif ($status === 'analyzed') {
                $stats['processed']++;
            } elseif ($status === 'pending') {
                $stats['pending']++;
            } else {
                $stats['pending']++;
            }

            // Remove raw JSON
            unset($applicant['all_matches']);
            $processedApplicants[] = $applicant;
        }

        echo json_encode([
            'success' => true,
            'category' => $awardCategory,
            'applicants' => $processedApplicants,
            'stats' => $stats
        ]);

    } elseif (($method === 'POST' || $method === 'PUT') && $awardId) {
        // Update specific user's award status
        $data = json_decode(file_get_contents('php://input'), true);
        $newStatus = $data['status'] ?? null;

        // Map display status to ENUM values
        $statusMap = [
            'pending' => 'pending',
            'recognized' => 'approved',
            'processed' => 'analyzed'
        ];

        if (!isset($statusMap[$newStatus])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid status']);
            exit();
        }

        $dbStatus = $statusMap[$newStatus];

        // Update award status
        $stmt = $pdo->prepare("UPDATE awards SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$dbStatus, $awardId]);

        echo json_encode([
            'success' => true,
            'message' => 'Status updated successfully',
            'new_status' => $newStatus
        ]);

    } elseif ($method === 'DELETE') {
        // Bulk delete awards
        $data = json_decode(file_get_contents('php://input'), true);
        $awardIds = $data['award_ids'] ?? [];

        if (empty($awardIds) || !is_array($awardIds)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'No award IDs provided']);
            exit();
        }

        // Sanitize IDs
        $awardIds = array_values(array_filter(array_map('intval', $awardIds)));
        if (empty($awardIds)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid award IDs provided']);
            exit();
        }

        // Prepare placeholders for IN clause
        $placeholders = str_repeat('?,', count($awardIds) - 1) . '?';
        
        // Get file paths before deleting (to clean up files)
        $stmt = $pdo->prepare("SELECT file_path FROM awards WHERE id IN ($placeholders)");
        $stmt->execute($awardIds);
        $filesToDelete = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Delete award analysis records first (foreign key constraint)
        $stmt = $pdo->prepare("DELETE FROM award_analysis WHERE award_id IN ($placeholders)");
        $stmt->execute($awardIds);

        // Delete awards
        $stmt = $pdo->prepare("DELETE FROM awards WHERE id IN ($placeholders)");
        $stmt->execute($awardIds);
        
        $deletedCount = $stmt->rowCount();

        // Clean up files
        foreach ($filesToDelete as $filePath) {
            if ($filePath && file_exists($filePath)) {
                @unlink($filePath);
            }
        }

        echo json_encode([
            'success' => true,
            'message' => "Successfully deleted $deletedCount award(s)",
            'deleted_count' => $deletedCount
        ]);

    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid request']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
