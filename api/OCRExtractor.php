<?php
/**
 * OCRExtractor – Anchor/stopper rule-based extraction for institution and country.
 * Use with raw Tesseract (or other OCR) text.
 */

class OCRExtractor {

    /** Words that signal an institution. */
    private $anchors = [
        'University', 'Institute', 'College', 'School', 'Academy',
        'Department', 'Center', 'Centre', 'Hospital', 'Ministry'
    ];

    /** Words that definitely STOP a name. */
    private $stoppers = [
        // 'between'/'among' helps stop reading before the actual name starts
        'for', 'to', 'from', 'at', 'by', 'in', 'near', 'with', 'between', 'among',
        'est', 'since', 'founded', 'established', 'about', 'contact',
        'published', 'prepared', 'signed', 'dated', 'fax', 'tel', 'email',
        'website', 'herein', 'hereinafter'
    ];

    /** Words allowed inside a name even if lowercase. */
    private $allowedConnectors = ['of', 'the', 'and', '&', 'de', 'la', 'del'];

    private $excluded = [
        'Central Philippine University',
        'CPU',
        'Central Philippine Univ',
    ];

    public function extract($text) {
        $cleanText = preg_replace('/\s+/', ' ', (string)$text);
        $cleanText = trim($cleanText);
        return [
            'institution' => $this->findInstitution($cleanText),
            'country'     => $this->findCountry($cleanText),
        ];
    }

    private function findInstitution($text) {
        if (trim((string)$text) === '') return null;

        // Build regex for anchors
        $anchorPattern = implode('|', array_map(function ($a) { return preg_quote($a, '/'); }, $this->anchors));

        // Find all anchors with their offsets
        preg_match_all('/\b(' . $anchorPattern . ')\b/i', $text, $matches, PREG_OFFSET_CAPTURE);

        if (empty($matches[0])) return null;

        foreach ($matches[0] as $match) {
            $anchorWord = $match[0];
            $anchorPos  = $match[1];

            // --- STRATEGY 1: Look Backward ---
            $precedingText = substr($text, 0, $anchorPos);
            $words = explode(' ', trim($precedingText));
            $collectedName = [];

            for ($i = count($words) - 1; $i >= 0; $i--) {
                $rawWord = $words[$i];

                // Clean trailing punctuation/junk for the check (keeps leading characters)
                $cleanWord = preg_replace('/[^\w]+$/u', '', (string)$rawWord);

                if ($cleanWord === '') break;
                if (in_array(strtolower($cleanWord), $this->stoppers)) break;
                if (is_numeric($cleanWord)) break;

                if (!ctype_upper($cleanWord[0])) {
                    if (empty($collectedName) || !in_array(strtolower($cleanWord), $this->allowedConnectors)) {
                        break;
                    }
                }

                // Add the CLEANED word (without brackets) to the name
                array_unshift($collectedName, $cleanWord);
            }

            // --- STRATEGY 2: Look Forward (The "Of" Clause) ---
            $suffix = '';
            if (preg_match('/^(University|Institute|College|Ministry|Department|School)/i', $anchorWord)) {
                $restOfText = substr($text, $anchorPos + strlen($anchorWord));

                // Matches " of " or " of the " then captures the next Proper-Name token
                if (preg_match('/^\s+(of(?:\sthe)?)\s+([A-Z0-9][\w\.\'\&\-]*)/u', $restOfText, $forwardMatch)) {
                    $suffix = $forwardMatch[0]; // e.g. " of the Philippines"
                }
            }

            // Combine parts
            $fullName = implode(' ', $collectedName) . ' ' . $anchorWord . $suffix;
            $fullName = $this->cleanName($fullName);

            // Validation: Must be at least 2 words (unless it's a known long name)
            if (str_word_count($fullName) < 2) continue;

            if ($this->isExcluded($fullName)) continue;

            return $fullName; // Return first valid match
        }

        return null;
    }

    private function cleanName($name) {
        $name = trim((string)$name);
        // NUCLEAR CLEANER: remove any non-alphanumeric (and _) from the end only
        return preg_replace('/[\W_]+$/u', '', $name);
    }

    private function isExcluded($name) {
        foreach ($this->excluded as $ex) {
            if (stripos($name, $ex) !== false) return true;
        }
        return false;
    }

    private function findCountry($text) {
        $countries = ['Philippines', 'United States', 'USA', 'UK', 'Japan', 'China', 'Singapore', 'Korea', 'Thailand', 'Vietnam'];
        foreach ($countries as $c) {
            if (preg_match('/\b' . preg_quote($c, '/') . '\b/i', $text)) {
                return $c;
            }
        }
        return null;
    }
}
