<?php
/**
 * AI Award Analyzer API
 * Automatically analyzes events, documents, and other items for ICONS 2025 award eligibility
 */

// Only execute headers and main logic if called directly
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
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
}

require_once __DIR__ . '/config.php';

// ICONS 2025 Award definitions with weighted keywords
$AWARDS_CONFIG = [
    'global-citizenship' => [
        'id' => 'global-citizenship',
        'title' => 'Global Citizenship Award',
        'category' => 'institutional',
        'keywords' => [
            'global citizenship' => 5,
            'intercultural understanding' => 4,
            'global awareness' => 4,
            'cultural understanding' => 3,
            'community engagement' => 3,
            'SDG' => 3,
            'sustainable development goals' => 4,
            'global' => 2,
            'citizenship' => 2,
            'international' => 2,
            'cross-cultural' => 3,
            'diversity' => 2,
            'inclusive' => 2,
            'global leaders' => 4,
            'changemakers' => 3
        ],
        'min_score' => 40
    ],
    'international-education' => [
        'id' => 'international-education',
        'title' => 'Outstanding International Education Program',
        'category' => 'institutional',
        'keywords' => [
            'international education' => 5,
            'exchange program' => 4,
            'student mobility' => 4,
            'student exchange' => 4,
            'inclusive internationalization' => 5,
            'academic partnership' => 3,
            'curriculum' => 2,
            'education' => 2,
            'exchange' => 2,
            'mobility' => 2,
            'indigenous perspectives' => 4,
            'diverse backgrounds' => 3,
            'collaborative innovation' => 3
        ],
        'min_score' => 40
    ],
    'sustainability' => [
        'id' => 'sustainability',
        'title' => 'Sustainability Award',
        'category' => 'institutional',
        'keywords' => [
            'sustainability' => 5,
            'SDG' => 4,
            'sustainable development' => 5,
            'environment' => 3,
            'environmental' => 3,
            'green campus' => 4,
            'climate' => 3,
            'renewable' => 3,
            'eco-friendly' => 3,
            'energy efficiency' => 4,
            'waste management' => 3,
            'carbon' => 2,
            'recycling' => 2,
            'social well-being' => 3
        ],
        'min_score' => 40
    ],
    'asean-awareness' => [
        'id' => 'asean-awareness',
        'title' => 'Best ASEAN Awareness Initiative',
        'category' => 'institutional',
        'keywords' => [
            'ASEAN' => 5,
            'Southeast Asia' => 4,
            'regional cooperation' => 4,
            'cultural exchange' => 3,
            'ASEAN community' => 5,
            'regional' => 2,
            'solidarity' => 3,
            'ASEAN awareness' => 5,
            'regional identity' => 4,
            'youth dialogue' => 3,
            'ASEAN studies' => 4,
            'cultural festivals' => 3
        ],
        'min_score' => 40
    ],
    'emerging-leadership' => [
        'id' => 'emerging-leadership',
        'title' => 'Emerging Leadership Award',
        'category' => 'individual',
        'keywords' => [
            'emerging leader' => 5,
            'young leader' => 4,
            'mentorship' => 4,
            'innovation' => 3,
            'leadership growth' => 4,
            'leadership' => 2,
            'potential' => 2,
            'IRO' => 3,
            'international relations' => 3,
            'junior leaders' => 4,
            'spearhead' => 3,
            'creative approach' => 3
        ],
        'min_score' => 40
    ],
    'izn-leadership' => [
        'id' => 'izn-leadership',
        'title' => 'Internationalization Leadership Award',
        'category' => 'individual',
        'keywords' => [
            'strategic vision' => 5,
            'governance' => 4,
            'institutional leadership' => 5,
            'ethical leadership' => 5,
            'global excellence' => 4,
            'leadership' => 2,
            'executive' => 3,
            'president' => 3,
            'vice-president' => 3,
            'internationalization' => 3,
            'fiscal management' => 4,
            'lifelong learning' => 3
        ],
        'min_score' => 40
    ],
    'ched-regional' => [
        'id' => 'ched-regional',
        'title' => 'Best CHED Regional Office',
        'category' => 'special',
        'keywords' => [
            'CHED' => 5,
            'regional office' => 5,
            'policy' => 3,
            'coordination' => 3,
            'governance' => 3,
            'regional' => 2,
            'office' => 2,
            'operational excellence' => 4,
            'compliance' => 3,
            'HEI' => 3
        ],
        'min_score' => 40
    ],
    'iro-community' => [
        'id' => 'iro-community',
        'title' => 'Most Promising IRO Community',
        'category' => 'special',
        'keywords' => [
            'IRO network' => 5,
            'IRO community' => 5,
            'collaboration' => 3,
            'regional partnership' => 4,
            'community' => 2,
            'IRO' => 4,
            'international relations office' => 4,
            'capacity building' => 3,
            'resource sharing' => 3
        ],
        'min_score' => 40
    ]
];

