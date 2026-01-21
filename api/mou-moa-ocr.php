<?php
/**
 * MOU/MOA OCR + Field Extraction API
 * Upload a file (PDF/DOCX/Image) and returns extracted fields for auto-fill.
 *
 * Currently supported fields:
 * - institution
 */

// Suppress error display and use output buffering to catch any accidental output
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
    http_response_code(200);
    exit();
}

session_start();

if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized. Please login.']);
    exit();
}

try {
    require_once __DIR__ . '/award-analysis-functions.php';
} catch (Throwable $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to load required functions: ' . $e->getMessage()]);
    exit();
}

// Clear any output that might have been generated during includes
ob_clean();

function normalizeWhitespaceKeepNewlines($text) {
    $text = (string)($text ?? '');
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    // Collapse horizontal whitespace but keep newlines
    $text = preg_replace('/[ \t]+/', ' ', $text);
    // Collapse excessive newlines
    $text = preg_replace("/\n{3,}/", "\n\n", $text);
    return trim($text);
}

function cleanInstitutionCandidate($s) {
    $s = trim((string)$s);
    $s = preg_replace('/\s+/', ' ', $s);

    // Remove leading labels/boilerplate
    $s = preg_replace('/^(the\s+)?(partner\s+)?institution\s*[:\-]\s*/i', '', $s);
    $s = preg_replace('/^(name\s+of\s+)?(the\s+)?institution\s*[:\-]\s*/i', '', $s);

    // Remove trailing boilerplate
    $s = preg_replace('/\s*,?\s*(hereinafter|herein after)\b.*$/i', '', $s);
    $s = preg_replace('/\s*[\.\,\;\:\-]+$/', '', $s);

    // Remove location/address information - stop at location indicators
    // Remove anything after patterns like ", located", ", in [city]", ", [country]", etc.
    $s = preg_replace('/\s*,\s*(located\s+(?:at|in)|in\s+[A-Z][a-z]+|at\s+[A-Z][a-z]+).*$/i', '', $s);
    
    // Remove country/city names at the end (common pattern: "University, City, Country")
    // List of common location keywords that shouldn't be in institution name
    $locationPatterns = [
        '/\s*,\s*(china|japan|philippines|philippine|united\s+states|usa|korea|canada|australia|singapore|malaysia|thailand|vietnam|indonesia|india|taiwan).*$/i',
        '/\s*,\s*([A-Z][a-z]+\s*(?:city|province|state|region|prefecture|county)).*$/i',
        '/\s*,\s*([A-Z][a-z]+,\s*(?:china|japan|philippines|philippine|united\s+states|usa|korea|canada|australia|singapore|malaysia|thailand|vietnam|indonesia|india|taiwan)).*$/i',
    ];
    foreach ($locationPatterns as $pattern) {
        $s = preg_replace($pattern, '', $s);
    }
    
    // If there are multiple commas, likely has address info - take only first part
    // But be careful: some institution names legitimately have commas (e.g., "X, Inc.")
    // Only split if we detect location keywords
    if (preg_match('/^([^,]+(?:,\s*[^,]+)*?)(?:,\s*[^,]*?(?:located|address|in\s+[A-Z]|city|province|state|country|china|japan|philippines|usa|korea|canada|australia))/i', $s, $m)) {
        $s = $m[1];
    }
    
    // Remove surrounding quotes (including curly quotes)
    $s = trim($s);
    $s = preg_replace('/^["\x{201C}\x{201D}\x{201E}\x{201F}]+|["\x{201C}\x{201D}\x{201E}\x{201F}]+$/u', '', $s);
    $s = preg_replace("/^['\x{2018}\x{2019}\x{201A}\x{201B}]+|['\x{2018}\x{2019}\x{201A}\x{201B}]+$/u", '', $s);

    // Guardrails: avoid returning generic words
    if (strlen($s) < 4) return '';
    if (preg_match('/^(memorandum|agreement|understanding|contract|page)\b/i', $s)) return '';
    
    // Reject person names: patterns like "nee", initials only, or name-like patterns
    $lower = strtolower($s);
    if (preg_match('/\bnee\b/i', $s)) return ''; // "nee" indicates a person's name (maiden name)
    if (preg_match('/^(mr|mrs|ms|dr|prof|professor)\s+/i', $s)) return ''; // Titles indicate person names
    if (preg_match('/\b(sr|jr|iii|iv|ii)\b/i', $s)) return ''; // Suffixes indicate person names
    
    // Reject if it looks like initials or very short name patterns (e.g., "KM SANG")
    // But allow if it contains institution keywords
    $hasInstitutionKeyword = preg_match('/\b(university|college|institute|institution|school|academy|polytechnic|foundation|corporation|inc|llc|ltd)\b/i', $s);
    if (!$hasInstitutionKeyword) {
        // If it's mostly uppercase initials/words without institution keywords, likely a person name
        $words = preg_split('/\s+/', $s);
        $shortWords = 0;
        foreach ($words as $word) {
            $cleanWord = preg_replace('/[^A-Za-z]/', '', $word);
            if (strlen($cleanWord) <= 3) $shortWords++;
        }
        // If more than half are short words, likely initials/name
        if (count($words) > 0 && $shortWords >= ceil(count($words) / 2) && count($words) <= 6) {
            return '';
        }
    }

    return trim($s);
}

