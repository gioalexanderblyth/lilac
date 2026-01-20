<?php
/**
 * MOU/MOA OCR + Field Extraction API
 * Upload a file (PDF/DOCX/Image) and returns extracted fields for auto-fill.
 *
 * Currently supported fields:
 * - institution
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized. Please login.']);
    exit();
}

require_once __DIR__ . '/award-analysis-functions.php';

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

    // Remove surrounding quotes
    $s = trim($s, " \t\n\r\0\x0B\"“”'");

    // Guardrails: avoid returning generic words
    if (strlen($s) < 4) return '';
    if (preg_match('/^(memorandum|agreement|understanding|contract|page)\b/i', $s)) return '';

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

    $len = strlen($line);
    if ($len < 8 || $len > 160) return 0;

    $score = 0;

    // Keyword boosts
    if (preg_match('/\b(university|college|institute|institution|school|academy|polytechnic)\b/i', $line)) $score += 6;
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
        $unique[] = $c;
    }

    // Choose first (label/between often strongest), fallback to best line-based
    // For MOU/MOA: typically "between OUR institution and PARTNER institution".
    // The modal "Institution" field is intended for the PARTNER, so if we have a between-clause,
    // prefer the second party as the chosen institution.
    $chosen = '';
    $method = 'none';
    if (is_array($betweenParties) && count($betweenParties) === 2 && !empty($betweenParties[1])) {
        $chosen = $betweenParties[1];
        $method = 'between';
    } else {
        $chosen = $unique[0]['value'] ?? '';
        $method = $unique[0]['method'] ?? 'none';
    }

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
 */
function normalizeLocationToCountry($location) {
    $loc = trim((string)$location);
    if ($loc === '') return $loc;

    $lower = strtolower($loc);

    // Explicit country cues
    if (strpos($lower, 'japan') !== false) return 'Japan';
    if (strpos($lower, 'philippine') !== false) return 'Philippines';
    if (strpos($lower, 'united states') !== false || strpos($lower, 'usa') !== false || strpos($lower, 'u.s.a') !== false) return 'United States';
    if (strpos($lower, 'korea') !== false) return 'Korea';
    if (strpos($lower, 'canada') !== false) return 'Canada';
    if (strpos($lower, 'australia') !== false) return 'Australia';
    if (strpos($lower, 'singapore') !== false) return 'Singapore';
    if (strpos($lower, 'malaysia') !== false) return 'Malaysia';
    if (strpos($lower, 'thailand') !== false) return 'Thailand';
    if (strpos($lower, 'vietnam') !== false) return 'Vietnam';
    if (strpos($lower, 'indonesia') !== false) return 'Indonesia';
    if (strpos($lower, 'china') !== false) return 'China';
    if (strpos($lower, 'india') !== false) return 'India';
    if (strpos($lower, 'taiwan') !== false) return 'Taiwan';

    // Use the broader detector (works on single strings too)
    $detected = detectCountryFromText($loc);
    if ($detected) return $detected;

    // Try comma-separated: take the last segment
    $parts = array_filter(array_map('trim', explode(',', $loc)));
    if (!empty($parts)) {
        // Prefer the last comma segment only if it contains a country keyword
        $last = end($parts);
        $lastCountry = detectCountryFromText($last);
        if ($lastCountry) return $lastCountry;
    }

    // No country detected — return empty to avoid populating with city/region
    return '';
}

/**
 * Detect a country from the full extracted text (broad scan).
 */
function detectCountryFromText($text) {
    $lower = strtolower((string)$text);
    if ($lower === '') return '';
    if (strpos($lower, 'japan') !== false) return 'Japan';
    if (strpos($lower, 'philippines') !== false || strpos($lower, 'philippine') !== false) return 'Philippines';
    if (strpos($lower, 'united states') !== false || strpos($lower, 'usa') !== false || strpos($lower, 'u.s.a') !== false) return 'United States';
    if (strpos($lower, 'korea') !== false) return 'Korea';
    if (strpos($lower, 'canada') !== false) return 'Canada';
    if (strpos($lower, 'australia') !== false) return 'Australia';
    if (strpos($lower, 'singapore') !== false) return 'Singapore';
    if (strpos($lower, 'malaysia') !== false) return 'Malaysia';
    if (strpos($lower, 'thailand') !== false) return 'Thailand';
    if (strpos($lower, 'vietnam') !== false) return 'Vietnam';
    if (strpos($lower, 'indonesia') !== false) return 'Indonesia';
    if (strpos($lower, 'china') !== false) return 'China';
    if (strpos($lower, 'india') !== false) return 'India';
    if (strpos($lower, 'taiwan') !== false) return 'Taiwan';
    return '';
}
function extractLocationsFromText($text) {
    $raw = normalizeWhitespaceKeepNewlines($text);
    $locations = [];

    // Pattern: "<party> located at <location> ..."
    if (preg_match_all('/([\\w\\s\\.,\\-&()]+?)\\s+located\\s+at\\s+([^\\.;\\n]+)(?:[\\.;]|\\n|,\\s*hereinafter|\\s+hereinafter|\\s+and\\s)/i', $raw, $m, PREG_SET_ORDER)) {
        foreach ($m as $match) {
            $loc = cleanLocationCandidate($match[2] ?? '');
            if ($loc !== '') $locations[] = $loc;
        }
    }

    // Fallback: collect standalone "located at ..." phrases
    if (empty($locations) && preg_match_all('/located\\s+at\\s+([^\\.;\\n]+)(?:[\\.;]|\\n|,\\s*hereinafter|\\s+hereinafter|\\s+and\\s)/i', $raw, $m2)) {
        foreach ($m2[1] as $loc) {
            $loc = cleanLocationCandidate($loc);
            if ($loc !== '') $locations[] = $loc;
        }
    }

    // Choose the second location if multiple (partner institution), else first
    $partnerLocation = $locations[1] ?? $locations[0] ?? '';

    return [
        'locations' => $locations,
        'partner_location' => $partnerLocation
    ];
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
    } finally {
        if (file_exists($tempPath)) {
            @unlink($tempPath);
        }
    }

    // Handle JSON error response from OCR utilities (e.g., missing tesseract)
    $jsonError = json_decode($extracted, true);
    if ($jsonError && isset($jsonError['error'])) {
        http_response_code(200);
        echo json_encode([
            'success' => false,
            'error' => $jsonError['user_message'] ?? $jsonError['message'] ?? 'OCR unavailable',
            'debug_info' => $jsonError['debug_info'] ?? null
        ]);
        exit();
    }

    $institutionFields = extractInstitutionFromText($extracted);
    $locationField = extractInstitutionLocationFromText($extracted, $institutionFields);
    // Always prefer country-level detection to avoid long addresses
    $countryFromText = detectCountryFromText($extracted);
    if ($countryFromText) {
        $locationField = $countryFromText;
    }
    $locationField = normalizeLocationToCountry($locationField ?: $countryFromText);
    $locationMethod = $locationField ? 'matched_institution' : 'none';

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

    echo json_encode($resp);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}


