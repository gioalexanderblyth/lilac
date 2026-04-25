-- Mobility Programs KPI counters table
-- Apply this to the current `lilac` database export/import.

CREATE TABLE IF NOT EXISTS `mobility_program_counters` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `metric_key` varchar(100) NOT NULL,
  `metric_value` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_metric_key` (`metric_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `mobility_program_counters` (`metric_key`, `metric_value`) VALUES
('international_awards', 0),
('active_partnerships', 0),
('outgoing_exchange_students', 0),
('incoming_exchange_students', 0),
('international_faculty_experts', 0),
('internationalization_target_met_percent', 0),
('joint_degree_programs', 0),
('transnational_centers', 0),
('international_internships', 0),
('research_grants', 0),
('scholarship_slots', 0),
('asean_event_attendees', 0),
('trend_inbound_students_percent', 0),
('trend_faculty_mobility_percent', 0),
('trend_global_awards_percent', 0)
ON DUPLICATE KEY UPDATE
`metric_key` = VALUES(`metric_key`);