function cleanLocationCandidate($s) {
    $s = trim((string)$s);
    // Strip common lead-ins
    $s = preg_replace('/^(address|location)\s*[:\-]\s*/i', '', $s);
    $s = preg_replace('/^(located\s+(at|in))\s+/i', '', $s);
    // Strip trailing boilerplate
    $s = preg_replace('/\s*hereinafter.*$/i', '', $s);
    $s = preg_replace('/\s*referred to as.*$/i', '', $s);
    $s = preg_replace('/\s*[,;]+$/', '', $s);
    $s = trim($s, "\"' ");

    if (strlen($s) < 5) return '';
    return $s;
}

function extractLocationsFromText($text) {
    $raw = normalizeWhitespaceKeepNewlines($text);
    $locations = [];

    // Patterns for "located at/in ..."
    if (preg_match_all('/located\s+(?:at|in)\s+([^\.;\n]{5,180})/i', $raw, $m)) {
        foreach ($m[1] as $loc) {
            $c = cleanLocationCandidate($loc);
            if ($c !== '') {
                $locations[] = ['value' => $c, 'method' => 'located'];
            }
        }
    }

    // Patterns for "address: ..."
    if (preg_match_all('/address\s*[:\-]\s*([^\n]{5,180})/i', $raw, $m2)) {
        foreach ($m2[1] as $loc) {
            $c = cleanLocationCandidate($loc);
            if ($c !== '') {
                $locations[] = ['value' => $c, 'method' => 'address'];
            }
        }
    }

    // De-dupe while preserving order
    $seen = [];
    $unique = [];
    foreach ($locations as $loc) {
        $key = strtolower(preg_replace('/\s+/', ' ', $loc['value']));
        if (isset($seen[$key])) continue;
        $seen[$key] = true;
        $unique[] = $loc;
    }

    return $unique;
}

function scoreInstitutionLine($line) {
    $line = trim((string)$line);
    if ($line === '') return 0;

    // Penalize obvious non-institution lines
    if (preg_match('/\bmemorandum\b|\bof\b\s+\b(understanding|agreement)\b/i', $line)) return 0;
    if (preg_match('/\bdate\b|\bday\b|\bmonth\b|\byear\b/i', $line)) return 0;
    if (preg_match('/\b(signature|signatory|witness|whereas|therefore)\b/i', $line)) return 0;
    
    // Reject person names
    if (preg_match('/\bnee\b/i', $line)) return 0; // "nee" indicates person name
    if (preg_match('/^(mr|mrs|ms|dr|prof|professor)\s+/i', $line)) return 0; // Titles
    if (preg_match('/\b(sr|jr|iii|iv|ii)\b/i', $line)) return 0; // Name suffixes

    $len = strlen($line);
    if ($len < 8 || $len > 160) return 0;

    $score = 0;

    // Keyword boosts (REQUIRED for valid institution)
    $hasInstitutionKeyword = preg_match('/\b(university|college|institute|institution|school|academy|polytechnic|foundation|corporation|inc|llc|ltd)\b/i', $line);
    if ($hasInstitutionKeyword) {
        $score += 8; // Strong boost for institution keywords
    } else {
        // Without institution keywords, heavily penalize (likely a person name)
        // Check if it looks like initials/name pattern
        $words = preg_split('/\s+/', $line);
        $shortWords = 0;
        foreach ($words as $word) {
            $cleanWord = preg_replace('/[^A-Za-z]/', '', $word);
            if (strlen($cleanWord) <= 3) $shortWords++;
        }
        if (count($words) > 0 && $shortWords >= ceil(count($words) / 2) && count($words) <= 6) {
            return 0; // Reject initials/name patterns without institution keywords
        }
        // If no institution keyword and doesn't look like name, give very low score
        $score -= 5;
    }
    
    if (preg_match('/\b(department|center|centre|office)\b/i', $line)) $score += 2;
    if (preg_match('/\b(philippines|philippine)\b/i', $line)) $score += 1;

    // Uppercase ratio (common in headings)
    $letters = preg_replace('/[^A-Za-z]/', '', $line);
    if ($letters !== '') {
        $upper = preg_replace('/[^A-Z]/', '', $letters);
        $ratio = strlen($upper) / max(1, strlen($letters));
        if ($ratio >= 0.75) $score += 3;
        elseif ($ratio >= 0.5) $score += 2;
        elseif ($ratio >= 0.3) $score += 1;
    }

    // Prefer medium-length lines
    if ($len >= 18 && $len <= 90) $score += 2;

    // Penalize too many digits/symbols
    $digitCount = preg_match_all('/\d/', $line);
    if ($digitCount >= 6) $score -= 3;

    return $score;
}

