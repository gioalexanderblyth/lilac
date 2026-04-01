-- Sync lesley account password with docs/README.md (Central@2025).
-- Run in phpMyAdmin or: mysql -u root lilac < database/fix_lesley_password_central2025.sql

UPDATE `users`
SET `password_hash` = '$2y$10$0gr6aMh.SCphve7yhtin.euUkRYYGXbyrBmDPtxzEWxyzXNUZ1y2m',
    `updated_at` = CURRENT_TIMESTAMP
WHERE `username` = 'lesley'
LIMIT 1;
