<?php
/**
 * Rule-Based Country Detection Module for MOU Documents
 * 
 * Deterministic, auditable country detection suitable for legal MOU processing.
 * Detects countries from OCR text using multiple signal types with weighted scoring.
 * 
 * Requirements:
 * - PHP 8+
 * - Deterministic rules only (no ML, no guessing)
 * - Requires at least 2 different signal types to confirm
 * - Returns evidence and confidence scores
 */

/**
 * Normalize text for pattern matching
 * 
 * @param string $text Input text
 * @return string Normalized text
 */
function normalizeText(string $text): string {
    $text = trim($text);
    if ($text === '') return '';
    
    // Convert to lowercase
    $text = strtolower($text);
    
    // Normalize whitespace
    $text = preg_replace('/\s+/', ' ', $text);
    
    // Remove excessive punctuation but keep word boundaries
    $text = preg_replace('/[^\w\s\.@\-]/', ' ', $text);
    
    // Normalize whitespace again
    $text = preg_replace('/\s+/', ' ', $text);
    
    return trim($text);
}

/**
 * Get comprehensive country detection rules
 * 
 * Structure:
 * - 'country_names': Direct country name patterns (high weight)
 * - 'legal_phrases': Legal phrases mentioning country (high weight)
 * - 'domain_suffixes': Email/website domain suffixes (medium weight)
 * - 'cities': City names (low weight)
 * - 'states_provinces': State/province names (low weight)
 * 
 * @return array Country rules indexed by country name
 */