if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    try {
        $pdo = getDatabaseConnection();
        
        switch ($action) {
            case 'analyze_event':
                $eventId = $_GET['event_id'] ?? $_POST['event_id'] ?? '';
                if (empty($eventId)) {
                    throw new Exception('Event ID is required');
                }
                echo json_encode(analyzeEvent($pdo, $eventId));
                break;
                
            case 'analyze_mou':
                $mouId = $_GET['mou_id'] ?? $_POST['mou_id'] ?? '';
                if (empty($mouId)) {
                    throw new Exception('MOU ID is required');
                }
                echo json_encode(analyzeMOU($pdo, $mouId));
                break;
                
            case 'analyze_document':
                $docId = $_GET['doc_id'] ?? $_POST['doc_id'] ?? '';
                if (empty($docId)) {
                    throw new Exception('Document ID is required');
                }
                echo json_encode(analyzeDocument($pdo, $docId));
                break;
                
            case 'analyze_text':
                $text = $_POST['text'] ?? '';
                if (empty($text)) {
                    throw new Exception('Text is required');
                }
                echo json_encode(analyzeText($text));
                break;
                
            case 'get_awards':
                echo json_encode([
                    'success' => true,
                    'awards' => array_map(function($award) {
                        return [
                            'id' => $award['id'],
                            'title' => $award['title'],
                            'category' => $award['category']
                        ];
                    }, array_values($AWARDS_CONFIG))
                ]);
                break;
                
            default:
                throw new Exception('Invalid action. Use: analyze_event, analyze_mou, analyze_document, analyze_text, or get_awards');
        }
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

/**
 * Analyze an event for award eligibility
 */
function analyzeEvent($pdo, $eventId) {
    global $AWARDS_CONFIG;
    
    // Get event data
    $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
    $stmt->execute([$eventId]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$event) {
        throw new Exception('Event not found');
    }
    
    // Combine all text fields for analysis
    $text = implode(' ', [
        $event['title'] ?? '',
        $event['description'] ?? '',
        $event['location'] ?? ''
    ]);
    
    $matches = analyzeTextAgainstAwards($text);
    
    // Save matches to database if any
    saveEventMatches($pdo, $eventId, $matches);
    
    return [
        'success' => true,
        'item_type' => 'event',
        'item_id' => $eventId,
        'item_title' => $event['title'],
        'matches' => $matches,
        'total_matches' => count($matches),
        'analyzed_at' => date('Y-m-d H:i:s')
    ];
}

/**
 * Analyze a MOU/MOA for award eligibility
 */
function analyzeMOU($pdo, $mouId) {
    global $AWARDS_CONFIG;
    
    $stmt = $pdo->prepare("SELECT * FROM mou_moa WHERE id = ?");
    $stmt->execute([$mouId]);
    $mou = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$mou) {
        throw new Exception('MOU/MOA not found');
    }
    
    $text = implode(' ', [
        $mou['title'] ?? '',
        $mou['institution'] ?? '',
        $mou['description'] ?? '',
        $mou['location'] ?? ''
    ]);
    
    $matches = analyzeTextAgainstAwards($text);
    
    return [
        'success' => true,
        'item_type' => 'mou',
        'item_id' => $mouId,
        'item_title' => $mou['title'] ?? $mou['institution'],
        'matches' => $matches,
        'total_matches' => count($matches),
        'analyzed_at' => date('Y-m-d H:i:s')
    ];
}

/**
 * Analyze a document for award eligibility
 */
function analyzeDocument($pdo, $docId) {
    global $AWARDS_CONFIG;
    
    $stmt = $pdo->prepare("SELECT * FROM other_documents WHERE id = ?");
    $stmt->execute([$docId]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$doc) {
        throw new Exception('Document not found');
    }
    
    $text = implode(' ', [
        $doc['title'] ?? '',
        $doc['description'] ?? '',
        $doc['category'] ?? ''
    ]);
    
    $matches = analyzeTextAgainstAwards($text);
    
    return [
        'success' => true,
        'item_type' => 'document',
        'item_id' => $docId,
        'item_title' => $doc['title'],
        'matches' => $matches,
        'total_matches' => count($matches),
        'analyzed_at' => date('Y-m-d H:i:s')
    ];
}

