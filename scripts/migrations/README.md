# Database & One-Time Migrations

These scripts are intended to be run **once** during setup or migration.

| Script | Purpose |
|--------|---------|
| `add-event-image-column.php` | Adds `image_path` column to `events` table. Run via CLI: `php add-event-image-column.php` or visit in browser. |
| `update-users-to-admin.php` | Updates all users to admin role. Visit in browser when needed. |

**Note:** Some migrations may already be applied. Check your database schema before running.
