-- ====================================================================
-- LILAC Awards System - MySQL Database Schema
-- For XAMPP/MySQL/MariaDB
-- ====================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- ====================================================================
-- 1. USERS TABLE
-- ====================================================================
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) UNIQUE NOT NULL,
  `email` VARCHAR(100) UNIQUE NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(100),
  `role` ENUM('admin', 'user', 'viewer') DEFAULT 'user',
  `status` ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_login` TIMESTAMP NULL,
  INDEX `idx_username` (`username`),
  INDEX `idx_email` (`email`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====================================================================
-- 2. SESSIONS TABLE
-- ====================================================================
CREATE TABLE IF NOT EXISTS `sessions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `session_token` VARCHAR(255) UNIQUE NOT NULL,
  `ip_address` VARCHAR(45),
  `user_agent` VARCHAR(255),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `expires_at` TIMESTAMP NOT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_session_token` (`session_token`),
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====================================================================
-- 3. AWARD CRITERIA TABLE (Admin-defined award criteria and requirements)
-- ====================================================================
CREATE TABLE IF NOT EXISTS `award_criteria` (
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
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_category_name` (`category_name`),
  INDEX `idx_status` (`status`),
  INDEX `idx_award_type` (`award_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====================================================================
-- 4. AWARD CATEGORIES TABLE (Legacy for backward compatibility)
-- ====================================================================
CREATE TABLE IF NOT EXISTS `award_categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `keywords` JSON,
  `total_weight` INT DEFAULT 100,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====================================================================
-- 5. AWARDS TABLE
-- ====================================================================
CREATE TABLE IF NOT EXISTS `awards` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `file_name` VARCHAR(255),
  `file_path` VARCHAR(500),
  `file_type` VARCHAR(50),
  `file_size` INT,
  `award_date` DATE,
  `status` ENUM('pending', 'analyzed', 'approved', 'rejected') DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====================================================================
-- 5. AWARD ANALYSIS TABLE
-- ====================================================================
CREATE TABLE IF NOT EXISTS `award_analysis` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `award_id` INT NOT NULL,
  `predicted_category` VARCHAR(255),
  `match_percentage` DECIMAL(5,2),
  `status` ENUM('Eligible', 'Almost Eligible', 'Not Eligible') DEFAULT 'Not Eligible',
  `detected_text` LONGTEXT,
  `matched_keywords` JSON,
  `all_matches` JSON,
  `recommendations` TEXT,
  `analysis_metadata` JSON,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`award_id`) REFERENCES `awards`(`id`) ON DELETE CASCADE,
  INDEX `idx_award_id` (`award_id`),
  INDEX `idx_predicted_category` (`predicted_category`),
  INDEX `idx_status` (`status`),
  INDEX `idx_match_percentage` (`match_percentage`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====================================================================
-- 6. EVENTS TABLE
-- ====================================================================
CREATE TABLE IF NOT EXISTS `events` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `event_date` DATE,
  `location` VARCHAR(255),
  `status` ENUM('planned', 'ongoing', 'completed', 'cancelled') DEFAULT 'planned',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_event_date` (`event_date`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====================================================================
-- 7. AWARD EVENT LINKS TABLE
-- ====================================================================
CREATE TABLE IF NOT EXISTS `award_event_links` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `award_id` INT NOT NULL,
  `event_id` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`award_id`) REFERENCES `awards`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE CASCADE,
  INDEX `idx_award_id` (`award_id`),
  INDEX `idx_event_id` (`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====================================================================
-- 8. AWARD STATISTICS TABLE
-- ====================================================================
CREATE TABLE IF NOT EXISTS `award_statistics` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT,
  `stat_date` DATE NOT NULL,
  `total_awards` INT DEFAULT 0,
  `eligible_count` INT DEFAULT 0,
  `almost_eligible_count` INT DEFAULT 0,
  `not_eligible_count` INT DEFAULT 0,
  `avg_match_percentage` DECIMAL(5,2),
  `category_distribution` JSON,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_stat_date` (`stat_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====================================================================
-- 9. ACTIVITY LOG TABLE
-- ====================================================================
CREATE TABLE IF NOT EXISTS `activity_log` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT,
  `action` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `ip_address` VARCHAR(45),
  `user_agent` VARCHAR(255),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_action` (`action`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====================================================================
-- INSERT DEFAULT DATA
-- ====================================================================

-- Insert default users with bcrypt hashed passwords
-- Password for 'admin' is: admin123
-- Password for 'user' is: user123
INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `full_name`, `role`, `status`) VALUES
(1, 'admin', 'admin@cpu.edu.ph', '$2y$10$JHOO6mOOQhMlva.DFeOeDOr.bKEYBUePOb9Qo99h5C9VEMHSU8D6q', 'System Administrator', 'admin', 'active'),
(2, 'user', 'user@cpu.edu.ph', '$2y$10$LEMpxXjrEomUVJt0sf9rseo7uNmYnKfsyTPUoOLKH0deanknG1bmq', 'Regular User', 'user', 'active')
ON DUPLICATE KEY UPDATE `username` = VALUES(`username`);

-- Insert ICONS 2025 Award Categories
INSERT INTO `award_categories` (`name`, `description`, `keywords`, `total_weight`) VALUES
('Global Citizenship Award', 'Recognition for fostering global citizenship and international understanding', '{"keywords": {"global": 15, "citizenship": 15, "international": 12, "intercultural": 12, "exchange": 10, "diversity": 10, "culture": 8, "understanding": 8, "collaboration": 7, "partnership": 5}, "phrases": {"global citizenship": 20, "intercultural understanding": 18, "international collaboration": 15, "cultural diversity": 12, "student exchange": 10}}', 100),
('Best ASEAN Awareness Award', 'Excellence in promoting ASEAN awareness and regional integration', '{"keywords": {"asean": 20, "southeast": 12, "regional": 12, "integration": 10, "awareness": 10, "cooperation": 8, "community": 8, "partnership": 7, "unity": 7, "solidarity": 6}, "phrases": {"asean awareness": 25, "regional integration": 15, "asean community": 15, "southeast asia": 12, "regional cooperation": 10}}', 100),
('Internationalization Leadership Award', 'Outstanding leadership in internationalization initiatives', '{"keywords": {"leadership": 15, "internationalization": 15, "strategy": 12, "innovation": 10, "development": 10, "excellence": 8, "vision": 8, "transformation": 7, "impact": 7, "advancement": 6}, "phrases": {"internationalization strategy": 20, "strategic leadership": 15, "institutional development": 12, "international excellence": 12, "transformative leadership": 10}}', 100),
('Outstanding International Education Award', 'Excellence in international education programs and initiatives', '{"keywords": {"education": 15, "international": 15, "academic": 12, "program": 10, "quality": 10, "excellence": 8, "curriculum": 8, "learning": 7, "teaching": 7, "innovation": 6}, "phrases": {"international education": 20, "academic excellence": 15, "educational innovation": 12, "quality education": 12, "learning outcomes": 10}}', 100),
('Most Promising IRO/Community Award', 'Recognition for promising International Relations Office or community initiatives', '{"keywords": {"community": 15, "engagement": 12, "outreach": 12, "partnership": 10, "development": 10, "impact": 8, "initiative": 8, "collaboration": 7, "service": 7, "support": 6}, "phrases": {"community engagement": 20, "international relations": 18, "community development": 15, "stakeholder engagement": 12, "partnership building": 10}}', 100),
('Emerging Leadership in Internationalization Award', 'Recognition for emerging leaders in internationalization', '{"keywords": {"emerging": 15, "leadership": 15, "potential": 12, "innovation": 10, "growth": 10, "development": 8, "initiative": 8, "vision": 7, "impact": 7, "commitment": 6}, "phrases": {"emerging leader": 20, "leadership potential": 15, "innovative approach": 12, "professional growth": 12, "future leader": 10}}', 100),
('Best CHED Regional Office for Internationalization Award', 'Excellence among CHED regional offices in internationalization', '{"keywords": {"ched": 20, "regional": 15, "office": 12, "governance": 10, "policy": 10, "implementation": 8, "coordination": 8, "support": 7, "development": 7, "excellence": 6}, "phrases": {"ched regional office": 25, "policy implementation": 15, "regional coordination": 12, "institutional support": 12, "governance excellence": 10}}', 100),
('Sustainability in Internationalization Award', 'Recognition for sustainable internationalization practices', '{"keywords": {"sustainability": 20, "sustainable": 15, "environment": 12, "green": 10, "impact": 10, "development": 8, "responsibility": 8, "future": 7, "practice": 7, "goals": 6}, "phrases": {"sustainable development": 20, "environmental sustainability": 18, "sustainable practices": 15, "long-term impact": 12, "sustainability goals": 10}}', 100)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- ====================================================================
-- DOCUMENTS TABLE
-- ====================================================================
CREATE TABLE IF NOT EXISTS `documents` (
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
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_category` (`category`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====================================================================
-- SCHEDULES TABLE
-- ====================================================================
CREATE TABLE IF NOT EXISTS `schedules` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `scheduled_date` DATE NOT NULL,
  `scheduled_time` TIME,
  `end_date` DATE,
  `end_time` TIME,
  `location` VARCHAR(255),
  `category` VARCHAR(100),
  `color` VARCHAR(20),
  `reminder_sent` TINYINT(1) DEFAULT 0,
  `status` ENUM('scheduled', 'completed', 'cancelled') DEFAULT 'scheduled',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_scheduled_date` (`scheduled_date`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====================================================================
-- MOU/MOA TABLE
-- ====================================================================
CREATE TABLE IF NOT EXISTS `mou_moa` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `partner` VARCHAR(255),
  `type` ENUM('MOU', 'MOA') DEFAULT 'MOU',
  `description` TEXT,
  `file_name` VARCHAR(255),
  `file_path` VARCHAR(500),
  `file_type` VARCHAR(50),
  `file_size` INT,
  `date_signed` DATE,
  `expiry_date` DATE,
  `status` ENUM('active', 'expired', 'terminated') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_partner` (`partner`),
  INDEX `idx_type` (`type`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====================================================================
-- SUCCESS MESSAGE
-- ====================================================================
SELECT 'Database schema created successfully!' as 'Status',
       'Default users: admin/admin123 and user/user123' as 'Info',
       '8 award categories loaded' as 'Categories';
