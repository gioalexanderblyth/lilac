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
        // Ensure deleted_at column exists
        try {
            $pdo->exec("ALTER TABLE awards ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL");
        } catch (PDOException $e) {
            // Column might already exist, ignore
        }
        
        // Get all award criteria with applicant counts (excluding deleted awards)
        $stmt = $pdo->query("
            SELECT
                ac.id,
                ac.category_name,
                ac.award_type,
                ac.description,
                ac.requirements,
                ac.keywords,
                ac.status,
                COUNT(DISTINCT CASE WHEN a.deleted_at IS NULL THEN aa.award_id END) as total_applicants,
                SUM(CASE WHEN a.deleted_at IS NULL AND a.status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN a.deleted_at IS NULL AND a.status = 'approved' THEN 1 ELSE 0 END) as recognized_count,
                SUM(CASE WHEN a.deleted_at IS NULL AND a.status = 'analyzed' THEN 1 ELSE 0 END) as processed_count
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

    } elseif ($method === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_details') {
        // Get details (detected text) for a specific award analysis
        $awardId = $_GET['award_id'] ?? 0;
        if (!$awardId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Award ID required']);
            exit();
        }

        $stmt = $pdo->prepare("SELECT detected_text FROM award_analysis WHERE award_id = ?");
        $stmt->execute([$awardId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            echo json_encode(['success' => true, 'detected_text' => $result['detected_text']]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Analysis not found']);
        }
        exit();

    } elseif (($method === 'POST' || $method === 'PUT') && isset($_GET['action']) && $_GET['action'] === 'toggle_criteria') {
        // Toggle a criteria between matched and unmatched
        $data = json_decode(file_get_contents('php://input'), true);
        $awardId = $data['award_id'] ?? 0;
        $criteria = $data['criteria'] ?? '';
        $moveToMatched = $data['move_to_matched'] ?? true; // true = move to matched, false = move to unmatched

        if (!$awardId || !$criteria) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Award ID and criteria required']);
            exit();
        }

        // Get current analysis data
        $stmt = $pdo->prepare("SELECT all_matches FROM award_analysis WHERE award_id = ?");
        $stmt->execute([$awardId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result || !$result['all_matches']) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Analysis not found']);
            exit();
        }

        $matches = json_decode($result['all_matches'], true);
        if (!$matches) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid analysis data']);
            exit();
        }

        // Handle both new and old formats
        $matched = [];
        $missing = [];
        $totalKeywords = 0;

        if (isset($matches['matched'])) {
            // New format
            $matched = $matches['matched'] ?? [];
            $missing = $matches['missing'] ?? [];
            $totalKeywords = $matches['total_keywords'] ?? (count($matched) + count($missing));
        } elseif (isset($matches[0])) {
            // Old format
            $firstMatch = $matches[0];
            $matched = $firstMatch['met_criteria'] ?? [];
            $missing = $firstMatch['unmet_criteria'] ?? [];
            $totalKeywords = $firstMatch['criteria_total'] ?? (count($matched) + count($missing));
        }

        // Remove from both arrays first (in case it exists in both)
        $matched = array_values(array_filter($matched, function($c) use ($criteria) {
            return $c !== $criteria;
        }));
        $missing = array_values(array_filter($missing, function($c) use ($criteria) {
            return $c !== $criteria;
        }));

        // Add to the appropriate array
        if ($moveToMatched) {
            $matched[] = $criteria;
        } else {
            $missing[] = $criteria;
        }

        // Recalculate match percentage
        $matchCount = count($matched);
        $newMatchPercentage = $totalKeywords > 0 ? round(($matchCount / $totalKeywords) * 100, 2) : 0;

        // Update the matches structure (use new format)
        $updatedMatches = [
            'matched' => $matched,
            'missing' => $missing,
            'match_count' => $matchCount,
            'total_keywords' => $totalKeywords
        ];

        // Update database
        $stmt = $pdo->prepare("UPDATE award_analysis SET all_matches = ?, match_percentage = ? WHERE award_id = ?");
        $stmt->execute([json_encode($updatedMatches), $newMatchPercentage, $awardId]);

        echo json_encode([
            'success' => true,
            'message' => 'Criteria toggled successfully',
            'matched' => $matched,
            'missing' => $missing,
            'match_count' => $matchCount,
            'total_keywords' => $totalKeywords,
            'match_percentage' => $newMatchPercentage
        ]);
        exit();

    } elseif ($method === 'GET' && $awardCategory) {
        // Get all users who applied for this award category
        // Handle NULL values and use case-insensitive matching
        try {
            // Check if event_id column exists in award_analysis table
            $checkColumn = $pdo->query("SHOW COLUMNS FROM award_analysis LIKE 'event_id'");
            $eventIdExists = $checkColumn->rowCount() > 0;
            
            // Build query with optional event_id column
            $eventIdSelect = $eventIdExists ? ', aa.event_id' : ', NULL as event_id';
            
            // First, try with exact match (case-insensitive)
            // Ensure deleted_at column exists and filter out deleted awards
            try {
                $pdo->exec("ALTER TABLE awards ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL");
            } catch (PDOException $e) {
                // Column might already exist, ignore
            }
            
            $sql = "
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
                    $eventIdSelect
                FROM awards a
                LEFT JOIN users u ON a.user_id = u.id
                INNER JOIN award_analysis aa ON a.id = aa.award_id
                WHERE aa.predicted_category IS NOT NULL 
                AND LOWER(aa.predicted_category) = LOWER(?)
                AND (a.deleted_at IS NULL)
                ORDER BY COALESCE(aa.match_percentage, 0) DESC, a.created_at DESC
            ";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([trim($awardCategory)]);
            $applicants = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error fetching applicants for category '$awardCategory': " . $e->getMessage());
            error_log("SQL Error Code: " . $e->getCode());
            if ($e instanceof PDOException) {
                $errorInfo = $e->errorInfo ?? [];
                error_log("SQL Error Info: " . print_r($errorInfo, true));
            }
            
            // If exact match fails, try partial match
            try {
                $checkColumn = $pdo->query("SHOW COLUMNS FROM award_analysis LIKE 'event_id'");
                $eventIdExists = $checkColumn->rowCount() > 0;
                $eventIdSelect = $eventIdExists ? ', aa.event_id' : ', NULL as event_id';
                
                $sql = "
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
                        $eventIdSelect
                    FROM awards a
                    LEFT JOIN users u ON a.user_id = u.id
                    INNER JOIN award_analysis aa ON a.id = aa.award_id
                    WHERE aa.predicted_category IS NOT NULL 
                    AND LOWER(aa.predicted_category) LIKE LOWER(?)
                    AND (a.deleted_at IS NULL)
                    ORDER BY COALESCE(aa.match_percentage, 0) DESC, a.created_at DESC
                ";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute(['%' . trim($awardCategory) . '%']);
                $applicants = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e2) {
                error_log("Alternative query also failed: " . $e2->getMessage());
                error_log("SQL Error Code: " . $e2->getCode());
                if ($e2 instanceof PDOException) {
                    $errorInfo = $e2->errorInfo ?? [];
                    error_log("SQL Error Info: " . print_r($errorInfo, true));
                }
                throw new Exception("Failed to fetch applicants: " . $e2->getMessage());
            }
        }

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
        // Bulk move awards to trash (soft delete)
        $data = json_decode(file_get_contents('php://input'), true);
        $awardIds = $data['award_ids'] ?? [];
        $permanent = isset($data['permanent']) && $data['permanent'] === true;

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
        
        if ($permanent) {
            // Permanent delete - remove files and database records
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
                'message' => "Successfully permanently deleted $deletedCount award(s)",
                'deleted_count' => $deletedCount
            ]);
        } else {
            // Soft delete - move to trash (set deleted_at)
            try {
                // Ensure deleted_at column exists
                $pdo->exec("ALTER TABLE awards ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL");
            } catch (PDOException $e) {
                // Column might already exist, ignore
            }
            
            $stmt = $pdo->prepare("UPDATE awards SET deleted_at = NOW() WHERE id IN ($placeholders)");
            $stmt->execute($awardIds);
            
            $deletedCount = $stmt->rowCount();

            echo json_encode([
                'success' => true,
                'message' => "Successfully moved $deletedCount award(s) to trash",
                'deleted_count' => $deletedCount
            ]);
        }

    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid request']);
    }

} catch (Exception $e) {
    http_response_code(500);
    error_log("Award Applicants API Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => [
            'category' => $awardCategory ?? null,
            'award_id' => $awardId ?? null,
            'method' => $method ?? null
        ]
    ]);
}
?>
