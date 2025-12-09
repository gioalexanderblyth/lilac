# Automatic Certificate-to-Award Linking

## Overview

This feature automatically determines if a certificate uploaded in the ICONS 2025 Awards Hub is eligible for specific CHED awards (like Global Citizenship Award) and automatically links it to the appropriate award category.

## How It Works

### 1. Eligibility Criteria Matching

When a certificate is uploaded and analyzed, the system checks the certificate text against the eligibility criteria for each CHED award. For example, the **Global Citizenship Award** requires all three criteria to be met:

1. **Ignite Intercultural Understanding** - Creates inclusive learning experiences fostering mutual respect across cultures, backgrounds, and abilities
2. **Empower Changemakers** - Students gain knowledge/skills to tackle challenges aligned with the SDGs
3. **Cultivate Active Engagement** - Provides accessible platforms for students to translate global awareness into concrete action

### 2. Automatic Linking

If a certificate matches **ALL** eligibility criteria for a specific award:
- The certificate is automatically linked to that award
- The `ched_award_category` field in the `awards` table is set to the award name (e.g., "Global Citizenship Award")
- The certificate will only appear under that specific award's tab in the Awards Hub

### 3. Certificate Display

- Certificates with a `ched_award_category` set will **only** appear under that specific award's tab
- Certificates without a `ched_award_category` will appear in all award tabs (based on keyword matching)
- Auto-linked certificates are marked with a 100% match score

## Database Schema

### New Columns Added

1. **`awards.ched_award_category`** (VARCHAR 255)
   - Stores which CHED award the certificate is eligible for
   - NULL if not eligible for any specific award
   - Example: "Global Citizenship Award"

2. **`award_analysis.eligibility_criteria_match`** (JSON)
   - Stores detailed information about which criteria were matched
   - Includes all eligible awards and matched criteria

## Installation

Run the migration script to add the necessary database columns:

```sql
-- Run this in your MySQL database
source database/migration_auto_award_linking.sql;
```

Or manually execute:

```sql
ALTER TABLE `awards` 
ADD COLUMN IF NOT EXISTS `ched_award_category` VARCHAR(255) NULL DEFAULT NULL 
COMMENT 'CHED Award category this certificate is eligible for' 
AFTER `status`;

ALTER TABLE `awards`
ADD INDEX IF NOT EXISTS `idx_ched_award_category` (`ched_award_category`);

ALTER TABLE `award_analysis`
ADD COLUMN IF NOT EXISTS `eligibility_criteria_match` JSON NULL DEFAULT NULL 
COMMENT 'JSON object storing which eligibility criteria were matched' 
AFTER `analysis_metadata`;
```

## How Eligibility is Determined

The system uses a multi-step process:

1. **Text Extraction**: OCR extracts text from the certificate
2. **Criterion Matching**: Each eligibility criterion is checked against the text
3. **Keyword Matching**: Uses keywords from `data/criteria/awards-rules.json`
4. **Phrase Detection**: Looks for key phrases related to each criterion
5. **Weighted Scoring**: Assigns weights to different keywords/phrases
6. **Final Decision**: Certificate is eligible only if **ALL** criteria are matched

### Example: Global Citizenship Award

For a certificate to be eligible for Global Citizenship Award, it must match:

- ✅ **Intercultural Understanding**: Text contains terms like "intercultural", "inclusive", "respect", "cultures", "diversity"
- ✅ **Empower Changemakers**: Text contains terms like "students", "skills", "SDG", "sustainable development", "challenges"
- ✅ **Active Engagement**: Text contains terms like "platform", "engagement", "action", "students", "global awareness"

## API Response

When a certificate is analyzed, the API response includes eligibility information:

```json
{
  "success": true,
  "award_id": 123,
  "analysis": {
    "ched_eligibility": {
      "eligible": true,
      "eligible_awards": [
        {
          "award_id": "global-citizenship",
          "award_title": "Global Citizenship Award",
          "matched_criteria": [...],
          "match_percentage": 100
        }
      ],
      "primary_award": "Global Citizenship Award"
    }
  }
}
```

## Files Modified

1. **`api/check-award-eligibility.php`** (NEW)
   - Contains functions to check eligibility against award criteria
   - `checkAwardEligibility()` - Checks single award
   - `checkAllAwardEligibility()` - Checks all awards
   - `checkCriterionMatch()` - Checks individual criterion

2. **`api/analyze-award-ocr.php`** (MODIFIED)
   - Added automatic eligibility checking after analysis
   - Updates `ched_award_category` field when eligible
   - Stores eligibility criteria match information

3. **`api/award-analysis-functions.php`** (MODIFIED)
   - Added eligibility checking in `storeAnalysisResults()`
   - Automatically links certificates to awards

4. **`api/awards-hub.php`** (MODIFIED)
   - Updated to include `ched_award_category` in queries
   - Filters certificates by `ched_award_category` in `calculateAwardStats()`
   - Only shows certificates under their linked award

5. **`database/migration_auto_award_linking.sql`** (NEW)
   - Database migration script to add new columns

## Configuration

Award eligibility criteria are defined in:
- **`data/criteria/awards-rules.json`** - Keyword weights and criteria definitions
- **`api/check-award-eligibility.php`** - Award requirements and criterion descriptions

To add a new award or modify criteria, update these files.

## Testing

To test the automatic linking:

1. Upload a certificate that mentions:
   - Intercultural understanding/inclusive learning
   - Student skills/SDGs/sustainable development
   - Student engagement/platforms/global awareness

2. Check the response - it should show `ched_eligibility.eligible: true`

3. Check the database - `awards.ched_award_category` should be set to "Global Citizenship Award"

4. Check the Awards Hub - certificate should only appear under "Global Citizenship Award" tab

## Notes

- A certificate can be eligible for multiple awards, but only the **first** (highest priority) award is set as `ched_award_category`
- All eligible awards are stored in `eligibility_criteria_match` JSON field
- Manual override is possible by directly updating the `ched_award_category` field in the database
- If eligibility checking fails, the certificate upload still succeeds (non-blocking)

