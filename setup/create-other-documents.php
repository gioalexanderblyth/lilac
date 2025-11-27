<?php
require_once __DIR__ . '/../api/config.php';

try {
    $pdo = getDatabaseConnection();
    
    // Check if table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'other_documents'");
    if ($stmt->rowCount() == 0) {
        echo "Table 'other_documents' is MISSING. Creating it now...\n";
        
        $sql = "CREATE TABLE `other_documents` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) DEFAULT NULL,
            `award_id` int(11) DEFAULT NULL,
            `title` varchar(255) NOT NULL,
            `description` text DEFAULT NULL,
            `file_name` varchar(255) NOT NULL,
            `file_path` varchar(255) NOT NULL,
            `file_type` varchar(50) DEFAULT NULL,
            `file_size` int(11) DEFAULT NULL,
            `category` varchar(50) DEFAULT 'Other',
            `tags` text DEFAULT NULL,
            `source_page` varchar(50) DEFAULT 'documents',
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `user_id` (`user_id`),
            KEY `award_id` (`award_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
        
        $pdo->exec($sql);
        echo "Table 'other_documents' created successfully.\n";
    } else {
        echo "Table 'other_documents' ALREADY EXISTS.\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>

