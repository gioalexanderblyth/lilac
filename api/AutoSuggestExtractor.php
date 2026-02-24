<?php
/**
 * AutoSuggestExtractor – DB-backed institution extraction with learning.
 *
 * Flow:
 * 1. Dictionary Match: approved partners from DB (canonical + aliases)
 * 2. If failed → Auto-Detect: look for University-like strings
 * 3. Save/Update Suggestion: upsert into partner_suggestions for admin review
 *
 * Requires MySQL (partners, partner_suggestions tables).
 */

class AutoSuggestExtractor {

    private $pdo;
    private $optimizedPartners = [];

    /** Keywords that trigger a suggestion (institution-like names). */
    private $suggestionKeywords = [
        'UNIVERSITY', 'COLLEGE', 'INSTITUTE', 'POLYTECHNIC',
        'ACADEMY', 'SCHOOL', 'HOSPITAL'
    ];

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->loadPartnersFromDB();
    }

    /**
     * Loads APPROVED partners from DB into memory.
     */
    private function loadPartnersFromDB() {
        try {
            $stmt = $this->pdo->query("SELECT canonical_name, aliases FROM partners WHERE status = 'active'");
            if (!$stmt) return;
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return;
        }

        foreach ($rows as $row) {
            $aliases = json_decode($row['aliases'] ?? '[]', true);
            if (!is_array($aliases)) $aliases = [];
            // Ensure canonical name is in aliases for matching
            if (!in_array($row['canonical_name'], $aliases)) {
                array_unshift($aliases, $row['canonical_name']);
            }
            // Sort longest first (match "Tra Vinh University" before "TVU")
            usort($aliases, fn($a, $b) => strlen($b) <=> strlen($a));
            $this->optimizedPartners[$row['canonical_name']] = $aliases;
        }
    }

    public function extract($text) {
        $cleanInput = $this->normalize($text);

        // ---------------------------------------------------------
        // PHASE 1: DICTIONARY MATCH (approved partners from DB)
        // ---------------------------------------------------------
        foreach ($this->optimizedPartners as $canonical => $aliases) {
            foreach ($aliases as $alias) {
                $cleanAlias = $this->normalize($alias);

                if (strlen($cleanAlias) <= 3) {
                    if (preg_match('/\b' . preg_quote($cleanAlias, '/') . '\b/', $cleanInput)) {
                        return $this->successResult($canonical, $alias, 'db_match');
                    }
                } else {
                    if (strpos($cleanInput, $cleanAlias) !== false) {
                        return $this->successResult($canonical, $alias, 'db_match');
                    }
                }
            }
        }

        // ---------------------------------------------------------
        // PHASE 2: AUTO-DETECT (discover new institution-like strings)
        // ---------------------------------------------------------
        $candidate = $this->detectCandidate($text);

        if ($candidate) {
            $this->saveSuggestion($candidate, $text);

            return [
                'institution' => null,
                'suggestion'  => $candidate,
                'status'      => 'pending_approval'
            ];
        }

        return ['institution' => null, 'status' => 'not_found'];
    }

    /**
     * Heuristic: look for UPPERCASE lines containing institution keywords.
     */
    private function detectCandidate($text) {
        preg_match_all('/\b[A-Z0-9\&\.\-\']{4,}(?:\s+[A-Z0-9\&\.\-\']+){1,}\b/u', $text, $matches);

        foreach ($matches[0] ?? [] as $line) {
            $pattern = implode('|', $this->suggestionKeywords);
            if (!preg_match("/($pattern)/i", $line)) {
                continue;
            }

            $cleanName = $this->cleanCandidateName($line);

            if (strlen($cleanName) > 10) {
                return $cleanName;
            }
        }
        return null;
    }

    /**
     * Upsert: insert new suggestion or increment occurrences.
     */
    private function saveSuggestion($name, $context) {
        try {
            $sql = "INSERT INTO partner_suggestions (detected_name, last_seen_text, occurrences)
                    VALUES (:name, :text, 1)
                    ON DUPLICATE KEY UPDATE
                        occurrences = occurrences + 1,
                        last_seen_text = :text2";
            $stmt = $this->pdo->prepare($sql);
            $snippet = substr((string)$context, 0, 500);
            $stmt->execute([
                ':name' => $name,
                ':text' => $snippet,
                ':text2' => $snippet
            ]);
        } catch (PDOException $e) {
            // Log but don't fail extraction
        }
    }

    private function cleanCandidateName($raw) {
        $remove = ['MEMORANDUM', 'UNDERSTANDING', 'BETWEEN', 'AMONG', 'AGREEMENT', 'OF', 'THE', 'AND'];

        $words = explode(' ', $raw);
        $filtered = array_filter($words, function ($w) use ($remove) {
            return !in_array(strtoupper(trim($w)), $remove);
        });

        $name = implode(' ', $filtered);
        return preg_replace('/[\W_]+$/u', '', trim($name));
    }

    private function normalize($str) {
        $str = strtolower((string)$str);
        $str = preg_replace('/[^a-z0-9 ]/', ' ', $str);
        return trim(preg_replace('/\s+/', ' ', $str));
    }

    private function successResult($canonical, $match, $method) {
        return [
            'institution' => $canonical,
            'matched_raw' => $match,
            'confidence'  => 'high',
            'method'      => $method
        ];
    }
}
