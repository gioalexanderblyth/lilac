-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 07, 2026 at 12:36 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `lilac`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_history`
--

CREATE TABLE `activity_history` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action_type` varchar(50) NOT NULL,
  `entity_type` varchar(50) NOT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `activity_log`
--

CREATE TABLE `activity_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `activity_log`
--

INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, NULL, 'login', NULL, '::1', NULL, '2025-11-06 00:27:33'),
(2, NULL, 'login', NULL, '::1', NULL, '2025-11-06 00:33:06'),
(3, NULL, 'login', NULL, '::1', NULL, '2025-11-06 00:48:00'),
(4, 1, 'login', NULL, '::1', NULL, '2025-11-06 01:16:36'),
(5, 1, 'login', NULL, '::1', NULL, '2025-11-06 01:25:19'),
(6, NULL, 'login', NULL, '::1', NULL, '2025-11-06 05:14:14'),
(7, NULL, 'registration', NULL, '::1', NULL, '2025-11-06 05:57:05'),
(8, NULL, 'login', NULL, '::1', NULL, '2025-11-06 05:57:21'),
(9, NULL, 'registration', NULL, '::1', NULL, '2025-11-06 06:28:46'),
(10, NULL, 'login', NULL, '::1', NULL, '2025-11-06 06:29:04'),
(11, NULL, 'login', NULL, '::1', NULL, '2025-11-06 07:27:43'),
(12, NULL, 'login', NULL, '::1', NULL, '2025-11-06 07:56:30'),
(13, 1, 'login', NULL, '::1', NULL, '2025-11-06 08:07:34'),
(14, 1, 'login', NULL, '::1', NULL, '2025-11-06 09:52:12'),
(15, NULL, 'login', NULL, '::1', NULL, '2025-11-06 10:23:29'),
(16, 1, 'login', NULL, '::1', NULL, '2025-11-06 10:30:58'),
(17, 1, 'login', NULL, '::1', NULL, '2025-11-14 13:01:02'),
(18, 1, 'login', NULL, '::1', NULL, '2025-11-14 13:01:56'),
(19, NULL, 'registration', NULL, '::1', NULL, '2025-11-14 17:12:10'),
(20, NULL, 'login', NULL, '::1', NULL, '2025-11-14 17:12:20'),
(21, NULL, 'login', NULL, '::1', NULL, '2025-11-15 15:44:57'),
(22, NULL, 'password_reset_request', 'Password reset requested', '::1', NULL, '2025-11-16 02:26:39'),
(23, NULL, 'login', NULL, '::1', NULL, '2025-11-16 02:30:30'),
(24, NULL, 'login', NULL, '::1', NULL, '2025-11-16 02:32:45'),
(25, NULL, 'password_reset_request', 'Password reset requested', '::1', NULL, '2025-11-16 02:32:58'),
(26, NULL, 'password_reset_request', 'Password reset requested', '::1', NULL, '2025-11-16 02:35:49'),
(27, NULL, 'password_reset_request', 'Password reset requested', '::1', NULL, '2025-11-16 02:37:43'),
(28, NULL, 'password_reset_request', 'Password reset requested', '::1', NULL, '2025-11-16 02:37:54'),
(29, NULL, 'password_reset_request', 'Password reset requested', '::1', NULL, '2025-11-16 02:37:58'),
(30, NULL, 'password_reset_request', 'Password reset requested', '::1', NULL, '2025-11-16 02:41:17'),
(31, NULL, 'password_reset_request', 'Password reset requested', '::1', NULL, '2025-11-16 02:41:25'),
(32, NULL, 'password_reset_request', 'Password reset requested', '::1', NULL, '2025-11-16 02:41:55'),
(33, NULL, 'password_reset_request', 'Password reset requested', '::1', NULL, '2025-11-16 03:15:51'),
(34, NULL, 'password_reset_request', 'Password reset requested', '::1', NULL, '2025-11-16 03:15:55'),
(35, NULL, 'password_reset_request', 'Password reset requested', '::1', NULL, '2025-11-16 03:16:01'),
(36, NULL, 'password_reset_request', 'Password reset requested', '::1', NULL, '2025-11-16 03:18:22'),
(37, NULL, 'password_reset_request', 'Password reset requested', '::1', NULL, '2025-11-16 03:18:29'),
(38, NULL, 'password_reset_request', 'Password reset requested', '::1', NULL, '2025-11-16 03:21:32'),
(39, NULL, 'password_reset_request', 'Password reset requested', '::1', NULL, '2025-11-16 03:32:10'),
(40, NULL, 'login', NULL, '::1', NULL, '2025-11-16 03:33:04'),
(41, NULL, 'login', NULL, '::1', NULL, '2025-11-18 05:27:28'),
(42, 1, 'login', NULL, '::1', NULL, '2025-11-18 06:12:10'),
(43, NULL, 'login', NULL, '::1', NULL, '2025-11-18 06:16:28'),
(44, NULL, 'login', NULL, '::1', NULL, '2025-11-18 12:44:10'),
(45, 1, 'login', NULL, '::1', NULL, '2025-11-18 14:52:52'),
(46, NULL, 'login', NULL, '::1', NULL, '2025-11-18 14:58:06'),
(47, 1, 'login', NULL, '::1', NULL, '2025-11-18 14:58:52'),
(48, NULL, 'login', NULL, '::1', NULL, '2025-11-18 14:59:28'),
(49, 1, 'login', NULL, '::1', NULL, '2025-11-18 15:43:58'),
(50, NULL, 'login', NULL, '::1', NULL, '2025-11-18 15:44:48'),
(51, 1, 'login', NULL, '::1', NULL, '2025-11-18 16:10:34'),
(52, NULL, 'login', NULL, '::1', NULL, '2025-11-18 22:59:57'),
(53, 13, 'registration', NULL, '::1', NULL, '2025-11-18 23:01:32'),
(54, 13, 'login', NULL, '::1', NULL, '2025-11-18 23:01:43'),
(55, 13, 'login', NULL, '::1', NULL, '2025-11-18 23:11:06'),
(56, 13, 'login', NULL, '::1', NULL, '2025-11-19 04:08:42'),
(57, 13, 'login', NULL, '::1', NULL, '2025-11-19 04:44:41'),
(58, 13, 'login', NULL, '::1', NULL, '2025-11-24 00:52:30'),
(59, 13, 'login', NULL, '::1', NULL, '2025-12-01 04:07:59'),
(60, 13, 'login', NULL, '::1', NULL, '2025-12-03 00:43:38'),
(61, 13, 'login', NULL, '::1', NULL, '2026-01-07 07:03:17'),
(62, 13, 'login', NULL, '::1', NULL, '2026-01-07 07:59:03');

-- --------------------------------------------------------

--
-- Table structure for table `awards`
--

CREATE TABLE `awards` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `ocr_text` longtext DEFAULT NULL,
  `file_type` varchar(50) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `award_date` date DEFAULT NULL,
  `status` enum('pending','analyzed','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `award_analysis`
--

CREATE TABLE `award_analysis` (
  `id` int(11) NOT NULL,
  `award_id` int(11) NOT NULL,
  `source_page` enum('documents','events','awards') DEFAULT 'awards',
  `document_id` int(11) DEFAULT NULL,
  `event_id` int(11) DEFAULT NULL,
  `predicted_category` varchar(255) DEFAULT NULL,
  `match_percentage` decimal(5,2) DEFAULT NULL,
  `confidence` varchar(20) DEFAULT 'medium',
  `status` enum('Eligible','Almost Eligible','Not Eligible') DEFAULT 'Not Eligible',
  `detected_text` longtext DEFAULT NULL,
  `matched_keywords` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`matched_keywords`)),
  `all_matches` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`all_matches`)),
  `recommendations` text DEFAULT NULL,
  `analysis_metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`analysis_metadata`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `award_categories`
