<?php
/**
 * Update Award Criteria with Weighted Keywords
 * Adds sample keyword data to existing award criteria
 */

require 'api/config.php';

try {
    $pdo = getDatabaseConnection();

    if ($pdo instanceof FileBasedDatabase) {
        echo "✗ Requires database connection\n";
        exit(1);
    }

    echo "=== Updating Award Criteria with Weighted Keywords ===\n\n";

    // Sample weighted keywords for each award
    $criteriaUpdates = [
        [
            'name' => 'Global Citizenship Award',
            'keywords' => 'global, citizenship, international, intercultural',
            'weight' => 50
        ],
        [
            'name' => 'Outstanding International Education Program Award',
            'keywords' => 'education, international, exchange, program',
            'weight' => 50
        ],
        [
            'name' => 'Sustainability Award',
            'keywords' => 'sustainability, environmental, green, carbon, renewable, conservation',
            'weight' => 50
        ]
    ];

    foreach ($criteriaUpdates as $update) {
        // Check if award exists
        $stmt = $pdo->prepare("SELECT id, keywords FROM award_criteria WHERE category_name = ?");
        $stmt->execute([$update['name']]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // Update keywords and weight
            $stmt = $pdo->prepare("
                UPDATE award_criteria
                SET keywords = ?, weight = ?
                WHERE id = ?
            ");
            $stmt->execute([$update['keywords'], $update['weight'], $existing['id']]);

            echo "✓ Updated: {$update['name']}\n";
            echo "  Keywords: {$update['keywords']}\n";
            echo "  Weight: {$update['weight']}\n\n";
        } else {
            echo "⚠ Not found: {$update['name']}\n\n";
        }
    }

    // Show current award criteria with keywords
    echo "\n" . str_repeat("=", 70) . "\n";
    echo "CURRENT AWARD CRITERIA WITH WEIGHTED KEYWORDS:\n";
    echo str_repeat("=", 70) . "\n\n";

    $stmt = $pdo->query("
        SELECT category_name, award_type, keywords, weight, status
        FROM award_criteria
        WHERE status = 'active'
        ORDER BY category_name
    ");

    $criteria = $stmt->fetchAll(PDO::FETCH_ASSOC);

    printf("%-40s %-50s %s\n", "Award Name", "Keywords", "Weight");
    echo str_repeat("-", 70) . "\n";

    foreach ($criteria as $crit) {
        $keywords = $crit['keywords'] ?? 'Not set';
        $weight = $crit['weight'] ?? 0;
        printf("%-40s %-50s %d\n",
            substr($crit['category_name'], 0, 38),
            substr($keywords, 0, 48),
            $weight
        );
    }

    echo "\n" . str_repeat("=", 70) . "\n";
    echo "✓ Update complete!\n";

} catch (Exception $e) {
    echo "✗ Error: {$e->getMessage()}\n";
}
