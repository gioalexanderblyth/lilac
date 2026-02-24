<?php
/**
 * MOU/MOA OCR + Field Extraction API
 * Upload a file (PDF/DOCX/Image) and returns extracted fields for auto-fill.
 *
 * Currently supported fields:
 * - institution
 * - location
 * - term (duration in years)
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
    require_once __DIR__ . '/mou-country-detector.php';
    require_once __DIR__ . '/OCRExtractor.php';
    require_once __DIR__ . '/AutoSuggestExtractor.php';
} catch (Throwable $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to load required functions: ' . $e->getMessage()]);
    exit();
}

// Clear any output that might have been generated during includes
ob_clean();

/**
 * Set to false to disable the simple extractMetadata() fallback (revert to previous behavior).
 * When true, if main extraction returns empty institution or null country, we use extractMetadata().
 */
define('USE_EXTRACT_METADATA_FALLBACK', true);

/** Max length for a single institution name (avoid sentences). */
define('MOU_OCR_INSTITUTION_MAX_LEN', 90);

/**
 * Trim extracted text to institution name only — no trailing sentence or location.
 * Stops at comma (when rest looks like location/boilerplate), hereinafter, located, etc.
 */
function trimToInstitutionNameOnly($s) {
    $s = trim((string)$s);
    if ($s === '') return '';

    $s = preg_replace('/\s+/', ' ', $s);

    // 1) Cut at sentence/boilerplate continuations (institution name never contains these)
    if (preg_match('/^(.{4,120}?)\s+(?:hereinafter|herein\s+after|referred\s+to\s+as|located\s+(?:at|in)|agree\s+to|pursuant\s+to|whereas|the\s+republic\s+of|as\s+follows|for\s+the\s+purposes?|in\s+witness\s+whereof|signed\s+by|represented\s+by)\b/i', $s, $m)) {
        $s = trim($m[1]);
    }
    if (preg_match('/^(.{4,120}?)\s+,?\s*(?:the\s+)?(?:republic\s+of|kingdom\s+of|state\s+of)\s+/i', $s, $m)) {
        $s = trim($m[1]);
    }

    // 2) Take only up to first comma if what follows is clearly location/boilerplate
    if (preg_match('/^([^,]{4,90}),\s*(?:the\s+)?(?:republic|kingdom|state|city|country|located|hereinafter|address|herein)/i', $s, $m)) {
        $s = trim($m[1]);
    }
    if (preg_match('/^([^,]{4,90}),\s*[A-Z][a-z]+(?:\s+[A-Z][a-z]+)*\s*,(?:\s*(?:republic|korea|japan|philippines|china|usa))/i', $s, $m)) {
        $s = trim($m[1]);
    }
    // 2b) Trailing " the Republic of X" without comma
    $s = preg_replace('/\s+(?:the\s+)?(?:republic|kingdom|state)\s+of\s+[A-Za-z\s]+$/i', '', $s);
    // 2c) Trailing country name only when it's "X University Country" (not "University of the Philippines")
    if (preg_match('/\s+(University|College|Institute|Institution|School|Academy|Polytechnic|Foundation)\s+(Korea|Japan|China|USA|UK|Singapore|Malaysia|Thailand|Vietnam|Indonesia|India|Australia|Canada)$/i', $s, $mx)) {
        $s = preg_replace('/\s+' . preg_quote($mx[2], '/') . '$/i', '', $s);
    }
    $s = trim($s);

    // 3) If still long, keep only the segment that ends with institution keyword (last occurrence)
    $kw = '\b(University|College|Institute|Institution|School|Academy|Polytechnic|Foundation|Corporation|Inc\.?|LLC|Ltd\.?)\b';
    if (strlen($s) > MOU_OCR_INSTITUTION_MAX_LEN && preg_match('/^(.{10,}' . $kw . '\s*)/i', $s, $m)) {
        $s = trim($m[1]);
    }
    if (strlen($s) > MOU_OCR_INSTITUTION_MAX_LEN && preg_match_all('/' . $kw . '/i', $s, $kws, PREG_OFFSET_CAPTURE)) {
        $last = end($kws[0]);
        $end = $last[1] + strlen($last[0]);
        $chunk = trim(substr($s, 0, $end));
        if (strlen($chunk) >= 8 && preg_match('/' . $kw . '/i', $chunk)) {
            $s = $chunk;
        }
    }

    // 4) Allow optional ", Inc." or " Foundation" at end; strip anything after
    if (preg_match('/^(.{4,}?\s+(?:University|College|Institute|Institution|School|Academy|Polytechnic|Foundation|Corporation)(?:\s*,\s*Inc\.?|\s+Inc\.?)?)\s*[,\.].*$/i', $s, $m)) {
        $s = trim(preg_replace('/\s*[,\.]\s*$/', '', $m[1]));
    }

    $s = trim(preg_replace('/\s*[,\.;]\s*$/', '', $s));
    // Strip trailing/leading parentheses and brackets (e.g. "Tra Vinh University (" -> "Tra Vinh University")
    $s = trim(preg_replace('/[\s]*[\(\)\[\]]+\s*$/', '', $s));
    $s = trim(preg_replace('/^[\s]*[\(\)\[\]]+\s*/', '', $s));
    if (strlen($s) > MOU_OCR_INSTITUTION_MAX_LEN) {
        $s = trim(substr($s, 0, MOU_OCR_INSTITUTION_MAX_LEN));
        $s = preg_replace('/\s+\S+$/', '', $s); // don't cut mid-word
    }
    return $s;
}

function normalizeWhitespaceKeepNewlines($text) {
    $text = (string)($text ?? '');
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    // Collapse horizontal whitespace but keep newlines
    $text = preg_replace('/[ \t]+/', ' ', $text);
    // Collapse excessive newlines
    $text = preg_replace("/\n{3,}/", "\n\n", $text);
    return trim($text);
}

/**
 * Preprocess raw OCR output to improve institution extraction.
 * - Fix run-together words (e.g. "DaeguUniversity" -> "Daegu University")
 * - Remove common OCR artifact patterns that break "at [Institution]" matching
 */
function preprocessOcrTextForInstitution($text) {
    $text = (string)($text ?? '');
    if ($text === '') return $text;

    // Remove OCR artifacts that often appear in signature blocks (so "at Institution" is not broken)
    $text = preg_replace('/_\s*[A-Za-z0-9\.]+\s*/u', ' ', $text);  // e.g. _J.gfl, _m_
    $text = preg_replace('/\s*[_\x{00AD}]\s*/u', ' ', $text);      // underscore, soft hyphen

    // Insert space before institution keywords when they're glued to the previous word (OCR often drops space)
    $keywords = ['University', 'College', 'Institute', 'Institution', 'School', 'Academy', 'Polytechnic', 'Foundation', 'Corporation', 'Inc', 'LLC', 'Ltd'];
    foreach ($keywords as $kw) {
        // "SomethingUniversity" or "Something University" (already has space) -> keep or fix
        $text = preg_replace('/\b([A-Za-z][a-z]+)(' . preg_quote($kw, '/') . ')\b/i', '$1 $2', $text);
        $text = preg_replace('/\b([A-Z]{2,})(' . preg_quote($kw, '/') . ')\b/i', '$1 $2', $text);
    }

    // Collapse multiple spaces
    $text = preg_replace('/\s+/', ' ', $text);
    return trim($text);
}