--

CREATE TABLE `award_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `keywords` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`keywords`)),
  `total_weight` int(11) DEFAULT 100,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `award_categories`
--

INSERT INTO `award_categories` (`id`, `name`, `description`, `keywords`, `total_weight`, `created_at`, `updated_at`) VALUES
(1, 'Global Citizenship Award', 'Recognition for fostering global citizenship and international understanding', '{\"keywords\": {\"global\": 15, \"citizenship\": 15, \"international\": 12, \"intercultural\": 12, \"exchange\": 10, \"diversity\": 10, \"culture\": 8, \"understanding\": 8, \"collaboration\": 7, \"partnership\": 5}, \"phrases\": {\"global citizenship\": 20, \"intercultural understanding\": 18, \"international collaboration\": 15, \"cultural diversity\": 12, \"student exchange\": 10}}', 100, '2025-11-06 00:27:13', '2025-11-06 00:27:13'),
(2, 'Best ASEAN Awareness Award', 'Excellence in promoting ASEAN awareness and regional integration', '{\"keywords\": {\"asean\": 20, \"southeast\": 12, \"regional\": 12, \"integration\": 10, \"awareness\": 10, \"cooperation\": 8, \"community\": 8, \"partnership\": 7, \"unity\": 7, \"solidarity\": 6}, \"phrases\": {\"asean awareness\": 25, \"regional integration\": 15, \"asean community\": 15, \"southeast asia\": 12, \"regional cooperation\": 10}}', 100, '2025-11-06 00:27:13', '2025-11-06 00:27:13'),
(3, 'Internationalization Leadership Award', 'Outstanding leadership in internationalization initiatives', '{\"keywords\": {\"leadership\": 15, \"internationalization\": 15, \"strategy\": 12, \"innovation\": 10, \"development\": 10, \"excellence\": 8, \"vision\": 8, \"transformation\": 7, \"impact\": 7, \"advancement\": 6}, \"phrases\": {\"internationalization strategy\": 20, \"strategic leadership\": 15, \"institutional development\": 12, \"international excellence\": 12, \"transformative leadership\": 10}}', 100, '2025-11-06 00:27:13', '2025-11-06 00:27:13'),
(4, 'Outstanding International Education Award', 'Excellence in international education programs and initiatives', '{\"keywords\": {\"education\": 15, \"international\": 15, \"academic\": 12, \"program\": 10, \"quality\": 10, \"excellence\": 8, \"curriculum\": 8, \"learning\": 7, \"teaching\": 7, \"innovation\": 6}, \"phrases\": {\"international education\": 20, \"academic excellence\": 15, \"educational innovation\": 12, \"quality education\": 12, \"learning outcomes\": 10}}', 100, '2025-11-06 00:27:13', '2025-11-06 00:27:13'),
(5, 'Most Promising IRO/Community Award', 'Recognition for promising International Relations Office or community initiatives', '{\"keywords\": {\"community\": 15, \"engagement\": 12, \"outreach\": 12, \"partnership\": 10, \"development\": 10, \"impact\": 8, \"initiative\": 8, \"collaboration\": 7, \"service\": 7, \"support\": 6}, \"phrases\": {\"community engagement\": 20, \"international relations\": 18, \"community development\": 15, \"stakeholder engagement\": 12, \"partnership building\": 10}}', 100, '2025-11-06 00:27:13', '2025-11-06 00:27:13'),
(6, 'Emerging Leadership in Internationalization Award', 'Recognition for emerging leaders in internationalization', '{\"keywords\": {\"emerging\": 15, \"leadership\": 15, \"potential\": 12, \"innovation\": 10, \"growth\": 10, \"development\": 8, \"initiative\": 8, \"vision\": 7, \"impact\": 7, \"commitment\": 6}, \"phrases\": {\"emerging leader\": 20, \"leadership potential\": 15, \"innovative approach\": 12, \"professional growth\": 12, \"future leader\": 10}}', 100, '2025-11-06 00:27:13', '2025-11-06 00:27:13'),
(7, 'Best CHED Regional Office for Internationalization Award', 'Excellence among CHED regional offices in internationalization', '{\"keywords\": {\"ched\": 20, \"regional\": 15, \"office\": 12, \"governance\": 10, \"policy\": 10, \"implementation\": 8, \"coordination\": 8, \"support\": 7, \"development\": 7, \"excellence\": 6}, \"phrases\": {\"ched regional office\": 25, \"policy implementation\": 15, \"regional coordination\": 12, \"institutional support\": 12, \"governance excellence\": 10}}', 100, '2025-11-06 00:27:13', '2025-11-06 00:27:13'),
(8, 'Sustainability in Internationalization Award', 'Recognition for sustainable internationalization practices', '{\"keywords\": {\"sustainability\": 20, \"sustainable\": 15, \"environment\": 12, \"green\": 10, \"impact\": 10, \"development\": 8, \"responsibility\": 8, \"future\": 7, \"practice\": 7, \"goals\": 6}, \"phrases\": {\"sustainable development\": 20, \"environmental sustainability\": 18, \"sustainable practices\": 15, \"long-term impact\": 12, \"sustainability goals\": 10}}', 100, '2025-11-06 00:27:13', '2025-11-06 00:27:13');

-- --------------------------------------------------------

--
-- Table structure for table `award_criteria`
--

CREATE TABLE `award_criteria` (
  `id` int(11) NOT NULL,
  `category_name` varchar(255) NOT NULL,
  `award_type` enum('Individual','Institutional','Regional') DEFAULT 'Institutional',
  `department` varchar(100) DEFAULT NULL,
  `description` text NOT NULL,
  `requirements` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`requirements`)),
  `keywords` varchar(500) DEFAULT NULL,
  `min_match_percentage` int(11) DEFAULT 60,
  `weight` int(11) DEFAULT 5,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_by` int(11) DEFAULT NULL,
  `assignee_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `award_criteria`
--

INSERT INTO `award_criteria` (`id`, `category_name`, `award_type`, `department`, `description`, `requirements`, `keywords`, `min_match_percentage`, `weight`, `status`, `created_by`, `assignee_id`, `created_at`, `updated_at`) VALUES
(9, 'Emerging Leadership Award', 'Individual', NULL, 'This award celebrates these rising leaders who are shaping the future of internationalization and making a significant difference in our increasingly interconnected world.', '[\"leads innovative\"]', 'leadership, leadership accessibility institution, inspiring leadership, internationalization efforts, dedication, innovative practices', 60, 6, 'active', 12, NULL, '2025-11-18 11:57:26', '2025-12-09 05:59:19'),
(10, 'Global Citizenship Award', 'Individual', NULL, 'Honors changemakers who model global citizenship—fostering cross-border understanding, advancing social responsibility, and driving measurable impact that improves lives worldwide.\n', '[\"programs ignite\"]', 'global, citizenship, culturally inclusive programs, responsible global citizens, community, international, sustainability, vision, institution\'s global engagement.', 60, 5, 'active', 12, NULL, '2025-11-18 14:20:04', '2025-12-09 05:59:29'),
(11, 'Sustainability Award', 'Institutional', NULL, 'The Sustainability Award recognizes and honors higher education institutions that demonstrate outstanding commitment to sustainability. We seek to highlight programs and initiatives that exemplify pioneering integration, impactful projects, and long-term commitment.\n\n', '[\"Demonstrated outstanding commitment to sustainability initiatives\",\"Implementation of pioneering or innovative sustainability programs\",\"Evidence of impactful environmental projects within the institution or community\",\"Long-term commitment to environmental stewardship and sustainability practices\",\"Active participation in community engagement or outreach related to sustainability\",\"Submission of documentation showing measurable outcomes or improvements\",\"Integration of sustainability principles into institutional operations or activities\"]', 'dedication, vision, innovative', 60, 5, 'active', 13, NULL, '2025-12-09 03:16:42', '2025-12-09 03:23:13'),
(12, 'Outstanding International Education Program Awards', 'Institutional', NULL, 'The Outstanding International Education Program Awards honor higher education institutions that champion inclusive internationalization. We recognize programs that expand access to global opportunites, foster collaborative innovation, and embrace inclusivity and beyond.\n\n', '[\"excellence\"]', 'international education, global learning, strong global partnerships, exchange programs, cross-cultural engagement , global competence, collaboration, academic exchange, global engagement', 60, 5, 'active', 13, NULL, '2025-12-09 03:42:34', '2025-12-09 05:59:08'),
(13, 'Best ASEAN Awareness Initiative Awards', 'Institutional', NULL, 'The Best ASEAN Awareness Initiative Award honors higher education institutions that have implemented initiatives that boost understanding and awareness of the Association of Southeast Asian Nations (ASEAN). We recognize programs that promote regional identity and solidarity, cross-cultural initiative programs, and measurable outreach and sustained commitment.', '[\"awareness\"]', 'ASEAN awareness, regional identity, integration, cross-cultural, ASEAN cooperation', 60, 5, 'active', 13, NULL, '2025-12-09 03:53:50', '2025-12-09 05:56:31'),
(14, 'Internationalization Leadership Award', 'Individual', NULL, 'The Internationalization (IZN) Leadership Award honors transformative leaders who are shaping the future of Philippine Higher Education Leaders who demonstrate excellence in strategic vision and integration; ethical leadership and governance; and sustained impact and development.\n\nThis award celebrates leaders who embody resilience, humility, and a development-driven mindset. They are the architects of a more connected and impactful higher education landscape.', '[\"Demonstrate\"]', 'exceptional leadership, innovative strategies, impactful global initiatives, international partnerships, institution’s global standing', 60, 5, 'active', 13, NULL, '2025-12-09 04:07:09', '2025-12-09 05:58:14'),
(15, 'Most Promising Regional IRO Community Award', 'Regional', NULL, 'This recognizes the Regional IRO Community, or Group, or Network, or Association that demonstrates  the most significant vision, early progress, and clear potential to strengthen internationalization capacity among Higher Education Institutions (HEIs) within its region. This award celebrates effective collaboration, resource-sharing, and future impact, honoring communities that are paving the way for a more connected and skilled regional internationalization ecosystem.\n\n', '[\"Global\"]', 'active collaboration, community-driven initiatives, international relations, global engagement, excellence in internationalization', 60, 5, 'active', 13, NULL, '2025-12-09 04:11:18', '2025-12-09 05:57:56'),
(16, 'No Poverty SDG', 'Individual', NULL, 'No poverty', '[\"outreach\"]', 'outreach, community extension', 60, 5, 'active', 13, NULL, '2025-12-09 06:18:22', '2025-12-09 06:18:22');

-- --------------------------------------------------------

--
-- Table structure for table `award_event_links`
--

CREATE TABLE `award_event_links` (
  `id` int(11) NOT NULL,
  `award_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `award_statistics`
