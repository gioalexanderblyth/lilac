<?php
/**
 * Check Certificate Eligibility Against CHED Award Criteria
 * Automatically determines if a certificate is eligible for specific CHED awards
 * based on eligibility criteria matching
 */

require_once __DIR__ . '/config.php';

/**
 * Check if certificate text matches eligibility criteria for a specific award
 * 
 * @param string $text The certificate text to analyze
 * @param string $awardId The award ID (e.g., 'global-citizenship')
 * @return array Eligibility result with matched criteria and overall eligibility
 */
function checkAwardEligibility($text, $awardId) {
    // Load award requirements from awards-hub.php configuration
    $awardRequirements = getAwardRequirements($awardId);
    
    if (!$awardRequirements) {
        return [
            'eligible' => false,
            'matched_criteria' => [],
            'total_criteria' => 0,
            'match_percentage' => 0
        ];
    }
    
    $textLower = strtolower($text);
    $matchedCriteria = [];
    $totalCriteria = count($awardRequirements['eligibilityCriteria']);
    
    // Check each eligibility criterion
    foreach ($awardRequirements['eligibilityCriteria'] as $criteria) {
        $criteriaId = $criteria['id'];
        $criteriaDescription = strtolower($criteria['description']);
        $criteriaLabel = strtolower($criteria['label']);
        
        // Get keywords for this specific criterion from awards-rules.json
        $criterionKeywords = getCriterionKeywords($awardId, $criteriaId);
        
        // Check if text matches this criterion
        $isMatched = checkCriterionMatch($textLower, $criteriaDescription, $criteriaLabel, $criterionKeywords);
        
        if ($isMatched) {
            $matchedCriteria[] = [
                'id' => $criteriaId,
                'label' => $criteria['label'],
                'description' => $criteria['description']
            ];
        }
    }
    
    // Calculate match percentage
    $matchPercentage = $totalCriteria > 0 ? (count($matchedCriteria) / $totalCriteria) * 100 : 0;
    
    // Certificate is eligible if ALL criteria are matched (100% match)
    $isEligible = count($matchedCriteria) === $totalCriteria && $totalCriteria > 0;
    
    return [
        'eligible' => $isEligible,
        'matched_criteria' => $matchedCriteria,
        'total_criteria' => $totalCriteria,
        'match_percentage' => round($matchPercentage, 2),
        'award_id' => $awardId,
        'award_title' => $awardRequirements['title'] ?? ''
    ];
}

/**
 * Check if text matches a specific criterion
 */
function checkCriterionMatch($textLower, $description, $label, $keywords) {
    // Extract key concepts from description and label
    $keyConcepts = [];
    
    // Extract important phrases from description
    $descriptionPhrases = [
        'intercultural understanding', 'inclusive learning', 'mutual respect', 'cultures', 'backgrounds', 'abilities',
        'students gain', 'knowledge', 'skills', 'tackle challenges', 'SDG', 'SDGs', 'sustainable development',
        'accessible platforms', 'students', 'translate', 'global awareness', 'concrete action', 'engagement',
        'community engagement', 'global opportunities', 'diverse backgrounds', 'financial situations',
        'collaborative innovation', 'partnerships', 'culturally-rich', 'barriers', 'inclusivity'
    ];
    
    // Check for key phrases first (more reliable)
    $phraseMatches = 0;
    foreach ($descriptionPhrases as $phrase) {
        if (strpos($textLower, $phrase) !== false) {
            $phraseMatches++;
        }
    }
    
    // If we found key phrases, that's a strong indicator
    if ($phraseMatches >= 2) {
        return true;
    }
    
    // Also check keywords from awards-rules.json
    if (!empty($keywords)) {
        $keywordMatches = 0;
        foreach ($keywords as $keyword => $weight) {
            if (strpos($textLower, $keyword) !== false) {
                $keywordMatches += $weight;
            }
        }
        // If weighted keyword matches exceed threshold, consider it matched
        if ($keywordMatches >= 2.0) {
            return true;
        }
    }
    
    // Combine all search terms
    $searchTerms = array_merge(
        explode(' ', $description),
        explode(' ', $label),
        array_keys($keywords ?? [])
    );
    
    // Remove common words and short words
    $stopWords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'from', 'is', 'are', 'was', 'were', 'be', 'been', 'being', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'should', 'could', 'may', 'might', 'must', 'can'];
    $searchTerms = array_filter($searchTerms, function($term) use ($stopWords) {
        $term = trim(strtolower($term));
        return strlen($term) > 3 && !in_array($term, $stopWords);
    });
    
    // Count how many relevant terms are found
    $foundTerms = 0;
    $totalTerms = count($searchTerms);
    
    if ($totalTerms === 0) {
        return false;
    }
    
    foreach ($searchTerms as $term) {
        if (strpos($textLower, $term) !== false) {
            $foundTerms++;
        }
    }
    
    // Require at least 30% of terms to match (lowered threshold for better matching)
    $matchThreshold = 0.3;
    return ($foundTerms / $totalTerms) >= $matchThreshold;
}