function cleanInstitutionCandidate($s) {
    $s = trim((string)$s);
    $originalS = $s; // Keep original for early rejection checks
    $s = preg_replace('/\s+/', ' ', $s);

    // Remove OCR artifacts in the middle of the string (e.g. "Daegu _J.gfl University" -> "Daegu University")
    $s = preg_replace('/\s+_[A-Za-z0-9\.]+\s+/', ' ', $s);
    $s = preg_replace('/_[A-Za-z0-9\.]+/', '', $s);
    $s = preg_replace('/\s+/', ' ', trim($s));

    // EARLY REJECTION: If it starts with signature-related text and has no institution keywords, reject immediately
    if (preg_match('/^(signatures?|signed|signatory|witness)/i', $s)) {
        // Only allow if it contains institution keywords (e.g., "signatures at University")
        if (!preg_match('/\b(university|college|institute|institution|school|academy|polytechnic|foundation|corporation|inc|llc|ltd)\b/i', $s)) {
            return '';
        }
    }
    
    // EARLY REJECTION: If it contains "day of" pattern without institution keywords, reject
    if (preg_match('/\b(day|month|year)\s+of\b/i', $s)) {
        if (!preg_match('/\b(university|college|institute|institution|school|academy|polytechnic|foundation|corporation|inc|llc|ltd)\b/i', $s)) {
            return '';
        }
    }
    
    // EARLY REJECTION: If it contains OCR artifacts like "_J.gfl" or "_m_" without institution keywords, reject
    if (preg_match('/_[A-Za-z0-9\.]+/', $s)) {
        if (!preg_match('/\b(university|college|institute|institution|school|academy|polytechnic|foundation|corporation|inc|llc|ltd)\b/i', $s)) {
            return '';
        }
    }

    // Remove signature-related text at the beginning - be more aggressive
    // If text contains "at" followed by institution, extract ONLY what's after "at"
    // Improved pattern to handle OCR artifacts like "_J.gﬂ day of m_. 2012 at Daegu University"
    $atMatch = null;
    // First try to match with institution keyword directly
    if (preg_match('/\bat\s+([A-Z][A-Za-z\s]{3,}(?:\s+(?:University|College|Institute|Institution|School|Academy|Polytechnic|Foundation|Corporation|Inc|LLC|Ltd))[^\n,]*?)(?:\s*,\s*the|\s*$)/i', $originalS, $atMatch)) {
        // Extract only the institution part after "at"
        $s = trim($atMatch[1]);
        $extractedFromAt = true;
    } elseif (preg_match('/\bat\s+([^\n,]{5,120}?)(?:\s*,\s*the|\s*$)/i', $originalS, $atMatch)) {
        // Fallback: extract anything after "at" up to comma or end, then clean and validate
        $s = trim($atMatch[1]);
        $extractedFromAt = true;
    } else {
        $s = $originalS;
        $extractedFromAt = false;
    }
    
    // If we extracted from "at" pattern, clean it
    if ($extractedFromAt && isset($atMatch[1])) {
        // Remove trailing "the" or comma+the
        $s = preg_replace('/\s*,\s*the\s*$/i', '', $s);
        $s = preg_replace('/\s+the\s*$/i', '', $s);
        // Remove any trailing punctuation
        $s = preg_replace('/[\.\,\;\:\-]+$/', '', $s);
        // Remove OCR artifacts (underscores, special characters) from the extracted institution
        $s = preg_replace('/_[A-Za-z0-9\.]+/', '', $s); // Remove patterns like "_J.gﬂ"
        $s = preg_replace('/\b(day|month|year)\s+of\s+[^\s]+\s+\d{4}\b/i', '', $s); // Remove date patterns
        $s = preg_replace('/\b\d{4}\b/', '', $s); // Remove standalone years
        
        // Remove city/country names that appear directly attached (no comma) - handle OCR errors
        // Common major cities that might appear after institution names
        $majorCities = ['seoul', 'tokyo', 'osaka', 'kyoto', 'manila', 'quezon city', 'davao', 'cebu', 
                       'beijing', 'shanghai', 'guangzhou', 'shenzhen', 'singapore', 'kuala lumpur',
                       'bangkok', 'hanoi', 'ho chi minh', 'jakarta', 'sydney', 'melbourne', 'toronto',
                       'vancouver', 'new york', 'los angeles', 'london', 'paris', 'berlin'];
        foreach ($majorCities as $city) {
            // Remove city name if it appears at the end (with or without comma, with or without space)
            $s = preg_replace('/\b' . preg_quote($city, '/') . '\s*[,\s]*\s*(korea|japan|philippines|china|thailand|vietnam|indonesia|malaysia|singapore|australia|canada|usa|united\s+states|uk|united\s+kingdom|france|germany)?.*$/i', '', $s);
            // Also handle when city is directly attached (OCR error: "UNIVERSITYSeoul")
            $s = preg_replace('/\b' . preg_quote(ucfirst($city), '/') . '\s*(korea|japan|philippines|china|thailand|vietnam|indonesia|malaysia|singapore|australia|canada|usa|united\s+states|uk|united\s+kingdom|france|germany)?.*$/i', '', $s);
        }
        
        // Remove country names that appear directly at the end (even without comma)
        $s = preg_replace('/\b(korea|japan|philippines|philippine|china|chinese|thailand|thai|vietnam|vietnamese|indonesia|indonesian|malaysia|malaysian|singapore|singaporean|australia|australian|canada|canadian|usa|united\s+states|uk|united\s+kingdom|france|french|germany|german|india|indian|taiwan|taiwanese)\s*[,\s]*$/i', '', $s);
        
        // Remove "City, Country" pattern even when directly attached (e.g., "Seoul, Korea" or "SeoulKorea")
        $s = preg_replace('/\b(seoul|tokyo|osaka|kyoto|manila|beijing|shanghai|singapore|bangkok|hanoi|jakarta|sydney|toronto|vancouver|new\s+york|los\s+angeles|london|paris|berlin)\s*[,\s]+\s*(korea|japan|philippines|china|thailand|vietnam|indonesia|malaysia|singapore|australia|canada|usa|united\s+states|uk|united\s+kingdom|france|germany)\s*$/i', '', $s);
        
        // Handle cases where city is directly attached to institution name (no space: "UNIVERSITYSeoul")
        // Match uppercase city names directly attached (common OCR error)
        $s = preg_replace('/(UNIVERSITY|COLLEGE|INSTITUTE|INSTITUTION|SCHOOL|ACADEMY)(Seoul|Tokyo|Osaka|Kyoto|Manila|Beijing|Shanghai|Singapore|Bangkok|Hanoi|Jakarta|Sydney|Toronto|Vancouver|London|Paris|Berlin)\s*[,\s]*(Korea|Japan|Philippines|China|Thailand|Vietnam|Indonesia|Malaysia|Singapore|Australia|Canada|USA)?.*$/i', '$1', $s);
        // Also handle lowercase/mixed case: "UniversitySeoul" or "University Seoul"
        $s = preg_replace('/(University|College|Institute|Institution|School|Academy)(Seoul|Tokyo|Osaka|Kyoto|Manila|Beijing|Shanghai|Singapore|Bangkok|Hanoi|Jakarta|Sydney|Toronto|Vancouver|London|Paris|Berlin)\s*[,\s]*(Korea|Japan|Philippines|China|Thailand|Vietnam|Indonesia|Malaysia|Singapore|Australia|Canada|USA)?.*$/i', '$1', $s);
        
        // Normalize whitespace
        $s = preg_replace('/\s+/', ' ', $s);
        $s = trim($s);
        // If we successfully extracted institution from "at" pattern, skip other cleaning
        // and go directly to final validation
        if (preg_match('/\b(university|college|institute|institution|school|academy|polytechnic|foundation)\b/i', $s)) {
            // Skip other cleaning - we already have clean institution name
            // Continue to final validation below
        } else {
            // If extraction didn't work, continue with normal cleaning
            // Fallback: remove everything before "at" if "at" exists
            $s = preg_replace('/^(signatures?\s+)?(this\s+)?.*?\s+at\s+/i', '', $s);
            $s = preg_replace('/^.*?\s+at\s+/i', '', $s); // Remove everything before "at"
            
            // Remove date patterns (e.g., "day of m_. 2012", "_J.gﬂ day", etc.)
            $s = preg_replace('/\b(day|month|year)\s+of\s+[^\s]+\s+\d{4}\b/i', '', $s);
            $s = preg_replace('/\b\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4}\b/', '', $s); // Dates like 01/01/2012
            $s = preg_replace('/\b\d{4}\b/', '', $s); // Standalone years
            $s = preg_replace('/\b(day|month|year)\s+of\b/i', '', $s);
            
            // Remove OCR artifacts (underscores, special characters that don't belong)
            $s = preg_replace('/_[A-Za-z0-9\.]+/', '', $s); // Remove patterns like "_J.gﬂ"
            $s = preg_replace('/[^\w\s\.,\-&()]/u', ' ', $s); // Keep only alphanumeric, spaces, and common punctuation
            
            // Remove leading labels/boilerplate
            $s = preg_replace('/^(the\s+)?(partner\s+)?institution\s*[:\-]\s*/i', '', $s);
            $s = preg_replace('/^(name\s+of\s+)?(the\s+)?institution\s*[:\-]\s*/i', '', $s);
            
            // Remove signature-related words
            $s = preg_replace('/\b(signatures?|signed|signatory|signatories|witness|witnesses?)\b/i', '', $s);
            $s = preg_replace('/\b(this|the)\s+day\b/i', '', $s);

            // Remove trailing boilerplate
            $s = preg_replace('/\s*,?\s*(hereinafter|herein after)\b.*$/i', '', $s);
            $s = preg_replace('/\s*[\.\,\;\:\-]+$/', '', $s);
            
            // Remove trailing "the" or other articles
            $s = preg_replace('/\s+the\s*$/i', '', $s);

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
        }
    } else {
        // No "at" pattern found, do normal cleaning
        // Fallback: remove everything before "at" if "at" exists
        $s = preg_replace('/^(signatures?\s+)?(this\s+)?.*?\s+at\s+/i', '', $s);
        $s = preg_replace('/^.*?\s+at\s+/i', '', $s); // Remove everything before "at"
        
        // Remove date patterns (e.g., "day of m_. 2012", "_J.gﬂ day", etc.)
        $s = preg_replace('/\b(day|month|year)\s+of\s+[^\s]+\s+\d{4}\b/i', '', $s);
        $s = preg_replace('/\b\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4}\b/', '', $s); // Dates like 01/01/2012
        $s = preg_replace('/\b\d{4}\b/', '', $s); // Standalone years
        $s = preg_replace('/\b(day|month|year)\s+of\b/i', '', $s);
        
        // Remove OCR artifacts (underscores, special characters that don't belong)
        $s = preg_replace('/_[A-Za-z0-9\.]+/', '', $s); // Remove patterns like "_J.gﬂ"
        $s = preg_replace('/[^\w\s\.,\-&()]/u', ' ', $s); // Keep only alphanumeric, spaces, and common punctuation
        
        // Remove leading labels/boilerplate
        $s = preg_replace('/^(the\s+)?(partner\s+)?institution\s*[:\-]\s*/i', '', $s);
        $s = preg_replace('/^(name\s+of\s+)?(the\s+)?institution\s*[:\-]\s*/i', '', $s);
        
        // Remove signature-related words
        $s = preg_replace('/\b(signatures?|signed|signatory|signatories|witness|witnesses?)\b/i', '', $s);
        $s = preg_replace('/\b(this|the)\s+day\b/i', '', $s);

        // Remove trailing boilerplate
        $s = preg_replace('/\s*,?\s*(hereinafter|herein after)\b.*$/i', '', $s);
        $s = preg_replace('/\s*[\.\,\;\:\-]+$/', '', $s);
        
        // Remove trailing "the" or other articles
        $s = preg_replace('/\s+the\s*$/i', '', $s);

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
        
        // Remove city/country names that appear directly attached (no comma) - handle OCR errors
        // Common major cities that might appear after institution names
        $majorCities = ['seoul', 'tokyo', 'osaka', 'kyoto', 'manila', 'quezon city', 'davao', 'cebu', 
                       'beijing', 'shanghai', 'guangzhou', 'shenzhen', 'singapore', 'kuala lumpur',
                       'bangkok', 'hanoi', 'ho chi minh', 'jakarta', 'sydney', 'melbourne', 'toronto',
                       'vancouver', 'new york', 'los angeles', 'london', 'paris', 'berlin'];
        foreach ($majorCities as $city) {
            // Remove city name if it appears at the end (with or without comma, with or without space)
            $s = preg_replace('/\b' . preg_quote($city, '/') . '\s*[,\s]*\s*(korea|japan|philippines|china|thailand|vietnam|indonesia|malaysia|singapore|australia|canada|usa|united\s+states|uk|united\s+kingdom|france|germany)?.*$/i', '', $s);
            // Also handle when city is directly attached (OCR error: "UNIVERSITYSeoul")
            $s = preg_replace('/\b' . preg_quote(ucfirst($city), '/') . '\s*(korea|japan|philippines|china|thailand|vietnam|indonesia|malaysia|singapore|australia|canada|usa|united\s+states|uk|united\s+kingdom|france|germany)?.*$/i', '', $s);
        }
        
        // Remove country names that appear directly at the end (even without comma)
        $s = preg_replace('/\b(korea|japan|philippines|philippine|china|chinese|thailand|thai|vietnam|vietnamese|indonesia|indonesian|malaysia|malaysian|singapore|singaporean|australia|australian|canada|canadian|usa|united\s+states|uk|united\s+kingdom|france|french|germany|german|india|indian|taiwan|taiwanese)\s*[,\s]*$/i', '', $s);
        
        // Remove "City, Country" pattern even when directly attached (e.g., "Seoul, Korea" or "SeoulKorea")
        $s = preg_replace('/\b(seoul|tokyo|osaka|kyoto|manila|beijing|shanghai|singapore|bangkok|hanoi|jakarta|sydney|toronto|vancouver|new\s+york|los\s+angeles|london|paris|berlin)\s*[,\s]+\s*(korea|japan|philippines|china|thailand|vietnam|indonesia|malaysia|singapore|australia|canada|usa|united\s+states|uk|united\s+kingdom|france|germany)\s*$/i', '', $s);
        
        // Remove city/country names that appear directly attached (no comma) - handle OCR errors
        // Common major cities that might appear after institution names
        $majorCities = ['seoul', 'tokyo', 'osaka', 'kyoto', 'manila', 'quezon city', 'davao', 'cebu', 
                       'beijing', 'shanghai', 'guangzhou', 'shenzhen', 'singapore', 'kuala lumpur',
                       'bangkok', 'hanoi', 'ho chi minh', 'jakarta', 'sydney', 'melbourne', 'toronto',
                       'vancouver', 'new york', 'los angeles', 'london', 'paris', 'berlin'];
        foreach ($majorCities as $city) {
            // Remove city name if it appears at the end (with or without comma, with or without space)
            $s = preg_replace('/\b' . preg_quote($city, '/') . '\s*[,\s]*\s*(korea|japan|philippines|china|thailand|vietnam|indonesia|malaysia|singapore|australia|canada|usa|united\s+states|uk|united\s+kingdom|france|germany)?.*$/i', '', $s);
            // Also handle when city is directly attached (OCR error: "UNIVERSITYSeoul")
            $s = preg_replace('/\b' . preg_quote(ucfirst($city), '/') . '\s*(korea|japan|philippines|china|thailand|vietnam|indonesia|malaysia|singapore|australia|canada|usa|united\s+states|uk|united\s+kingdom|france|germany)?.*$/i', '', $s);
        }
        
        // Remove country names that appear directly at the end (even without comma)
        $s = preg_replace('/\b(korea|japan|philippines|philippine|china|chinese|thailand|thai|vietnam|vietnamese|indonesia|indonesian|malaysia|malaysian|singapore|singaporean|australia|australian|canada|canadian|usa|united\s+states|uk|united\s+kingdom|france|french|germany|german|india|indian|taiwan|taiwanese)\s*[,\s]*$/i', '', $s);
        
        // Remove "City, Country" pattern even when directly attached (e.g., "Seoul, Korea" or "SeoulKorea")
        $s = preg_replace('/\b(seoul|tokyo|osaka|kyoto|manila|beijing|shanghai|singapore|bangkok|hanoi|jakarta|sydney|toronto|vancouver|new\s+york|los\s+angeles|london|paris|berlin)\s*[,\s]+\s*(korea|japan|philippines|china|thailand|vietnam|indonesia|malaysia|singapore|australia|canada|usa|united\s+states|uk|united\s+kingdom|france|germany)\s*$/i', '', $s);
        
        // Handle cases where city is directly attached to institution name (no space: "UNIVERSITYSeoul")
        // Match uppercase city names directly attached (common OCR error)
        $s = preg_replace('/(UNIVERSITY|COLLEGE|INSTITUTE|INSTITUTION|SCHOOL|ACADEMY)(Seoul|Tokyo|Osaka|Kyoto|Manila|Beijing|Shanghai|Singapore|Bangkok|Hanoi|Jakarta|Sydney|Toronto|Vancouver|London|Paris|Berlin)\s*[,\s]*(Korea|Japan|Philippines|China|Thailand|Vietnam|Indonesia|Malaysia|Singapore|Australia|Canada|USA)?.*$/i', '$1', $s);
        // Also handle lowercase/mixed case: "UniversitySeoul" or "University Seoul"
        $s = preg_replace('/(University|College|Institute|Institution|School|Academy)(Seoul|Tokyo|Osaka|Kyoto|Manila|Beijing|Shanghai|Singapore|Bangkok|Hanoi|Jakarta|Sydney|Toronto|Vancouver|London|Paris|Berlin)\s*[,\s]*(Korea|Japan|Philippines|China|Thailand|Vietnam|Indonesia|Malaysia|Singapore|Australia|Canada|USA)?.*$/i', '$1', $s);
        
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
    }

    // Guardrails: avoid returning generic words
    if (strlen($s) < 4) return '';
    if (preg_match('/^(memorandum|agreement|understanding|contract|page|signature|signatory|witness|this|the)\b/i', $s)) return '';
    
    // FINAL REJECTION: If after cleaning it still contains signature/date words without institution keywords, reject
    if (preg_match('/\b(day|month|year|signature|signed|witness|signatory)\b/i', $s)) {
        // Only allow if it also has institution keywords
        if (!preg_match('/\b(university|college|institute|institution|school|academy|polytechnic|foundation|corporation|inc|llc|ltd)\b/i', $s)) {
            return '';
        }
    }
    
    // FINAL REJECTION: If it's mostly signature/date text (more than 50% of words are signature-related), reject
    $words = preg_split('/\s+/', $s);
    $signatureWords = ['signature', 'signatures', 'signed', 'signatory', 'witness', 'day', 'month', 'year', 'this', 'the'];
    $signatureCount = 0;
    foreach ($words as $word) {
        $cleanWord = strtolower(preg_replace('/[^A-Za-z]/', '', $word));
        if (in_array($cleanWord, $signatureWords)) {
            $signatureCount++;
        }
    }
    if (count($words) > 0 && ($signatureCount / count($words)) > 0.5) {
        // More than 50% are signature words - reject unless it has institution keywords
        if (!preg_match('/\b(university|college|institute|institution|school|academy|polytechnic|foundation|corporation|inc|llc|ltd)\b/i', $s)) {
            return '';
        }
    }
    
    // FINAL REJECTION: If cleaned result starts with signature/date words, reject
    if (count($words) > 0) {
        $firstWord = strtolower(preg_replace('/[^A-Za-z]/', '', $words[0]));
        if (in_array($firstWord, $signatureWords)) {
            // Only allow if it has institution keywords
            if (!preg_match('/\b(university|college|institute|institution|school|academy|polytechnic|foundation|corporation|inc|llc|ltd)\b/i', $s)) {
                return '';
            }
        }
    }
    
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

    $s = trim($s);
    // Ensure we return only institution name, not a full sentence
    $s = trimToInstitutionNameOnly($s);
    return $s;
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

    // Patterns for "based in ..."
    if (preg_match_all('/based\s+in\s+([^\.;\n]{5,180})/i', $raw, $m3)) {
        foreach ($m3[1] as $loc) {
            $c = cleanLocationCandidate($loc);
            if ($c !== '') {
                $locations[] = ['value' => $c, 'method' => 'based'];
            }
        }
    }

    // Patterns for "headquartered in ..."
    if (preg_match_all('/headquartered\s+(?:in|at)\s+([^\.;\n]{5,180})/i', $raw, $m4)) {
        foreach ($m4[1] as $loc) {
            $c = cleanLocationCandidate($loc);
            if ($c !== '') {
                $locations[] = ['value' => $c, 'method' => 'headquartered'];
            }
        }
    }

    // Patterns for "from [location]" (e.g., "University from Japan")
    if (preg_match_all('/\b(university|college|institute|institution|school|academy)\s+from\s+([^\.;\n]{5,180})/i', $raw, $m5)) {
        foreach ($m5[2] as $loc) {
            $c = cleanLocationCandidate($loc);
            if ($c !== '') {
                $locations[] = ['value' => $c, 'method' => 'from'];
            }
        }
    }

    // Patterns for "in [City, Country]" after institution names
    if (preg_match_all('/\b(university|college|institute|institution|school|academy)\s+[^,\n]{0,60}?\s+in\s+([^\.;\n]{5,180})/i', $raw, $m6)) {
        foreach ($m6[2] as $loc) {
            $c = cleanLocationCandidate($loc);
            if ($c !== '') {
                $locations[] = ['value' => $c, 'method' => 'in_pattern'];
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

function extractTermFromText($text) {
    $raw = normalizeWhitespaceKeepNewlines($text);
    $lower = strtolower($raw);
    
    $term = null;
    $confidence = 0;
    $method = '';
    
    // Pattern 1: "term of X years" or "term: X years" or "term is X years"
    if (preg_match('/\bterm\s*(?:of|is|:|=)\s*(\d+)\s*(?:year|years)\b/i', $raw, $m)) {
        $years = intval($m[1]);
        if ($years > 0 && $years <= 50) { // Reasonable range
            $term = $years . ' ' . ($years == 1 ? 'Year' : 'Years');
            $confidence = 0.9;
            $method = 'term_of';
        }
    }
    
    // Pattern 2: "valid for X years" or "validity of X years"
    if (!$term && preg_match('/\b(?:valid|validity)\s+(?:for|of)\s*(\d+)\s*(?:year|years)\b/i', $raw, $m)) {
        $years = intval($m[1]);
        if ($years > 0 && $years <= 50) {
            $term = $years . ' ' . ($years == 1 ? 'Year' : 'Years');
            $confidence = 0.85;
            $method = 'valid_for';
        }
    }
    
    // Pattern 3: "duration of X years" or "duration: X years"
    if (!$term && preg_match('/\bduration\s*(?:of|:|=)\s*(\d+)\s*(?:year|years)\b/i', $raw, $m)) {
        $years = intval($m[1]);
        if ($years > 0 && $years <= 50) {
            $term = $years . ' ' . ($years == 1 ? 'Year' : 'Years');
            $confidence = 0.85;
            $method = 'duration';
        }
    }
    
    // Pattern 4: "renewable for X years" or "renewable every X years"
    if (!$term && preg_match('/\brenewable\s+(?:for|every)\s*(\d+)\s*(?:year|years)\b/i', $raw, $m)) {
        $years = intval($m[1]);
        if ($years > 0 && $years <= 50) {
            $term = $years . ' ' . ($years == 1 ? 'Year' : 'Years');
            $confidence = 0.8;
            $method = 'renewable';
        }
    }
    
    // Pattern 5: "effective for X years" or "effective period of X years"
    if (!$term && preg_match('/\beffective\s+(?:for|period\s+of)\s*(\d+)\s*(?:year|years)\b/i', $raw, $m)) {
        $years = intval($m[1]);
        if ($years > 0 && $years <= 50) {
            $term = $years . ' ' . ($years == 1 ? 'Year' : 'Years');
            $confidence = 0.8;
            $method = 'effective';
        }
    }
    
    // Pattern 6: "X years" near term-related keywords (less specific, lower confidence)
    if (!$term && preg_match('/\b(?:term|validity|duration|period|agreement|mou|moa)\b[^.]{0,100}?(\d+)\s*(?:year|years)\b/i', $raw, $m)) {
        $years = intval($m[1]);
        if ($years > 0 && $years <= 50) {
            $term = $years . ' ' . ($years == 1 ? 'Year' : 'Years');
            $confidence = 0.6;
            $method = 'contextual';
        }
    }
    
    // Pattern 7: "X year(s) term" (number before "year" and "term" after)
    if (!$term && preg_match('/\b(\d+)\s*(?:year|years)\s+term\b/i', $raw, $m)) {
        $years = intval($m[1]);
        if ($years > 0 && $years <= 50) {
            $term = $years . ' ' . ($years == 1 ? 'Year' : 'Years');
            $confidence = 0.75;
            $method = 'years_term';
        }
    }
    
    return [
        'term' => $term,
        'confidence' => $confidence,
        'method' => $method
    ];
}

function scoreInstitutionLine($line) {
    $line = trim((string)$line);
    if ($line === '') return 0;

    // Penalize obvious non-institution lines
    if (preg_match('/\bmemorandum\b|\bof\b\s+\b(understanding|agreement)\b/i', $line)) return 0;
    if (preg_match('/\bdate\b|\bday\b|\bmonth\b|\byear\b/i', $line)) {
        // Only allow if it has institution keywords (e.g., "University day")
        if (!preg_match('/\b(university|college|institute|institution|school|academy|polytechnic|foundation|corporation|inc|llc|ltd)\b/i', $line)) {
            return 0;
        }
    }
    if (preg_match('/\b(signature|signatory|witness|whereas|therefore|signed)\b/i', $line)) {
        // Only allow if it has institution keywords (e.g., "signatures at University")
        if (!preg_match('/\b(university|college|institute|institution|school|academy|polytechnic|foundation|corporation|inc|llc|ltd)\b/i', $line)) {
            return 0;
        }
    }
    
    // Reject lines with date patterns (e.g., "day of m_. 2012")
    if (preg_match('/\b(day|month|year)\s+of\s+[^\s]+\s+\d{4}\b/i', $line)) {
        if (!preg_match('/\b(university|college|institute|institution|school|academy|polytechnic|foundation|corporation|inc|llc|ltd)\b/i', $line)) {
            return 0;
        }
    }
    if (preg_match('/\b\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4}\b/', $line)) {
        if (!preg_match('/\b(university|college|institute|institution|school|academy|polytechnic|foundation|corporation|inc|llc|ltd)\b/i', $line)) {
            return 0;
        }
    }
    
    // Reject lines with OCR artifacts that suggest signature blocks (e.g., "_J.gfl", "_m_")
    if (preg_match('/_[A-Za-z0-9\.]+/', $line)) {
        if (!preg_match('/\b(university|college|institute|institution|school|academy|polytechnic|foundation|corporation|inc|llc|ltd)\b/i', $line)) {
            return 0;
        }
    }
    
    // Reject lines that start with signature-related text
    if (preg_match('/^(signatures?\s+)?(this\s+)?(day|month|year)/i', $line)) {
        if (!preg_match('/\b(university|college|institute|institution|school|academy|polytechnic|foundation|corporation|inc|llc|ltd)\b/i', $line)) {
            return 0;
        }
    }
    
    // Reject lines that are mostly signature/date text
    $words = preg_split('/\s+/', $line);
    $signatureWords = ['signature', 'signatures', 'signed', 'signatory', 'witness', 'day', 'month', 'year', 'this', 'the'];
    $signatureCount = 0;
    foreach ($words as $word) {
        $cleanWord = strtolower(preg_replace('/[^A-Za-z]/', '', $word));
        if (in_array($cleanWord, $signatureWords)) {
            $signatureCount++;
        }
    }
    if (count($words) > 0 && ($signatureCount / count($words)) > 0.5) {
        // More than 50% are signature words - reject unless it has institution keywords
        if (!preg_match('/\b(university|college|institute|institution|school|academy|polytechnic|foundation|corporation|inc|llc|ltd)\b/i', $line)) {
            return 0;
        }
    }
    
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

    // 1) Explicit label: "Institution: XYZ" — capture only up to first comma or sentence continuation
    if (preg_match('/(?:partner\s+institution|institution|name\s+of\s+institution)\s*[:\-]\s*([^\n,]{4,90}?)(?:\s*,\s*(?:hereinafter|located|the\s+republic|address|referred)|\s*$)/i', $raw, $m)) {
        $cand = cleanInstitutionCandidate($m[1]);
        if ($cand !== '') $candidates[] = ['value' => $cand, 'method' => 'label'];
    }
    if (empty($candidates) && preg_match('/(?:partner\s+institution|institution|name\s+of\s+institution)\s*[:\-]\s*([^,\n]{4,90})/i', $raw, $m)) {
        $cand = cleanInstitutionCandidate($m[1]);
        if ($cand !== '') $candidates[] = ['value' => $cand, 'method' => 'label'];
    }
    
    // 1b) Pattern: "... at [Institution]" — capture ONLY the institution name (stop at comma, "the Republic", etc.)
    $atPatternShort = '/\bat\s+([A-Z][A-Za-z\s]{2,50}?(?:\s+(?:University|College|Institute|Institution|School|Academy|Polytechnic|Foundation|Corporation))(?:\s*,\s*Inc\.?)?)\s*(?:,\s*(?:the\s+)?(?:republic|hereinafter|located)|[\.\n]|\s*$)/i';
    $atPatternFallback = '/\bat\s+([^\n,]{4,80}?)\s*(?:,\s*the|,\s*republic|,\s*hereinafter|[\.\n]|\s*$)/i';
    if (preg_match_all($atPatternShort, $raw, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $m) {
            $institutionPart = trim($m[1]);
            $institutionPart = preg_replace('/_[A-Za-z0-9\.]+/', '', $institutionPart);
            $institutionPart = preg_replace('/\b(day|month|year)\s+of\s+[^\s]+\s+\d{4}\b/i', '', $institutionPart);
            $institutionPart = preg_replace('/\b\d{4}\b/', '', $institutionPart);
            $institutionPart = preg_replace('/\s+/', ' ', trim($institutionPart));
            $cand = cleanInstitutionCandidate($institutionPart);
            if ($cand !== '' && preg_match('/\b(university|college|institute|institution|school|academy|polytechnic|foundation)\b/i', $cand) && !preg_match('/^(signatures?|signed|signatory|witness|day|month|year|this|the)\b/i', $cand)) {
                array_unshift($candidates, ['value' => $cand, 'method' => 'at_pattern']);
            }
        }
    }
    if (empty($candidates) && preg_match_all($atPatternFallback, $raw, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $m) {
            $institutionPart = trim($m[1]);
            $institutionPart = preg_replace('/\s+,\s*the\s*$/i', '', $institutionPart);
            $institutionPart = preg_replace('/_[A-Za-z0-9\.]+/', '', $institutionPart);
            $institutionPart = preg_replace('/\b(day|month|year)\s+of\s+[^\s]+\s+\d{4}\b/i', '', $institutionPart);
            $institutionPart = preg_replace('/\b\d{4}\b/', '', $institutionPart);
            $institutionPart = preg_replace('/\s+/', ' ', trim($institutionPart));
            if (preg_match('/\b(university|college|institute|institution|school|academy|polytechnic|foundation|corporation|inc|llc|ltd)\b/i', $institutionPart)) {
                $cand = cleanInstitutionCandidate($institutionPart);
                if ($cand !== '' && preg_match('/\b(university|college|institute|institution|school|academy|polytechnic|foundation)\b/i', $cand) && !preg_match('/^(signatures?|signed|signatory|witness|day|month|year|this|the)\b/i', $cand)) {
                    array_unshift($candidates, ['value' => $cand, 'method' => 'at_pattern']);
                }
            }
        }
    }

    // 2) "by and between X and Y" — capture short phrases only (institution names, not sentences)
    $betweenStrict = '/\b(?:by\s+and\s+between|between)\b\s*([^,\n]{4,85}?)\s+(?:and|&)\s+([^,\n]{4,85}?)(?:\n|,|\.|;|\s+hereinafter)/i';
    if (preg_match($betweenStrict, $raw, $m)) {
        $a = cleanInstitutionCandidate($m[1]);
        $b = cleanInstitutionCandidate($m[2]);
        if ($a !== '' && $b !== '') {
            $betweenParties = [$a, $b];
        }
        if ($a !== '') $candidates[] = ['value' => $a, 'method' => 'between'];
        if ($b !== '') $candidates[] = ['value' => $b, 'method' => 'between'];
    }
    if (!is_array($betweenParties) && preg_match('/\b(?:by\s+and\s+between|between)\b\s*([\s\S]{0,120}?)\s+(?:and|&)\s+([\s\S]{0,120}?)(?:\n|,|\.|;)/i', $raw, $m)) {
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
        // 2. "at_pattern" method: accept if has institution keywords (extracted from "at [Institution]" patterns)
        // 3. "between" method: accept if has keywords OR if no person name indicators and reasonable length
        // 4. "lines" method: require keywords (too noisy otherwise)
        if ($c['method'] === 'label') {
            // Always accept labeled institutions
            $unique[] = ['value' => $cleaned, 'method' => $c['method']];
        } elseif ($c['method'] === 'at_pattern') {
            // Accept if has institution keywords (already checked in extraction, but double-check)
            if ($hasKeyword) {
                $unique[] = ['value' => $cleaned, 'method' => $c['method']];
            }
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

    // Filter out CPU (Central Philippine University) - this is our institution, not the partner
    $cpuPatterns = [
        '/^central\s+philippine\s+university$/i',
        '/^cpu\b/i',
        '/central\s+philippine\s+university/i',
    ];
    
    $isCpu = function($name) use ($cpuPatterns) {
        $nameLower = strtolower(trim($name));
        foreach ($cpuPatterns as $pattern) {
            if (preg_match($pattern, $nameLower)) {
                return true;
            }
        }
        return false;
    };
    
    // Filter CPU from candidates
    $filteredCandidates = [];
    foreach ($candidates as $cand) {
        if (!$isCpu($cand['value'])) {
            $filteredCandidates[] = $cand;
        }
    }
    
    // Filter CPU from between parties
    $filteredBetweenParties = null;
    if (is_array($betweenParties)) {
        $filtered = [];
        foreach ($betweenParties as $party) {
            if (!$isCpu($party)) {
                $filtered[] = $party;
            }
        }
        if (count($filtered) > 0) {
            $filteredBetweenParties = $filtered;
        }
    }
    
    // Filter CPU from unique candidates
    $filteredUnique = [];
    foreach ($unique as $u) {
        if (!$isCpu($u['value'])) {
            $filteredUnique[] = $u;
        }
    }
    
    // Choose first (prioritize at_pattern, then label/between, then line-based)
    // For MOU/MOA: typically "between OUR institution (CPU) and PARTNER institution".
    // The modal "Institution" field is intended for the PARTNER, so if we have a between-clause,
    // prefer the non-CPU party as the chosen institution.
    $chosen = '';
    $method = 'none';
    
    // PRIORITY 1: "at_pattern" method (extracted from "at [Institution]" patterns) - highest priority
    foreach ($filteredUnique as $c) {
        if ($c['method'] === 'at_pattern') {
            $chosen = $c['value'];
            $method = 'at_pattern';
            break;
        }
    }
    
    // PRIORITY 2: "label" method (explicitly labeled)
    if ($chosen === '') {
        foreach ($filteredUnique as $c) {
            if ($c['method'] === 'label') {
                $chosen = $c['value'];
                $method = 'label';
                break;
            }
        }
    }
    
    // PRIORITY 3: If we have filtered between parties, prefer the first non-CPU party
    if ($chosen === '' && is_array($filteredBetweenParties) && count($filteredBetweenParties) > 0) {
        $cleanedParty = cleanInstitutionCandidate($filteredBetweenParties[0]);
        if ($cleanedParty !== '' && !$isCpu($cleanedParty)) {
            $chosen = $cleanedParty;
            $method = 'between';
        }
    }
    
    // PRIORITY 4: Fallback to first valid non-CPU candidate
    if ($chosen === '' && !empty($filteredUnique)) {
        $chosen = $filteredUnique[0]['value'] ?? '';
        $method = $filteredUnique[0]['method'] ?? 'none';
    }
    
    // Final check: if chosen is still CPU, try filtered candidates
    if ($isCpu($chosen)) {
        $chosen = '';
        if (!empty($filteredCandidates)) {
            $chosen = $filteredCandidates[0]['value'] ?? '';
            $method = $filteredCandidates[0]['method'] ?? 'none';
        }
    }

    // Final cleanup pass: ensure institution name doesn't contain location info
    $chosen = cleanInstitutionCandidate($chosen);

    // Confidence heuristic
    $confidence = 'low';
    if ($chosen !== '') {
        if ($method === 'label') $confidence = 'high';
        elseif ($method === 'at_pattern' || $method === 'between') $confidence = 'medium';
        else $confidence = 'low';
    }

    return [
        'institution' => $chosen,
        'method' => $method,
        'confidence' => $confidence,
        'between_parties' => $filteredBetweenParties ?? $betweenParties  // Use filtered parties
    ];
}

/**
 * Extract the partner institution location (favor the second party / matched name).
 * Filters out CPU (Central Philippine University) locations since we want the foreign partner's location.
 */
function extractInstitutionLocationFromText($text, $instData) {
    $raw = normalizeWhitespaceKeepNewlines($text);
    $institution = trim((string)($instData['institution'] ?? ''));
    $institutionLower = strtolower($institution);
    $betweenParties = $instData['between_parties'] ?? null;

    // CPU patterns to filter out
    $cpuPatterns = [
        '/^central\s+philippine\s+university$/i',
        '/^cpu\b/i',
        '/central\s+philippine\s+university/i',
    ];
    $isCpu = function($name) use ($cpuPatterns) {
        $nameLower = strtolower(trim($name));
        foreach ($cpuPatterns as $pattern) {
            if (preg_match($pattern, $nameLower)) {
                return true;
            }
        }
        return false;
    };

    // Collect all "X located at|in Y" pairs
    $pairs = [];
    if (preg_match_all('/([^\n]{2,140}?)\s+located\s+(?:at|in)\s+([^\n\.]{5,220}?)(?:(?:\s*,?\s*hereinafter\b)|\.|\n)/i', $raw, $all, PREG_SET_ORDER)) {
        foreach ($all as $m) {
            $name = cleanInstitutionCandidate($m[1]);
            $loc = cleanLocationCandidate($m[2]);
            // Filter out CPU locations
            if ($name && $loc && !$isCpu($name)) {
                $pairs[] = ['name' => $name, 'loc' => $loc];
            }
        }
    }

    // Also collect patterns like "based in", "headquartered in", "from [location]"
    $additionalPatterns = [
        '/\b(based\s+in|headquartered\s+in|from)\s+([^\n\.]{5,180}?)(?:(?:\s*,?\s*hereinafter\b)|\.|\n|$)/i',
        '/\b(institution|university|college|institute)\s+(?:in|at)\s+([^\n\.]{5,180}?)(?:(?:\s*,?\s*hereinafter\b)|\.|\n|$)/i',
    ];
    
    foreach ($additionalPatterns as $pattern) {
        if (preg_match_all($pattern, $raw, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $loc = cleanLocationCandidate($m[2] ?? $m[1] ?? '');
                if ($loc !== '') {
                    // Check if this location is associated with the partner institution
                    // by looking at context before the match
                    $contextStart = max(0, strpos($raw, $m[0]) - 100);
                    $context = substr($raw, $contextStart, 200);
                    $contextLower = strtolower($context);
                    
                    // If institution name appears in context, this is likely the partner location
                    if ($institution && strpos($contextLower, $institutionLower) !== false) {
                        if (!$isCpu($institution)) {
                            $pairs[] = ['name' => $institution, 'loc' => $loc];
                        }
                    } elseif (!preg_match('/central\s+philippine\s+university|cpu/i', $context)) {
                        // If no CPU mention, might be partner location
                        $pairs[] = ['name' => '', 'loc' => $loc];
                    }
                }
            }
        }
    }

    // 1) Exact/contains match vs chosen institution (which should already be filtered from CPU)
    foreach ($pairs as $p) {
        $nLower = strtolower($p['name']);
        if ($institution && (strpos($institutionLower, $nLower) !== false || strpos($nLower, $institutionLower) !== false)) {
            return $p['loc'];
        }
    }

    // 2) If we have two parties, prefer non-CPU party
    if (is_array($betweenParties) && count($betweenParties) >= 1) {
        // Find first non-CPU party
        $partnerParty = null;
        foreach ($betweenParties as $party) {
            if (!$isCpu($party)) {
                $partnerParty = $party;
                break;
            }
        }
        
        if ($partnerParty !== null) {
            $partyLower = strtolower($partnerParty);
            foreach ($pairs as $p) {
                $nLower = strtolower($p['name']);
                if (strpos($partyLower, $nLower) !== false || strpos($nLower, $partyLower) !== false) {
                    return $p['loc'];
                }
            }
        }
        
        // If we have pairs and found a partner party, prefer first non-CPU location
        if (count($pairs) >= 1) {
            return $pairs[0]['loc'];
        }
    }

    // 3) If two locations found, assume second is partner (first is usually CPU)
    if (count($pairs) >= 2) {
        return $pairs[1]['loc'];
    }

    // 4) Fallback to first found (should already be filtered from CPU)
    if (count($pairs) >= 1) {
        return $pairs[0]['loc'];
    }

    // 5) Use the more comprehensive extractLocationsFromText as final fallback
    $allLocations = extractLocationsFromText($text);
    if (!empty($allLocations)) {
        // Filter out locations that might be CPU-related
        foreach ($allLocations as $locData) {
            $locValue = strtolower($locData['value']);
            // Skip if it contains CPU-related terms
            if (!preg_match('/central\s+philippine\s+university|cpu|iloilo\s+city/i', $locValue)) {
                return $locData['value'];
            }
        }
        // If all contain CPU terms, return first anyway (might be false positive filter)
        return $allLocations[0]['value'];
    }

    return '';
}

/**
 * Institution-to-Country lookup table for known institutions.
 * Maps institution name patterns (case-insensitive partial matches) to countries.
 */
function getInstitutionCountryLookup() {
    return [
        // Korea
        'HALLA' => 'Korea',
        'Halla' => 'Korea',
        'Hallym' => 'Korea',
        'Hanyang' => 'Korea',
        'Korea' => 'Korea',
        'Korean' => 'Korea',
        'Seoul' => 'Korea',
        'Daegu' => 'Korea',
        'Busan' => 'Korea',
        'Incheon' => 'Korea',
        'Daejeon' => 'Korea',
        'Gwangju' => 'Korea',
        
        // Japan
        'IKUEI' => 'Japan',
        'Ikuei' => 'Japan',
        'Japanese' => 'Japan',
        'Tokyo' => 'Japan',
        'Osaka' => 'Japan',
        'Kyoto' => 'Japan',
        'Kobe' => 'Japan',
        'Nagoya' => 'Japan',
        'Yokohama' => 'Japan',
        'Saitama' => 'Japan',
        'Hiroshima' => 'Japan',
        'Sendai' => 'Japan',
        'Nagata' => 'Japan',
        
        // Vietnam
        'TRA VINH' => 'Vietnam',
        'Tra Vinh' => 'Vietnam',
        'Travinh' => 'Vietnam',
        'Vietnamese' => 'Vietnam',
        'Hanoi' => 'Vietnam',
        'Ho Chi Minh' => 'Vietnam',
        'Da Nang' => 'Vietnam',
        'Hue' => 'Vietnam',
        'Can Tho' => 'Vietnam',
        
        // China
        'Chinese' => 'China',
        'Beijing' => 'China',
        'Shanghai' => 'China',
        'Guangzhou' => 'China',
        'Shenzhen' => 'China',
        'Fujian' => 'China',
        'Zhangzhou' => 'China',
        'Xiamen' => 'China',
        'Chengdu' => 'China',
        'Hangzhou' => 'China',
        
        // Thailand
        'Thai' => 'Thailand',
        'Bangkok' => 'Thailand',
        'Chiang Mai' => 'Thailand',
        
        // Indonesia
        'Indonesian' => 'Indonesia',
        'Jakarta' => 'Indonesia',
        'Surabaya' => 'Indonesia',
        'Bandung' => 'Indonesia',
        
        // Malaysia
        'Malaysian' => 'Malaysia',
        'Kuala Lumpur' => 'Malaysia',
        'Malaysia' => 'Malaysia',
        
        // Singapore
        'Singapore' => 'Singapore',
        'Singaporean' => 'Singapore',
        
        // India
        'Indian' => 'India',
        'Mumbai' => 'India',
        'Delhi' => 'India',
        'Bangalore' => 'India',
        'Chennai' => 'India',
        
        // Taiwan
        'Taiwanese' => 'Taiwan',
        'Taipei' => 'Taiwan',
        'Taiwan' => 'Taiwan',
        
        // Australia
        'Australian' => 'Australia',
        'Sydney' => 'Australia',
        'Melbourne' => 'Australia',
        'Brisbane' => 'Australia',
        
        // Canada
        'Canadian' => 'Canada',
        'Toronto' => 'Canada',
        'Vancouver' => 'Canada',
        'Montreal' => 'Canada',
        
        // United States
        'American' => 'United States',
        'New York' => 'United States',
        'Los Angeles' => 'United States',
        'Chicago' => 'United States',
        'Houston' => 'United States',
    ];
}

/**
 * Look up country from institution name using the lookup table.
 * Returns country name if found, null otherwise.
 */
function lookupInstitutionCountry($institutionName) {
    if (empty($institutionName)) {
        return null;
    }
    
    $lookup = getInstitutionCountryLookup();
    $institutionUpper = strtoupper(trim($institutionName));
    
    foreach ($lookup as $pattern => $country) {
        if (stripos($institutionUpper, $pattern) !== false) {
            return $country;
        }
    }
    
    return null;
}

/**
 * Detect explicit location patterns from text.
 * Looks for: "executed at", "made and entered into at", stamped cities, address blocks.
 * Returns array of detected locations with their patterns.
 */
function detectExplicitLocations($text) {
    $raw = normalizeWhitespaceKeepNewlines($text);
    $locations = [];
    
    // Pattern 1: "executed at [location]"
    if (preg_match_all('/executed\s+at\s+([^\.;\n]{5,180}?)(?:(?:\s*,?\s*hereinafter\b)|\.|\n|$)/i', $raw, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $m) {
            $loc = cleanLocationCandidate($m[1]);
            if ($loc !== '') {
                $locations[] = [
                    'value' => $loc,
                    'pattern' => 'executed_at',
                    'confidence' => 0.9
                ];
            }
        }
    }
    
    // Pattern 2: "made and entered into at [location]"
    if (preg_match_all('/made\s+and\s+entered\s+into\s+at\s+([^\.;\n]{5,180}?)(?:(?:\s*,?\s*hereinafter\b)|\.|\n|$)/i', $raw, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $m) {
            $loc = cleanLocationCandidate($m[1]);
            if ($loc !== '') {
                $locations[] = [
                    'value' => $loc,
                    'pattern' => 'made_entered_at',
                    'confidence' => 0.9
                ];
            }
        }
    }
    
    // Pattern 3: "signed at [location]" or "signed in [location]"
    if (preg_match_all('/signed\s+(?:at|in)\s+([^\.;\n]{5,180}?)(?:(?:\s*,?\s*hereinafter\b)|\.|\n|$)/i', $raw, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $m) {
            $loc = cleanLocationCandidate($m[1]);
            if ($loc !== '') {
                $locations[] = [
                    'value' => $loc,
                    'pattern' => 'signed_at',
                    'confidence' => 0.85
                ];
            }
        }
    }
    
    // Pattern 4: Stamped cities (common pattern: "City, Country" on separate line or after signature)
    // Look for standalone city/country patterns near signature blocks
    if (preg_match_all('/\b([A-Z][a-z]+(?:\s+[A-Z][a-z]+)*)\s*,\s*([A-Z][a-z]+(?:\s+[A-Z][a-z]+)*)\s*$/m', $raw, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $m) {
            $city = trim($m[1]);
            $country = trim($m[2]);
            // Check if country is a known country name
            $countryResult = detectCountry($country, null);
            if ($countryResult['country'] !== 'Country not reliable') {
                $locations[] = [
                    'value' => $countryResult['country'],
                    'pattern' => 'stamped_city',
                    'confidence' => 0.8
                ];
            }
        }
    }
    
    // Pattern 5: Address blocks (lines that look like addresses with city, country)
    $lines = preg_split("/\n+/", $raw);
    foreach ($lines as $line) {
        $line = trim($line);
        if (strlen($line) < 10 || strlen($line) > 200) continue;
        
        // Check if line contains address-like patterns
        if (preg_match('/\b(street|avenue|road|boulevard|drive|way|city|province|state|country)\b/i', $line)) {
            $countryResult = detectCountry($line, null);
            if ($countryResult['country'] !== 'Country not reliable') {
                $locations[] = [
                    'value' => $countryResult['country'],
                    'pattern' => 'address_block',
                    'confidence' => 0.75
                ];
            }
        }
    }
    
    // De-duplicate by value
    $seen = [];
    $unique = [];
    foreach ($locations as $loc) {
        $key = strtolower(preg_replace('/\s+/', ' ', $loc['value']));
        if (!isset($seen[$key])) {
            $seen[$key] = true;
            $unique[] = $loc;
        }
    }
    
    return $unique;
}

/**
 * Infer country from contextual clues (organization type, signatory context).
 * Returns country name and confidence score.
 */
function inferCountryFromContext($text, $institutionName) {
    $raw = normalizeWhitespaceKeepNewlines($text);
    $lower = strtolower($raw);
    
    // Context clues for different countries
    $contextClues = [
        'Korea' => [
            'patterns' => ['korean', 'republic of korea', 'rok', 'south korea'],
            'confidence' => 0.6
        ],
        'Japan' => [
            'patterns' => ['japanese', 'nippon', 'nihon'],
            'confidence' => 0.6
        ],
        'Vietnam' => [
            'patterns' => ['vietnamese', 'socialist republic of vietnam', 'sr vietnam'],
            'confidence' => 0.6
        ],
        'China' => [
            'patterns' => ['chinese', 'people\'s republic of china', 'prc', 'mainland china'],
            'confidence' => 0.6
        ],
        'Thailand' => [
            'patterns' => ['thai', 'kingdom of thailand'],
            'confidence' => 0.6
        ],
        'Indonesia' => [
            'patterns' => ['indonesian', 'republic of indonesia'],
            'confidence' => 0.6
        ],
        'Malaysia' => [
            'patterns' => ['malaysian', 'federation of malaysia'],
            'confidence' => 0.6
        ],
    ];
    
    foreach ($contextClues as $country => $clue) {
        foreach ($clue['patterns'] as $pattern) {
            if (stripos($lower, $pattern) !== false) {
                return [
                    'country' => $country,
                    'confidence' => $clue['confidence'],
                    'source' => 'contextual'
                ];
            }
        }
    }
    
    // Check organization type patterns
    // Government agencies often indicate country
    if (preg_match('/\b(ministry|department|bureau|agency)\s+of\s+([^,\n]{5,60})/i', $raw, $m)) {
        $orgName = trim($m[2]);
        $countryResult = detectCountry($orgName, $institutionName);
        if ($countryResult['country'] !== 'Country not reliable' && $countryResult['country'] !== 'Philippines') {
            return [
                'country' => $countryResult['country'],
                'confidence' => 0.55,
                'source' => 'contextual'
            ];
        }
    }
    
    return null;
}

/**
 * Confidence-based location extraction with fallback strategy.
 * Returns array with: country, confidence (0-1), source (explicit|inferred|unknown)
 */
function extractLocationWithConfidence($text, $institutionData) {
    $institution = trim((string)($institutionData['institution'] ?? ''));
    $confidenceThreshold = 0.5; // Minimum confidence to return a result
    
    $result = [
        'country' => null,
        'confidence' => 0.0,
        'source' => 'unknown'
    ];
    
    // Priority 1: Explicit text detection
    $explicitLocations = detectExplicitLocations($text);
    if (!empty($explicitLocations)) {
        // Use the location with highest confidence
        usort($explicitLocations, function($a, $b) {
            return $b['confidence'] <=> $a['confidence'];
        });
        
        $bestLocation = $explicitLocations[0];
        $normalizedCountry = normalizeLocationToCountry($bestLocation['value'], $institution);
        
        if ($normalizedCountry !== '') {
            $result['country'] = $normalizedCountry;
            $result['confidence'] = $bestLocation['confidence'];
            $result['source'] = 'explicit';
            return $result;
        }
    }
    
    // Priority 2: Institution-to-Country lookup
    if ($institution !== '') {
        $lookupCountry = lookupInstitutionCountry($institution);
        if ($lookupCountry !== null) {
            $result['country'] = $lookupCountry;
            $result['confidence'] = 0.85; // High confidence for known mappings
            $result['source'] = 'inferred';
            return $result;
        }
    }
    
    // Priority 3: Extract from existing location extraction methods
    $locationRaw = extractInstitutionLocationFromText($text, $institutionData);
    if ($locationRaw !== '') {
        $normalizedCountry = normalizeLocationToCountry($locationRaw, $institution);
        if ($normalizedCountry !== '') {
            $result['country'] = $normalizedCountry;
            $result['confidence'] = 0.7; // Medium confidence for extracted locations
            $result['source'] = 'inferred';
            return $result;
        }
    }
    
    // Priority 4: Contextual inference
    $contextResult = inferCountryFromContext($text, $institution);
    if ($contextResult !== null && $contextResult['confidence'] >= $confidenceThreshold) {
        $result['country'] = $contextResult['country'];
        $result['confidence'] = $contextResult['confidence'];
        $result['source'] = $contextResult['source'];
        return $result;
    }
    
    // Priority 5: Fallback to full-text country detection (lower confidence)
    $cpuPatterns = [
        '/central\s+philippine\s+university/i',
        '/\bcpu\b/i',
    ];
    $filteredText = $text;
    foreach ($cpuPatterns as $pattern) {
        $filteredText = preg_replace($pattern, '', $filteredText);
    }
    
    $countryResult = detectCountry($filteredText, $institution);
    if ($countryResult['country'] !== 'Country not reliable') {
        // Only return if it's a foreign country (not Philippines) or if institution also indicates Philippines
        if ($countryResult['country'] !== 'Philippines') {
            $result['country'] = $countryResult['country'];
            $result['confidence'] = 0.5; // Lower confidence for fallback
            $result['source'] = 'inferred';
        } elseif ($institution !== '') {
            $institutionResult = detectCountry($institution, null);
            if ($institutionResult['country'] === 'Philippines') {
                $result['country'] = 'Philippines';
                $result['confidence'] = 0.5;
                $result['source'] = 'inferred';
            }
        }
    }
    
    // Only return if confidence meets threshold
    if ($result['confidence'] < $confidenceThreshold) {
        return [
            'country' => null,
            'confidence' => $result['confidence'],
            'source' => 'unknown'
        ];
    }
    
    return $result;
}

/**
 * Normalize location down to country-level to keep it short (e.g., "Nagata-ku, Kobe-shi, Japan" -> "Japan").
 * Uses the enhanced rule-based country detector with institution name support.
 * Prioritizes foreign countries (non-Philippines) since CPU is in Philippines.
 */
function normalizeLocationToCountry($location, $institutionName = null) {
    $loc = trim((string)$location);
    if ($loc === '') return $loc;

    // Filter out CPU mentions from location to avoid false positives
    $cpuPatterns = [
        '/central\s+philippine\s+university/i',
        '/\bcpu\b/i',
    ];
    $filteredLoc = $loc;
    foreach ($cpuPatterns as $pattern) {
        $filteredLoc = preg_replace($pattern, '', $filteredLoc);
    }
    $filteredLoc = trim($filteredLoc);
    if ($filteredLoc === '') $filteredLoc = $loc; // Fallback to original if filtered is empty

    // Use new country detector with institution name (if available)
    $result = detectCountry($filteredLoc, $institutionName);
    if ($result['country'] !== 'Country not reliable') {
        // Prioritize foreign countries (non-Philippines)
        if ($result['country'] !== 'Philippines') {
            return $result['country'];
        }
        // Only return Philippines if institution name also indicates Philippines
        // (both parties in Philippines)
        if ($institutionName !== null && $institutionName !== '') {
            $institutionResult = detectCountry($institutionName, null);
            if ($institutionResult['country'] === 'Philippines') {
                return 'Philippines'; // Both parties in Philippines
            }
        }
        // If location says Philippines but institution doesn't, it's likely CPU's location
        // Return empty to let other detection methods try
        return '';
    }

    // Fallback: if location has commas, check the last segment (common pattern: "City, Country")
    $parts = array_filter(array_map('trim', explode(',', $filteredLoc)));
    if (!empty($parts) && count($parts) > 1) {
        // Try last segment first (most common pattern)
        $last = end($parts);
        $result = detectCountry($last, $institutionName);
        if ($result['country'] !== 'Country not reliable') {
            if ($result['country'] !== 'Philippines') {
                return $result['country'];
            }
            // Check if institution also indicates Philippines
            if ($institutionName !== null && $institutionName !== '') {
                $institutionResult = detectCountry($institutionName, null);
                if ($institutionResult['country'] === 'Philippines') {
                    return 'Philippines';
                }
            }
        }
        
        // Also check second-to-last segment (some formats: "City, State, Country")
        if (count($parts) >= 2) {
            $partsArray = array_values($parts);
            $secondLast = $partsArray[count($partsArray) - 2];
            $result = detectCountry($secondLast, $institutionName);
            if ($result['country'] !== 'Country not reliable') {
                if ($result['country'] !== 'Philippines') {
                    return $result['country'];
                }
                // Check if institution also indicates Philippines
                if ($institutionName !== null && $institutionName !== '') {
                    $institutionResult = detectCountry($institutionName, null);
                    if ($institutionResult['country'] === 'Philippines') {
                        return 'Philippines';
                    }
                }
            }
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
 * - Prefer partner-tied location string first (if present), with institution name as context
 * - Prioritize FOREIGN countries (non-Philippines) since CPU is in Philippines
 * - Only return Philippines if BOTH parties are in Philippines (partner is also in Philippines)
 * 
 * Uses the new country detector which considers institution names as strong signals.
 */
function detectPartnerCountryFromOcr($fullText, $partnerInstitution, $partnerLocationStr) {
    $partnerInstitutionLower = strtolower((string)$partnerInstitution);
    $partnerLocLower = strtolower((string)$partnerLocationStr);

    // Filter out CPU mentions from text to avoid false positives
    $cpuPatterns = [
        '/central\s+philippine\s+university/i',
        '/\bcpu\b/i',
    ];
    $filteredText = $fullText;
    foreach ($cpuPatterns as $pattern) {
        $filteredText = preg_replace($pattern, '', $filteredText);
    }

    // 1) Check partner location first (with institution name as context)
    $locationResult = detectCountry($partnerLocationStr, $partnerInstitution);
    if ($locationResult['country'] !== 'Country not reliable') {
        // If location indicates a foreign country, use it
        if ($locationResult['country'] !== 'Philippines') {
            return $locationResult['country'];
        }
        // If location indicates Philippines, check if partner institution also indicates Philippines
        // (both parties in Philippines)
        if ($partnerInstitution !== '') {
            $institutionResult = detectCountry($partnerInstitution, null);
            if ($institutionResult['country'] === 'Philippines') {
                return 'Philippines'; // Both parties in Philippines
            }
        }
    }

    // 2) Check if institution name contains country indicators (strong signal)
    if ($partnerInstitution !== '') {
        $institutionResult = detectCountry($partnerInstitution, null);
        if ($institutionResult['country'] !== 'Country not reliable') {
            // Prefer foreign countries from institution name
            if ($institutionResult['country'] !== 'Philippines') {
                return $institutionResult['country'];
            }
            // If institution indicates Philippines, check location too
            if ($locationResult['country'] === 'Philippines') {
                return 'Philippines'; // Both indicate Philippines
            }
        }
    }

    // 3) Prefer non-PH countries from filtered full text (avoid CPU address dominating)
    $fullTextResult = detectCountry($filteredText, $partnerInstitution);
    if ($fullTextResult['country'] !== 'Country not reliable' && $fullTextResult['country'] !== 'Philippines') {
        return $fullTextResult['country'];
    }

    // 4) Philippines only if BOTH partner location AND institution indicate Philippines
    // (meaning both parties are in Philippines)
    $hasPhInLocation = strpos($partnerLocLower, 'philippine') !== false || strpos($partnerLocLower, 'philippines') !== false;
    $hasPhInInstitution = strpos($partnerInstitutionLower, 'philippine') !== false || strpos($partnerInstitutionLower, 'philippines') !== false;
    
    if ($hasPhInLocation && $hasPhInInstitution) {
        return 'Philippines'; // Both parties in Philippines
    }
    
    // If only one indicates Philippines, it's likely CPU, so don't return Philippines
    // (we want foreign country, or nothing if unclear)

    return '';
}

// =============================================================================
// BEGIN parseOCRMetadata fallback (simple regex extraction)
// To revert: set USE_EXTRACT_METADATA_FALLBACK to false above, or delete this
// block and the fallback usage below (search for "USE_EXTRACT_METADATA_FALLBACK").
// =============================================================================
function parseOCRMetadata($text) {
    $data = [
        'institution' => null,
        'country' => null
    ];
    $text = (string)$text;
    if ($text === '') return $data;

    // --- RULE 1: Find Country ---
    $countries = ['Philippines', 'United States', 'USA', 'UK', 'Japan', 'China', 'Singapore', 'Korea'];

    foreach ($countries as $c) {
        if (preg_match('/\b' . preg_quote($c, '/') . '\b/i', $text)) {
            $data['country'] = $c;
            break;
        }
    }

    // --- RULE 2: Find Institution ---
    // [A-Z] (not [A-Z0-9]) so words must start with a letter — e.g. "2013" is ignored
    $keywords = 'University|Institute|College|School|Academy|Department|Center|Centre|Hospital';
    $pattern = '/\b((?:[A-Z][\w\']*\s+)*(?:' . $keywords . ')(?:\s+of\s+[A-Z][\w\s]+)?)/i';

    if (preg_match($pattern, $text, $matches)) {
        $raw_inst = trim($matches[1]);

        // Sanitization: strip leading For|To|From|At|By|In|Near|Est|Since|Founded
        $ignored_prefixes = 'For|To|From|At|By|In|Near|Est|Since|Founded';
        $clean_inst = preg_replace('/^(' . $ignored_prefixes . ')\s+/i', '', $raw_inst);

        // Extra cleanup: remove leading years if they snuck in (e.g. "2013 Halla" -> "Halla")
        $clean_inst = preg_replace('/^\d{4}\s+/', '', $clean_inst);
        $clean_inst = trim($clean_inst);

        if ($clean_inst !== '') {
            if (strlen($clean_inst) <= MOU_OCR_INSTITUTION_MAX_LEN) {
                $data['institution'] = $clean_inst;
            } else {
                $data['institution'] = trim(substr($clean_inst, 0, MOU_OCR_INSTITUTION_MAX_LEN));
                $data['institution'] = preg_replace('/\s+\S+$/', '', $data['institution']);
            }
        }
    }

    return $data;
}
// =============================================================================
// END parseOCRMetadata fallback
// =============================================================================

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

    // Preprocess OCR text to fix run-together words and artifacts for better institution extraction
    $extracted = preprocessOcrTextForInstitution($extracted);

    $institutionFields = extractInstitutionFromText($extracted);
    
    // Use confidence-based location extraction
    $locationResult = extractLocationWithConfidence($extracted, $institutionFields);
    
    $locationField = $locationResult['country'];
    $locationConfidence = $locationResult['confidence'];
    $locationSource = $locationResult['source'];
    
    // Extract term/duration
    $termResult = extractTermFromText($extracted);
    $termField = $termResult['term'];
    $termConfidence = $termResult['confidence'];
    $termMethod = $termResult['method'];

    // --- Fallback: AutoSuggestExtractor (DB) → OCRExtractor (anchor/stopper) → parseOCRMetadata ---
    $institutionSuggestion = null; // Pending approval suggestion from AutoSuggestExtractor
    if (defined('USE_EXTRACT_METADATA_FALLBACK') && USE_EXTRACT_METADATA_FALLBACK) {
        $needInstitution = empty($institutionFields['institution']) || trim((string)$institutionFields['institution']) === '';
        $needCountry = ($locationField === null || $locationField === '');
        if ($needInstitution || $needCountry) {
            $fallback = null;
            // 1. Try AutoSuggestExtractor first (DB-backed: approved partners + auto-detect + save suggestion)
            if ($needInstitution && class_exists('AutoSuggestExtractor')) {
                try {
                    $db = function_exists('getDatabaseConnection') ? getDatabaseConnection() : null;
                    if ($db && !($db instanceof FileBasedDatabase)) {
                        $autoExtractor = new AutoSuggestExtractor($db);
                        $autoResult = $autoExtractor->extract($extracted);
                        if (!empty($autoResult['institution'])) {
                            $fallback = ['institution' => $autoResult['institution'], 'country' => null];
                            $institutionFields['method'] = 'AutoSuggest_db_match';
                        } elseif (!empty($autoResult['suggestion'])) {
                            $institutionSuggestion = trim((string)$autoResult['suggestion']);
                        }
                    }
                } catch (Throwable $e) {
                    // Continue to OCRExtractor on failure
                }
            }
            // 2. OCRExtractor (anchor/stopper rule-based)
            if ($fallback === null && class_exists('OCRExtractor')) {
                $extractor = new OCRExtractor();
                $fallback = $extractor->extract($extracted);
            }
            if ($fallback === null || (empty($fallback['institution']) && empty($fallback['country']))) {
                $fallback = parseOCRMetadata($extracted);
            }
            if ($needInstitution && !empty($fallback['institution'])) {
                $raw = trim((string)$fallback['institution']);
                $institutionFields['institution'] = trimToInstitutionNameOnly($raw) ?: $raw;
                $institutionFields['confidence'] = ($institutionFields['method'] ?? '') === 'AutoSuggest_db_match' ? 'high' : 'low';
                $institutionFields['method'] = $institutionFields['method'] ?? 'OCRExtractor_fallback';
            }
            if ($needCountry && !empty($fallback['country'])) {
                $locationField = trim((string)$fallback['country']);
                $locationConfidence = 0.5;
                $locationSource = 'OCRExtractor_fallback';
            }
        }
    }

    // Build list of alternative institution candidates (institution name only, no extra text)
    $institutionCandidates = [];
    $addCandidate = function ($name) use (&$institutionCandidates) {
        $name = trim((string)$name);
        if ($name === '') return;
        $trimmed = trimToInstitutionNameOnly($name) ?: $name;
        if ($trimmed !== '' && !in_array($trimmed, $institutionCandidates)) {
            $institutionCandidates[] = $trimmed;
        }
    };
    if (!empty($institutionFields['institution'])) {
        $addCandidate($institutionFields['institution']);
    }
    if (!empty($institutionSuggestion)) {
        $addCandidate($institutionSuggestion);
    }
    if (is_array($institutionFields['between_parties'] ?? null)) {
        foreach ($institutionFields['between_parties'] as $party) {
            $addCandidate(is_string($party) ? $party : '');
        }
    }
    $institutionCandidates = array_slice(array_values($institutionCandidates), 0, 5);

    // Rule: output only the institution name — no location, no trailing sentence, nothing else
    $institutionOutput = trim((string)($institutionFields['institution'] ?? ''));
    if ($institutionOutput !== '') {
        $institutionOutput = trimToInstitutionNameOnly($institutionOutput) ?: $institutionOutput;
    }

    $debug = isset($_GET['debug']) && $_GET['debug'] === '1';
    $resp = [
        'success' => true,
        'fields' => [
            'institution' => $institutionOutput,
            'location' => $locationField,
            'term' => $termField
        ],
        'meta' => [
            'institution_confidence' => $institutionFields['confidence'],
            'institution_method' => $institutionFields['method'],
            'institution_candidates' => $institutionCandidates,
            'institution_pending_suggestion' => $institutionSuggestion,
            'location_confidence' => $locationConfidence,
            'location_source' => $locationSource,
            'location_requires_review' => ($locationField === null || $locationConfidence < 0.5),
            'between_parties' => $institutionFields['between_parties'],
            'term_confidence' => $termConfidence,
            'term_method' => $termMethod
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