--

CREATE TABLE `award_statistics` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `stat_date` date NOT NULL,
  `total_awards` int(11) DEFAULT 0,
  `eligible_count` int(11) DEFAULT 0,
  `almost_eligible_count` int(11) DEFAULT 0,
  `not_eligible_count` int(11) DEFAULT 0,
  `avg_match_percentage` decimal(5,2) DEFAULT NULL,
  `category_distribution` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`category_distribution`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `name`, `code`, `description`, `status`, `created_at`, `updated_at`) VALUES
(1, 'College of Engineering', 'COE', 'Engineering programs and research', 'active', '2025-11-06 05:42:17', '2025-11-06 05:42:17'),
(2, 'College of Computer Studies', 'CCS', 'Computer science and IT programs', 'active', '2025-11-06 05:42:17', '2025-11-06 05:42:17'),
(3, 'College of Business Administration', 'CBA', 'Business and management programs', 'active', '2025-11-06 05:42:17', '2025-11-06 05:42:17'),
(4, 'College of Arts and Sciences', 'CAS', 'Liberal arts and sciences', 'active', '2025-11-06 05:42:17', '2025-11-06 05:42:17'),
(5, 'College of Education', 'COEd', 'Education and teacher training', 'active', '2025-11-06 05:42:17', '2025-11-06 05:42:17'),
(6, 'College of Law', 'COL', 'Law school programs', 'active', '2025-11-06 05:42:17', '2025-11-06 05:42:17'),
(7, 'College of Medicine', 'COM', 'Medical programs', 'active', '2025-11-06 05:42:17', '2025-11-06 05:42:17'),
(8, 'Graduate School', 'GS', 'Graduate programs', 'active', '2025-11-06 05:42:17', '2025-11-06 05:42:17'),
(9, 'Research and Development', 'R&D', 'Research department', 'active', '2025-11-06 05:42:17', '2025-11-06 05:42:17'),
(10, 'Administration', 'ADMIN', 'Administrative department', 'active', '2025-11-06 05:42:17', '2025-11-06 05:42:17');

