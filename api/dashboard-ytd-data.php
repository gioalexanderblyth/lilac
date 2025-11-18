<?php
/**
 * Dashboard YTD Data API
 * Returns category distribution for a specific year (YTD)
 */

session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized. Please login.']);
    exit();
}

$year = $_GET['year'] ?? date('Y');

if (!preg_match('/^\d{4}$/', (string)$year)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid year format. Use YYYY']);
    exit();
}

$yearInt = (int)$year;

try {
    $pdo = getDatabaseConnection();

    if ($pdo instanceof FileBasedDatabase) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'MySQL database required']);
        exit();
    }

    $displayAwards = [
        'Global Citizenship Award',
        'Best ASEAN Awareness Award',
        'Internationalization Leadership Award',
        'Outstanding International Education Award',
        'Most Promising IRO/Community Award',
        'Emerging Leadership in Internationalization Award',
        'Best CHED Regional Office for Internationalization',
        'Sustainability in Internationalization Award'
    ];

    $categoryDistribution = [];
    foreach ($displayAwards as $awardName) {
        $categoryDistribution[$awardName] = 0;
    }

    $stmt = $pdo->prepare("
        SELECT 
            a.id,
            a.title as award_title,
            aa.predicted_category,
            aa.match_percentage
        FROM award_analysis aa
        INNER JOIN awards a ON a.id = aa.award_id
        WHERE aa.match_percentage >= 90
        AND YEAR(a.created_at) = ?
        ORDER BY aa.match_percentage DESC
    ");

    $stmt->execute([$yearInt]);
    $eligibleAwards = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $specificAwards = [
        'Global Citizenship Award',
        'Best ASEAN Awareness Award',
        'Internationalization Leadership Award',
        'Outstanding International Education Award',
        'Outstanding International Education Program Award',
        'Most Promising IRO/Community Award',
        'Emerging Leadership in Internationalization Award',
        'Best CHED Regional Office for Internationalization',
        'Sustainability in Internationalization Award'
    ];

    foreach ($eligibleAwards as $award) {
        $predictedCategory = $award['predicted_category'] ?? '';
        $awardTitle = $award['award_title'] ?? '';
        $awardName = !empty($predictedCategory) ? $predictedCategory : $awardTitle;

        $normalizedAwardName = str_ireplace([' Program', ' Award'], '', $awardName);

        $matched = false;
        foreach ($displayAwards as $displayAward) {
            $normalizedDisplay = str_ireplace([' Program', ' Award'], '', $displayAward);

            if (strcasecmp($awardName, $displayAward) === 0 || 
                stripos($awardName, $displayAward) !== false || 
                stripos($displayAward, $awardName) !== false ||
                strcasecmp($normalizedAwardName, $normalizedDisplay) === 0 ||
                stripos($normalizedAwardName, $normalizedDisplay) !== false ||
                stripos($normalizedDisplay, $normalizedAwardName) !== false) {
                
                if (stripos($awardName, 'Outstanding International Education') !== false) {
                    $categoryDistribution['Outstanding International Education Award']++;
                } else {
                    $categoryDistribution[$displayAward]++;
                }
                $matched = true;
                break;
            }
        }

        if (!$matched) {
            foreach ($specificAwards as $specificAward) {
                if (stripos($awardName, $specificAward) !== false || 
                    stripos($specificAward, $awardName) !== false) {

                    if (stripos($awardName, 'Outstanding International Education') !== false) {
                        $categoryDistribution['Outstanding International Education Award']++;
                    } else {
                        foreach ($displayAwards as $displayAward) {
                            if (stripos($specificAward, $displayAward) !== false || 
                                stripos($displayAward, $specificAward) !== false) {
                                $categoryDistribution[$displayAward]++;
                                break;
                            }
                        }
                    }
                    break;
                }
            }
        }
    }

    echo json_encode([
        'success' => true,
        'data' => $categoryDistribution,
        'year' => $yearInt
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}

