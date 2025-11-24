-- Migration: Unified File Upload System
-- Adds support for linking documents to awards and events
-- Run this script to update your database schema

-- Step 1: Add award_id and source_page to other_documents table
ALTER TABLE `other_documents` 
ADD COLUMN `award_id` INT NULL DEFAULT NULL AFTER `category`,
ADD COLUMN `source_page` ENUM('documents', 'events', 'awards') NULL DEFAULT NULL AFTER `award_id`;

-- Add foreign key constraint for award_id
ALTER TABLE `other_documents`
ADD CONSTRAINT `fk_other_documents_award_id` 
FOREIGN KEY (`award_id`) REFERENCES `awards` (`id`) 
ON DELETE SET NULL ON UPDATE CASCADE;

-- Add index for better query performance
ALTER TABLE `other_documents`
ADD INDEX `idx_award_id` (`award_id`),
ADD INDEX `idx_source_page` (`source_page`);

-- Step 2: Add document_id to events table
ALTER TABLE `events`
ADD COLUMN `document_id` INT NULL DEFAULT NULL AFTER `status`;

-- Add foreign key constraint for document_id
ALTER TABLE `events`
ADD CONSTRAINT `fk_events_document_id`
FOREIGN KEY (`document_id`) REFERENCES `other_documents` (`id`)
ON DELETE SET NULL ON UPDATE CASCADE;

-- Add index for better query performance
ALTER TABLE `events`
ADD INDEX `idx_document_id` (`document_id`);

-- Step 3: Add source_page tracking to award_analysis table (optional enhancement)
ALTER TABLE `award_analysis`
ADD COLUMN `source_page` ENUM('documents', 'events', 'awards') NULL DEFAULT 'awards' AFTER `award_id`,
ADD COLUMN `document_id` INT NULL DEFAULT NULL AFTER `source_page`;

-- Add foreign key constraint for document_id in award_analysis
ALTER TABLE `award_analysis`
ADD CONSTRAINT `fk_award_analysis_document_id`
FOREIGN KEY (`document_id`) REFERENCES `other_documents` (`id`)
ON DELETE SET NULL ON UPDATE CASCADE;

-- Add index for better query performance
ALTER TABLE `award_analysis`
ADD INDEX `idx_document_id` (`document_id`),
ADD INDEX `idx_source_page` (`source_page`);

-- Verification queries (optional - uncomment to verify)
-- SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT 
-- FROM INFORMATION_SCHEMA.COLUMNS 
-- WHERE TABLE_SCHEMA = DATABASE() 
-- AND TABLE_NAME = 'other_documents' 
-- AND COLUMN_NAME IN ('award_id', 'source_page');

-- SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT 
-- FROM INFORMATION_SCHEMA.COLUMNS 
-- WHERE TABLE_SCHEMA = DATABASE() 
-- AND TABLE_NAME = 'events' 
-- AND COLUMN_NAME = 'document_id';

