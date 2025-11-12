<?php
/**
 * User Award Submissions API
 * Returns user's own award submissions for the Awards List table
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

session_start();

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$userId = $_SESSION['user_id'];

require_once __DIR__ . '/config.php';

try {
    $pdo = getDatabaseConnection();

    if ($pdo instanceof FileBasedDatabase) {
        echo json_encode(['success' => false, 'error' => 'Database required']);
        exit();
    }

    // Get user's award submissions with analysis data and user info
    $stmt = $pdo->prepare("
        SELECT
            a.id as award_id,
            a.title as submission_title,
            a.description,
            a.file_name,
            a.file_path,
            a.status,
            a.created_at,
            aa.predicted_category as award_name,
            aa.match_percentage,
            aa.matched_keywords,
            aa.all_matches,
            u.username,
            u.email,
            u.full_name,
            CASE
                WHEN aa.all_matches IS NOT NULL THEN
                    CASE
                        WHEN JSON_VALID(aa.all_matches) THEN
                            CASE
                                WHEN JSON_TYPE(aa.all_matches) = 'OBJECT' THEN JSON_UNQUOTE(JSON_EXTRACT(aa.all_matches, '$.match_count'))
                                WHEN JSON_TYPE(aa.all_matches) = 'ARRAY' THEN JSON_UNQUOTE(JSON_EXTRACT(aa.all_matches, '$[0].criteria_met'))
                                ELSE 0
                            END
                        ELSE 0
                    END
                ELSE 0
            END as criteria_met,
            CASE
                WHEN aa.all_matches IS NOT NULL THEN
                    CASE
                        WHEN JSON_VALID(aa.all_matches) THEN
                            CASE
                                WHEN JSON_TYPE(aa.all_matches) = 'OBJECT' THEN JSON_UNQUOTE(JSON_EXTRACT(aa.all_matches, '$.total_keywords'))
                                WHEN JSON_TYPE(aa.all_matches) = 'ARRAY' THEN JSON_UNQUOTE(JSON_EXTRACT(aa.all_matches, '$[0].criteria_total'))
                                ELSE 0
                            END
                        ELSE 0
                    END
                ELSE 0
            END as criteria_total
        FROM awards a
        LEFT JOIN award_analysis aa ON aa.award_id = a.id
        LEFT JOIN users u ON u.id = a.user_id
        WHERE a.user_id = ?
        ORDER BY a.created_at DESC
    ");

    $stmt->execute([$userId]);
    $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Process each submission to extract matched/missing keywords
    foreach ($submissions as &$submission) {
        if ($submission['all_matches']) {
            $matches = json_decode($submission['all_matches'], true);
            if ($matches && is_array($matches)) {
                // Handle two formats
                if (isset($matches['matched'])) {
                    // New format
                    $submission['matched_keywords_array'] = $matches['matched'] ?? [];
                    $submission['missing_keywords_array'] = $matches['missing'] ?? [];
                } elseif (isset($matches[0])) {
                    // Old format
                    $submission['matched_keywords_array'] = $matches[0]['met_criteria'] ?? [];
                    $submission['missing_keywords_array'] = $matches[0]['unmet_criteria'] ?? [];
                }
            }
        }

        // Remove large JSON fields from response
        unset($submission['all_matches']);
    }

    echo json_encode([
        'success' => true,
        'submissions' => $submissions,
        'total' => count($submissions)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch submissions',
        'message' => $e->getMessage()
    ]);
}
?>
