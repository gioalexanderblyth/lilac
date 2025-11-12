<?php
require_once 'api/config.php';

try {
    $pdo = getDatabaseConnection();

    echo "Seeding multiple users per award category...\n\n";

    // Sample users
    $users = [
        ['username' => 'john_doe', 'email' => 'john.doe@cpu.edu.ph', 'name' => 'John Doe'],
        ['username' => 'maria_santos', 'email' => 'maria.santos@cpu.edu.ph', 'name' => 'Maria Santos'],
        ['username' => 'robert_lee', 'email' => 'robert.lee@cpu.edu.ph', 'name' => 'Robert Lee'],
        ['username' => 'sarah_kim', 'email' => 'sarah.kim@cpu.edu.ph', 'name' => 'Sarah Kim'],
        ['username' => 'david_chen', 'email' => 'david.chen@cpu.edu.ph', 'name' => 'David Chen'],
    ];

    // Insert users (without password field)
    echo "Adding users...\n";
    $userIds = [];
    foreach ($users as $user) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO users (username, email, role, created_at)
                VALUES (?, ?, 'user', NOW())
                ON DUPLICATE KEY UPDATE email = VALUES(email)
            ");
            $stmt->execute([$user['username'], $user['email']]);

            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$user['username']]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result) {
                $userIds[$user['username']] = $result['id'];
                echo "✓ User: {$user['username']} (ID: {$result['id']})\n";
            }
        } catch (Exception $e) {
            echo "⚠ Error with user {$user['username']}: " . $e->getMessage() . "\n";
        }
    }

    echo "\n" . count($userIds) . " users ready\n\n";

    // Award submissions - Multiple users per category
    $submissions = [
        // Zero Waste Campus Project - 4 applicants
        [
            'user' => 'john_doe',
            'title' => 'Zero Waste Campus Project',
            'description' => 'Initiative to eliminate waste through recycling, composting, and reduction programs.',
            'category' => 'Sustainability Award',
            'status' => 'recognized',
            'match_percentage' => 90,
            'similarity_score' => 0.90,
            'matched_criteria' => ['Waste reduction', 'Recycling program', 'Environmental impact', 'Community engagement'],
            'unmatched_criteria' => ['Carbon footprint tracking'],
        ],
        [
            'user' => 'maria_santos',
            'title' => 'Campus Recycling Excellence Program',
            'description' => 'Comprehensive recycling program covering all campus buildings with student volunteers.',
            'category' => 'Sustainability Award',
            'status' => 'pending',
            'match_percentage' => 85,
            'similarity_score' => 0.85,
            'matched_criteria' => ['Recycling program', 'Student involvement', 'Environmental impact'],
            'unmatched_criteria' => ['Waste reduction', 'Carbon footprint tracking'],
        ],
        [
            'user' => 'robert_lee',
            'title' => 'Green Building Initiative',
            'description' => 'Sustainable building practices and energy-efficient campus infrastructure.',
            'category' => 'Sustainability Award',
            'status' => 'processed',
            'match_percentage' => 78,
            'similarity_score' => 0.78,
            'matched_criteria' => ['Environmental impact', 'Energy efficiency', 'Sustainable practices'],
            'unmatched_criteria' => ['Recycling program', 'Waste reduction'],
        ],
        [
            'user' => 'sarah_kim',
            'title' => 'Eco-Friendly Campus Transportation',
            'description' => 'Promoting bike sharing, electric shuttles, and reduced carbon emissions.',
            'category' => 'Sustainability Award',
            'status' => 'pending',
            'match_percentage' => 72,
            'similarity_score' => 0.72,
            'matched_criteria' => ['Environmental impact', 'Carbon reduction'],
            'unmatched_criteria' => ['Recycling program', 'Waste reduction', 'Community engagement'],
        ],

        // Global Outreach Program - 3 applicants
        [
            'user' => 'maria_santos',
            'title' => 'Global Outreach Program 2024',
            'description' => 'Comprehensive international student exchange program with 15 partner universities.',
            'category' => 'Global Citizenship Award',
            'status' => 'recognized',
            'match_percentage' => 92,
            'similarity_score' => 0.92,
            'matched_criteria' => ['International engagement', 'Cultural awareness', 'Community service', 'Language programs', 'Student exchange'],
            'unmatched_criteria' => ['Documentation of impact metrics'],
        ],
        [
            'user' => 'david_chen',
            'title' => 'International Cultural Exchange Initiative',
            'description' => 'Cultural immersion programs connecting local and international students.',
            'category' => 'Global Citizenship Award',
            'status' => 'pending',
            'match_percentage' => 88,
            'similarity_score' => 0.88,
            'matched_criteria' => ['International engagement', 'Cultural awareness', 'Student exchange', 'Community service'],
            'unmatched_criteria' => ['Language programs', 'Documentation of impact metrics'],
        ],
        [
            'user' => 'john_doe',
            'title' => 'Global Service Learning Program',
            'description' => 'Community service projects in partnership with international organizations.',
            'category' => 'Global Citizenship Award',
            'status' => 'processed',
            'match_percentage' => 82,
            'similarity_score' => 0.82,
            'matched_criteria' => ['Community service', 'International engagement', 'Cultural awareness'],
            'unmatched_criteria' => ['Language programs', 'Student exchange', 'Documentation of impact metrics'],
        ],

        // ASEAN Youth Leadership - 3 applicants
        [
            'user' => 'sarah_kim',
            'title' => 'ASEAN Youth Leadership Summit 2024',
            'description' => 'Annual summit bringing together youth leaders from all ASEAN member states.',
            'category' => 'Best ASEAN Awareness Initiative Award',
            'status' => 'recognized',
            'match_percentage' => 96,
            'similarity_score' => 0.96,
            'matched_criteria' => ['ASEAN engagement', 'Youth leadership', 'Regional cooperation', 'Cultural exchange', 'Policy dialogue'],
            'unmatched_criteria' => [],
        ],
        [
            'user' => 'robert_lee',
            'title' => 'ASEAN Cultural Festival 2024',
            'description' => 'Week-long festival celebrating ASEAN cultures through food, music, and art.',
            'category' => 'Best ASEAN Awareness Initiative Award',
            'status' => 'pending',
            'match_percentage' => 89,
            'similarity_score' => 0.89,
            'matched_criteria' => ['ASEAN engagement', 'Cultural exchange', 'Community participation'],
            'unmatched_criteria' => ['Youth leadership', 'Policy dialogue'],
        ],
        [
            'user' => 'maria_santos',
            'title' => 'ASEAN Business Leaders Network',
            'description' => 'Networking platform for young entrepreneurs across ASEAN countries.',
            'category' => 'Best ASEAN Awareness Initiative Award',
            'status' => 'pending',
            'match_percentage' => 84,
            'similarity_score' => 0.84,
            'matched_criteria' => ['ASEAN engagement', 'Regional cooperation', 'Youth leadership'],
            'unmatched_criteria' => ['Cultural exchange', 'Policy dialogue'],
        ],

        // International Education Excellence - 2 applicants
        [
            'user' => 'david_chen',
            'title' => 'International Education Excellence Program',
            'description' => 'Comprehensive program to internationalize curriculum and enhance global competency.',
            'category' => 'Outstanding International Education Program Award',
            'status' => 'recognized',
            'match_percentage' => 93,
            'similarity_score' => 0.93,
            'matched_criteria' => ['Curriculum internationalization', 'Faculty development', 'Student mobility', 'Quality assurance', 'Partnership network'],
            'unmatched_criteria' => ['Research collaboration'],
        ],
        [
            'user' => 'john_doe',
            'title' => 'Global Learning Experience Program',
            'description' => 'Study abroad and international internship opportunities for all students.',
            'category' => 'Outstanding International Education Program Award',
            'status' => 'pending',
            'match_percentage' => 78,
            'similarity_score' => 0.78,
            'matched_criteria' => ['Student mobility', 'Partnership network', 'Global competency'],
            'unmatched_criteria' => ['Curriculum internationalization', 'Faculty development', 'Research collaboration'],
        ],

        // Emerging Leadership - 2 applicants
        [
            'user' => 'robert_lee',
            'title' => 'Future Leaders Development Program',
            'description' => 'Intensive leadership training for emerging student leaders with mentorship.',
            'category' => 'Emerging Leadership Award',
            'status' => 'pending',
            'match_percentage' => 85,
            'similarity_score' => 0.85,
            'matched_criteria' => ['Leadership training', 'Mentorship program', 'Skill development', 'Community impact'],
            'unmatched_criteria' => ['Research component'],
        ],
        [
            'user' => 'sarah_kim',
            'title' => 'Next Generation Leaders Initiative',
            'description' => 'Student-led projects addressing campus and community challenges.',
            'category' => 'Emerging Leadership Award',
            'status' => 'processed',
            'match_percentage' => 81,
            'similarity_score' => 0.81,
            'matched_criteria' => ['Leadership training', 'Community impact', 'Skill development'],
            'unmatched_criteria' => ['Mentorship program', 'Research component'],
        ],
    ];

    echo "Adding award submissions...\n\n";

    foreach ($submissions as $sub) {
        if (!isset($userIds[$sub['user']])) {
            echo "⚠ User {$sub['user']} not found, skipping\n";
            continue;
        }

        $userId = $userIds[$sub['user']];

        // Insert award
        $stmt = $pdo->prepare("
            INSERT INTO awards (user_id, title, description, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([$userId, $sub['title'], $sub['description'], $sub['status']]);
        $awardId = $pdo->lastInsertId();

        // Create analysis data
        $analysisData = [[
            'category' => $sub['category'],
            'met_criteria' => $sub['matched_criteria'],
            'unmet_criteria' => $sub['unmatched_criteria'],
            'criteria_met' => count($sub['matched_criteria']),
            'criteria_total' => count($sub['matched_criteria']) + count($sub['unmatched_criteria']),
            'similarity_score' => $sub['similarity_score'],
            'keyword_score' => $sub['similarity_score'] * 0.9,
            'final_score' => $sub['match_percentage'],
        ]];

        // Insert award analysis
        $analysisStatus = $sub['match_percentage'] >= 85 ? 'Eligible' : ($sub['match_percentage'] >= 70 ? 'Almost Eligible' : 'Under Review');

        $stmt = $pdo->prepare("
            INSERT INTO award_analysis (award_id, predicted_category, match_percentage, status, all_matches, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $awardId,
            $sub['category'],
            $sub['match_percentage'],
            $analysisStatus,
            json_encode($analysisData)
        ]);

        echo "✓ {$sub['title']}\n";
        echo "  User: {$sub['user']}, Status: {$sub['status']}, Match: {$sub['match_percentage']}%\n\n";
    }

    echo "\n✓ Seeding complete!\n\n";

    // Show summary by category
    echo "Summary by Category:\n";
    echo str_repeat('=', 80) . "\n\n";

    $stmt = $pdo->query("
        SELECT
            aa.predicted_category,
            COUNT(*) as total,
            SUM(CASE WHEN a.status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN a.status = 'recognized' THEN 1 ELSE 0 END) as recognized,
            SUM(CASE WHEN a.status = 'processed' THEN 1 ELSE 0 END) as processed
        FROM awards a
        LEFT JOIN award_analysis aa ON a.id = aa.award_id
        WHERE aa.predicted_category IS NOT NULL
        GROUP BY aa.predicted_category
        ORDER BY total DESC
    ");

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "{$row['predicted_category']}:\n";
        echo "  Total: {$row['total']} | Pending: {$row['pending']} | Recognized: {$row['recognized']} | Processed: {$row['processed']}\n\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
?>
