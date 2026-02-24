<?php
/**
 * PartnerExtractor – Pre-defined partner alias matching for OCR text.
 * Matches known partner institutions by their aliases (full name, abbreviations).
 */

class PartnerExtractor {

    /**
     * RAW CONFIGURATION
     * The human-readable list. We process this ONCE in the constructor.
     */
    private $rawPartners = [
        'Tra Vinh University' => [
            'Tra Vinh University', 
            'Tra Vinh Univ', 
            'TVU', 
            'Tra Vinh'
        ],
        'Nagoya Gakuin University' => [
            'Nagoya Gakuin University', 
            'Nagoya Gakuin', 
            'NGU'
        ],
        'Universitas Kristen Indonesia' => [
            'Universitas Kristen Indonesia', 
            'UKI'
        ],
        // Add more partners here...
    ];

    /**
     * OPTIMIZED LOOKUP TABLE
     * Populated by __construct(). Contains normalized, sorted aliases.
     */
    private $optimizedPartners = [];

    public function __construct() {
        // 1. PRE-COMPUTE: Optimize the list once on startup.
        foreach ($this->rawPartners as $canonical => $aliases) {
            
            // A. Normalize all aliases immediately
            $cleanAliases = array_map([$this, 'normalize'], $aliases);

            // B. Sort by Length (DESC) to ensure "Tra Vinh University" matches before "TVU"
            usort($cleanAliases, function($a, $b) {
                return strlen($b) <=> strlen($a);
            });

            // Store the optimized list
            $this->optimizedPartners[$canonical] = $cleanAliases;
        }
    }

    public function extract($text) {
        // 2. NORMALIZE INPUT: Strip junk/punctuation from OCR text
        $cleanInput = $this->normalize($text);

        foreach ($this->optimizedPartners as $canonical => $aliases) {
            foreach ($aliases as $alias) {
                
                // 3. SHORT ALIAS PROTECTION (The "Safe Mode")
                // If alias is short (e.g. "tvu"), use STRICT word boundaries.
                // This prevents "tvu" from matching inside "smartvue" or random noise.
                if (strlen($alias) <= 3) {
                    // Regex: \b means "Word Boundary" (start or end of a word)
                    if (preg_match('/\b' . preg_quote($alias, '/') . '\b/', $cleanInput)) {
                         return $this->buildResult($canonical, $alias, 'strict_boundary');
                    }
                } 
                // 4. LONG ALIAS: Standard fast substring check
                else {
                    if (strpos($cleanInput, $alias) !== false) {
                        return $this->buildResult($canonical, $alias, 'substring');
                    }
                }
            }
        }

        return [
            'institution' => null,
            'confidence'  => 'none',
            'method'      => 'failed'
        ];
    }

    private function buildResult($canonical, $match, $method) {
        return [
            'institution' => $canonical, // Always the clean DB name
            'matched_raw' => $match,     // The specific alias that triggered it
            'confidence'  => 'high',
            'method'      => $method     // 'substring' or 'strict_boundary'
        ];
    }

    /**
     * Helper: Lowercase + Keep only a-z, 0-9, and space.
     * Replaces multiple spaces with a single space.
     */
    private function normalize($str) {
        $str = strtolower((string)$str);
        // Replace non-alphanumeric chars with space
        $str = preg_replace('/[^a-z0-9 ]/', ' ', $str); 
        // Collapse multiple spaces
        $str = preg_replace('/\s+/', ' ', $str);
        return trim($str);
    }
}
