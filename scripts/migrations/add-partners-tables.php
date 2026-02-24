<?php
/**
 * Migration: Add partners and partner_suggestions tables for Auto-Suggest institution extraction.
 * Run via: php add-partners-tables.php (from scripts/migrations/ or project root)
 *
 * Requires MySQL (JSON, ENUM, ON DUPLICATE KEY UPDATE).
 */
require_once dirname(__DIR__, 2) . '/api/config.php';

try {
    $pdo = getDatabaseConnection();
    if ($pdo instanceof FileBasedDatabase) {
        die("This migration requires MySQL. File-based database not supported.\n");
    }

    // 1. Canonical approved partners
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS partners (
            id INT AUTO_INCREMENT PRIMARY KEY,
            canonical_name VARCHAR(255) UNIQUE NOT NULL,
            aliases JSON,
            status ENUM('active', 'inactive') DEFAULT 'active'
        )
    ");
    echo "Table 'partners' created or already exists.\n";

    // 2. Suggestions waiting for admin approval
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS partner_suggestions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            detected_name VARCHAR(255) UNIQUE NOT NULL,
            last_seen_text TEXT,
            occurrences INT DEFAULT 1,
            status ENUM('pending','approved','rejected') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
    echo "Table 'partner_suggestions' created or already exists.\n";

    // Seed initial partners if empty
    $stmt = $pdo->query("SELECT COUNT(*) FROM partners");
    if ($stmt && (int) $stmt->fetchColumn() === 0) {
        $pdo->exec("
            INSERT INTO partners (canonical_name, aliases, status) VALUES
            ('Tra Vinh University', '[\"Tra Vinh University\", \"Tra Vinh Univ\", \"TVU\", \"Tra Vinh\"]', 'active'),
            ('Nagoya Gakuin University', '[\"Nagoya Gakuin University\", \"Nagoya Gakuin\", \"NGU\"]', 'active'),
            ('Universitas Kristen Indonesia', '[\"Universitas Kristen Indonesia\", \"UKI\"]', 'active')
        ");
        echo "Seeded initial partners.\n";
    }

    echo "Migration completed successfully.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
