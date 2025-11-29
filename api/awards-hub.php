<?php
/**
 * ICONS 2025 Awards Hub API
 * Provides combined evidence retrieval and AI-powered analysis
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/config.php';

// Award definitions with keywords for matching
$AWARDS_CONFIG = [
    [
        'id' => 'global-citizenship',
        'title' => 'Global Citizenship Award',
        'category' => 'institutional',
        'keywords' => ['global citizenship', 'intercultural', 'global awareness', 'cultural understanding', 'community engagement', 'SDG', 'sustainable development', 'global', 'citizenship', 'international'],
        'weight' => ['global citizenship' => 3, 'intercultural' => 2, 'SDG' => 2]
    ],
    [
        'id' => 'international-education',
        'title' => 'Outstanding International Education Program',
        'category' => 'institutional',
        'keywords' => ['international education', 'exchange program', 'student mobility', 'inclusive', 'academic partnership', 'curriculum', 'education', 'exchange', 'mobility'],
        'weight' => ['international education' => 3, 'exchange program' => 2, 'student mobility' => 2]
    ],
    [
        'id' => 'sustainability',
        'title' => 'Sustainability Award',
        'category' => 'institutional',
        'keywords' => ['sustainability', 'SDG', 'environment', 'green campus', 'climate', 'renewable', 'eco-friendly', 'environmental', 'sustainable', 'carbon'],
        'weight' => ['sustainability' => 3, 'SDG' => 2, 'environment' => 2]
    ],
    [
        'id' => 'asean-awareness',
        'title' => 'Best ASEAN Awareness Initiative',
        'category' => 'institutional',
        'keywords' => ['ASEAN', 'Southeast Asia', 'regional cooperation', 'cultural exchange', 'ASEAN community', 'regional', 'solidarity'],
        'weight' => ['ASEAN' => 3, 'Southeast Asia' => 2, 'regional cooperation' => 2]
    ],
    [
        'id' => 'emerging-leadership',
        'title' => 'Emerging Leadership Award',
        'category' => 'individual',
        'keywords' => ['emerging leader', 'young leader', 'mentorship', 'innovation', 'leadership growth', 'leadership', 'potential'],
        'weight' => ['emerging leader' => 3, 'leadership' => 2, 'mentorship' => 2]
    ],
    [
        'id' => 'izn-leadership',
        'title' => 'Internationalization Leadership Award',
        'category' => 'individual',
        'keywords' => ['strategic vision', 'governance', 'institutional leadership', 'ethical leadership', 'global excellence', 'leadership', 'executive'],
        'weight' => ['institutional leadership' => 3, 'strategic vision' => 2, 'governance' => 2]
    ],
    [
        'id' => 'ched-regional',
        'title' => 'Best CHED Regional Office',
        'category' => 'special',
        'keywords' => ['CHED', 'regional office', 'policy', 'coordination', 'governance', 'regional', 'office'],
        'weight' => ['CHED' => 3, 'regional office' => 2, 'coordination' => 2]
    ],
    [
        'id' => 'iro-community',
        'title' => 'Most Promising IRO Community',
        'category' => 'special',
        'keywords' => ['IRO network', 'collaboration', 'regional partnership', 'community', 'IRO', 'international relations'],
        'weight' => ['IRO' => 3, 'collaboration' => 2, 'community' => 2]
    ]
];

$action = $_GET['action'] ?? $_POST['action'] ?? 'summary';

try {
    $pdo = getDatabaseConnection();
    
    switch ($action) {
        case 'summary':
            // Get summary of all awards with evidence counts
            echo json_encode(getAwardsSummary($pdo));
            break;
            
        case 'evidence':
            // Get all evidence for a specific award
            $awardId = $_GET['award'] ?? $_POST['award'] ?? '';
            if (empty($awardId)) {
                throw new Exception('Award ID is required');
            }
            echo json_encode(getAwardEvidence($pdo, $awardId));
            break;
            
        case 'analyze_event':
            // Analyze an event for award eligibility
            $eventId = $_GET['event_id'] ?? $_POST['event_id'] ?? '';
            if (empty($eventId)) {
                throw new Exception('Event ID is required');
            }
            echo json_encode(analyzeEventForAwards($pdo, $eventId));
            break;
            
        case 'analyze_all':
            // Analyze all items and update matches
            echo json_encode(analyzeAllItems($pdo));
            break;
            
        case 'combined':
            // Get all evidence combined from all sources
            echo json_encode(getCombinedEvidence($pdo));
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Get summary of all awards with evidence counts
 */
