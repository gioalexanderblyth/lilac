<?php
require_once __DIR__ . '/api/config.php';

echo "Creating missing tables...\n\n";

try {
    $pdo = getDatabaseConnection();

    if ($pdo instanceof FileBasedDatabase) {
        die("Error: MySQL database connection required\n");
    }

    $sql = "CREATE TABLE IF NOT EXISTS `documents` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `title` VARCHAR(255) NOT NULL,
        `description` TEXT,
        `file_name` VARCHAR(255),
        `file_path` VARCHAR(500),
        `file_type` VARCHAR(50),
        `file_size` INT,
        `category` VARCHAR(100),
        `status` ENUM('active', 'archived', 'deleted') DEFAULT 'active',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        INDEX `idx_user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $pdo->exec($sql);
    echo "✓ Table 'documents' created\n";

    $sql = "CREATE TABLE IF NOT EXISTS `schedules` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `title` VARCHAR(255) NOT NULL,
        `description` TEXT,
        `scheduled_date` DATE NOT NULL,
        `scheduled_time` TIME,
        `status` ENUM('scheduled', 'completed', 'cancelled') DEFAULT 'scheduled',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        INDEX `idx_user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $pdo->exec($sql);
    echo "✓ Table 'schedules' created\n";

    $sql = "CREATE TABLE IF NOT EXISTS `mou_moa` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `title` VARCHAR(255) NOT NULL,
        `partner` VARCHAR(255),
        `type` ENUM('MOU', 'MOA') DEFAULT 'MOU',
        `description` TEXT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        INDEX `idx_user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $pdo->exec($sql);
    echo "✓ Table 'mou_moa' created\n";

    echo "\n✓ All tables created successfully!\n";

} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
