<?php
/**
 * User Award Analytics API
 * Provides personalized analytics data for logged-in user
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

session_start();

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$userId = $_SESSION['user_id'];
$user = $_SESSION['user'];

require_once __DIR__ . '/config.php';

try {
    $pdo = getDatabaseConnection();

    // Initialize response data
    $responseData = [
        'statusDistribution' => [],
        'awardStatusDistribution' => [],
        'trends' => [],
        'requirements' => [],
        'kpis' => [
            'total_awards' => 0,
            'awards_eligible' => 0,
            'awards_recognized' => 0,
            'success_rate' => 0,
            'orc_data_analyzed' => 0
        ],
        'insights' => [
            'close_to_achieving' => [],
            'achieved' => [],
            'needs_work' => [],
            'recommendations' => []
        ]
    ];

    // Get user's eligibility status distribution (for pie chart)
    $statusQuery = "
        SELECT
            CASE
                WHEN aa.match_percentage >= 90 THEN 'Eligible'
                WHEN aa.match_percentage >= 70 THEN 'Almost Eligible'
                ELSE 'Not Eligible'
            END as eligibility_status,
            COUNT(*) as count
        FROM awards a
        LEFT JOIN award_analysis aa ON aa.award_id = a.id
        WHERE a.user_id = ? AND aa.match_percentage IS NOT NULL
        GROUP BY eligibility_status
    ";

    $statusStmt = $pdo->prepare($statusQuery);
    $statusStmt->execute([$userId]);
    $statusDistribution = $statusStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get user's award status distribution (pending, approved, analyzed)
    $awardStatusQuery = "
        SELECT
            CASE
                WHEN a.status = 'approved' THEN 'Recognized'
                WHEN a.status = 'analyzed' THEN 'Processed'
                WHEN a.status = 'pending' THEN 'Pending'
                ELSE 'Other'
            END as status_label,
            a.status as status_value,
            COUNT(*) as count
        FROM awards a
        WHERE a.user_id = ?
        GROUP BY a.status, status_label
    ";

    $awardStatusStmt = $pdo->prepare($awardStatusQuery);
    $awardStatusStmt->execute([$userId]);
    $awardStatusDistribution = $awardStatusStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get user's award fulfillment trends over time (last 6 months)
    $trendsQuery = "
        SELECT
            DATE_FORMAT(a.created_at, '%Y-%m') as month,
            COUNT(*) as total_submissions,
            SUM(CASE WHEN aa.match_percentage >= 90 THEN 1 ELSE 0 END) as eligible_submissions,
            SUM(CASE WHEN aa.match_percentage >= 70 AND aa.match_percentage < 90 THEN 1 ELSE 0 END) as almost_eligible_submissions
        FROM awards a
        LEFT JOIN award_analysis aa ON aa.award_id = a.id
        WHERE a.user_id = ? AND a.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY month
        ORDER BY month ASC
    ";

    $trendsStmt = $pdo->prepare($trendsQuery);
    $trendsStmt->execute([$userId]);
    $trends = $trendsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get user's eligible requirements per award category (optimized with JOIN)
    $requirementsQuery = "
        SELECT
            aa.predicted_category as award_name,
            a.title as submission_title,
            aa.match_percentage,
            aa.matched_keywords,
            aa.all_matches,
            a.file_name,
            a.file_path,
            a.description,
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
            END as criteria_total,
            a.status,
            a.created_at,
            CASE
                WHEN a.status = 'approved' THEN 'Recognized'
                WHEN a.status = 'analyzed' THEN 'Processed'
                WHEN a.status = 'pending' THEN 'Pending Review'
                ELSE 'Unknown'
            END as status_label
        FROM awards a
        LEFT JOIN award_analysis aa ON aa.award_id = a.id
        LEFT JOIN award_criteria ac ON ac.category_name = aa.predicted_category
        WHERE a.user_id = ?
        ORDER BY aa.match_percentage DESC, a.created_at DESC
    ";

    $requirementsStmt = $pdo->prepare($requirementsQuery);
    $requirementsStmt->execute([$userId]);
    $requirements = $requirementsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get user's KPIs
    $kpiQuery = "
        SELECT
            COUNT(DISTINCT a.id) as total_awards,
            COUNT(DISTINCT CASE WHEN aa.match_percentage >= 90 THEN a.id END) as awards_eligible,
            COUNT(DISTINCT CASE WHEN a.status = 'approved' THEN a.id END) as awards_recognized,
            ROUND(AVG(aa.match_percentage), 2) as success_rate,
            COUNT(DISTINCT a.id) as orc_data_analyzed
        FROM awards a
        LEFT JOIN award_analysis aa ON aa.award_id = a.id
        WHERE a.user_id = ?
    ";

    $kpiStmt = $pdo->prepare($kpiQuery);
    $kpiStmt->execute([$userId]);
    $kpis = $kpiStmt->fetch(PDO::FETCH_ASSOC);

    // Calculate personalized insights
    $insights = [
        'close_to_achieving' => [],
        'achieved' => [],
        'needs_work' => [],
        'recommendations' => []
    ];

    foreach ($requirements as $req) {
        $matchPct = floatval($req['match_percentage']);

        if ($matchPct >= 90) {
            $insights['achieved'][] = $req['award_name'];
        } elseif ($matchPct >= 70) {
            $insights['close_to_achieving'][] = [
                'name' => $req['award_name'],
                'percentage' => $matchPct,
                'missing' => 90 - $matchPct
            ];
        } elseif ($matchPct < 50) {
            $insights['needs_work'][] = $req['award_name'];
        }
    }

    // Generate recommendations
    if (!empty($insights['close_to_achieving'])) {
        $closest = $insights['close_to_achieving'][0];
        $insights['recommendations'][] = [
            'type' => 'opportunity',
            'message' => "You're only " . round($closest['missing'], 1) . "% away from achieving the " . $closest['name'] . " award!"
        ];
    }

    if (!empty($insights['achieved'])) {
        $insights['recommendations'][] = [
            'type' => 'success',
            'message' => "Great job! You've qualified for " . count($insights['achieved']) . " award(s)."
        ];
    }

    if (!empty($insights['needs_work'])) {
        $insights['recommendations'][] = [
            'type' => 'improvement',
            'message' => "Focus on improving documentation and evidence for " . implode(', ', array_slice($insights['needs_work'], 0, 2)) . " awards."
        ];
    }

    // Update response data
    $responseData['statusDistribution'] = $statusDistribution;
    $responseData['awardStatusDistribution'] = $awardStatusDistribution;
    $responseData['trends'] = $trends;
    $responseData['requirements'] = $requirements;
    $responseData['kpis'] = $kpis;
    $responseData['insights'] = $insights;

    $response = [
        'success' => true,
        'data' => $responseData
    ];

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch user analytics data',
        'message' => $e->getMessage()
    ]);
}
