<?php
// Simple endpoint for the Hub page to get 100% matched awards from the Awards list
// Focused ONLY on: non-deleted awards whose latest analysis has match_percentage = 100

require_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    $pdo = getDatabaseConnection();

    // If we're in file-based fallback mode, just return an empty list for now
    if ($pdo instanceof FileBasedDatabase) {
        echo json_encode([]);
        exit;
    }

    // Ensure deleted_at column exists so filter won't fail
    try {
        $pdo->exec("ALTER TABLE awards ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL");
    } catch (PDOException $e) {
        // Column might already exist – ignore
    }

    // Get latest analysis per award and filter to 100% matches
    $sql = "
        SELECT 
            a.id,
            a.user_id,
            a.title,
            a.description,
            a.file_name,
            a.file_path,
            a.ocr_text,
            a.created_at,
            aa.match_percentage,
            aa.predicted_category,
            aa.status
        FROM awards a
        INNER JOIN (
            SELECT aa1.*
            FROM award_analysis aa1
            INNER JOIN (
                SELECT award_id, MAX(id) AS max_id
                FROM award_analysis
                GROUP BY award_id
            ) latest ON latest.award_id = aa1.award_id AND latest.max_id = aa1.id
        ) aa ON a.id = aa.award_id
        WHERE a.deleted_at IS NULL
          AND aa.match_percentage = 100
        ORDER BY a.created_at DESC
    ";

    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Normalize shape a bit so frontend can treat these like "certificates"
    $certs = array_map(function ($row) {
        return [
            'id' => (int)$row['id'],
            'title' => $row['title'],
            'description' => $row['description'],
            'file_name' => $row['file_name'],
            'file_path' => $row['file_path'],
            'ocr_text' => $row['ocr_text'],
            'created_at' => $row['created_at'],
            'user_id' => isset($row['user_id']) ? (int)$row['user_id'] : null,
            'match_percentage' => 100,
            'predicted_category' => $row['predicted_category'] ?? null,
            'analysis_status' => $row['status'] ?? null,
            // keep a simple analysis block for compatibility
            'analysis' => [
                'match_percentage' => 100,
                'predicted_category' => $row['predicted_category'] ?? null,
                'status' => $row['status'] ?? null,
            ],
        ];
    }, $rows);

    echo json_encode($certs);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load hub supporting docs: ' . $e->getMessage()]);
}

?>


