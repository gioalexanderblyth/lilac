-- Migration: MOU/MOA Multiple Files Support
-- Adds support for storing multiple files per MOU/MOA entry
-- Run this script to update your database schema

-- Create mou_moa_files table to store additional files
CREATE TABLE IF NOT EXISTS `mou_moa_files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `mou_moa_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(11) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_primary` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_mou_moa_id` (`mou_moa_id`),
  CONSTRAINT `fk_mou_moa_files_mou_moa_id` 
    FOREIGN KEY (`mou_moa_id`) REFERENCES `mou_moa` (`id`) 
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migrate existing single files to the new table
-- This will preserve existing file data
INSERT INTO `mou_moa_files` (`mou_moa_id`, `file_name`, `file_path`, `is_primary`, `uploaded_at`)
SELECT `id`, `file_name`, `file_path`, 1, `created_at`
FROM `mou_moa`
WHERE `file_name` IS NOT NULL AND `file_path` IS NOT NULL
AND NOT EXISTS (
    SELECT 1 FROM `mou_moa_files` WHERE `mou_moa_files`.`mou_moa_id` = `mou_moa`.`id`
);
