<?php
session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/config.php';

$userId = (int)$_SESSION['user_id'];
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 5;
$limit = max(1, min(10, $limit));

function buildActivityPayload(array $activity): array
{
    $statusType = $activity['status_type'] ?? 'pending';
    $statusLabels = [
        'success' => 'Approved',
        'review' => 'Under Review',
        'info' => 'Analyzed',
        'warning' => 'Uploaded',
        'danger' => 'Needs Attention',
    ];

    $detailTexts = [
        'success' => 'Processed successfully',
        'review' => 'Similarity review in progress',
        'info' => 'Analysis completed',
        'warning' => 'Awaiting processing',
        'danger' => 'Action required',
    ];

    return [
        'id' => (int)($activity['award_id'] ?? 0),
        'title' => trim(($activity['title'] ?? 'Untitled Award')),
        'status_label' => $statusLabels[$statusType] ?? 'Uploaded',
        'status_type' => $statusType,
        'detail' => $detailTexts[$statusType] ?? 'Awaiting processing',
        'match_percentage' => isset($activity['match_percentage']) && $activity['match_percentage'] !== null
            ? round((float)$activity['match_percentage'])
            : null,
        'analysis_status' => $activity['analysis_status'] ?? null,
        'award_status' => $activity['award_status'] ?? null,
        'predicted_category' => $activity['predicted_category'] ?? null,
        'timestamp' => $activity['timestamp'] ?? null,
    ];
}

try {
    $pdo = getDatabaseConnection();

    if ($pdo instanceof FileBasedDatabase) {
        $sampleActivities = [
            [
                'award_id' => 1,
                'title' => 'Innovation Award',
                'status_type' => 'success',
                'match_percentage' => 94,
                'timestamp' => date(DATE_ATOM, strtotime('-2 hours')),
            ],
            [
                'award_id' => 2,
                'title' => 'Leadership Award',
                'status_type' => 'review',
                'match_percentage' => 96,
                'timestamp' => date(DATE_ATOM, strtotime('-5 hours')),
            ],
            [
                'award_id' => 3,
                'title' => 'Global Citizenship Award',
                'status_type' => 'info',
                'match_percentage' => 87,
                'timestamp' => date(DATE_ATOM, strtotime('-1 day')),
            ],
        ];

        echo json_encode([
            'success' => true,
            'activities' => array_map('buildActivityPayload', $sampleActivities),
            'source' => 'file_fallback',
        ]);
        exit();
    }

    $sql = "
        SELECT 
            a.id AS award_id,
            a.title,
            a.status AS award_status,
            a.created_at AS award_created_at,
            a.updated_at AS award_updated_at,
            aa.status AS analysis_status,
            aa.match_percentage,
            aa.predicted_category,
            aa.updated_at AS analysis_updated_at
        FROM awards a
        LEFT JOIN award_analysis aa ON aa.award_id = a.id
        WHERE a.user_id = ?
          AND (a.deleted_at IS NULL OR a.deleted_at = '')
          AND a.title IS NOT NULL 
          AND a.title != ''
        ORDER BY COALESCE(aa.updated_at, a.updated_at, a.created_at) DESC
        LIMIT ?
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId, $limit]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $activities = [];

    foreach ($rows as $row) {
        $analysisStatus = $row['analysis_status'] ?? null;
        $awardStatus = strtolower($row['award_status'] ?? 'pending');
        $statusType = 'warning';

        if ($awardStatus === 'approved' || $analysisStatus === 'Eligible') {
            $statusType = 'success';
        } elseif ($analysisStatus === 'Not Eligible' || $awardStatus === 'rejected') {
            $statusType = 'danger';
        } elseif ($analysisStatus === 'Almost Eligible') {
            $statusType = 'review';
        } elseif ($analysisStatus) {
            $statusType = 'info';
        }

        $timestamp = $row['analysis_updated_at'] ?? $row['award_updated_at'] ?? $row['award_created_at'];

        $activities[] = buildActivityPayload([
            'award_id' => $row['award_id'],
            'title' => $row['title'],
            'status_type' => $statusType,
            'match_percentage' => $row['match_percentage'],
            'analysis_status' => $analysisStatus,
            'award_status' => $row['award_status'],
            'predicted_category' => $row['predicted_category'],
            'timestamp' => $timestamp ? date(DATE_ATOM, strtotime($timestamp)) : null,
        ]);
    }

    echo json_encode([
        'success' => true,
        'activities' => $activities,
        'source' => 'database',
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load award activity',
        'details' => $e->getMessage(),
    ]);
}