function getAwardsSummary($pdo) {
    global $AWARDS_CONFIG;
    
    $combined = getCombinedEvidence($pdo);
    $summary = [];
    
    foreach ($AWARDS_CONFIG as $award) {
        $stats = calculateAwardStats($award, $combined);
        $summary[] = [
            'id' => $award['id'],
            'title' => $award['title'],
            'category' => $award['category'],
            'total_evidence' => $stats['total'],
            'eligible_count' => $stats['eligible'],
            'ai_detected' => $stats['aiDetected'],
            'readiness' => $stats['readiness']
        ];
    }
    
    return [
        'success' => true,
        'awards' => $summary,
        'stats' => [
            'total_evidence' => array_sum(array_column($summary, 'total_evidence')),
            'total_eligible' => array_sum(array_column($summary, 'eligible_count')),
            'total_ai_detected' => array_sum(array_column($summary, 'ai_detected')),
            'avg_readiness' => count($summary) > 0 ? round(array_sum(array_column($summary, 'readiness')) / count($summary)) : 0
        ]
    ];
}

/**
 * Get all evidence for a specific award
 */
function getAwardEvidence($pdo, $awardId) {
    global $AWARDS_CONFIG;
    
    $award = null;
    foreach ($AWARDS_CONFIG as $a) {
        if ($a['id'] === $awardId) {
            $award = $a;
            break;
        }
    }
    
    if (!$award) {
        throw new Exception('Award not found');
    }
    
    $combined = getCombinedEvidence($pdo);
    $stats = calculateAwardStats($award, $combined);
    
    return [
        'success' => true,
        'award' => [
            'id' => $award['id'],
            'title' => $award['title'],
            'category' => $award['category']
        ],
        'evidence' => [
            'certificates' => $stats['certificates'],
            'mous' => $stats['mous'],
            'events' => $stats['events'],
            'documents' => $stats['documents']
        ],
        'stats' => [
            'total' => $stats['total'],
            'eligible' => $stats['eligible'],
            'ai_detected' => $stats['aiDetected'],
            'readiness' => $stats['readiness']
        ]
    ];
}

/**
 * Get all evidence combined from all sources
 */
