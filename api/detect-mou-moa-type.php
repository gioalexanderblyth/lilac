<?php
/**
 * Detect MOU/MOA type from an uploaded document (PDF/DOCX/Image) using server-side extraction/OCR.
 * Returns: { success: true, type: "MOU" | "MOA" | "Unknown" }
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

// Auth required (same pattern as other APIs)
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized. Please login.']);
    exit();
}

require_once __DIR__ . '/award-analysis-functions.php';

function detectMouMoaTypeFromText($text) {
    $text = strtolower($text ?? '');

    // Normalize some common OCR spacing issues: "M O U" / "M O A"
    $hasMouAcronym = preg_match('/\bm\s*o\s*u\b/i', $text) === 1;
    $hasMoaAcronym = preg_match('/\bm\s*o\s*a\b/i', $text) === 1;

    // Common OCR partials (memorand* / understand* / agreem*)
    $hasMemorandum = preg_match('/memorand/i', $text) === 1;
    $hasUnderstanding = preg_match('/understand/i', $text) === 1;
    $hasAgreement = preg_match('/agreem|agreement/i', $text) === 1;

    $mouScore = 0;
    $moaScore = 0;

    if ($hasMouAcronym) $mouScore += 3;
    if ($hasMoaAcronym) $moaScore += 3;

    if ($hasMemorandum && $hasUnderstanding) $mouScore += 5;
    if ($hasMemorandum && $hasAgreement) $moaScore += 5;

    // Exact phrases (when OCR is clean)
    if (strpos($text, 'memorandum of understanding') !== false) $mouScore += 7;
    if (strpos($text, 'memorandum of agreement') !== false) $moaScore += 7;

    if ($mouScore > $moaScore && $mouScore > 0) return 'MOU';
    if ($moaScore > $mouScore && $moaScore > 0) return 'MOA';
    return 'Unknown';
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

    $extensionMimeMap = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
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
    $base = tempnam(sys_get_temp_dir(), 'mou_type_');
    if ($base === false) {
        throw new Exception('Failed to create temporary file');
    }
    // tempnam creates the file; replace it with our own with extension
    @unlink($base);
    $tempPath = $base . ($originalExt ? '.' . $originalExt : '');

    if (!move_uploaded_file($file['tmp_name'], $tempPath)) {
        // Fallback copy (should rarely happen)
        if (!copy($file['tmp_name'], $tempPath)) {
            throw new Exception('Failed to stage uploaded file for analysis');
        }
    }

    $extracted = '';
    try {
        $extracted = extractTextFromFile($tempPath, $mimeType ?: ($file['type'] ?? ''));
    } finally {
        // Cleanup temp file
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

    $type = detectMouMoaTypeFromText($extracted);

    echo json_encode([
        'success' => true,
        'type' => $type
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}


