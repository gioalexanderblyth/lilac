# Files Safe to Delete - LILAC Awards System

This document lists files that are safe to delete as they have no active function in the current system.

## ✅ DEFINITELY SAFE TO DELETE

### One-Time Setup/Migration Scripts
These are one-time scripts used during initial setup or database migrations. They're no longer needed after setup:

1. **seed-enhanced-data.php** - One-time data seeding script (adds sample data)
2. **seed-multiple-users.php** - One-time data seeding script
3. **seed-sample-data.php** - One-time data seeding script
4. **setup-award-criteria.php** - One-time setup script for award_criteria table
5. **setup-missing-tables.php** - One-time migration script
6. **update-mou-table.php** - One-time migration script for MOU table
7. **update-mou-type-column.php** - One-time migration script
8. **update-criteria-keywords.php** - One-time update script

### Migration/Update Scripts (Code Already Integrated)
These JavaScript files contain code that has already been integrated into user-awards.php:

9. **update-awards-list-table.js** - Migration script (code already in user-awards.php)
10. **update-success-rate-function.js** - Migration script (code already in user-awards.php)

### Test/Verification Scripts
These are diagnostic/verification scripts, not used in production:

11. **verify-integration.php** - Test script for verifying database integration
12. **verify-fix.php** - Test script for verifying events database
13. **check-extensions.php** - Diagnostic script to check PHP extensions

### Unused API Files
14. **api/analyze-award-enhanced.php** - Not referenced anywhere in the codebase
   - The system uses `analyze-award.php` and `analyze-award-ocr.php` instead

### Duplicate/Alternative Database Schema
15. **database/schema-mysql.sql** - Alternative schema file
   - The main schema is in `database/lilac_awards.sql`
   - This appears to be an older/alternative version

## ⚠️ POTENTIALLY SAFE (But Check First)

### Award Detail Pages (Broken Links)
The files in `awards/` folder are referenced as `.html` but the actual files are `.php`:
- `awards/global-citizenship-award.php`
- `awards/outstanding-international-education-award.php`
- `awards/sustainability-award.php`
- `awards/best-asean-awareness-award.php`
- `awards/emerging-leadership-award.php`
- `awards/internationalization-leadership-award.php`
- `awards/best-ched-regional-office-award.php`
- `awards/most-promising-iro-community-award.php`

**Issue**: In `user-awards.php` line 5193-5200, these are referenced as `.html` files, but the actual files are `.php`. This means the links are broken.

**Options**:
- Fix the references to use `.php` instead of `.html` (if you want to keep these pages)
- Delete these files if the detail pages aren't needed

## 📋 SUMMARY

**Total files safe to delete: 15 files**

### Quick Delete List:
```
seed-enhanced-data.php
seed-multiple-users.php
seed-sample-data.php
setup-award-criteria.php
setup-missing-tables.php
update-mou-table.php
update-mou-type-column.php
update-criteria-keywords.php
update-awards-list-table.js
update-success-rate-function.js
verify-integration.php
verify-fix.php
check-extensions.php
api/analyze-award-enhanced.php
database/schema-mysql.sql
```

## ⚠️ IMPORTANT NOTES

1. **Backup First**: Always backup your database before deleting files
2. **Test After**: Test the system after deletion to ensure nothing breaks
3. **Award Pages**: The award detail pages in `awards/` folder have broken links - either fix the links or delete them
4. **Keep Documentation**: The `docs/` folder contains documentation - keep these files

## ✅ FILES TO KEEP (Active/Required)

- All files in `api/` folder (except analyze-award-enhanced.php)
- All main PHP pages (index.php, dashboard.php, user-awards.php, etc.)
- All JavaScript files in `js/` folder
- Database files: `lilac_awards.sql`, `logo.sql`
- Documentation: `docs/README.md`, `docs/Algo.md`
- Configuration: `api/config.php`
- Assets: All files in `assets/` folder