function getCountryRules(): array {
    return [
        'Philippines' => [
            'country_names' => [
                '/\bphilippines\b/',  // Matches "philippines" (plural)
                '/\bphilippine\b/',   // Matches "philippine" (singular, e.g., in "CENTRAL PHILIPPINE UNIVERSITY")
                '/\brepublic\s+of\s+the\s+philippines\b/',
                '/\bph\b/',
                '/\bphl\b/',
            ],
            'legal_phrases' => [
                '/\brepublic\s+of\s+the\s+philippines\b/',
                '/\bunder\s+the\s+laws\s+of\s+the\s+philippines\b/',
                '/\bgoverned\s+by\s+the\s+laws\s+of\s+the\s+philippines\b/',
                '/\bphilippine\s+laws?\b/',
            ],
            'domain_suffixes' => [
                '/\.edu\.ph\b/',
                '/\.gov\.ph\b/',
                '/\.ph\b/',
            ],
            'cities' => [
                'manila', 'quezon city', 'davao', 'caloocan', 'cebu', 'zamboanga',
                'antipolo', 'pasig', 'tagig', 'valenzuela', 'paranaque', 'makati',
                'san jose del monte', 'las pinas', 'bacolod', 'iloilo', 'muntinlupa',
                'calamba', 'marikina', 'butuan', 'mandaluyong', 'taguig',
            ],
            'states_provinces' => [
                'metro manila', 'cavite', 'laguna', 'rizal', 'bulacan', 'pampanga',
                'bataan', 'nueva ecija', 'tarlac', 'pangasinan', 'batangas', 'quezon',
                'ncr', 'national capital region',
            ],
        ],
        
        'China' => [
            'country_names' => [
                '/\bchina\b/',
                '/\bchinese\b/',
                '/\bpeople\'?s\s+republic\s+of\s+china\b/',
                '/\bprc\b/',
                '/\bcn\b/',
            ],
            'legal_phrases' => [
                '/\bpeople\'?s\s+republic\s+of\s+china\b/',
                '/\bunder\s+the\s+laws\s+of\s+the\s+people\'?s\s+republic\s+of\s+china\b/',
                '/\bchinese\s+laws?\b/',
            ],
            'domain_suffixes' => [
                '/\.edu\.cn\b/',
                '/\.ac\.cn\b/',
                '/\.cn\b/',
            ],
            'cities' => [
                'beijing', 'shanghai', 'guangzhou', 'shenzhen', 'chengdu', 'hangzhou',
                'wuhan', 'xian', 'nanjing', 'tianjin', 'chongqing', 'dalian',
                'xiamen', 'qingdao', 'fuzhou', 'suzhou', 'ningbo', 'wenzhou',
                'zhangzhou',
            ],
            'states_provinces' => [
                'guangdong', 'jiangsu', 'zhejiang', 'shandong', 'henan', 'sichuan',
                'hubei', 'hunan', 'fujian', 'anhui', 'liaoning', 'hebei',
                'shaanxi', 'jiangxi', 'guangxi', 'yunnan', 'heilongjiang',
            ],
        ],
        
        'Japan' => [
            'country_names' => [
                '/\bjapan\b/',
                '/\bjapanese\b/',
                '/\bjp\b/',
            ],
            'legal_phrases' => [
                '/\bunder\s+the\s+laws\s+of\s+japan\b/',
                '/\bjapanese\s+laws?\b/',
            ],
            'domain_suffixes' => [
                '/\.ac\.jp\b/',
                '/\.edu\.jp\b/',
                '/\.jp\b/',
            ],
            'cities' => [
                'tokyo', 'osaka', 'yokohama', 'nagoya', 'sapporo', 'fukuoka',
                'kobe', 'kyoto', 'saitama', 'hiroshima', 'sendai', 'kawasaki',
                'chiba', 'kitakyushu', 'sakai', 'shizuoka', 'nagata',
            ],
            'states_provinces' => [
                'hokkaido', 'aomori', 'iwate', 'miyagi', 'akita', 'yamagata',
                'fukushima', 'ibaraki', 'tochigi', 'gunma', 'saitama', 'chiba',
                'tokyo', 'kanagawa', 'yamanashi', 'nagano', 'niigata', 'toyama',
            ],
        ],
        
        'United States' => [
            'country_names' => [
                '/\bunited\s+states\b/',
                '/\busa\b/',
                '/\bu\.s\.a\b/',
                '/\bu\.s\b/',
                '/\bus\b/',
                '/\bamerican\b/',
            ],
            'legal_phrases' => [
                '/\bunited\s+states\s+of\s+america\b/',
                '/\bunder\s+the\s+laws\s+of\s+the\s+united\s+states\b/',
                '/\bamerican\s+laws?\b/',
            ],
            'domain_suffixes' => [
                '/\.edu\b/',
                '/\.edu\.us\b/',
                '/\.us\b/',
            ],
            'cities' => [
                'new york', 'los angeles', 'chicago', 'houston', 'phoenix', 'philadelphia',
                'san antonio', 'san diego', 'dallas', 'san jose', 'austin', 'jacksonville',
                'san francisco', 'indianapolis', 'columbus', 'fort worth', 'charlotte',
            ],
            'states_provinces' => [
                'california', 'texas', 'florida', 'new york', 'pennsylvania', 'illinois',
                'ohio', 'georgia', 'north carolina', 'michigan', 'new jersey', 'virginia',
            ],
        ],
        
        'Korea' => [
            'country_names' => [
                '/\bsouth\s+korea\b/',
                '/\bkorea\b/',
                '/\bkorean\b/',
                '/\bkr\b/',
                '/\bkorea\s+republic\b/',
            ],
            'legal_phrases' => [
                '/\brepublic\s+of\s+korea\b/',
                '/\bunder\s+the\s+laws\s+of\s+(the\s+)?republic\s+of\s+korea\b/',
                '/\bkorean\s+laws?\b/',
            ],
            'domain_suffixes' => [
                '/\.ac\.kr\b/',
                '/\.edu\.kr\b/',
                '/\.kr\b/',
            ],
            'cities' => [
                'seoul', 'busan', 'incheon', 'daegu', 'daejeon', 'gwangju',
                'suwon', 'ulsan', 'changwon', 'goyang', 'yongin', 'bucheon',
            ],
            'states_provinces' => [
                'gyeonggi', 'gangwon', 'chungbuk', 'chungnam', 'jeonbuk', 'jeonnam',
                'gyeongbuk', 'gyeongnam', 'jeju',
            ],
        ],
        
        'Canada' => [
            'country_names' => [
                '/\bcanada\b/',
                '/\bcanadian\b/',
                '/\bca\b/',
                '/\bcan\b/',
            ],
            'legal_phrases' => [
                '/\bunder\s+the\s+laws\s+of\s+canada\b/',
                '/\bcanadian\s+laws?\b/',
            ],
            'domain_suffixes' => [
                '/\.ca\b/',
                '/\.edu\.ca\b/',
                '/\.ac\.ca\b/',
            ],
            'cities' => [
                'toronto', 'montreal', 'calgary', 'ottawa', 'edmonton', 'winnipeg',
                'vancouver', 'mississauga', 'brampton', 'hamilton', 'quebec',
            ],
            'states_provinces' => [
                'ontario', 'quebec', 'british columbia', 'alberta', 'manitoba',
                'saskatchewan', 'nova scotia', 'new brunswick', 'newfoundland',
            ],
        ],
        
        'Australia' => [
            'country_names' => [
                '/\baustralia\b/',
                '/\baustralian\b/',
                '/\bau\b/',
                '/\baus\b/',
            ],
            'legal_phrases' => [
                '/\bcommonwealth\s+of\s+australia\b/',
                '/\bunder\s+the\s+laws\s+of\s+australia\b/',
                '/\baustralian\s+laws?\b/',
            ],
            'domain_suffixes' => [
                '/\.edu\.au\b/',
                '/\.au\b/',
            ],
            'cities' => [
                'sydney', 'melbourne', 'brisbane', 'perth', 'adelaide', 'gold coast',
                'newcastle', 'canberra', 'sunshine coast', 'wollongong', 'hobart',
            ],
            'states_provinces' => [
                'new south wales', 'victoria', 'queensland', 'western australia',
                'south australia', 'tasmania', 'australian capital territory',
            ],
        ],
        
        'Singapore' => [
            'country_names' => [
                '/\bsingapore\b/',
                '/\bsingaporean\b/',
                '/\bsg\b/',
                '/\bsgp\b/',
            ],
            'legal_phrases' => [
                '/\brepublic\s+of\s+singapore\b/',
                '/\bunder\s+the\s+laws\s+of\s+singapore\b/',
                '/\bsingaporean\s+laws?\b/',
            ],
            'domain_suffixes' => [
                '/\.edu\.sg\b/',
                '/\.sg\b/',
            ],
            'cities' => [
                'singapore',
            ],
            'states_provinces' => [],
        ],
        
        'Malaysia' => [
            'country_names' => [
                '/\bmalaysia\b/',
                '/\bmalaysian\b/',
                '/\bmy\b/',
                '/\bmys\b/',
            ],
            'legal_phrases' => [
                '/\bunder\s+the\s+laws\s+of\s+malaysia\b/',
                '/\bmalaysian\s+laws?\b/',
            ],
            'domain_suffixes' => [
                '/\.edu\.my\b/',
                '/\.my\b/',
            ],
            'cities' => [
                'kuala lumpur', 'george town', 'ipoh', 'shah alam', 'petaling jaya',
                'johor bahru', 'melaka', 'kuching', 'kota kinabalu', 'seremban',
            ],
            'states_provinces' => [
                'johor', 'kedah', 'kelantan', 'melaka', 'negeri sembilan',
                'pahang', 'penang', 'perak', 'perlis', 'sabah', 'sarawak', 'selangor',
            ],
        ],
        
        'Thailand' => [
            'country_names' => [
                '/\bthailand\b/',
                '/\bthai\b/',
                '/\bth\b/',
                '/\btha\b/',
            ],
            'legal_phrases' => [
                '/\bkingdom\s+of\s+thailand\b/',
                '/\bunder\s+the\s+laws\s+of\s+thailand\b/',
                '/\bthai\s+laws?\b/',
            ],
            'domain_suffixes' => [
                '/\.ac\.th\b/',
                '/\.edu\.th\b/',
                '/\.th\b/',
            ],
            'cities' => [
                'bangkok', 'nonthaburi', 'nakhon ratchasima', 'chiang mai', 'hat yai',
                'udon thani', 'pak kret', 'khon kaen', 'chaophraya surasak',
            ],
            'states_provinces' => [
                'bangkok', 'chiang mai', 'chiang rai', 'phuket', 'pattaya',
                'ayutthaya', 'sukhothai', 'kanchanaburi',
            ],
        ],
        
        'Vietnam' => [
            'country_names' => [
                '/\bvietnam\b/',
                '/\bvietnamese\b/',
                '/\bvn\b/',
                '/\bvnm\b/',
            ],
            'legal_phrases' => [
                '/\bsocialist\s+republic\s+of\s+vietnam\b/',
                '/\bunder\s+the\s+laws\s+of\s+vietnam\b/',
                '/\bvietnamese\s+laws?\b/',
            ],
            'domain_suffixes' => [
                '/\.edu\.vn\b/',
                '/\.vn\b/',
            ],
            'cities' => [
                'ho chi minh city', 'hanoi', 'haiphong', 'can tho', 'da nang',
                'bian hoa', 'hue', 'nha trang', 'vung tau', 'quy nhon',
            ],
            'states_provinces' => [
                'ho chi minh', 'hanoi', 'haiphong', 'can tho', 'da nang',
                'dong nai', 'binh duong', 'long an', 'tien giang',
            ],
        ],
        
        'Indonesia' => [
            'country_names' => [
                '/\bindonesia\b/',
                '/\bindonesian\b/',
                '/\bid\b/',
                '/\bidn\b/',
            ],
            'legal_phrases' => [
                '/\brepublic\s+of\s+indonesia\b/',
                '/\bunder\s+the\s+laws\s+of\s+indonesia\b/',
                '/\bindonesian\s+laws?\b/',
            ],
            'domain_suffixes' => [
                '/\.ac\.id\b/',
                '/\.edu\.id\b/',
                '/\.id\b/',
            ],
            'cities' => [
                'jakarta', 'surabaya', 'bandung', 'medan', 'semarang', 'makassar',
                'palembang', 'tangerang', 'depok', 'bekasi', 'yogyakarta', 'bogor',
            ],
            'states_provinces' => [
                'java', 'sumatra', 'kalimantan', 'sulawesi', 'papua', 'bali',
                'west java', 'east java', 'central java', 'jakarta',
            ],
        ],
        
        'India' => [
            'country_names' => [
                '/\bindia\b/',
                '/\bindian\b/',
                '/\bin\b/',
                '/\bind\b/',
            ],
            'legal_phrases' => [
                '/\brepublic\s+of\s+india\b/',
                '/\bunder\s+the\s+laws\s+of\s+india\b/',
                '/\bindian\s+laws?\b/',
            ],
            'domain_suffixes' => [
                '/\.ac\.in\b/',
                '/\.edu\.in\b/',
                '/\.in\b/',
            ],
            'cities' => [
                'mumbai', 'delhi', 'bangalore', 'hyderabad', 'chennai', 'kolkata',
                'pune', 'ahmedabad', 'surat', 'jaipur', 'lucknow', 'kanpur',
            ],
            'states_provinces' => [
                'maharashtra', 'karnataka', 'tamil nadu', 'delhi', 'gujarat',
                'west bengal', 'rajasthan', 'uttar pradesh', 'andhra pradesh',
            ],
        ],
        
        'Taiwan' => [
            'country_names' => [
                '/\btaiwan\b/',
                '/\btaiwanese\b/',
                '/\broc\b/',
                '/\btw\b/',
                '/\btwn\b/',
            ],
            'legal_phrases' => [
                '/\brepublic\s+of\s+china\s+\(taiwan\)\b/',
                '/\bunder\s+the\s+laws\s+of\s+taiwan\b/',
                '/\btaiwanese\s+laws?\b/',
            ],
            'domain_suffixes' => [
                '/\.edu\.tw\b/',
                '/\.tw\b/',
            ],
            'cities' => [
                'taipei', 'kaohsiung', 'taichung', 'tainan', 'banqiao', 'hsinchu',
                'taoyuan', 'keelung', 'chiayi', 'changhua',
            ],
            'states_provinces' => [
                'taipei', 'new taipei', 'taoyuan', 'taichung', 'tainan', 'kaohsiung',
            ],
        ],
        
        'United Kingdom' => [
            'country_names' => [
                '/\bunited\s+kingdom\b/',
                '/\buk\b/',
                '/\bbritain\b/',
                '/\bbritish\b/',
                '/\bgb\b/',
                '/\bgbr\b/',
            ],
            'legal_phrases' => [
                '/\bunder\s+the\s+laws\s+of\s+(the\s+)?united\s+kingdom\b/',
                '/\bbritish\s+laws?\b/',
            ],
            'domain_suffixes' => [
                '/\.ac\.uk\b/',
                '/\.edu\.uk\b/',
                '/\.uk\b/',
            ],
            'cities' => [
                'london', 'manchester', 'birmingham', 'glasgow', 'liverpool', 'leeds', 'edinburgh',
            ],
            'states_provinces' => [
                'england', 'scotland', 'wales', 'northern ireland',
            ],
        ],
        
        'Germany' => [
            'country_names' => [
                '/\bgermany\b/',
                '/\bgerman\b/',
                '/\bde\b/',
                '/\bdeu\b/',
            ],
            'legal_phrases' => [
                '/\bfederal\s+republic\s+of\s+germany\b/',
                '/\bunder\s+the\s+laws\s+of\s+germany\b/',
                '/\bgerman\s+laws?\b/',
            ],
            'domain_suffixes' => [
                '/\.de\b/',
                '/\.edu\.de\b/',
            ],
            'cities' => [
                'berlin', 'munich', 'hamburg', 'frankfurt', 'cologne', 'stuttgart',
            ],
            'states_provinces' => [
                'bavaria', 'baden-wurttemberg', 'north rhine-westphalia', 'hesse',
            ],
        ],
        
        'France' => [
            'country_names' => [
                '/\bfrance\b/',
                '/\bfrench\b/',
                '/\bfr\b/',
                '/\bfra\b/',
            ],
            'legal_phrases' => [
                '/\brepublic\s+of\s+france\b/',
                '/\bunder\s+the\s+laws\s+of\s+france\b/',
                '/\bfrench\s+laws?\b/',
            ],
            'domain_suffixes' => [
                '/\.fr\b/',
                '/\.edu\.fr\b/',
            ],
            'cities' => [
                'paris', 'marseille', 'lyon', 'toulouse', 'nice', 'nantes',
            ],
            'states_provinces' => [
                'ile-de-france', 'provence-alpes-cote d\'azur', 'auvergne-rhone-alpes',
            ],
        ],
        
        'New Zealand' => [
            'country_names' => [
                '/\bnew\s+zealand\b/',
                '/\bnz\b/',
                '/\bnzl\b/',
            ],
            'legal_phrases' => [
                '/\bunder\s+the\s+laws\s+of\s+new\s+zealand\b/',
                '/\bnew\s+zealand\s+laws?\b/',
            ],
            'domain_suffixes' => [
                '/\.ac\.nz\b/',
                '/\.edu\.nz\b/',
                '/\.nz\b/',
            ],
            'cities' => [
                'auckland', 'wellington', 'christchurch', 'hamilton', 'dunedin',
            ],
            'states_provinces' => [
                'auckland', 'wellington', 'canterbury', 'waikato',
            ],
        ],
        
        'Hong Kong' => [
            'country_names' => [
                '/\bhong\s+kong\b/',
                '/\bhk\b/',
                '/\bhkg\b/',
            ],
            'legal_phrases' => [
                '/\bunder\s+the\s+laws\s+of\s+hong\s+kong\b/',
                '/\bhong\s+kong\s+laws?\b/',
            ],
            'domain_suffixes' => [
                '/\.edu\.hk\b/',
                '/\.hk\b/',
            ],
            'cities' => [
                'hong kong', 'kowloon', 'new territories',
            ],
            'states_provinces' => [],
        ],
        
        'South Africa' => [
            'country_names' => [
                '/\bsouth\s+africa\b/',
                '/\bza\b/',
                '/\bzaf\b/',
            ],
            'legal_phrases' => [
                '/\brepublic\s+of\s+south\s+africa\b/',
                '/\bunder\s+the\s+laws\s+of\s+south\s+africa\b/',
            ],
            'domain_suffixes' => [
                '/\.ac\.za\b/',
                '/\.edu\.za\b/',
                '/\.za\b/',
            ],
            'cities' => [
                'johannesburg', 'cape town', 'durban', 'pretoria', 'port elizabeth',
            ],
            'states_provinces' => [
                'gauteng', 'western cape', 'kwazulu-natal', 'eastern cape',
            ],
        ],
    ];
}