function extractInstitutionFromText($text) {
    $raw = normalizeWhitespaceKeepNewlines($text);
    $lower = strtolower($raw);

    $candidates = [];
    $betweenParties = null; // [party1, party2]

    // 1) Explicit label: "Institution: XYZ"
    if (preg_match('/(?:partner\s+institution|institution|name\s+of\s+institution)\s*[:\-]\s*([^\n]{4,160})/i', $raw, $m)) {
        $cand = cleanInstitutionCandidate($m[1]);
        if ($cand !== '') $candidates[] = ['value' => $cand, 'method' => 'label'];
    }

    // 2) "by and between X and Y" clause
    if (preg_match('/\b(?:by\s+and\s+between|between)\b\s*([\s\S]{0,220}?)\s+(?:and|&)\s+([\s\S]{0,220}?)(?:\n|,|\.|;)/i', $raw, $m)) {
        $a = cleanInstitutionCandidate($m[1]);
        $b = cleanInstitutionCandidate($m[2]);
        if ($a !== '' && $b !== '') {
            $betweenParties = [$a, $b];
        }
        if ($a !== '') $candidates[] = ['value' => $a, 'method' => 'between'];
        if ($b !== '') $candidates[] = ['value' => $b, 'method' => 'between'];
    }

    // 2b) Common scanned layout:
    // "between" on its own line, then PARTY A, then "and", then PARTY B (each often on separate lines).
    if (!is_array($betweenParties)) {
        $lines = preg_split("/\n+/", $raw);
        $betweenIdx = -1;
        for ($i = 0; $i < count($lines); $i++) {
            $l = trim((string)$lines[$i]);
            if ($l === '') continue;
            if (preg_match('/^\s*between\s*$/i', $l) || preg_match('/\bbetween\b/i', $l)) {
                $betweenIdx = $i;
                break;
            }
        }

        if ($betweenIdx >= 0) {
            // Try inline "between A and B" on same line first
            $inline = trim((string)$lines[$betweenIdx]);
            if (preg_match('/\bbetween\b\s+(.+?)\s+(?:and|&)\s+(.+)\s*$/i', $inline, $m2)) {
                $a2 = cleanInstitutionCandidate($m2[1]);
                $b2 = cleanInstitutionCandidate($m2[2]);
                if ($a2 !== '' && $b2 !== '') {
                    $betweenParties = [$a2, $b2];
                }
            }

            // Otherwise parse subsequent lines
            if (!is_array($betweenParties)) {
                $partyA = '';
                $partyB = '';
                $state = 'seekA'; // seekA -> seekAnd -> seekB

                for ($j = $betweenIdx + 1; $j < min(count($lines), $betweenIdx + 18); $j++) {
                    $l = trim((string)$lines[$j]);
                    if ($l === '') continue;

                    // Stop if we hit body text early
                    if (preg_match('/\b(hereinafter|located at|agree|resolve|whereas)\b/i', $l)) {
                        break;
                    }

                    if ($state === 'seekA') {
                        // Skip headings like "MEMORANDUM OF ..."
                        if (preg_match('/\bmemorandum\b/i', $l)) continue;
                        if (preg_match('/^\s*between\s*$/i', $l)) continue;

                        // Sometimes "PARTY A and" appears on one line
                        if (preg_match('/^(.*?)(?:\s+(?:and|&)\s*)$/i', $l, $mA) && !preg_match('/^\s*and\s*$/i', $l)) {
                            $partyA = cleanInstitutionCandidate($mA[1]);
                            $state = 'seekB';
                            continue;
                        }

                        $partyA = cleanInstitutionCandidate($l);
                        if ($partyA !== '') {
                            $state = 'seekAnd';
                        }
                        continue;
                    }

                    if ($state === 'seekAnd') {
                        if (preg_match('/^\s*(?:and|&)\s*$/i', $l) || preg_match('/^\s*and\s+$/i', $l)) {
                            $state = 'seekB';
                            continue;
                        }
                        // Sometimes OCR drops the standalone "and"; if this line looks like an institution, treat as B.
                        if (scoreInstitutionLine($l) >= 4) {
                            $partyB = cleanInstitutionCandidate($l);
                            if ($partyB !== '') break;
                        }
                        continue;
                    }

                    if ($state === 'seekB') {
                        $partyB = cleanInstitutionCandidate($l);
                        if ($partyB !== '') break;
                    }
                }

                if ($partyA !== '' && $partyB !== '') {
                    $betweenParties = [$partyA, $partyB];
                    $candidates[] = ['value' => $partyA, 'method' => 'between_lines'];
                    $candidates[] = ['value' => $partyB, 'method' => 'between_lines'];
                }
            }
        }
    }

    // 3) Line-based best guess (headings / party names)
    $lines = preg_split("/\n+/", $raw);
    $best = ['value' => '', 'score' => 0, 'method' => 'lines'];

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') continue;

        // Skip lines that are clearly not institution names
        if (strpos($lower, 'memorandum of understanding') !== false || strpos($lower, 'memorandum of agreement') !== false) {
            // no-op (kept for readability)
        }

        $score = scoreInstitutionLine($line);
        if ($score <= 0) continue;

        $cand = cleanInstitutionCandidate($line);
        if ($cand === '') continue;

        if ($score > $best['score']) {
            $best = ['value' => $cand, 'score' => $score, 'method' => 'lines'];
        }
    }

    if ($best['value'] !== '') {
        $candidates[] = ['value' => $best['value'], 'method' => $best['method']];
    }

    // De-dupe while preserving order
    $seen = [];
    $unique = [];
    foreach ($candidates as $c) {
        $key = strtolower(preg_replace('/\s+/', ' ', $c['value']));
        if (isset($seen[$key])) continue;
        $seen[$key] = true;
        
        // Final validation: reject person names even from "between" clauses
        $cleaned = cleanInstitutionCandidate($c['value']);
        if ($cleaned === '') continue; // Rejected by cleanup
        
        // Check for institution keywords
        $hasKeyword = preg_match('/\b(university|college|institute|institution|school|academy|polytechnic|foundation|corporation|inc|llc|ltd)\b/i', $cleaned);
        
        // Validation rules:
        // 1. "label" method: always accept (user explicitly labeled it)
        // 2. "between" method: accept if has keywords OR if no person name indicators and reasonable length
        // 3. "lines" method: require keywords (too noisy otherwise)
        if ($c['method'] === 'label') {
            // Always accept labeled institutions
            $unique[] = ['value' => $cleaned, 'method' => $c['method']];
        } elseif ($c['method'] === 'between' || $c['method'] === 'between_lines') {
            // For between clauses: accept if has keywords OR if it's not obviously a person name
            if ($hasKeyword) {
                $unique[] = ['value' => $cleaned, 'method' => $c['method']];
            } else {
                // Without keywords, check it's not a person name pattern
                // If it's long enough and doesn't look like initials/name, allow it
                $len = strlen($cleaned);
                $words = preg_split('/\s+/', $cleaned);
                $shortWords = 0;
                foreach ($words as $word) {
                    $cleanWord = preg_replace('/[^A-Za-z]/', '', $word);
                    if (strlen($cleanWord) <= 3) $shortWords++;
                }
                // Allow if: reasonable length AND not mostly short words (initials)
                if ($len >= 8 && $len <= 160 && !($shortWords >= ceil(count($words) / 2) && count($words) <= 6)) {
                    $unique[] = ['value' => $cleaned, 'method' => $c['method']];
                }
            }
        } else {
            // For line-based detection: require keywords (too noisy otherwise)
            if ($hasKeyword) {
                $unique[] = ['value' => $cleaned, 'method' => $c['method']];
            }
        }
    }

    // Choose first (label/between often strongest), fallback to best line-based
    // For MOU/MOA: typically "between OUR institution and PARTNER institution".
    // The modal "Institution" field is intended for the PARTNER, so if we have a between-clause,
    // prefer the second party as the chosen institution.
    $chosen = '';
    $method = 'none';
    if (is_array($betweenParties) && count($betweenParties) === 2 && !empty($betweenParties[1])) {
        $cleanedParty = cleanInstitutionCandidate($betweenParties[1]);
        // Use if it passes validation (cleaned successfully - already filtered person names)
        // Don't require keywords here - allow legitimate institutions without keywords
        if ($cleanedParty !== '') {
            $chosen = $cleanedParty;
            $method = 'between';
        }
    }
    
    // Fallback to first valid candidate if between didn't yield valid result
    if ($chosen === '' && !empty($unique)) {
        $chosen = $unique[0]['value'] ?? '';
        $method = $unique[0]['method'] ?? 'none';
    }

    // Final cleanup pass: ensure institution name doesn't contain location info
    $chosen = cleanInstitutionCandidate($chosen);

    // Confidence heuristic
    $confidence = 'low';
    if ($chosen !== '') {
        if ($method === 'label') $confidence = 'high';
        elseif ($method === 'between') $confidence = 'medium';
        else $confidence = 'low';
    }

    return [
        'institution' => $chosen,
        'method' => $method,
        'confidence' => $confidence,
        'between_parties' => $betweenParties
    ];
}

