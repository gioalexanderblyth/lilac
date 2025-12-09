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
    
    // Check if we're using file-based fallback (no real database)
    if ($pdo instanceof FileBasedDatabase) {
        // Return empty data structure instead of error
        $response = [
            'success' => true,
            'data' => [
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
            ]
        ];
        echo json_encode($response);
        exit();
    }

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

    // Ensure deleted_at column exists
    try {
        $pdo->exec("ALTER TABLE awards ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL");
    } catch (PDOException $e) {
        // Column might already exist, ignore
    }
    
    // Get user's eligibility status distribution (for pie chart) - excluding deleted awards
    // Include all analyzed awards (even with 0% match) to show accurate status
    $statusQuery = "
        SELECT
            CASE
                WHEN aa.match_percentage IS NULL THEN 'Not Analyzed'
                WHEN aa.match_percentage >= 90 THEN 'Eligible'
                WHEN aa.match_percentage >= 70 THEN 'Almost Eligible'
                ELSE 'Not Eligible'
            END as eligibility_status,
            COUNT(*) as count
        FROM awards a
        LEFT JOIN award_analysis aa ON aa.award_id = a.id
        WHERE a.user_id = ? 
          AND (a.deleted_at IS NULL OR a.deleted_at = '')
          AND a.title IS NOT NULL 
          AND a.title != ''
        GROUP BY eligibility_status
    ";

    $statusStmt = $pdo->prepare($statusQuery);
    $statusStmt->execute([$userId]);
    $statusDistribution = $statusStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get user's award status distribution (pending, approved, analyzed) - excluding deleted awards
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
          AND (a.deleted_at IS NULL OR a.deleted_at = '')
          AND a.title IS NOT NULL 
          AND a.title != ''
        GROUP BY a.status, status_label
    ";

    $awardStatusStmt = $pdo->prepare($awardStatusQuery);
    $awardStatusStmt->execute([$userId]);
    $awardStatusDistribution = $awardStatusStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get user's award fulfillment trends over time (last 6 months) - excluding deleted awards
    $trendsQuery = "
        SELECT
            DATE_FORMAT(a.created_at, '%Y-%m') as month,
            COUNT(*) as total_submissions,
            SUM(CASE WHEN aa.match_percentage >= 90 THEN 1 ELSE 0 END) as eligible_submissions,
            SUM(CASE WHEN aa.match_percentage >= 70 AND aa.match_percentage < 90 THEN 1 ELSE 0 END) as almost_eligible_submissions
        FROM awards a
        LEFT JOIN award_analysis aa ON aa.award_id = a.id
        WHERE a.user_id = ? 
          AND a.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) 
          AND (a.deleted_at IS NULL OR a.deleted_at = '')
          AND a.title IS NOT NULL 
          AND a.title != ''
        GROUP BY month
        ORDER BY month ASC
    ";

    $trendsStmt = $pdo->prepare($trendsQuery);
    $trendsStmt->execute([$userId]);
    $trends = $trendsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Ensure deleted_at column exists
    try {
        $pdo->exec("ALTER TABLE awards ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL");
    } catch (PDOException $e) {
        // Column might already exist, ignore
    }
    
    // Get user's eligible requirements per award category (optimized with JOIN)
    // Only include awards that have been analyzed (have award_analysis records)
    // Use COALESCE to handle NULL values and provide fallbacks
    $requirementsQuery = "
        SELECT
            COALESCE(aa.predicted_category, a.title, 'Not Analyzed') as award_name,
            a.title as submission_title,
            COALESCE(aa.match_percentage, 0) as match_percentage,
            aa.matched_keywords,
            aa.all_matches,
            a.file_name,
            a.file_path,
            a.description,
            CASE
                WHEN aa.all_matches IS NOT NULL AND aa.all_matches != '' AND JSON_VALID(aa.all_matches) THEN
                    CASE
                        WHEN JSON_TYPE(aa.all_matches) = 'OBJECT' THEN 
                            COALESCE(
                                CAST(JSON_EXTRACT(aa.all_matches, '$.match_count') AS UNSIGNED),
                                CAST(JSON_EXTRACT(aa.all_matches, '$.criteria_met') AS UNSIGNED),
                                CAST(JSON_EXTRACT(aa.all_matches, '$.keyword_match_count') AS UNSIGNED),
                                0
                            )
                        WHEN JSON_TYPE(aa.all_matches) = 'ARRAY' THEN 
                            COALESCE(
                                CAST(JSON_EXTRACT(aa.all_matches, '$[0].criteria_met') AS UNSIGNED),
                                CAST(JSON_EXTRACT(aa.all_matches, '$[0].matched_count') AS UNSIGNED),
                                CAST(JSON_EXTRACT(aa.all_matches, '$[0].keyword_match_count') AS UNSIGNED),
                                0
                            )
                        ELSE 0
                    END
                WHEN aa.matched_keywords IS NOT NULL AND aa.matched_keywords != '' AND JSON_VALID(aa.matched_keywords) THEN
                    JSON_LENGTH(aa.matched_keywords)
                ELSE 0
            END as criteria_met,
            CASE
                WHEN aa.all_matches IS NOT NULL AND aa.all_matches != '' AND JSON_VALID(aa.all_matches) THEN
                    CASE
                        WHEN JSON_TYPE(aa.all_matches) = 'OBJECT' THEN 
                            COALESCE(
                                CAST(JSON_EXTRACT(aa.all_matches, '$.total_keywords') AS UNSIGNED),
                                CAST(JSON_EXTRACT(aa.all_matches, '$.criteria_total') AS UNSIGNED),
                                0
                            )
                        WHEN JSON_TYPE(aa.all_matches) = 'ARRAY' THEN 
                            COALESCE(
                                CAST(JSON_EXTRACT(aa.all_matches, '$[0].criteria_total') AS UNSIGNED),
                                CAST(JSON_EXTRACT(aa.all_matches, '$[0].total_keywords') AS UNSIGNED),
                                0
                            )
                        ELSE 0
                    END
                WHEN (aa.matched_keywords IS NOT NULL AND aa.matched_keywords != '' AND JSON_VALID(aa.matched_keywords))
                     OR (aa.missing_keywords IS NOT NULL AND aa.missing_keywords != '' AND JSON_VALID(aa.missing_keywords)) THEN
                    COALESCE(JSON_LENGTH(COALESCE(aa.matched_keywords, '[]')), 0) + 
                    COALESCE(JSON_LENGTH(COALESCE(aa.missing_keywords, '[]')), 0)
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
          AND (a.deleted_at IS NULL OR a.deleted_at = '')
          AND a.title IS NOT NULL 
          AND a.title != ''
          AND a.id IS NOT NULL
        ORDER BY COALESCE(aa.match_percentage, 0) DESC, a.created_at DESC
    ";

    try {
        // Simplified query - we'll process JSON in PHP
        $requirementsQuerySimple = "
            SELECT
                COALESCE(aa.predicted_category, a.title, 'Not Analyzed') as award_name,
                a.title as submission_title,
                COALESCE(aa.match_percentage, 0) as match_percentage,
                aa.matched_keywords,
                aa.missing_keywords,
                aa.all_matches,
                a.file_name,
                a.file_path,
                a.description,
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
              AND (a.deleted_at IS NULL OR a.deleted_at = '')
              AND a.title IS NOT NULL 
              AND a.title != ''
              AND a.id IS NOT NULL
            ORDER BY COALESCE(aa.match_percentage, 0) DESC, a.created_at DESC
        ";
        
        $requirementsStmt = $pdo->prepare($requirementsQuerySimple);
        $requirementsStmt->execute([$userId]);
        $requirementsRaw = $requirementsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Process JSON in PHP to calculate criteria_met and criteria_total
        $requirements = [];
        foreach ($requirementsRaw as $req) {
            $criteriaMet = 0;
            $criteriaTotal = 0;
            
            // Try to extract from all_matches first
            if (!empty($req['all_matches'])) {
                $allMatches = json_decode($req['all_matches'], true);
                if (is_array($allMatches)) {
                    if (isset($allMatches[0])) {
                        // Array format
                        $criteriaMet = intval($allMatches[0]['criteria_met'] ?? $allMatches[0]['matched_count'] ?? $allMatches[0]['keyword_match_count'] ?? 0);
                        $criteriaTotal = intval($allMatches[0]['criteria_total'] ?? $allMatches[0]['total_keywords'] ?? 0);
                    } elseif (isset($allMatches['match_count'])) {
                        // Object format
                        $criteriaMet = intval($allMatches['match_count'] ?? $allMatches['criteria_met'] ?? $allMatches['keyword_match_count'] ?? 0);
                        $criteriaTotal = intval($allMatches['total_keywords'] ?? $allMatches['criteria_total'] ?? 0);
                    }
                }
            }
            
            // Fallback to matched_keywords and missing_keywords if all_matches didn't work
            if ($criteriaTotal == 0) {
                $matchedKeywords = [];
                $missingKeywords = [];
                
                if (!empty($req['matched_keywords'])) {
                    $matchedKeywords = json_decode($req['matched_keywords'], true);
                    if (!is_array($matchedKeywords)) $matchedKeywords = [];
                }
                
                if (!empty($req['missing_keywords'])) {
                    $missingKeywords = json_decode($req['missing_keywords'], true);
                    if (!is_array($missingKeywords)) $missingKeywords = [];
                }
                
                $criteriaMet = count($matchedKeywords);
                $criteriaTotal = count($matchedKeywords) + count($missingKeywords);
            }
            
            $req['criteria_met'] = $criteriaMet;
            $req['criteria_total'] = $criteriaTotal;
            $requirements[] = $req;
        }
    } catch (PDOException $e) {
        error_log('Error executing requirements query: ' . $e->getMessage());
        $requirements = [];
    }

    // Get user's KPIs (excluding deleted awards)
    // Calculate success_rate only from analyzed awards (not NULL match_percentage)
    // OCR Data Analyzed: Only count awards from awards progress page (not from documents page)
    $kpiQuery = "
        SELECT
            COUNT(DISTINCT a.id) as total_awards,
            COUNT(DISTINCT CASE WHEN aa.match_percentage >= 90 THEN a.id END) as awards_eligible,
            COUNT(DISTINCT CASE WHEN a.status = 'approved' THEN a.id END) as awards_recognized,
            ROUND(AVG(CASE WHEN aa.match_percentage IS NOT NULL THEN aa.match_percentage END), 2) as success_rate,
            COUNT(DISTINCT CASE 
                WHEN aa.detected_text IS NOT NULL 
                AND aa.detected_text != '' 
                AND (aa.source_page = 'awards' OR aa.source_page IS NULL)
                AND (aa.source_page IS NULL OR aa.source_page != 'documents')
                THEN a.id 
            END) as orc_data_analyzed
        FROM awards a
        LEFT JOIN award_analysis aa ON aa.award_id = a.id
        WHERE a.user_id = ? 
          AND (a.deleted_at IS NULL OR a.deleted_at = '')
          AND a.title IS NOT NULL 
          AND a.title != ''
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

} catch (PDOException $e) {
    // Database-specific errors
    error_log('Database error in user-award-analytics.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database connection error',
        'message' => 'Unable to connect to database. Please ensure MySQL is running and the database exists.'
    ]);
} catch (Exception $e) {
    // General errors
    error_log('Error in user-award-analytics.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch user analytics data',
        'message' => $e->getMessage()
    ]);
}