/**
 * Extract email addresses and website URLs from text
 * 
 * @param string $text Input text
 * @return array Array of email addresses and URLs
 */
function extractDomains(string $text): array {
    $domains = [];
    
    // Extract email addresses
    preg_match_all('/\b[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}\b/', $text, $emails);
    $domains = array_merge($domains, $emails[0] ?? []);
    
    // Extract URLs (http/https/www)
    preg_match_all('/\b(?:https?:\/\/|www\.)[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}(?:\/[^\s]*)?\b/', $text, $urls);
    $domains = array_merge($domains, $urls[0] ?? []);
    
    return array_unique($domains);
}

/**
 * Detect country from text using rule-based scoring
 * 
 * Scoring weights:
 * - Country names or legal phrases: 3 points (high weight)
 * - Domain suffixes: 2 points (medium weight)
 * - Cities or states/provinces: 1 point (low weight)
 * 
 * Requires at least 2 different signal types to confirm.
 * 
 * @param string $text OCR text to analyze
 * @param string|null $institutionName Optional institution name to use as additional signal
 * @return array Result with country, confidence, and evidence
 */
function detectCountry(string $text, ?string $institutionName = null): array {
    $text = normalizeText($text);
    if ($text === '') {
        return [
            'country' => 'Country not reliable',
            'confidence' => 0,
            'evidence' => [],
        ];
    }
    
    // Combine text with institution name if provided (institution names are strong signals)
    $combinedText = $text;
    if ($institutionName !== null && $institutionName !== '') {
        $normalizedInstitution = normalizeText($institutionName);
        $combinedText = $normalizedInstitution . ' ' . $text;
    }
    
    $countryRules = getCountryRules();
    $countryScores = [];
    $countryEvidence = [];
    $countrySignalTypes = [];
    
    // Extract domains for separate analysis
    $domains = extractDomains($combinedText);
    $domainText = implode(' ', $domains);
    
    foreach ($countryRules as $country => $rules) {
        $score = 0;
        $evidence = [];
        $signalTypes = [];
        
        // Check country names (high weight: 3 points)
        // Check in combined text (includes institution name if provided)
        if (isset($rules['country_names'])) {
            foreach ($rules['country_names'] as $pattern) {
                if (preg_match($pattern, $combinedText)) {
                    $score += 3;
                    // Check if match came from institution name (stronger signal)
                    if ($institutionName !== null && preg_match($pattern, normalizeText($institutionName))) {
                        $evidence[] = 'institution_name';
                    } else {
                        $evidence[] = 'country_name';
                    }
                    $signalTypes['country_name'] = true;
                    break; // Only count once per type
                }
            }
        }
        
        // Check legal phrases (high weight: 3 points)
        if (isset($rules['legal_phrases'])) {
            foreach ($rules['legal_phrases'] as $pattern) {
                if (preg_match($pattern, $combinedText)) {
                    $score += 3;
                    $evidence[] = 'legal_phrase';
                    $signalTypes['legal_phrase'] = true;
                    break; // Only count once per type
                }
            }
        }
        
        // Check domain suffixes (medium weight: 2 points)
        if (isset($rules['domain_suffixes'])) {
            foreach ($rules['domain_suffixes'] as $pattern) {
                // Check in both main text and extracted domains
                if (preg_match($pattern, $combinedText) || preg_match($pattern, $domainText)) {
                    $score += 2;
                    // Extract the actual domain found
                    $matches = [];
                    if (preg_match($pattern, $combinedText, $matches)) {
                        $evidence[] = $matches[0] ?? 'domain_suffix';
                    } elseif (preg_match($pattern, $domainText, $matches)) {
                        $evidence[] = $matches[0] ?? 'domain_suffix';
                    } else {
                        $evidence[] = 'domain_suffix';
                    }
                    $signalTypes['domain_suffix'] = true;
                    break; // Only count once per type
                }
            }
        }
        
        // Check cities (low weight: 1 point)
        if (isset($rules['cities'])) {
            foreach ($rules['cities'] as $city) {
                $cityPattern = '/\b' . preg_quote($city, '/') . '\b/';
                if (preg_match($cityPattern, $combinedText)) {
                    $score += 1;
                    $evidence[] = $city;
                    $signalTypes['city'] = true;
                    break; // Only count once per type
                }
            }
        }
        
        // Check states/provinces (low weight: 1 point)
        if (isset($rules['states_provinces'])) {
            foreach ($rules['states_provinces'] as $state) {
                $statePattern = '/\b' . preg_quote($state, '/') . '\b/';
                if (preg_match($statePattern, $combinedText)) {
                    $score += 1;
                    $evidence[] = $state;
                    $signalTypes['state_province'] = true;
                    break; // Only count once per type
                }
            }
        }
        
        // Store results if score > 0
        if ($score > 0) {
            $countryScores[$country] = $score;
            $countryEvidence[$country] = array_unique($evidence);
            $countrySignalTypes[$country] = $signalTypes;
        }
    }
    
    // Find the country with the highest score
    if (empty($countryScores)) {
        return [
            'country' => 'Country not reliable',
            'confidence' => 0,
            'evidence' => [],
        ];
    }
    
    arsort($countryScores);
    $topCountry = array_key_first($countryScores);
    $topScore = $countryScores[$topCountry];
    $topEvidence = $countryEvidence[$topCountry];
    $topSignalTypes = $countrySignalTypes[$topCountry];
    
    // Special case: If institution name was provided and contains a country name signal,
    // trust it even with just 1 signal type (institution names are strong indicators)
    $hasInstitutionSignal = false;
    if ($institutionName !== null && $institutionName !== '') {
        $normalizedInstitution = normalizeText($institutionName);
        foreach ($countryRules[$topCountry]['country_names'] ?? [] as $pattern) {
            if (preg_match($pattern, $normalizedInstitution)) {
                $hasInstitutionSignal = true;
                break;
            }
        }
    }
    
    // Require at least 2 different signal types, UNLESS we have a strong institution name signal
    $signalTypeCount = count($topSignalTypes);
    
    if ($signalTypeCount < 2 && !$hasInstitutionSignal) {
        return [
            'country' => 'Country not reliable',
            'confidence' => $topScore,
            'evidence' => $topEvidence,
        ];
    }
    
    return [
        'country' => $topCountry,
        'confidence' => $topScore,
        'evidence' => $topEvidence,
    ];
}

/**
 * Wrapper function for compatibility with existing code
 * Returns just the country name string (or empty string if not reliable)
 * 
 * @param string $text OCR text to analyze
 * @param string|null $institutionName Optional institution name
 * @return string Country name or empty string
 */
function detectCountryString(string $text, ?string $institutionName = null): string {
    $result = detectCountry($text, $institutionName);
    if ($result['country'] === 'Country not reliable') {
        return '';
    }
    return $result['country'];
}