/**
 * Extract the partner institution location (favor the second party / matched name).
 */
function extractInstitutionLocationFromText($text, $instData) {
    $raw = normalizeWhitespaceKeepNewlines($text);
    $institution = trim((string)($instData['institution'] ?? ''));
    $institutionLower = strtolower($institution);
    $betweenParties = $instData['between_parties'] ?? null;

    // Collect all "X located at|in Y" pairs
    $pairs = [];
    if (preg_match_all('/([^\n]{2,140}?)\s+located\s+(?:at|in)\s+([^\n\.]{5,220}?)(?:(?:\s*,?\s*hereinafter\b)|\.|\n)/i', $raw, $all, PREG_SET_ORDER)) {
        foreach ($all as $m) {
            $name = cleanInstitutionCandidate($m[1]);
            $loc = cleanLocationCandidate($m[2]);
            if ($name && $loc) {
                $pairs[] = ['name' => $name, 'loc' => $loc];
            }
        }
    }

    // 1) Exact/contains match vs chosen institution
    foreach ($pairs as $p) {
        $nLower = strtolower($p['name']);
        if ($institution && (strpos($institutionLower, $nLower) !== false || strpos($nLower, $institutionLower) !== false)) {
            return $p['loc'];
        }
    }

    // 2) If we have two parties, prefer party B
    if (is_array($betweenParties) && count($betweenParties) === 2) {
        $partyBLower = strtolower($betweenParties[1]);
        foreach ($pairs as $p) {
            $nLower = strtolower($p['name']);
            if (strpos($partyBLower, $nLower) !== false || strpos($nLower, $partyBLower) !== false) {
                return $p['loc'];
            }
        }
        if (count($pairs) >= 2) {
            return $pairs[1]['loc'];
        }
    }

    // 3) If two locations found, assume second is partner
    if (count($pairs) >= 2) {
        return $pairs[1]['loc'];
    }

    // 4) Fallback to first found
    if (count($pairs) >= 1) {
        return $pairs[0]['loc'];
    }

    return '';
}

/**
 * Normalize location down to country-level to keep it short (e.g., "Nagata-ku, Kobe-shi, Japan" -> "Japan").
 * Uses the enhanced rule-based country detector.
 */
function normalizeLocationToCountry($location) {
    $loc = trim((string)$location);
    if ($loc === '') return $loc;

    // First try: detect country from the full location string
    $detected = detectCountryFromText($loc);
    if ($detected) return $detected;

    // Second try: if location has commas, check the last segment (common pattern: "City, Country")
    $parts = array_filter(array_map('trim', explode(',', $loc)));
    if (!empty($parts) && count($parts) > 1) {
        // Try last segment first (most common pattern)
        $last = end($parts);
        $lastCountry = detectCountryFromText($last);
        if ($lastCountry) return $lastCountry;
        
        // Also check second-to-last segment (some formats: "City, State, Country")
        if (count($parts) >= 2) {
            $partsArray = array_values($parts);
            $secondLast = $partsArray[count($partsArray) - 2];
            $secondLastCountry = detectCountryFromText($secondLast);
            if ($secondLastCountry) return $secondLastCountry;
        }
    }

    // No country detected — return empty to avoid populating with city/region
    return '';
}

