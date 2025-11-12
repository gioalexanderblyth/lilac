<?php
require_once __DIR__ . '/api/config.php';

echo "Setting up award_criteria table...\n\n";

try {
    $pdo = getDatabaseConnection();

    if ($pdo instanceof FileBasedDatabase) {
        die("Error: MySQL database connection required\n");
    }

    $sql = "CREATE TABLE IF NOT EXISTS `award_criteria` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `category_name` VARCHAR(255) NOT NULL,
        `award_type` ENUM('Individual', 'Institutional', 'Regional') DEFAULT 'Institutional',
        `description` TEXT NOT NULL,
        `requirements` JSON NOT NULL,
        `keywords` VARCHAR(500),
        `min_match_percentage` INT DEFAULT 60,
        `weight` INT DEFAULT 5,
        `status` ENUM('active', 'inactive') DEFAULT 'active',
        `created_by` INT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_category_name` (`category_name`),
        INDEX `idx_status` (`status`),
        INDEX `idx_award_type` (`award_type`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $pdo->exec($sql);
    echo "✓ Table 'award_criteria' created successfully\n\n";

    $stmt = $pdo->query("SELECT COUNT(*) FROM award_criteria");
    $count = $stmt->fetchColumn();

    if ($count == 0) {
        echo "Adding sample award criteria...\n\n";

        $sampleCriteria = [
            [
                'category_name' => 'Global Citizenship Award',
                'award_type' => 'Individual',
                'description' => 'Recognizes individuals who demonstrate outstanding global citizenship through international engagement and cultural awareness.',
                'requirements' => json_encode([
                    'Demonstrated participation in international programs or exchanges',
                    'Evidence of cross-cultural collaboration',
                    'Community service with international impact',
                    'Leadership in promoting global awareness',
                    'Documented international partnerships or projects'
                ]),
                'keywords' => 'global, citizenship, international, cultural, exchange, community, awareness',
                'min_match_percentage' => 60,
                'weight' => 7
            ],
            [
                'category_name' => 'Outstanding International Education Program Award',
                'award_type' => 'Institutional',
                'description' => 'Honors institutions with exemplary international education programs that foster global learning.',
                'requirements' => json_encode([
                    'Established international education curriculum',
                    'Student exchange programs with foreign institutions',
                    'International faculty collaboration',
                    'Research projects with global scope',
                    'Cultural diversity initiatives',
                    'International student support services'
                ]),
                'keywords' => 'education, program, international, curriculum, exchange, research, diversity',
                'min_match_percentage' => 70,
                'weight' => 9
            ],
            [
                'category_name' => 'Sustainability Award',
                'award_type' => 'Institutional',
                'description' => 'Recognizes organizations committed to sustainability and environmental responsibility.',
                'requirements' => json_encode([
                    'Environmental sustainability initiatives',
                    'Green campus programs or practices',
                    'Waste reduction and recycling programs',
                    'Energy efficiency measures',
                    'Community environmental outreach'
                ]),
                'keywords' => 'sustainability, environmental, green, waste, recycling, energy, climate',
                'min_match_percentage' => 65,
                'weight' => 8
            ]
        ];

        $stmt = $pdo->prepare("
            INSERT INTO award_criteria
            (category_name, award_type, description, requirements, keywords, min_match_percentage, weight, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'active')
        ");

        foreach ($sampleCriteria as $criteria) {
            $stmt->execute([
                $criteria['category_name'],
                $criteria['award_type'],
                $criteria['description'],
                $criteria['requirements'],
                $criteria['keywords'],
                $criteria['min_match_percentage'],
                $criteria['weight']
            ]);
            echo "  ✓ Added: {$criteria['category_name']}\n";
        }

        echo "\n✓ Sample criteria added successfully\n\n";
    } else {
        echo "Table already contains $count criteria\n\n";
    }

    echo "Setup complete! You can now use the Admin Panel in awards.php\n";

} catch (PDOException $e) {
    echo "✗ Database error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
