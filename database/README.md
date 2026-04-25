# Database Folder Guide

This folder stores SQL exports and schema migration scripts for the `lilac` database.

## Main Dumps

- `lilac (current).sql` - latest full export used as the current baseline.
- `lilac.sql` - older baseline export kept for reference/backward compatibility.

## Migration Files

- `migration_unified_upload.sql`
- `migration_mou_moa_files.sql`
- `migration_auto_award_linking.sql`
- `migration_icons2025.sql`
- `migration_mobility_program_counters.sql`
- `migration_mobility_international_awards.sql`

Apply migrations only when needed for an existing database that was not imported from the latest full export.