/**
 * Rule-based country detector with comprehensive patterns, cities, and variations.
 * Detects countries from text using multiple strategies:
 * 1. Direct country name matches (with variations)
 * 2. City-to-country mappings
 * 3. Province/state-to-country mappings
 * 4. Country codes and abbreviations
 * 5. Region-to-country mappings
 */
function detectCountryFromText($text) {
    $text = trim((string)$text);
    if ($text === '') return '';
    
    $lower = strtolower($text);
    $normalized = preg_replace('/[^\w\s]/', ' ', $lower); // Remove punctuation, keep words
    $normalized = preg_replace('/\s+/', ' ', $normalized); // Normalize whitespace
    
    // Comprehensive country detection rules
    $countryRules = [
        // China - high priority for Fujian/Zhangzhou
        'China' => [
            'patterns' => [
                '/\bchina\b/',
                '/\bfujian\b/',
                '/\bzhangzhou\b/',
                '/\bchinese\b/',
                '/\bprc\b/', // People's Republic of China
                '/\bcn\b/',  // Country code (with word boundaries)
            ],
            'cities' => [
                'beijing', 'shanghai', 'guangzhou', 'shenzhen', 'chengdu', 'hangzhou',
                'wuhan', 'xian', 'nanjing', 'tianjin', 'chongqing', 'dalian',
                'xiamen', 'qingdao', 'fuzhou', 'suzhou', 'ningbo', 'wenzhou'
            ],
            'provinces' => [
                'guangdong', 'jiangsu', 'zhejiang', 'shandong', 'henan', 'sichuan',
                'hubei', 'hunan', 'fujian', 'anhui', 'liaoning', 'hebei',
                'shaanxi', 'jiangxi', 'guangxi', 'yunnan', 'heilongjiang'
            ]
        ],
        
        // Japan
        'Japan' => [
            'patterns' => [
                '/\bjapan\b/',
                '/\bjapanese\b/',
                '/\bjp\b/',  // Country code
            ],
            'cities' => [
                'tokyo', 'osaka', 'yokohama', 'nagoya', 'sapporo', 'fukuoka',
                'kobe', 'kyoto', 'saitama', 'hiroshima', 'sendai', 'kawasaki',
                'chiba', 'kitakyushu', 'sakai', 'shizuoka', 'nagata'
            ],
            'provinces' => [
                'hokkaido', 'aomori', 'iwate', 'miyagi', 'akita', 'yamagata',
                'fukushima', 'ibaraki', 'tochigi', 'gunma', 'saitama', 'chiba',
                'tokyo', 'kanagawa', 'yamanashi', 'nagano', 'niigata', 'toyama'
            ]
        ],
        
        // Philippines
        'Philippines' => [
            'patterns' => [
                '/\bphilippines\b/',
                '/\bphilippine\b/',
                '/\bph\b/',      // Country code
                '/\bphl\b/',     // ISO code
            ],
            'cities' => [
                'manila', 'quezon city', 'davao', 'caloocan', 'cebu', 'zamboanga',
                'antipolo', 'pasig', 'tagig', 'valenzuela', 'paranaque', 'makati',
                'san jose del monte', 'las pinas', 'bacolod', 'iloilo', 'muntinlupa',
                'calamba', 'marikina', 'butuan', 'las pinas', 'mandaluyong'
            ],
            'provinces' => [
                'metro manila', 'cavite', 'laguna', 'rizal', 'bulacan', 'pampanga',
                'bataan', 'nueva ecija', 'tarlac', 'pangasinan', 'batangas', 'quezon'
            ]
        ],
        
        // United States
        'United States' => [
            'patterns' => [
                '/\bunited\s+states\b/',
                '/\busa\b/',
                '/\bu\.s\.a\b/',
                '/\bu\.s\b/',
                '/\bus\b/',      // Country code
                '/\bamerican\b/',
            ],
            'cities' => [
                'new york', 'los angeles', 'chicago', 'houston', 'phoenix', 'philadelphia',
                'san antonio', 'san diego', 'dallas', 'san jose', 'austin', 'jacksonville',
                'san francisco', 'indianapolis', 'columbus', 'fort worth', 'charlotte'
            ],
            'provinces' => [
                'california', 'texas', 'florida', 'new york', 'pennsylvania', 'illinois',
                'ohio', 'georgia', 'north carolina', 'michigan', 'new jersey', 'virginia'
            ]
        ],
        
        // South Korea
        'Korea' => [
            'patterns' => [
                '/\bsouth\s+korea\b/',
                '/\bkorea\b/',
                '/\bkorean\b/',
                '/\bkr\b/',      // Country code
                '/\bkorea\s+republic\b/',
            ],
            'cities' => [
                'seoul', 'busan', 'incheon', 'daegu', 'daejeon', 'gwangju',
                'suwon', 'ulsan', 'changwon', 'goyang', 'yongin', 'bucheon'
            ],
            'provinces' => [
                'gyeonggi', 'gangwon', 'chungbuk', 'chungnam', 'jeonbuk', 'jeonnam',
                'gyeongbuk', 'gyeongnam', 'jeju'
            ]
        ],
        
        // North Korea (separate handling if needed)
        'North Korea' => [
            'patterns' => [
                '/\bnorth\s+korea\b/',
                '/\bdprk\b/',
            ],
            'cities' => ['pyongyang'],
        ],
        
        // Canada
        'Canada' => [
            'patterns' => [
                '/\bcanada\b/',
                '/\bcanadian\b/',
                '/\bca\b/',      // Country code
                '/\bcan\b/',
            ],
            'cities' => [
                'toronto', 'montreal', 'calgary', 'ottawa', 'edmonton', 'winnipeg',
                'vancouver', 'mississauga', 'brampton', 'hamilton', 'quebec'
            ],
            'provinces' => [
                'ontario', 'quebec', 'british columbia', 'alberta', 'manitoba',
                'saskatchewan', 'nova scotia', 'new brunswick', 'newfoundland'
            ]
        ],
        
        // Australia
        'Australia' => [
            'patterns' => [
                '/\baustralia\b/',
                '/\baustralian\b/',
                '/\bau\b/',      // Country code
                '/\baus\b/',
            ],
            'cities' => [
                'sydney', 'melbourne', 'brisbane', 'perth', 'adelaide', 'gold coast',
                'newcastle', 'canberra', 'sunshine coast', 'wollongong', 'hobart'
            ],
            'provinces' => [
                'new south wales', 'victoria', 'queensland', 'western australia',
                'south australia', 'tasmania', 'australian capital territory'
            ]
        ],
        
        // Singapore
        'Singapore' => [
            'patterns' => [
                '/\bsingapore\b/',
                '/\bsingaporean\b/',
                '/\bsg\b/',      // Country code
                '/\bsgp\b/',
            ],
            'cities' => ['singapore'],
        ],
        
        // Malaysia
        'Malaysia' => [
            'patterns' => [
                '/\bmalaysia\b/',
                '/\bmalaysian\b/',
                '/\bmy\b/',      // Country code
                '/\bmys\b/',
            ],
            'cities' => [
                'kuala lumpur', 'george town', 'ipoh', 'shah alam', 'petaling jaya',
                'johor bahru', 'melaka', 'kuching', 'kota kinabalu', 'seremban'
            ],
            'provinces' => [
                'johor', 'kedah', 'kelantan', 'melaka', 'negeri sembilan',
                'pahang', 'penang', 'perak', 'perlis', 'sabah', 'sarawak', 'selangor'
            ]
        ],
        
        // Thailand
        'Thailand' => [
            'patterns' => [
                '/\bthailand\b/',
                '/\bthai\b/',
                '/\bth\b/',      // Country code
                '/\btha\b/',
            ],
            'cities' => [
                'bangkok', 'nonthaburi', 'nakhon ratchasima', 'chiang mai', 'hat yai',
                'udon thani', 'pak kret', 'khon kaen', 'chaophraya surasak'
            ],
            'provinces' => [
                'bangkok', 'chiang mai', 'chiang rai', 'phuket', 'pattaya',
                'ayutthaya', 'sukhothai', 'kanchanaburi'
            ]
        ],
        
        // Vietnam
        'Vietnam' => [
            'patterns' => [
                '/\bvietnam\b/',
                '/\bvietnamese\b/',
                '/\bvn\b/',      // Country code
                '/\bvnm\b/',
            ],
            'cities' => [
                'ho chi minh city', 'hanoi', 'haiphong', 'can tho', 'da nang',
                'bian hoa', 'hue', 'nha trang', 'vung tau', 'quy nhon'
            ],
            'provinces' => [
                'ho chi minh', 'hanoi', 'haiphong', 'can tho', 'da nang',
                'dong nai', 'binh duong', 'long an', 'tien giang'
            ]
        ],
        
        // Indonesia
        'Indonesia' => [
            'patterns' => [
                '/\bindonesia\b/',
                '/\bindonesian\b/',
                '/\bid\b/',      // Country code
                '/\bidn\b/',
            ],
            'cities' => [
                'jakarta', 'surabaya', 'bandung', 'medan', 'semarang', 'makassar',
                'palembang', 'tangerang', 'depok', 'bekasi', 'yogyakarta', 'bogor'
            ],
            'provinces' => [
                'java', 'sumatra', 'kalimantan', 'sulawesi', 'papua', 'bali',
                'west java', 'east java', 'central java', 'jakarta'
            ]
        ],
        
        // India
        'India' => [
            'patterns' => [
                '/\bindia\b/',
                '/\bindian\b/',
                '/\bin\b/',      // Country code
                '/\bind\b/',
            ],
            'cities' => [
                'mumbai', 'delhi', 'bangalore', 'hyderabad', 'chennai', 'kolkata',
                'pune', 'ahmedabad', 'surat', 'jaipur', 'lucknow', 'kanpur'
            ],
            'provinces' => [
                'maharashtra', 'karnataka', 'tamil nadu', 'delhi', 'gujarat',
                'west bengal', 'rajasthan', 'uttar pradesh', 'andhra pradesh'
            ]
        ],
        
        // Taiwan
        'Taiwan' => [
            'patterns' => [
                '/\btaiwan\b/',
                '/\btaiwanese\b/',
                '/\broc\b/',     // Republic of China (Taiwan)
                '/\btw\b/',      // Country code
                '/\btwn\b/',
            ],
            'cities' => [
                'taipei', 'kaohsiung', 'taichung', 'tainan', 'banqiao', 'hsinchu',
                'taoyuan', 'keelung', 'chiayi', 'changhua'
            ],
            'provinces' => [
                'taipei', 'new taipei', 'taoyuan', 'taichung', 'tainan', 'kaohsiung'
            ]
        ],
        
        // Additional countries
        'United Kingdom' => [
            'patterns' => ['/\bunited\s+kingdom\b/', '/\buk\b/', '/\bbritain\b/', '/\bbritish\b/', '/\bgb\b/', '/\bgbr\b/'],
            'cities' => ['london', 'manchester', 'birmingham', 'glasgow', 'liverpool', 'leeds', 'edinburgh'],
            'provinces' => ['england', 'scotland', 'wales', 'northern ireland'],
        ],
        
        'Germany' => [
            'patterns' => ['/\bgermany\b/', '/\bgerman\b/', '/\bde\b/', '/\bdeu\b/'],
            'cities' => ['berlin', 'munich', 'hamburg', 'frankfurt', 'cologne', 'stuttgart'],
            'provinces' => ['bavaria', 'baden-wurttemberg', 'north rhine-westphalia', 'hesse'],
        ],
        
        'France' => [
            'patterns' => ['/\bfrance\b/', '/\bfrench\b/', '/\bfr\b/', '/\bfra\b/'],
            'cities' => ['paris', 'marseille', 'lyon', 'toulouse', 'nice', 'nantes'],
            'provinces' => ['ile-de-france', 'provence-alpes-cote d\'azur', 'auvergne-rhone-alpes'],
        ],
        
        'Italy' => [
            'patterns' => ['/\bitaly\b/', '/\bitalian\b/', '/\bit\b/', '/\bita\b/'],
            'cities' => ['rome', 'milan', 'naples', 'turin', 'palermo', 'genoa'],
            'provinces' => ['lombardy', 'lazio', 'campania', 'sicily', 'veneto'],
        ],
        
        'Spain' => [
            'patterns' => ['/\bspain\b/', '/\bspanish\b/', '/\bes\b/', '/\besp\b/'],
            'cities' => ['madrid', 'barcelona', 'valencia', 'seville', 'zaragoza', 'malaga'],
            'provinces' => ['andalusia', 'catalonia', 'madrid', 'valencia', 'castile'],
        ],
        
        'Brazil' => [
            'patterns' => ['/\bbrazil\b/', '/\bbrazilian\b/', '/\bbr\b/', '/\bbra\b/'],
            'cities' => ['sao paulo', 'rio de janeiro', 'brasilia', 'salvador', 'fortaleza', 'belo horizonte'],
            'provinces' => ['sao paulo', 'rio de janeiro', 'minas gerais', 'bahia'],
        ],
        
        'Mexico' => [
            'patterns' => ['/\bmexico\b/', '/\bmexican\b/', '/\bmx\b/', '/\bmex\b/'],
            'cities' => ['mexico city', 'guadalajara', 'monterrey', 'puebla', 'tijuana'],
            'provinces' => ['jalisco', 'nuevo leon', 'puebla', 'mexico state'],
        ],
        
        'New Zealand' => [
            'patterns' => ['/\bnew\s+zealand\b/', '/\bnz\b/', '/\bnzl\b/'],
            'cities' => ['auckland', 'wellington', 'christchurch', 'hamilton', 'dunedin'],
            'provinces' => ['auckland', 'wellington', 'canterbury', 'waikato'],
        ],
        
        'Hong Kong' => [
            'patterns' => ['/\bhong\s+kong\b/', '/\bhk\b/', '/\bhkg\b/'],
            'cities' => ['hong kong', 'kowloon', 'new territories'],
        ],
        
        'South Africa' => [
            'patterns' => ['/\bsouth\s+africa\b/', '/\bza\b/', '/\bzaf\b/'],
            'cities' => ['johannesburg', 'cape town', 'durban', 'pretoria', 'port elizabeth'],
            'provinces' => ['gauteng', 'western cape', 'kwazulu-natal', 'eastern cape'],
        ],
    ];
    
    // Priority order: check patterns first, then cities, then provinces
    // This ensures direct country mentions take precedence
    
    // Step 1: Check direct country patterns (highest priority)
    foreach ($countryRules as $country => $rules) {
        if (isset($rules['patterns'])) {
            foreach ($rules['patterns'] as $pattern) {
                if (preg_match($pattern, $normalized)) {
                    return $country;
                }
            }
        }
    }
    
    // Step 2: Check cities (medium priority)
    foreach ($countryRules as $country => $rules) {
        if (isset($rules['cities'])) {
            foreach ($rules['cities'] as $city) {
                // Use word boundary to avoid partial matches
                if (preg_match('/\b' . preg_quote($city, '/') . '\b/', $normalized)) {
                    return $country;
                }
            }
        }
    }
    
    // Step 3: Check provinces/states (lower priority)
    foreach ($countryRules as $country => $rules) {
        if (isset($rules['provinces'])) {
            foreach ($rules['provinces'] as $province) {
                // Use word boundary to avoid partial matches
                if (preg_match('/\b' . preg_quote($province, '/') . '\b/', $normalized)) {
                    return $country;
                }
            }
        }
    }
    
    return '';
}