/**
 * Get keywords for a specific criterion from awards-rules.json
 */
function getCriterionKeywords($awardId, $criteriaId) {
    $rulesFile = __DIR__ . '/../data/criteria/awards-rules.json';
    
    if (!file_exists($rulesFile)) {
        return [];
    }
    
    $rules = json_decode(file_get_contents($rulesFile), true);
    
    // Map award IDs to rule keys
    $awardKeyMap = [
        'global-citizenship' => 'gca',
        'international-education' => 'oiep',
        'sustainability' => 'sustainability',
        'emerging-leadership' => 'ela',
        'internationalization-leadership' => 'il',
        'best-regional-office' => 'bro'
    ];
    
    $ruleKey = $awardKeyMap[$awardId] ?? null;
    
    if (!$ruleKey || !isset($rules['categories'][$ruleKey]['criteria'][$criteriaId])) {
        return [];
    }
    
    return $rules['categories'][$ruleKey]['criteria'][$criteriaId]['keywords'] ?? [];
}

/**
 * Get award requirements configuration
 */
function getAwardRequirements($awardId) {
    // Map award IDs to their requirements
    $requirements = [
        'global-citizenship' => [
            'title' => 'Global Citizenship Award',
            'eligibilityCriteria' => [
                [
                    'id' => 'intercultural',
                    'label' => 'Ignite Intercultural Understanding',
                    'description' => 'Creates inclusive learning experiences fostering mutual respect across cultures, backgrounds, and abilities'
                ],
                [
                    'id' => 'changemakers',
                    'label' => 'Empower Changemakers',
                    'description' => 'Students gain knowledge/skills to tackle challenges aligned with the SDGs'
                ],
                [
                    'id' => 'engagement',
                    'label' => 'Cultivate Active Engagement',
                    'description' => 'Provides accessible platforms for students to translate global awareness into concrete action'
                ]
            ]
        ],
        'international-education' => [
            'title' => 'Outstanding International Education Program Award',
            'eligibilityCriteria' => [
                [
                    'id' => 'access',
                    'label' => 'Expand Access to Global Opportunities',
                    'description' => 'Break down barriers to include students from various backgrounds, abilities, and financial situations'
                ],
                [
                    'id' => 'innovation',
                    'label' => 'Foster Collaborative Innovation',
                    'description' => 'Partnerships with local and international partners fuel innovative, culturally-rich experiences'
                ],
                [
                    'id' => 'inclusivity',
                    'label' => 'Embrace Inclusivity and Beyond',
                    'description' => 'Actively dismantle barriers so international education benefits everyone'
                ]
            ]
        ]
        // Add more awards as needed
    ];
    
    return $requirements[$awardId] ?? null;
}

/**
 * Check certificate against all CHED awards and return eligible awards
 */
function checkAllAwardEligibility($text) {
    $eligibleAwards = [];
    
    // List of all CHED award IDs to check
    $awardIds = [
        'global-citizenship',
        'international-education',
        'sustainability',
        'emerging-leadership',
        'internationalization-leadership'
    ];
    
    foreach ($awardIds as $awardId) {
        $result = checkAwardEligibility($text, $awardId);
        
        if ($result['eligible']) {
            $eligibleAwards[] = $result;
        }
    }
    
    return $eligibleAwards;
}