-- --------------------------------------------------------

--
-- Table structure for table `documents`
--

CREATE TABLE `documents` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `file_type` varchar(50) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `status` enum('active','archived','deleted') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `event_date` date DEFAULT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `status` enum('planned','ongoing','completed','cancelled') DEFAULT 'planned',
  `document_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`id`, `user_id`, `title`, `description`, `event_date`, `start_time`, `end_time`, `location`, `image_path`, `status`, `document_id`, `created_at`, `updated_at`, `deleted_at`) VALUES
(31, 13, 'Test 1', '', '2025-12-10', '00:00:00', '00:30:00', 'AVR', NULL, 'planned', NULL, '2025-12-09 06:09:11', '2026-01-07 07:25:27', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `mou_moa`
--

CREATE TABLE `mou_moa` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `institution` varchar(255) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `contact_email` varchar(255) DEFAULT NULL,
  `term` varchar(100) DEFAULT NULL,
  `sign_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('Active','Expired','Expires Soon','Pending') DEFAULT 'Active',
  `renewal_confirmed` tinyint(1) DEFAULT 0,
  `renewal_confirmed_at` timestamp NULL DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `partner` varchar(255) DEFAULT NULL,
  `type` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `type` enum('mou_expiring','mou_expiring_soon','mou_expired','event_upcoming','event_today','system') DEFAULT 'system',
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `related_id` int(11) DEFAULT NULL,
  `related_type` varchar(50) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `read_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `message`, `related_id`, `related_type`, `is_read`, `created_at`, `read_at`) VALUES
(1, NULL, 'event_upcoming', 'Upcoming Event: FINAL DEFENSE', 'The event \'FINAL DEFENSE\' is happening in 4 day(s) on November 19, 2025 at 12:00 AM at Central Philippine University, Iloilo City, Philippines.', 3, 'event', 0, '2025-11-15 02:18:45', NULL),
(2, NULL, 'event_today', 'Event Today: LUNCH', 'The event \'LUNCH\' is happening today at 12:00 AM at Sugbaha ko Gha.', 4, 'event', 0, '2025-11-15 02:30:18', NULL),
(3, NULL, 'event_today', 'Event Today: TEST', 'The event \'TEST\' is happening today at 12:00 AM at Santa Barbara, Iloilo, Philippines.', 5, 'event', 0, '2025-11-15 02:53:24', NULL),
(4, NULL, 'event_today', 'Event Today: Test', 'The event \'Test\' is happening today at 12:00 AM at Central Philippine University, Iloilo City, Philippines.', 6, 'event', 0, '2025-11-15 02:58:34', NULL),
(5, NULL, 'event_today', 'Event Today: Test', 'The event \'Test\' is happening today at 12:00 AM at Central Philippine University, Iloilo City, Philippines.', 7, 'event', 0, '2025-11-15 04:01:46', NULL),
(6, NULL, 'mou_expired', 'MOU/MOA Expired: Central', 'The MOU/MOA with Central expired on November 15, 2025.', 25, 'mou_moa', 0, '2025-11-15 05:38:36', NULL),
(7, NULL, 'mou_expired', 'MOU/MOA Expired: TEST', 'The MOU/MOA with TEST expired on November 16, 2025.', 26, 'mou_moa', 0, '2025-11-16 01:22:27', NULL),
(8, NULL, 'mou_expiring', 'MOU/MOA Expiring Soon: TEST', 'The MOU/MOA with TEST will expire in 2 day(s) on November 20, 2025.', 27, 'mou_moa', 0, '2025-11-18 16:09:12', NULL),
(9, NULL, 'mou_expiring', 'MOU/MOA Expiring Soon: kuma', 'The MOU/MOA with kuma will expire in 1 day(s) on November 20, 2025.', 28, 'mou_moa', 0, '2025-11-19 02:41:06', NULL),
(10, NULL, 'event_today', 'Event Today: FINAL DEFENSE', 'The event \'FINAL DEFENSE\' is happening today at 3:00 AM at Central Philippine University, Iloilo City, Philippines.', 8, 'event', 0, '2025-11-19 02:54:06', NULL),
(11, NULL, 'mou_expiring', 'MOU/MOA Expiring Soon: xyz', 'The MOU/MOA with xyz will expire in 1 day(s) on November 20, 2025.', 29, 'mou_moa', 0, '2025-11-19 04:24:44', NULL),
(12, NULL, 'event_upcoming', 'Upcoming Event: test', 'The event \'test\' is happening in 7 day(s) on December 3, 2025 at 12:00 AM.', 12, 'event', 0, '2025-11-26 02:12:13', NULL),
(13, NULL, 'event_upcoming', 'Upcoming Event: test', 'The event \'test\' is happening in 7 day(s) on December 4, 2025 at 12:00 AM.', 17, 'event', 0, '2025-11-27 01:24:46', NULL),
(14, NULL, 'event_upcoming', 'Upcoming Event: test', 'The event \'test\' is happening in 7 day(s) on December 4, 2025 at 12:00 AM.', 18, 'event', 0, '2025-11-27 01:35:32', NULL),
(15, NULL, 'event_upcoming', 'Upcoming Event: test', 'The event \'test\' is happening in 7 day(s) on December 4, 2025 at 12:00 AM.', 19, 'event', 0, '2025-11-27 01:42:22', NULL),
(16, NULL, 'event_upcoming', 'Upcoming Event: test', 'The event \'test\' is happening in 7 day(s) on December 4, 2025 at 12:00 AM.', 20, 'event', 0, '2025-11-27 01:44:48', NULL),
(17, NULL, 'event_upcoming', 'Upcoming Event: test', 'The event \'test\' is happening in 7 day(s) on December 4, 2025 at 12:45 AM.', 21, 'event', 0, '2025-11-27 01:48:10', NULL),
(18, NULL, 'event_upcoming', 'Upcoming Event: test', 'The event \'test\' is happening in 7 day(s) on December 4, 2025 at 12:00 AM.', 22, 'event', 0, '2025-11-27 01:48:55', NULL),
(19, NULL, 'event_upcoming', 'Upcoming Event: test A', 'The event \'test A\' is happening in 7 day(s) on December 4, 2025 at 12:00 AM.', 23, 'event', 0, '2025-11-27 01:50:36', NULL),
(20, NULL, 'event_upcoming', 'Upcoming Event: test b', 'The event \'test b\' is happening in 7 day(s) on December 4, 2025 at 12:00 AM.', 24, 'event', 0, '2025-11-27 02:03:19', NULL),
(21, NULL, 'event_upcoming', 'Upcoming Event: tst', 'The event \'tst\' is happening in 7 day(s) on December 4, 2025 at 12:00 AM.', 25, 'event', 0, '2025-11-27 06:33:48', NULL),
(22, NULL, 'event_upcoming', 'Upcoming Event: stest', 'The event \'stest\' is happening in 1 day(s) on December 4, 2025 at 12:00 AM at Central Philippine University, Iloilo City, Philippines.', 26, 'event', 0, '2025-12-03 00:19:02', NULL),
(23, NULL, 'event_today', 'Event Today: tst', 'The event \'tst\' is happening today at 12:00 AM.', 27, 'event', 0, '2025-12-03 00:19:52', NULL),
(24, NULL, 'mou_expiring', 'MOU/MOA Expiring Soon: test', 'The MOU/MOA with test will expire in 1 day(s) on December 4, 2025.', 36, 'mou_moa', 0, '2025-12-03 00:27:51', NULL),
(26, NULL, 'event_upcoming', 'Upcoming Event: test', 'The event \'test\' is happening in 1 day(s) on December 10, 2025 at 12:00 AM at test.', 28, 'event', 0, '2025-12-09 01:56:19', NULL),
(27, NULL, 'event_upcoming', 'Upcoming Event: try', 'The event \'try\' is happening in 1 day(s) on December 10, 2025 at 12:00 AM at AVR.', 29, 'event', 0, '2025-12-09 01:57:48', NULL),
(28, NULL, 'event_upcoming', 'Upcoming Event: test', 'The event \'test\' is happening in 1 day(s) on December 10, 2025 at 12:00 AM at tst.', 30, 'event', 0, '2025-12-09 02:35:22', NULL),
(29, NULL, 'event_upcoming', 'Upcoming Event: Test 1', 'The event \'Test 1\' is happening in 1 day(s) on December 10, 2025 at 12:00 AM at AVR.', 31, 'event', 0, '2025-12-09 06:14:50', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `notification_confirmations`
--

CREATE TABLE `notification_confirmations` (
  `id` int(11) NOT NULL,
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `renewal_status` enum('renewed','not_renewed') NOT NULL,
  `confirmed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notification_confirmations`
--

INSERT INTO `notification_confirmations` (`id`, `notification_id`, `user_id`, `renewal_status`, `confirmed_at`) VALUES
(1, 8, 1, 'not_renewed', '2025-11-18 22:54:12'),
(2, 9, 1, 'renewed', '2025-11-19 02:42:31');

-- --------------------------------------------------------

--
-- Table structure for table `notification_reads`
--

CREATE TABLE `notification_reads` (
  `id` int(11) NOT NULL,
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `read_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notification_reads`
--

INSERT INTO `notification_reads` (`id`, `notification_id`, `user_id`, `read_at`) VALUES
(15, 7, 1, '2025-11-18 15:44:31'),
(26, 8, 1, '2025-11-19 02:22:34'),
(30, 1, 1, '2025-11-19 02:22:41'),
(31, 2, 1, '2025-11-19 02:22:41'),
(32, 3, 1, '2025-11-19 02:22:41'),
(33, 4, 1, '2025-11-19 02:22:41'),
(34, 5, 1, '2025-11-19 02:22:41'),
(35, 6, 1, '2025-11-19 02:22:41'),
(36, 1, 13, '2025-12-01 11:23:14'),
(37, 2, 13, '2025-12-01 11:23:14'),
(38, 3, 13, '2025-12-01 11:23:14'),
(39, 4, 13, '2025-12-01 11:23:14'),
(40, 5, 13, '2025-12-01 11:23:14'),
(41, 6, 13, '2025-12-01 11:23:14'),
(42, 7, 13, '2025-12-01 11:23:14'),
(43, 8, 13, '2025-12-01 11:23:14'),
(44, 9, 13, '2025-12-01 11:23:14'),
(45, 10, 13, '2025-12-01 11:23:14'),
(46, 11, 13, '2025-12-01 11:23:14'),
(47, 12, 13, '2025-12-01 11:23:14'),
(48, 13, 13, '2025-12-01 11:23:14'),
(49, 14, 13, '2025-12-01 11:23:14'),
(50, 15, 13, '2025-12-01 11:23:14'),
(51, 16, 13, '2025-12-01 11:23:14'),
(52, 17, 13, '2025-12-01 11:23:14'),
(53, 18, 13, '2025-12-01 11:23:14'),
(54, 19, 13, '2025-12-01 11:23:14'),
(55, 20, 13, '2025-12-01 11:23:14'),
(56, 21, 13, '2025-12-01 11:23:14'),
(57, 22, 13, '2025-12-03 00:20:13'),
(58, 23, 13, '2025-12-03 00:30:29'),
(61, 24, 13, '2025-12-03 00:36:43'),
(62, 29, 13, '2025-12-09 06:22:36');

-- --------------------------------------------------------

--
-- Table structure for table `other_documents`
--

CREATE TABLE `other_documents` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `category` varchar(100) DEFAULT 'Other Documents',
  `award_id` int(11) DEFAULT NULL,
  `source_page` enum('documents','events','awards') DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `schedules`
--

CREATE TABLE `schedules` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `scheduled_date` date NOT NULL,
  `scheduled_time` time DEFAULT NULL,
  `status` enum('scheduled','completed','cancelled') DEFAULT 'scheduled',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `session_token` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sessions`