/**
 * Detect partner country while avoiding false positives from CPU's local address.
 * Rule:
 * - Prefer partner-tied location string first (if present)
 * - If fallback is needed, prefer NON-Philippines country mentions from the full text
 * - Only return Philippines if it appears tied to the partner (partner location string / partner name)
 */
function detectPartnerCountryFromOcr($fullText, $partnerInstitution, $partnerLocationStr) {
    $fullTextLower = strtolower((string)$fullText);
    $partnerInstitutionLower = strtolower((string)$partnerInstitution);
    $partnerLocLower = strtolower((string)$partnerLocationStr);

    // 1) If partner location explicitly contains a country (including PH), trust it.
    $countryFromPartnerLoc = detectCountryFromText($partnerLocationStr);
    if ($countryFromPartnerLoc) return $countryFromPartnerLoc;

    // 2) Prefer non-PH countries from full text (avoid CPU address dominating).
    $nonPh = [
        'China' => ['china', 'fujian', 'zhangzhou'],
        'Japan' => ['japan'],
        'United States' => ['united states', 'usa', 'u.s.a'],
        'Korea' => ['korea'],
        'Canada' => ['canada'],
        'Australia' => ['australia'],
        'Singapore' => ['singapore'],
        'Malaysia' => ['malaysia'],
        'Thailand' => ['thailand'],
        'Vietnam' => ['vietnam'],
        'Indonesia' => ['indonesia'],
        'India' => ['india'],
        'Taiwan' => ['taiwan'],
    ];
    foreach ($nonPh as $country => $needles) {
        foreach ($needles as $needle) {
            if (strpos($fullTextLower, $needle) !== false) return $country;
        }
    }

    // 3) Philippines only if tied to partner (name/location contains it).
    if (strpos($partnerLocLower, 'philippine') !== false || strpos($partnerInstitutionLower, 'philippine') !== false || strpos($partnerInstitutionLower, 'philippines') !== false) {
        return 'Philippines';
    }

    return '';
}

