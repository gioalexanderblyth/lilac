<?php
/**
 * Award Analytics API
 * Provides analytics data for admin dashboard
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

$user = $_SESSION['user'];
$isAdmin = $user['role'] === 'admin';

// Only admins can access analytics
if (!$isAdmin) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden - Admin access required']);
    exit();
}

require_once __DIR__ . '/config.php';

try {
    $pdo = getDatabaseConnection();

    // Get eligibility status distribution (for pie chart)
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
        WHERE aa.match_percentage IS NOT NULL
        GROUP BY eligibility_status
    ";

    $statusStmt = $pdo->query($statusQuery);
    $statusDistribution = $statusStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get award status distribution (pending, approved, analyzed)
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
        GROUP BY a.status, status_label
    ";

    $awardStatusStmt = $pdo->query($awardStatusQuery);
    $awardStatusDistribution = $awardStatusStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get award fulfillment trends over time (last 6 months)
    // Only count awards that have analysis records (to avoid counting orphaned records)
    $trendsQuery = "
        SELECT
            DATE_FORMAT(a.created_at, '%Y-%m') as month,
            COUNT(DISTINCT a.id) as total_submissions,
            SUM(CASE WHEN aa.match_percentage >= 90 THEN 1 ELSE 0 END) as eligible_submissions,
            SUM(CASE WHEN aa.match_percentage >= 70 AND aa.match_percentage < 90 THEN 1 ELSE 0 END) as almost_eligible_submissions
        FROM awards a
        INNER JOIN award_analysis aa ON aa.award_id = a.id
        WHERE a.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY month
        ORDER BY month ASC
    ";

    $trendsStmt = $pdo->query($trendsQuery);
    $trends = $trendsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get eligible requirements per award category
    $requirementsQuery = "
        SELECT
            ac.category_name as award_name,
            ac.award_type,
            COUNT(DISTINCT a.id) as total_applicants,
            SUM(CASE WHEN aa.match_percentage >= 90 THEN 1 ELSE 0 END) as eligible_count,
            SUM(CASE WHEN aa.match_percentage >= 70 AND aa.match_percentage < 90 THEN 1 ELSE 0 END) as almost_eligible_count,
            SUM(CASE WHEN aa.match_percentage < 70 THEN 1 ELSE 0 END) as not_eligible_count,
            ROUND(AVG(aa.match_percentage), 2) as avg_match_percentage,
            JSON_LENGTH(ac.requirements) as total_criteria
        FROM award_criteria ac
        LEFT JOIN award_analysis aa ON aa.predicted_category = ac.category_name
        LEFT JOIN awards a ON a.id = aa.award_id
        GROUP BY ac.category_name, ac.award_type, ac.requirements
        ORDER BY avg_match_percentage DESC
    ";

    $requirementsStmt = $pdo->query($requirementsQuery);
    $requirements = $requirementsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get overall KPIs including eligible departments
    $kpiQuery = "
        SELECT
            COUNT(DISTINCT a.id) as total_awards,
            COUNT(DISTINCT CASE WHEN aa.match_percentage >= 90 THEN a.user_id END) as eligible_users,
            COUNT(DISTINCT ac.category_name) as total_categories,
            ROUND(AVG(aa.match_percentage), 2) as overall_success_rate,
            COUNT(DISTINCT CASE WHEN aa.match_percentage >= 90 THEN u.department END) as eligible_departments
        FROM awards a
        LEFT JOIN award_analysis aa ON aa.award_id = a.id
        LEFT JOIN award_criteria ac ON 1=1
        LEFT JOIN users u ON u.id = a.user_id
        WHERE u.department IS NOT NULL
    ";

    $kpiStmt = $pdo->query($kpiQuery);
    $kpis = $kpiStmt->fetch(PDO::FETCH_ASSOC);

    // Also get total departments count
    $totalDeptQuery = "SELECT COUNT(DISTINCT name) as total_departments FROM departments WHERE status = 'active'";
    $totalDeptStmt = $pdo->query($totalDeptQuery);
    $totalDeptResult = $totalDeptStmt->fetch(PDO::FETCH_ASSOC);
    $kpis['total_departments'] = $totalDeptResult['total_departments'];

    // Calculate additional insights
    $insights = [
        'top_performing_award' => null,
        'most_challenging_award' => null,
        'trending_up' => [],
        'needs_attention' => []
    ];

    if (!empty($requirements)) {
        // Find top performing (highest avg match %)
        $insights['top_performing_award'] = $requirements[0]['award_name'];

        // Find most challenging (lowest avg match %)
        $insights['most_challenging_award'] = end($requirements)['award_name'];

        // Find awards with high eligible count
        foreach ($requirements as $req) {
            if ($req['eligible_count'] > 5) {
                $insights['trending_up'][] = $req['award_name'];
            }
            if ($req['avg_match_percentage'] < 60 && $req['total_applicants'] > 0) {
                $insights['needs_attention'][] = $req['award_name'];
            }
        }
    }

    $response = [
        'success' => true,
        'data' => [
            'statusDistribution' => $statusDistribution,
            'awardStatusDistribution' => $awardStatusDistribution,
            'trends' => $trends,
            'requirements' => $requirements,
            'kpis' => $kpis,
            'insights' => $insights
        ]
    ];

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch analytics data',
        'message' => $e->getMessage()
    ]);
}
