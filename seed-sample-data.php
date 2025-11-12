<?php
require_once __DIR__ . '/api/config.php';

echo "Seeding sample award data...\n\n";

try {
    $pdo = getDatabaseConnection();

    if ($pdo instanceof FileBasedDatabase) {
        die("Error: MySQL database connection required\n");
    }

    // Sample award submissions with analysis
    $sampleAwards = [
        [
            'user_id' => 2, // Regular user
            'title' => 'Global Outreach Program 2024',
            'description' => 'Comprehensive international student exchange program with partner universities across 15 countries in Asia and Europe.',
            'category' => 'Global Citizenship Award',
            'status' => 'Eligible',
            'match_percentage' => 92
        ],
        [
            'user_id' => 2,
            'title' => 'Sustainability Initiative - Green Campus Project',
            'description' => 'Implementation of renewable energy systems, waste management, and environmental awareness campaigns across campus.',
            'category' => 'Sustainability Award',
            'status' => 'Eligible',
            'match_percentage' => 88
        ],
        [
            'user_id' => 2,
            'title' => 'International Education Partnership Program',
            'description' => 'Collaborative research and faculty exchange with leading universities in Southeast Asia.',
            'category' => 'Outstanding International Education Program Award',
            'status' => 'Almost Eligible',
            'match_percentage' => 75
        ],
        [
            'user_id' => 2,
            'title' => 'ASEAN Youth Leadership Summit',
            'description' => 'Annual summit bringing together students from all ASEAN member states for leadership training and cultural exchange.',
            'category' => 'Best ASEAN Awareness Initiative Award',
            'status' => 'Eligible',
            'match_percentage' => 95
        ],
        [
            'user_id' => 2,
            'title' => 'Emerging Leaders Program',
            'description' => 'Mentorship and training program for student leaders focusing on internationalization and global competencies.',
            'category' => 'Emerging Leadership Award',
            'status' => 'Almost Eligible',
            'match_percentage' => 70
        ]
    ];

    foreach ($sampleAwards as $award) {
        // Insert award
        $stmt = $pdo->prepare("
            INSERT INTO awards (user_id, title, description, status, created_at)
            VALUES (?, ?, ?, 'analyzed', NOW())
        ");
        $stmt->execute([$award['user_id'], $award['title'], $award['description']]);
        $awardId = $pdo->lastInsertId();

        // Insert analysis
        $analysisData = [
            [
                'category' => $award['category'],
                'award_type' => 'Institutional',
                'status' => $award['status'],
                'match_percentage' => $award['match_percentage'],
                'criteria_met' => rand(4, 7),
                'criteria_total' => 7,
                'met_criteria' => ['International engagement', 'Cultural awareness', 'Community impact'],
                'unmet_criteria' => [],
                'keyword_score' => rand(70, 95),
                'final_score' => $award['match_percentage']
            ]
        ];

        $stmt = $pdo->prepare("
            INSERT INTO award_analysis (award_id, predicted_category, match_percentage, status, detected_text, all_matches, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $awardId,
            $award['category'],
            $award['match_percentage'],
            $award['status'],
            $award['description'],
            json_encode($analysisData)
        ]);

        echo "✓ Added award: {$award['title']} ({$award['status']} - {$award['match_percentage']}%)\n";
    }

    // Add some sample MOUs/MOAs for partnerships
    $sampleMOUs = [
        ['title' => 'MOU with University of Tokyo', 'partner' => 'University of Tokyo, Japan', 'type' => 'MOU'],
        ['title' => 'MOA with National University of Singapore', 'partner' => 'National University of Singapore', 'type' => 'MOA'],
        ['title' => 'MOU with Seoul National University', 'partner' => 'Seoul National University, South Korea', 'type' => 'MOU']
    ];

    foreach ($sampleMOUs as $mou) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO mou_moa (user_id, title, partner, type, description, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                2,
                $mou['title'],
                $mou['partner'],
                $mou['type'],
                'Partnership agreement for academic collaboration and student exchange'
            ]);
            echo "✓ Added partnership: {$mou['title']}\n";
        } catch (Exception $e) {
            // Table might not exist
            echo "⚠ Could not add MOUs (table may not exist): {$e->getMessage()}\n";
            break;
        }
    }

    echo "\n✓ Sample data seeded successfully!\n";
    echo "\nSummary:\n";
    echo "- " . count($sampleAwards) . " sample awards added\n";
    echo "- " . count($sampleMOUs) . " sample partnerships added\n";

} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
?>
