-- Migration: Automatic Award Linking for Certificates
-- Adds support for automatically linking certificates to CHED awards based on eligibility criteria
-- Run this script to update your database schema

-- Step 1: Add ched_award_category column to awards table to store which CHED award the certificate is eligible for
ALTER TABLE `awards` 
ADD COLUMN IF NOT EXISTS `ched_award_category` VARCHAR(255) NULL DEFAULT NULL 
COMMENT 'CHED Award category this certificate is eligible for (e.g., Global Citizenship Award)' 
AFTER `status`;

-- Add index for better query performance
ALTER TABLE `awards`
ADD INDEX IF NOT EXISTS `idx_ched_award_category` (`ched_award_category`);

-- Step 2: Add eligibility_criteria_match column to award_analysis table to store which criteria were matched
ALTER TABLE `award_analysis`
ADD COLUMN IF NOT EXISTS `eligibility_criteria_match` JSON NULL DEFAULT NULL 
COMMENT 'JSON object storing which eligibility criteria were matched for each award' 
AFTER `analysis_metadata`;

COMMIT;

