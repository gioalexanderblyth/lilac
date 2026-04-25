-- Mobility Programs - International Awards records table
-- Applied against `lilac` database.

CREATE TABLE IF NOT EXISTS `mobility_international_awards` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `indicator_name` varchar(255) NOT NULL,
  `color_token` varchar(30) NOT NULL DEFAULT 'violet',
  `y2019` int(11) NOT NULL DEFAULT 0,
  `y2020` int(11) NOT NULL DEFAULT 0,
  `y2021` int(11) NOT NULL DEFAULT 0,
  `y2022` int(11) NOT NULL DEFAULT 0,
  `y2023` int(11) NOT NULL DEFAULT 0,
  `y2024` int(11) NOT NULL DEFAULT 0,
  `y2025_proj` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_id_deleted` (`user_id`,`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