--

INSERT INTO `sessions` (`id`, `user_id`, `session_token`, `ip_address`, `user_agent`, `created_at`, `expires_at`, `is_active`) VALUES
(4, 1, '9b8c32c6b4a344238f3bc021d8f2dbd611cc3d9ad4a64653144b16cf5e9f0472', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-06 01:16:36', '2025-11-06 18:16:36', 1),
(5, 1, '259ffbe84b47eeb0dcfdb2077358b01b9d5e1a182248d36b94f9f173912cf423', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-06 01:25:19', '2025-11-06 18:25:19', 1),
(11, 1, '467abb84b1889b45ca583976943f869e48ab8026e0a6934e60af3ee04031653a', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-06 08:07:34', '2025-11-07 01:07:34', 1),
(12, 1, '352c385365f40817cf7c62b3b3bb87175c5a2a38cbc4d889c8a28754041158ab', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-06 09:52:12', '2025-11-07 02:52:12', 1),
(14, 1, 'd71e312c43f242277e9be1a8a8529512ed763e919c04defda9476a1861ae9033', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-06 10:30:58', '2025-11-07 03:30:58', 1),
(15, 1, 'c4e9e3f1bc3cc9d73541ddb00b69db232281679ddc1ed4b8748f29017058663f', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-14 13:01:02', '2025-11-15 06:01:02', 1),
(16, 1, '2ecc72847cbe60a028b8b08e11859491a534361974052245e5dd559b8ed2bdbe', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-14 13:01:56', '2025-11-15 06:01:56', 1),
(23, 1, 'a041f5501d2802e186e393f0635054d2d2c3fa924751bd171f5a815faec41333', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-18 06:12:10', '2025-11-18 23:12:10', 1),
(26, 1, '3b72c9af7b429a0771bc351074ac831b2f3844be09cb363877c2bf5f773db543', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-18 14:52:52', '2025-11-19 07:52:52', 1),
(28, 1, '254a63857b05a4adc4eba94861d950527598f1bfd4d344879e770c98f62072a0', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-18 14:58:52', '2025-11-19 07:58:52', 1),
(30, 1, 'e82f9d5f359bfdf0d6d76a27d2c472b478451b5ef9e1bde60856e69711c9a300', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-18 15:43:58', '2025-11-19 08:43:58', 1),
(32, 1, 'd467a2ee6eb8372a7d2fec7753124159075bbecea316f23cc12406c3d0706b6b', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-18 16:10:34', '2025-11-19 09:10:34', 1),
(34, 13, 'c7ea509e4a9fee37d8be1b7791c365d3a5abd8e46102dbfc7e4253bce50f5c8d', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-18 23:01:43', '2025-11-19 16:01:43', 1),
(35, 13, '557a17a1f8740bd56f0d1a37ee4c35b9cc76d3ed392189cc2a5d00dc1310a86a', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-18 23:11:06', '2025-11-19 16:11:06', 1),
(36, 13, 'e77e139a8d62bd66f3000c59647fa3eab0f7d987fec58198c7526f8d2e5d12d2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 04:08:42', '2025-11-19 21:08:42', 1),
(37, 13, '3f4b8e64562bbd5ba2933472a070f0283faa0725fbe395671f7fd5b3e82c427e', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 04:44:41', '2025-11-19 21:44:41', 1),
(38, 13, '30dd058e5e8fc191d58eaa0e4f4a65ff759d7b343d5eeb8fa97222b235143b06', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-24 00:52:30', '2025-11-24 17:52:30', 1),
(39, 13, '5188e86de5770527653dbefa1d8daf595c8725953d8f63d0dfd04171a989fe89', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-01 04:07:59', '2025-12-01 21:07:59', 1),
(40, 13, '3e3e953c8ce6472fa5bc9fd022af0e91b07c30c638ab52bc74da8876ff21b00a', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-03 00:43:38', '2025-12-03 17:43:38', 1),
(41, 13, 'b1766de8b39d6a87553e6f24ed06c640420e3fbc0bb4dde72d07cb1ce19288a0', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-07 07:03:17', '2026-01-08 00:03:17', 1),
(42, 13, '23f3ed530d64bc2d92d2fc109d3793aab85255dd9a3fef30f3424bb459055612', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-07 07:59:03', '2026-02-06 00:59:03', 1);

-- --------------------------------------------------------

--
-- Table structure for table `site_settings`
--

CREATE TABLE `site_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` longblob DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_token_expires` datetime DEFAULT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `role` enum('admin','user','viewer') DEFAULT 'user',
  `status` enum('active','inactive','suspended') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `phone`, `password_hash`, `reset_token`, `reset_token_expires`, `full_name`, `profile_picture`, `department`, `role`, `status`, `created_at`, `updated_at`, `last_login`) VALUES
(1, 'admin', 'admin@cpu.edu.ph', NULL, '$2y$10$sVoVWIn8azmDdAgCBHXR3eTQSbbVqqBoYFs6SJ8THBXahh9kqaWiq', NULL, NULL, 'System Administrator', NULL, 'College of Arts and Sciences', 'admin', 'active', '2025-11-06 00:27:13', '2025-11-18 16:10:34', '2025-11-18 16:10:34'),
(13, 'lesley', 'lesley.dignadice@cpu.edu.ph', NULL, '$2y$10$GcBInWiG0QehcKJA3dzc4eVJPssxF8rqoC/LAEZvt3p8I10.QFse6', NULL, NULL, 'Lesley Dignadice', NULL, 'Administration', 'admin', 'active', '2025-11-18 23:01:32', '2026-01-07 07:59:03', '2026-01-07 07:59:03');

-- --------------------------------------------------------

--
-- Table structure for table `user_preferences`
--

CREATE TABLE `user_preferences` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `preference_key` varchar(100) NOT NULL,
  `preference_value` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_preferences`
--

INSERT INTO `user_preferences` (`id`, `user_id`, `preference_key`, `preference_value`, `created_at`, `updated_at`) VALUES
(22, 1, 'ched_requirement_tracker', '{\"videos\":false,\"supporting-docs\":false,\"nomination-form\":false,\"impact-metrics\":false,\"deadline-plan\":false}', '2025-11-18 14:53:00', '2025-11-18 14:53:00'),
(23, 13, 'ched_requirement_tracker', '{\"videos\":false,\"supporting-docs\":false,\"nomination-form\":false,\"impact-metrics\":false,\"deadline-plan\":false}', '2025-11-18 23:12:31', '2025-11-24 01:56:19');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_history`
--
ALTER TABLE `activity_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_entity_type` (`entity_type`),
  ADD KEY `idx_entity_id` (`entity_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `awards`
--
ALTER TABLE `awards`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `award_analysis`
--
ALTER TABLE `award_analysis`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_award_id` (`award_id`),
  ADD KEY `idx_predicted_category` (`predicted_category`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_match_percentage` (`match_percentage`),
  ADD KEY `idx_document_id` (`document_id`),
  ADD KEY `idx_source_page` (`source_page`);

--
-- Indexes for table `award_categories`
--
ALTER TABLE `award_categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_name` (`name`);

--
-- Indexes for table `award_criteria`
--
ALTER TABLE `award_criteria`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_category_name` (`category_name`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_award_type` (`award_type`);

--
-- Indexes for table `award_event_links`
--
ALTER TABLE `award_event_links`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_award_id` (`award_id`),
  ADD KEY `idx_event_id` (`event_id`);

--
-- Indexes for table `award_statistics`
--
ALTER TABLE `award_statistics`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_stat_date` (`stat_date`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_event_date` (`event_date`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_document_id` (`document_id`);

--
-- Indexes for table `mou_moa`
--
ALTER TABLE `mou_moa`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_is_read` (`is_read`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_type` (`type`);

--
-- Indexes for table `notification_confirmations`
--
ALTER TABLE `notification_confirmations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_notification_confirmation` (`notification_id`,`user_id`),
  ADD KEY `idx_notification_id` (`notification_id`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indexes for table `notification_reads`
--
ALTER TABLE `notification_reads`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_notification` (`notification_id`,`user_id`),
  ADD KEY `idx_notification_id` (`notification_id`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indexes for table `other_documents`
--
ALTER TABLE `other_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_award_id` (`award_id`),
  ADD KEY `idx_source_page` (`source_page`);

--
-- Indexes for table `schedules`
--
ALTER TABLE `schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `session_token` (`session_token`),
  ADD KEY `idx_session_token` (`session_token`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_expires_at` (`expires_at`);

--
-- Indexes for table `site_settings`
--
ALTER TABLE `site_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `user_preferences`
--
ALTER TABLE `user_preferences`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_preference` (`user_id`,`preference_key`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_history`
--
ALTER TABLE `activity_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=63;

--
-- AUTO_INCREMENT for table `awards`
--
ALTER TABLE `awards`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=184;

--
-- AUTO_INCREMENT for table `award_analysis`
--
ALTER TABLE `award_analysis`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=180;

--
-- AUTO_INCREMENT for table `award_categories`
--
ALTER TABLE `award_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `award_criteria`
--
ALTER TABLE `award_criteria`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `award_event_links`
--
ALTER TABLE `award_event_links`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `award_statistics`
--
ALTER TABLE `award_statistics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `documents`
--
ALTER TABLE `documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `mou_moa`
--
ALTER TABLE `mou_moa`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `notification_confirmations`
--
ALTER TABLE `notification_confirmations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `notification_reads`
--
ALTER TABLE `notification_reads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=63;

--
-- AUTO_INCREMENT for table `other_documents`
--
ALTER TABLE `other_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=70;

--
-- AUTO_INCREMENT for table `schedules`
--
ALTER TABLE `schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `sessions`
--
ALTER TABLE `sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `site_settings`
--
ALTER TABLE `site_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `user_preferences`
--
ALTER TABLE `user_preferences`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_history`
--
ALTER TABLE `activity_history`
  ADD CONSTRAINT `activity_history_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD CONSTRAINT `activity_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `awards`
--
ALTER TABLE `awards`
  ADD CONSTRAINT `awards_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `award_analysis`
--
ALTER TABLE `award_analysis`
  ADD CONSTRAINT `award_analysis_ibfk_1` FOREIGN KEY (`award_id`) REFERENCES `awards` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_award_analysis_document_id` FOREIGN KEY (`document_id`) REFERENCES `other_documents` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `award_event_links`
--
ALTER TABLE `award_event_links`
  ADD CONSTRAINT `award_event_links_ibfk_1` FOREIGN KEY (`award_id`) REFERENCES `awards` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `award_event_links_ibfk_2` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `award_statistics`
--
ALTER TABLE `award_statistics`
  ADD CONSTRAINT `award_statistics_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `documents`
--
ALTER TABLE `documents`
  ADD CONSTRAINT `documents_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `events`
--
ALTER TABLE `events`
  ADD CONSTRAINT `events_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_events_document_id` FOREIGN KEY (`document_id`) REFERENCES `other_documents` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `mou_moa`
--
ALTER TABLE `mou_moa`
  ADD CONSTRAINT `mou_moa_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notification_confirmations`
--
ALTER TABLE `notification_confirmations`
  ADD CONSTRAINT `notification_confirmations_ibfk_1` FOREIGN KEY (`notification_id`) REFERENCES `notifications` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notification_confirmations_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notification_reads`
--
ALTER TABLE `notification_reads`
  ADD CONSTRAINT `notification_reads_ibfk_1` FOREIGN KEY (`notification_id`) REFERENCES `notifications` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notification_reads_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `other_documents`
--
ALTER TABLE `other_documents`
  ADD CONSTRAINT `fk_other_documents_award_id` FOREIGN KEY (`award_id`) REFERENCES `awards` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `other_documents_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `schedules`
--
ALTER TABLE `schedules`
  ADD CONSTRAINT `schedules_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sessions`
--
ALTER TABLE `sessions`
  ADD CONSTRAINT `sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_preferences`
--
ALTER TABLE `user_preferences`
  ADD CONSTRAINT `user_preferences_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