/**
 * Analyze raw text against all awards
 */
function analyzeText($text) {
    $matches = analyzeTextAgainstAwards($text);
    
    return [
        'success' => true,
        'matches' => $matches,
        'total_matches' => count($matches),
        'analyzed_at' => date('Y-m-d H:i:s')
    ];
}

/**
 * Core analysis function - matches text against all award keywords
 */
function analyzeTextAgainstAwards($text) {
    global $AWARDS_CONFIG;
    
    $textLower = strtolower($text);
    $matches = [];
    
    foreach ($AWARDS_CONFIG as $awardId => $award) {
        $result = calculateWeightedScore($textLower, $award['keywords']);
        
        if ($result['score'] >= $award['min_score']) {
            $matches[] = [
                'award_id' => $award['id'],
                'award_title' => $award['title'],
                'category' => $award['category'],
                'match_score' => $result['score'],
                'matched_keywords' => $result['matched_keywords'],
                'eligible' => $result['score'] >= 70,
                'status' => getScoreStatus($result['score'])
            ];
        }
    }
    
    // Sort by score descending
    usort($matches, function($a, $b) {
        return $b['match_score'] - $a['match_score'];
    });
    
    return $matches;
}

/**
 * Calculate weighted keyword score
 */
function calculateWeightedScore($text, $keywords) {
    $totalWeight = 0;
    $matchedWeight = 0;
    $matchedKeywords = [];
    
    foreach ($keywords as $keyword => $weight) {
        $totalWeight += $weight;
        $keywordLower = strtolower($keyword);
        
        if (strpos($text, $keywordLower) !== false) {
            $matchedWeight += $weight;
            $matchedKeywords[] = $keyword;
        }
    }
    
    $score = $totalWeight > 0 ? round(($matchedWeight / $totalWeight) * 100) : 0;
    
    return [
        'score' => $score,
        'matched_keywords' => $matchedKeywords,
        'matched_count' => count($matchedKeywords),
        'total_keywords' => count($keywords)
    ];
}

/**
 * Get status based on score
 */
function getScoreStatus($score) {
    if ($score >= 70) return 'Eligible';
    if ($score >= 50) return 'Almost Eligible';
    return 'Partially Matched';
}

/**
 * Save event matches to database
 */
function saveEventMatches($pdo, $eventId, $matches) {
    if (empty($matches)) return;
    
    // Check if award_event_evidence table exists
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'award_event_evidence'");
        $tableExists = $stmt->rowCount() > 0;
        
        if (!$tableExists) {
            // Create the table if it doesn't exist
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `award_event_evidence` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `event_id` int(11) NOT NULL,
                    `award_id` varchar(100) NOT NULL,
                    `match_score` decimal(5,2) DEFAULT NULL,
                    `matched_keywords` text DEFAULT NULL,
                    `status` varchar(50) DEFAULT 'pending',
                    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                    `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                    PRIMARY KEY (`id`),
                    KEY `idx_event_id` (`event_id`),
                    KEY `idx_award_id` (`award_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }
        
        // Delete existing matches for this event
        $stmt = $pdo->prepare("DELETE FROM award_event_evidence WHERE event_id = ?");
        $stmt->execute([$eventId]);
        
        // Insert new matches
        $stmt = $pdo->prepare("
            INSERT INTO award_event_evidence (event_id, award_id, match_score, matched_keywords, status)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        foreach ($matches as $match) {
            $stmt->execute([
                $eventId,
                $match['award_id'],
                $match['match_score'],
                json_encode($match['matched_keywords']),
                $match['status']
            ]);
        }
        
        // Update event to mark as award evidence if high score
        $hasHighScore = false;
        foreach ($matches as $match) {
            if ($match['match_score'] >= 40) {
                $hasHighScore = true;
                break;
            }
        }
        
        if ($hasHighScore) {
            // Check if is_award_evidence column exists
            $stmt = $pdo->query("SHOW COLUMNS FROM events LIKE 'is_award_evidence'");
            if ($stmt->rowCount() > 0) {
                $stmt = $pdo->prepare("UPDATE events SET is_award_evidence = 1 WHERE id = ?");
                $stmt->execute([$eventId]);
            }
        }
        
    } catch (Exception $e) {
        error_log("Error saving event matches: " . $e->getMessage());
        // Don't throw - just log the error
    }
}

