<?php
require_once __DIR__ . '/api/config.php';

echo "Seeding enhanced award data with multiple users...\n\n";

try {
    $pdo = getDatabaseConnection();

    if ($pdo instanceof FileBasedDatabase) {
        die("Error: MySQL database connection required\n");
    }

    // First, clear existing sample data
    echo "Clearing existing sample data...\n";
    $pdo->exec("DELETE FROM award_analysis WHERE award_id IN (SELECT id FROM awards WHERE user_id >= 2)");
    $pdo->exec("DELETE FROM awards WHERE user_id >= 2");
    $pdo->exec("DELETE FROM mou_moa WHERE user_id >= 2");
    echo "✓ Cleared old data\n\n";

    // Add sample users
    echo "Adding sample users...\n";
    $sampleUsers = [
        ['username' => 'user1', 'email' => 'user1@lilac.edu', 'password' => password_hash('user123', PASSWORD_BCRYPT), 'role' => 'user', 'name' => 'John Smith'],
        ['username' => 'user2', 'email' => 'user2@lilac.edu', 'password' => password_hash('user123', PASSWORD_BCRYPT), 'role' => 'user', 'name' => 'Maria Garcia'],
        ['username' => 'user3', 'email' => 'user3@lilac.edu', 'password' => password_hash('user123', PASSWORD_BCRYPT), 'role' => 'user', 'name' => 'David Chen'],
        ['username' => 'user4', 'email' => 'user4@lilac.edu', 'password' => password_hash('user123', PASSWORD_BCRYPT), 'role' => 'user', 'name' => 'Sarah Johnson'],
    ];

    foreach ($sampleUsers as $user) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO users (username, email, password, role, created_at)
                VALUES (?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE email = VALUES(email)
            ");
            $stmt->execute([$user['username'], $user['email'], $user['password'], $user['role']]);
            echo "✓ Added user: {$user['username']} ({$user['name']})\n";
        } catch (Exception $e) {
            echo "⚠ User {$user['username']} might already exist\n";
        }
    }

    // Get user IDs
    $userIds = [];
    foreach ($sampleUsers as $user) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$user['username']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $userIds[$user['username']] = $result['id'];
        }
    }

    echo "\n" . count($userIds) . " users ready\n\n";

    // Enhanced award submissions with detailed analysis
    $enhancedAwards = [
        // Global Citizenship Award - Multiple submissions
        [
            'user' => 'user1',
            'title' => 'Global Outreach Program 2024',
            'description' => 'Comprehensive international student exchange program with partner universities across 15 countries in Asia, Europe, and Americas. Includes cultural awareness workshops, language training, and community service projects.',
            'category' => 'Global Citizenship Award',
            'status' => 'Eligible',
            'match_percentage' => 92,
            'similarity_score' => 0.92,
            'matched_criteria' => ['International engagement', 'Cultural awareness', 'Community service', 'Language programs', 'Student exchange'],
            'unmatched_criteria' => ['Documentation of impact metrics'],
            'keywords_found' => ['global', 'international', 'exchange', 'cultural', 'community', 'awareness', 'partnership']
        ],
        [
            'user' => 'user2',
            'title' => 'Cross-Cultural Leadership Initiative',
            'description' => 'Year-long program bringing together students from 10 ASEAN countries for collaborative projects focused on sustainable development and cultural preservation.',
            'category' => 'Global Citizenship Award',
            'status' => 'Eligible',
            'match_percentage' => 88,
            'similarity_score' => 0.88,
            'matched_criteria' => ['International engagement', 'Cultural awareness', 'Leadership development', 'Collaborative projects'],
            'unmatched_criteria' => ['Community service documentation', 'Impact assessment'],
            'keywords_found' => ['cultural', 'leadership', 'international', 'collaboration', 'sustainable']
        ],
        [
            'user' => 'user3',
            'title' => 'International Student Mentorship Program',
            'description' => 'Peer mentorship connecting local and international students to foster cultural understanding and academic success.',
            'category' => 'Global Citizenship Award',
            'status' => 'Almost Eligible',
            'match_percentage' => 72,
            'similarity_score' => 0.72,
            'matched_criteria' => ['Cultural awareness', 'Student support', 'Peer engagement'],
            'unmatched_criteria' => ['International partnerships', 'Community impact', 'Documentation'],
            'keywords_found' => ['international', 'cultural', 'student', 'mentorship']
        ],

        // Sustainability Award
        [
            'user' => 'user1',
            'title' => 'Green Campus Initiative 2024',
            'description' => 'Comprehensive sustainability program including solar panel installation, waste segregation systems, rainwater harvesting, and campus-wide environmental education campaigns.',
            'category' => 'Sustainability Award',
            'status' => 'Eligible',
            'match_percentage' => 95,
            'similarity_score' => 0.95,
            'matched_criteria' => ['Environmental programs', 'Waste management', 'Energy efficiency', 'Water conservation', 'Education campaigns'],
            'unmatched_criteria' => [],
            'keywords_found' => ['sustainability', 'environmental', 'green', 'waste', 'recycling', 'energy', 'conservation']
        ],
        [
            'user' => 'user4',
            'title' => 'Zero Waste Campus Project',
            'description' => 'Initiative to eliminate single-use plastics, implement composting programs, and achieve 90% waste diversion rate through recycling and upcycling programs.',
            'category' => 'Sustainability Award',
            'status' => 'Eligible',
            'match_percentage' => 90,
            'similarity_score' => 0.90,
            'matched_criteria' => ['Waste management', 'Recycling programs', 'Environmental awareness', 'Campus engagement'],
            'unmatched_criteria' => ['Energy efficiency metrics'],
            'keywords_found' => ['waste', 'recycling', 'sustainability', 'environmental', 'campus']
        ],

        // Outstanding International Education Program Award
        [
            'user' => 'user2',
            'title' => 'International Education Excellence Program',
            'description' => 'Comprehensive international education framework with dual-degree programs, faculty exchange, joint research initiatives, and international curriculum development across 20 partner universities.',
            'category' => 'Outstanding International Education Program Award',
            'status' => 'Eligible',
            'match_percentage' => 93,
            'similarity_score' => 0.93,
            'matched_criteria' => ['International curriculum', 'Faculty exchange', 'Research collaboration', 'Dual-degree programs', 'Partner universities'],
            'unmatched_criteria' => ['Student satisfaction survey'],
            'keywords_found' => ['international', 'education', 'program', 'exchange', 'research', 'curriculum']
        ],
        [
            'user' => 'user3',
            'title' => 'Global Learning Experience Program',
            'description' => 'Study abroad program offering immersive international experiences with academic credit in 15 countries, cultural workshops, and global competency certifications.',
            'category' => 'Outstanding International Education Program Award',
            'status' => 'Almost Eligible',
            'match_percentage' => 78,
            'similarity_score' => 0.78,
            'matched_criteria' => ['Study abroad', 'Cultural immersion', 'Academic credit', 'Global competency'],
            'unmatched_criteria' => ['Faculty collaboration', 'Research component'],
            'keywords_found' => ['international', 'education', 'study', 'abroad', 'cultural', 'global']
        ],

        // ASEAN Awareness Initiative Award
        [
            'user' => 'user4',
            'title' => 'ASEAN Youth Leadership Summit 2024',
            'description' => 'Annual summit bringing together 200+ students from all 10 ASEAN member states for leadership training, cultural exchange, regional policy discussions, and collaborative projects.',
            'category' => 'Best ASEAN Awareness Initiative Award',
            'status' => 'Eligible',
            'match_percentage' => 96,
            'similarity_score' => 0.96,
            'matched_criteria' => ['ASEAN engagement', 'Youth leadership', 'Regional collaboration', 'Cultural exchange', 'Policy awareness'],
            'unmatched_criteria' => [],
            'keywords_found' => ['ASEAN', 'leadership', 'regional', 'summit', 'collaboration', 'youth']
        ],

        // Emerging Leadership Award
        [
            'user' => 'user3',
            'title' => 'Future Leaders Development Program',
            'description' => 'Intensive leadership training program for emerging student leaders focusing on global challenges, innovation, and cross-cultural collaboration skills.',
            'category' => 'Emerging Leadership Award',
            'status' => 'Eligible',
            'match_percentage' => 85,
            'similarity_score' => 0.85,
            'matched_criteria' => ['Leadership training', 'Student development', 'Innovation focus', 'Global perspective'],
            'unmatched_criteria' => ['Community impact documentation'],
            'keywords_found' => ['leadership', 'development', 'emerging', 'training', 'student']
        ],

        // Internationalization Leadership Award
        [
            'user' => 'user2',
            'title' => 'Institutional Internationalization Strategy',
            'description' => 'Comprehensive university-wide internationalization initiative including curriculum reform, international partnerships, faculty development, and global engagement metrics.',
            'category' => 'Internationalization Leadership Award',
            'status' => 'Eligible',
            'match_percentage' => 91,
            'similarity_score' => 0.91,
            'matched_criteria' => ['Strategic planning', 'Institutional commitment', 'Faculty development', 'International partnerships', 'Curriculum integration'],
            'unmatched_criteria' => ['Long-term impact assessment'],
            'keywords_found' => ['internationalization', 'leadership', 'strategy', 'institutional', 'global']
        ],
    ];

    echo "Adding enhanced award submissions with detailed analysis...\n\n";
    foreach ($enhancedAwards as $award) {
        $userId = $userIds[$award['user']] ?? 2;

        // Insert award
        $stmt = $pdo->prepare("
            INSERT INTO awards (user_id, title, description, status, created_at)
            VALUES (?, ?, ?, 'analyzed', NOW() - INTERVAL FLOOR(RAND() * 30) DAY)
        ");
        $stmt->execute([$userId, $award['title'], $award['description']]);
        $awardId = $pdo->lastInsertId();

        // Create detailed analysis data
        $analysisData = [[
            'category' => $award['category'],
            'award_type' => 'Institutional',
            'status' => $award['status'],
            'match_percentage' => $award['match_percentage'],
            'criteria_met' => count($award['matched_criteria']),
            'criteria_total' => count($award['matched_criteria']) + count($award['unmatched_criteria']),
            'met_criteria' => $award['matched_criteria'],
            'unmet_criteria' => $award['unmatched_criteria'],
            'keyword_score' => round((count($award['keywords_found']) / 10) * 100),
            'final_score' => $award['match_percentage'],
            'similarity_score' => $award['similarity_score'],
            'keywords_found' => $award['keywords_found']
        ]];

        // Insert analysis
        $stmt = $pdo->prepare("
            INSERT INTO award_analysis (
                award_id, predicted_category, match_percentage, status,
                detected_text, matched_keywords, all_matches, created_at
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW() - INTERVAL FLOOR(RAND() * 30) DAY)
        ");

        $stmt->execute([
            $awardId,
            $award['category'],
            $award['match_percentage'],
            $award['status'],
            $award['description'],
            json_encode($award['keywords_found']),
            json_encode($analysisData)
        ]);

        echo "✓ Added: {$award['title']}\n";
        echo "  User: {$award['user']}, Category: {$award['category']}, Status: {$award['status']} ({$award['match_percentage']}%)\n";
        echo "  Matched: " . count($award['matched_criteria']) . "/" . (count($award['matched_criteria']) + count($award['unmatched_criteria'])) . " criteria\n\n";
    }

    // Add MOUs/MOAs
    echo "Adding sample partnerships...\n";
    $sampleMOUs = [
        ['user' => 'user1', 'title' => 'MOU with University of Tokyo', 'partner' => 'University of Tokyo, Japan', 'type' => 'MOU'],
        ['user' => 'user1', 'title' => 'MOA with National University of Singapore', 'partner' => 'National University of Singapore', 'type' => 'MOA'],
        ['user' => 'user2', 'title' => 'MOU with Seoul National University', 'partner' => 'Seoul National University, South Korea', 'type' => 'MOU'],
        ['user' => 'user2', 'title' => 'MOA with Chulalongkorn University', 'partner' => 'Chulalongkorn University, Thailand', 'type' => 'MOA'],
        ['user' => 'user3', 'title' => 'MOU with University of Melbourne', 'partner' => 'University of Melbourne, Australia', 'type' => 'MOU'],
    ];

    foreach ($sampleMOUs as $mou) {
        $userId = $userIds[$mou['user']] ?? 2;
        try {
            $stmt = $pdo->prepare("
                INSERT INTO mou_moa (user_id, title, partner, type, description, created_at)
                VALUES (?, ?, ?, ?, ?, NOW() - INTERVAL FLOOR(RAND() * 60) DAY)
            ");
            $stmt->execute([
                $userId,
                $mou['title'],
                $mou['partner'],
                $mou['type'],
                'Partnership agreement for academic collaboration, student exchange, and joint research initiatives'
            ]);
            echo "✓ Added partnership: {$mou['title']} (User: {$mou['user']})\n";
        } catch (Exception $e) {
            echo "⚠ Error adding MOU: {$e->getMessage()}\n";
        }
    }

    echo "\n✓ Enhanced data seeded successfully!\n\n";

    // Show summary
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM awards WHERE user_id >= 2");
    $awardCount = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM award_analysis");
    $analysisCount = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(DISTINCT user_id) as total FROM awards WHERE user_id >= 2");
    $userCount = $stmt->fetchColumn();

    echo "Summary:\n";
    echo "- {$userCount} users with submissions\n";
    echo "- {$awardCount} award submissions\n";
    echo "- {$analysisCount} analysis records\n";
    echo "- 5 partnership agreements\n\n";

    // Show breakdown by category
    echo "Awards by Category:\n";
    $stmt = $pdo->query("
        SELECT predicted_category, COUNT(*) as count,
               SUM(CASE WHEN status = 'Eligible' THEN 1 ELSE 0 END) as eligible,
               SUM(CASE WHEN status = 'Almost Eligible' THEN 1 ELSE 0 END) as almost
        FROM award_analysis
        GROUP BY predicted_category
    ");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  • {$row['predicted_category']}: {$row['count']} total ({$row['eligible']} eligible, {$row['almost']} almost)\n";
    }

} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
?>