try {
    if (!isset($_FILES['file']) || $_FILES['file']['error'] === UPLOAD_ERR_NO_FILE) {
        throw new Exception('No file uploaded');
    }

    $file = $_FILES['file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('File upload error: ' . $file['error']);
    }

    $originalExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    // Detect MIME type (best-effort) and provide an extension-based fallback.
    $mimeType = null;
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
        }
    }

    // Normalize common oddities (DOCX sometimes shows as zip)
    if ($originalExt === 'docx') {
        $mimeType = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    }

    $extensionMimeMap = [
        'pdf' => 'application/pdf',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp'
    ];
    if (!$mimeType && isset($extensionMimeMap[$originalExt])) {
        $mimeType = $extensionMimeMap[$originalExt];
    }

    // Copy to temp file with extension (some extractors behave better with proper extension)
    $base = tempnam(sys_get_temp_dir(), 'mou_ocr_');
    if ($base === false) {
        throw new Exception('Failed to create temporary file');
    }
    @unlink($base);
    $tempPath = $base . ($originalExt ? '.' . $originalExt : '');

    if (!move_uploaded_file($file['tmp_name'], $tempPath)) {
        if (!copy($file['tmp_name'], $tempPath)) {
            throw new Exception('Failed to stage uploaded file for OCR');
        }
    }

    $extracted = '';
    try {
        $extracted = extractTextFromFile($tempPath, $mimeType ?: ($file['type'] ?? ''));
        
        // Handle JSON error response from OCR utilities (e.g., missing tesseract)
        if (is_string($extracted) && !empty($extracted)) {
            $jsonError = json_decode($extracted, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($jsonError) && isset($jsonError['error'])) {
                ob_end_clean();
                http_response_code(200);
                echo json_encode([
                    'success' => false,
                    'error' => $jsonError['user_message'] ?? $jsonError['message'] ?? 'OCR unavailable',
                    'debug_info' => $jsonError['debug_info'] ?? null
                ]);
                exit();
            }
        }
    } catch (Throwable $e) {
        if (file_exists($tempPath)) {
            @unlink($tempPath);
        }
        throw new Exception('Text extraction failed: ' . $e->getMessage());
    } finally {
        if (file_exists($tempPath)) {
            @unlink($tempPath);
        }
    }

    $institutionFields = extractInstitutionFromText($extracted);
    $locationRaw = extractInstitutionLocationFromText($extracted, $institutionFields);

    $locationMethod = 'none';

    // Country-only: first try partner-tied location string
    $locationField = normalizeLocationToCountry($locationRaw);
    if ($locationField) {
        $locationMethod = 'matched_institution';
    }

    // If we didn't get a country from the partner-tied string, try safe fallback logic:
    // prefer non-PH countries from full text; only return PH if tied to partner.
    if (!$locationField) {
        $partnerInstitution = (string)($institutionFields['institution'] ?? '');
        $locationField = detectPartnerCountryFromOcr($extracted, $partnerInstitution, $locationRaw);
        if ($locationField) {
            $locationMethod = 'partner_country';
        }
    }

    // Final fallback: detect any country mention from full text
    if (!$locationField) {
        $locationField = detectCountryFromText($extracted);
        if ($locationField) {
            $locationMethod = 'country_fallback';
        }
    }

    $debug = isset($_GET['debug']) && $_GET['debug'] === '1';
    $resp = [
        'success' => true,
        'fields' => [
            'institution' => $institutionFields['institution'],
            'location' => $locationField
        ],
        'meta' => [
            'institution_confidence' => $institutionFields['confidence'],
            'institution_method' => $institutionFields['method'],
            'location_method' => $locationMethod,
            'between_parties' => $institutionFields['between_parties']
        ]
    ];

    if ($debug) {
        $resp['debug'] = [
            'mime' => $mimeType,
            'ext' => $originalExt,
            'extracted_preview' => substr((string)$extracted, 0, 600)
        ];
    }

    // Clear any accidental output before sending JSON
    ob_end_clean();
    echo json_encode($resp);
} catch (Throwable $e) {
    // Clear any accidental output before sending error JSON
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}