function getCombinedEvidence($pdo) {
    $evidence = [
        'certificates' => [],
        'mous' => [],
        'events' => [],
        'documents' => []
    ];
    
    // Get certificates/awards - from awards page (awards table)
    // These are files uploaded from the awards page and eligibility is determined by match score
    // Ensure deleted_at column exists and filter out deleted awards
    try {
        try {
            $pdo->exec("ALTER TABLE awards ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL");
        } catch (PDOException $e) {
            // Column might already exist, ignore
        }
        $stmt = $pdo->query("SELECT id, user_id, title, description, file_name, file_path, status, created_at FROM awards WHERE deleted_at IS NULL ORDER BY created_at DESC");
        $evidence['certificates'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error fetching awards: " . $e->getMessage());
    }
    
    // Get MOUs/MOAs - from MOU/MOA page (mou_moa table)
    // These are files uploaded from the MOU/MOA page
    try {
        $stmt = $pdo->query("SELECT id, user_id, title, institution, location, description, file_name, file_path, status, sign_date, end_date, created_at FROM mou_moa ORDER BY created_at DESC");
        $evidence['mous'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error fetching MOUs: " . $e->getMessage());
    }
    
    // Get Events - from events page (events table)
    // These are events created from the events/activities page
    try {
        $stmt = $pdo->query("SELECT id, user_id, title, description, event_date, location, status, created_at FROM events ORDER BY event_date DESC");
        $evidence['events'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error fetching events: " . $e->getMessage());
    }
    
    // Get Other Documents - from documents page (other_documents table)
    // Exclude MOU/MOA documents (they should only appear in MOU/MOA tab)
    // Check category, title, and file_name for MOU/MOA indicators (case-insensitive)
    try {
        $stmt = $pdo->query("
            SELECT id, user_id, title, description, file_name, file_path, category, created_at 
            FROM other_documents 
            WHERE (
                (category IS NULL OR (LOWER(category) NOT LIKE '%mou%' AND LOWER(category) NOT LIKE '%moa%'))
                AND (title IS NULL OR (LOWER(title) NOT LIKE '%mou%' AND LOWER(title) NOT LIKE '%moa%'))
                AND (file_name IS NULL OR (LOWER(file_name) NOT LIKE '%mou%' AND LOWER(file_name) NOT LIKE '%moa%'))
            )
            ORDER BY created_at DESC
        ");
        $evidence['documents'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error fetching documents: " . $e->getMessage());
    }
    
    return $evidence;
}

/**
 * Calculate award statistics based on keyword matching
 */
function calculateAwardStats($award, $evidence) {
    $stats = [
        'total' => 0,
        'eligible' => 0,
        'aiDetected' => 0,
        'certificates' => [],
        'mous' => [],
        'events' => [],
        'documents' => [],
        'readiness' => 0
    ];
    
    $keywords = $award['keywords'];
    $weights = $award['weight'] ?? [];
    
    // Process certificates - from awards page
    // Include ALL files, calculate match score to determine eligibility for this award
    foreach ($evidence['certificates'] as $item) {
        $text = strtolower(($item['title'] ?? '') . ' ' . ($item['description'] ?? ''));
        $score = calculateMatchScore($text, $keywords, $weights);
        // Include all files, but still calculate match score for display and eligibility
        $item['matchScore'] = $score;
        $item['aiDetected'] = $score >= 30; // Mark as AI-detected if score is >= 30
        $stats['certificates'][] = $item;
        $stats['total']++;
        if ($score >= 30) $stats['aiDetected']++;
        if ($score >= 70) $stats['eligible']++;
    }
    
    // Process MOUs - from MOU/MOA page
    // Include ALL files regardless of match score
    foreach ($evidence['mous'] as $item) {
        $text = strtolower(($item['title'] ?? '') . ' ' . ($item['institution'] ?? '') . ' ' . ($item['description'] ?? ''));
        $score = calculateMatchScore($text, $keywords, $weights);
        // Include all files, but still calculate match score for display
        $item['matchScore'] = $score;
        $item['aiDetected'] = $score >= 30; // Mark as AI-detected if score is >= 30
        $stats['mous'][] = $item;
        $stats['total']++;
        if ($score >= 30) $stats['aiDetected']++;
        if ($score >= 70) $stats['eligible']++;
    }
    
    // Process events - from events page
    // Include ALL files regardless of match score
    foreach ($evidence['events'] as $item) {
        $text = strtolower(($item['title'] ?? '') . ' ' . ($item['description'] ?? '') . ' ' . ($item['location'] ?? ''));
        $score = calculateMatchScore($text, $keywords, $weights);
        // Include all files, but still calculate match score for display
        $item['matchScore'] = $score;
        $item['aiDetected'] = $score >= 30; // Mark as AI-detected if score is >= 30
        $stats['events'][] = $item;
        $stats['total']++;
        if ($score >= 30) $stats['aiDetected']++;
        if ($score >= 70) $stats['eligible']++;
    }
    
    // Process documents - from documents page (excluding MOU/MOA)
    // Include ALL files regardless of match score
    foreach ($evidence['documents'] as $item) {
        $text = strtolower(($item['title'] ?? '') . ' ' . ($item['description'] ?? '') . ' ' . ($item['category'] ?? ''));
        $score = calculateMatchScore($text, $keywords, $weights);
        // Include all files, but still calculate match score for display
        $item['matchScore'] = $score;
        $item['aiDetected'] = $score >= 30; // Mark as AI-detected if score is >= 30
        $stats['documents'][] = $item;
        $stats['total']++;
        if ($score >= 30) $stats['aiDetected']++;
        if ($score >= 70) $stats['eligible']++;
    }
    
    // Calculate readiness (5 items = 100%)
    $stats['readiness'] = min(100, round(($stats['total'] / 5) * 100));
    
    return $stats;
}

/**
 * Calculate match score using weighted keyword matching
 */
function calculateMatchScore($text, $keywords, $weights = []) {
    $totalWeight = 0;
    $matchedWeight = 0;
    
    foreach ($keywords as $keyword) {
        $keywordLower = strtolower($keyword);
        $weight = $weights[$keyword] ?? 1;
        $totalWeight += $weight;
        
        if (strpos($text, $keywordLower) !== false) {
            $matchedWeight += $weight;
        }
    }
    
    if ($totalWeight === 0) return 0;
    
    return round(($matchedWeight / $totalWeight) * 100);
}

/**
 * Analyze an event for award eligibility
 */
function analyzeEventForAwards($pdo, $eventId) {
    global $AWARDS_CONFIG;
    
    // Get event data
    $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
    $stmt->execute([$eventId]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$event) {
        throw new Exception('Event not found');
    }
    
    $text = strtolower(($event['title'] ?? '') . ' ' . ($event['description'] ?? '') . ' ' . ($event['location'] ?? ''));
    $matches = [];
    
    foreach ($AWARDS_CONFIG as $award) {
        $score = calculateMatchScore($text, $award['keywords'], $award['weight'] ?? []);
        if ($score >= 30) {
            $matches[] = [
                'award_id' => $award['id'],
                'award_title' => $award['title'],
                'match_score' => $score,
                'eligible' => $score >= 70
            ];
        }
    }
    
    // Sort by score descending
    usort($matches, function($a, $b) {
        return $b['match_score'] - $a['match_score'];
    });
    
    return [
        'success' => true,
        'event' => [
            'id' => $event['id'],
            'title' => $event['title']
        ],
        'matches' => $matches,
        'total_matches' => count($matches)
    ];
}

/**
 * Analyze all items and return aggregated results
 */
function analyzeAllItems($pdo) {
    global $AWARDS_CONFIG;
    
    $combined = getCombinedEvidence($pdo);
    $results = [];
    
    foreach ($AWARDS_CONFIG as $award) {
        $stats = calculateAwardStats($award, $combined);
        $results[$award['id']] = [
            'award' => $award['title'],
            'category' => $award['category'],
            'stats' => [
                'total' => $stats['total'],
                'eligible' => $stats['eligible'],
                'readiness' => $stats['readiness']
            ]
        ];
    }
    
    return [
        'success' => true,
        'analysis' => $results,
        'timestamp' => date('Y-m-d H:i:s')
    ];
}

