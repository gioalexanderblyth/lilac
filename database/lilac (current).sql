-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 25, 2026 at 12:55 AM
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
(4, NULL, 'login', NULL, '::1', NULL, '2025-11-06 01:16:36'),
(5, NULL, 'login', NULL, '::1', NULL, '2025-11-06 01:25:19'),
(6, NULL, 'login', NULL, '::1', NULL, '2025-11-06 05:14:14'),
(7, NULL, 'registration', NULL, '::1', NULL, '2025-11-06 05:57:05'),
(8, NULL, 'login', NULL, '::1', NULL, '2025-11-06 05:57:21'),
(9, NULL, 'registration', NULL, '::1', NULL, '2025-11-06 06:28:46'),
(10, NULL, 'login', NULL, '::1', NULL, '2025-11-06 06:29:04'),
(11, NULL, 'login', NULL, '::1', NULL, '2025-11-06 07:27:43'),
(12, NULL, 'login', NULL, '::1', NULL, '2025-11-06 07:56:30'),
(13, NULL, 'login', NULL, '::1', NULL, '2025-11-06 08:07:34'),
(14, NULL, 'login', NULL, '::1', NULL, '2025-11-06 09:52:12'),
(15, NULL, 'login', NULL, '::1', NULL, '2025-11-06 10:23:29'),
(16, NULL, 'login', NULL, '::1', NULL, '2025-11-06 10:30:58'),
(17, NULL, 'login', NULL, '::1', NULL, '2025-11-14 13:01:02'),
(18, NULL, 'login', NULL, '::1', NULL, '2025-11-14 13:01:56'),
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
(42, NULL, 'login', NULL, '::1', NULL, '2025-11-18 06:12:10'),
(43, NULL, 'login', NULL, '::1', NULL, '2025-11-18 06:16:28'),
(44, NULL, 'login', NULL, '::1', NULL, '2025-11-18 12:44:10'),
(45, NULL, 'login', NULL, '::1', NULL, '2025-11-18 14:52:52'),
(46, NULL, 'login', NULL, '::1', NULL, '2025-11-18 14:58:06'),
(47, NULL, 'login', NULL, '::1', NULL, '2025-11-18 14:58:52'),
(48, NULL, 'login', NULL, '::1', NULL, '2025-11-18 14:59:28'),
(49, NULL, 'login', NULL, '::1', NULL, '2025-11-18 15:43:58'),
(50, NULL, 'login', NULL, '::1', NULL, '2025-11-18 15:44:48'),
(51, NULL, 'login', NULL, '::1', NULL, '2025-11-18 16:10:34'),
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
(62, 13, 'login', NULL, '::1', NULL, '2026-01-07 07:59:03'),
(63, 13, 'login', NULL, '::1', NULL, '2026-01-20 05:55:23'),
(64, 13, 'login', NULL, '::1', NULL, '2026-01-20 06:20:39'),
(65, 13, 'login', NULL, '::1', NULL, '2026-01-22 07:34:25'),
(66, 13, 'login', NULL, '::1', NULL, '2026-01-27 01:10:48'),
(67, 13, 'login', NULL, '::1', NULL, '2026-01-27 02:30:58'),
(68, 13, 'login', NULL, '::1', NULL, '2026-01-27 05:39:25'),
(69, 13, 'login', NULL, '::1', NULL, '2026-01-27 05:42:17'),
(70, 13, 'login', NULL, '::1', NULL, '2026-01-27 08:33:40'),
(71, 13, 'login', NULL, '::1', NULL, '2026-01-27 14:35:33'),
(72, 13, 'login', NULL, '::1', NULL, '2026-01-28 03:24:58'),
(73, 13, 'login', NULL, '::1', NULL, '2026-01-28 03:39:17'),
(74, 13, 'login', NULL, '::1', NULL, '2026-01-28 04:28:29'),
(75, 13, 'login', NULL, '::1', NULL, '2026-01-28 04:31:12'),
(76, NULL, 'registration', NULL, '::1', NULL, '2026-01-28 05:26:38'),
(77, NULL, 'login', NULL, '::1', NULL, '2026-01-28 05:26:46'),
(78, 13, 'login', NULL, '::1', NULL, '2026-01-28 05:27:56'),
(79, 13, 'login', NULL, '::1', NULL, '2026-01-30 03:41:23'),
(80, 13, 'login', NULL, '::1', NULL, '2026-02-03 05:54:17'),
(81, 13, 'login', NULL, '::1', NULL, '2026-02-03 06:52:22'),
(82, 13, 'login', NULL, '::1', NULL, '2026-02-03 07:49:17'),
(83, 13, 'login', NULL, '::1', NULL, '2026-02-04 01:01:12'),
(84, 13, 'login', NULL, '::1', NULL, '2026-02-05 01:25:43'),
(85, 13, 'login', NULL, '::1', NULL, '2026-02-09 05:50:36'),
(86, 13, 'login', NULL, '::1', NULL, '2026-02-10 01:24:01'),
(87, 13, 'login', NULL, '::1', NULL, '2026-02-10 02:24:57'),
(88, 13, 'login', NULL, '::1', NULL, '2026-02-24 06:21:45'),
(89, 13, 'login', NULL, '::1', NULL, '2026-02-24 06:22:20'),
(90, 13, 'login', NULL, '::1', NULL, '2026-03-25 01:28:08'),
(91, 13, 'login', NULL, '::1', NULL, '2026-04-24 22:53:47');

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

-- --------------------------------------------------------

--
-- Table structure for table `forms`
--

CREATE TABLE `forms` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(512) NOT NULL,
  `file_size` int(11) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `forms`
--

INSERT INTO `forms` (`id`, `title`, `file_name`, `file_path`, `file_size`, `created_by`, `created_at`, `deleted_at`) VALUES
(1, 'test', 'form_6997bd316e7c1_1771552049.jpg', 'uploads/forms/form_6997bd316e7c1_1771552049.jpg', 63678, 13, '2026-02-20 01:47:29', '2026-02-20 14:43:49');

-- --------------------------------------------------------

--
-- Table structure for table `institutional_memberships`
--

CREATE TABLE `institutional_memberships` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `org_name` varchar(255) NOT NULL,
  `membership_type` enum('International','Local') NOT NULL,
  `membership_status` enum('Annual','Lifetime') NOT NULL,
  `membership_year` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `institutional_memberships`
--

INSERT INTO `institutional_memberships` (`id`, `user_id`, `org_name`, `membership_type`, `membership_status`, `membership_year`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 13, 'gfdfddf', 'International', 'Annual', 2021, '2026-03-26 07:02:15', '2026-03-26 08:34:04', '2026-03-26 16:34:04'),
(2, 13, 'Test', 'International', 'Lifetime', 2024, '2026-03-26 08:34:23', '2026-03-26 08:34:35', '2026-03-26 16:34:35');

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

--
-- Dumping data for table `mou_moa`
--

INSERT INTO `mou_moa` (`id`, `user_id`, `institution`, `location`, `contact_email`, `term`, `sign_date`, `end_date`, `status`, `renewal_confirmed`, `renewal_confirmed_at`, `file_name`, `file_path`, `title`, `partner`, `type`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES
(110, 13, 'TEST', 'test', NULL, '1', '2025-02-12', '2026-02-12', 'Expires Soon', 0, NULL, NULL, NULL, 'TEST', NULL, 'Unknown', NULL, '2026-02-12 08:10:24', '2026-02-20 05:55:51', '2026-02-20 13:55:51'),
(111, 13, 'TEST', 'test', NULL, '1', '2024-06-24', '2025-06-24', 'Expired', 0, NULL, NULL, NULL, 'TEST', NULL, 'Unknown', NULL, '2026-02-24 06:18:55', '2026-03-25 02:00:46', '2026-03-25 10:00:46'),
(112, 13, 'TEST', 'test', NULL, '1', '2025-02-26', '2026-02-26', 'Expires Soon', 0, NULL, NULL, NULL, 'TEST', NULL, 'Unknown', NULL, '2026-02-24 07:49:36', '2026-03-25 02:00:46', '2026-03-25 10:00:46'),
(113, 13, 'TEST', 'test', NULL, '1', '2025-02-25', '2026-02-25', 'Expires Soon', 0, NULL, NULL, NULL, 'TEST', NULL, 'Unknown', NULL, '2026-02-24 08:06:21', '2026-03-25 02:00:46', '2026-03-25 10:00:46');

-- --------------------------------------------------------

--
-- Table structure for table `mou_moa_files`
--

CREATE TABLE `mou_moa_files` (
  `id` int(11) NOT NULL,
  `mou_moa_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(11) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_primary` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `mou_moa_files`
--

INSERT INTO `mou_moa_files` (`id`, `mou_moa_id`, `file_name`, `file_path`, `file_size`, `uploaded_at`, `is_primary`) VALUES
(1, 86, 'expired-MOU.jpg', 'uploads/mou/mou_69829e0c5232b_1770167820.jpg', 40013, '2026-02-04 01:17:00', 0),
(2, 86, 'Halla Middle School.jpg', 'uploads/mou/mou_69829e0c52660_1770167820.jpg', 92061, '2026-02-04 01:17:00', 0),
(3, 86, 'Ikuei.jpg', 'uploads/mou/mou_69829e0c52f70_1770167820.jpg', 3218298, '2026-02-04 01:17:00', 0),
(4, 86, 'Jeonju.jpg', 'uploads/mou/mou_69829e0c53254_1770167820.jpg', 65497, '2026-02-04 01:17:00', 0),
(5, 86, 'Matsugaya.jpg', 'uploads/mou/mou_69829e0c535b2_1770167820.jpg', 74661, '2026-02-04 01:17:00', 0),
(6, 86, 'Ppd-poa.jpg', 'uploads/mou/mou_69829e0c5388f_1770167820.jpg', 70811, '2026-02-04 01:17:00', 0),
(7, 90, 'Scan_20260205.jpg', 'uploads/mou/mou_6984417adf941_1770275194.jpg', 109694, '2026-02-05 07:06:34', 0),
(8, 90, 'expired-MOU.jpg', 'uploads/mou/mou_698441a7518fb_1770275239.jpg', 40013, '2026-02-05 07:07:19', 0),
(9, 90, 'Halla Middle School.jpg', 'uploads/mou/mou_698441a751cc3_1770275239.jpg', 92061, '2026-02-05 07:07:19', 0),
(10, 90, 'Ikuei.jpg', 'uploads/mou/mou_698441a7524e9_1770275239.jpg', 3218298, '2026-02-05 07:07:19', 0),
(11, 90, 'Jeonju.jpg', 'uploads/mou/mou_698441a752989_1770275239.jpg', 65497, '2026-02-05 07:07:19', 0),
(12, 90, 'Matsugaya.jpg', 'uploads/mou/mou_698441a752ee7_1770275239.jpg', 74661, '2026-02-05 07:07:19', 0),
(13, 90, 'Ppd-poa.jpg', 'uploads/mou/mou_698441a7533a4_1770275239.jpg', 70811, '2026-02-05 07:07:19', 0),
(14, 90, 'Soongsil University.jpg', 'uploads/mou/mou_698441a753a2a_1770275239.jpg', 318025, '2026-02-05 07:07:19', 0),
(15, 90, 'Sun-Moon-University.jpg', 'uploads/mou/mou_698441a753e30_1770275239.jpg', 219146, '2026-02-05 07:07:19', 0),
(16, 90, 'TraVinh University.jpg', 'uploads/mou/mou_698441a7545d0_1770275239.jpg', 210509, '2026-02-05 07:07:19', 0),
(17, 91, 'Scan_20260205.jpg', 'uploads/mou/mou_698442046b1c0_1770275332.jpg', 109694, '2026-02-05 07:08:52', 0),
(18, 92, 'Scan_20260205 (4).jpg', 'uploads/mou/mou_6984432e89d82_1770275630.jpg', 136168, '2026-02-05 07:13:50', 0),
(19, 93, 'Scan_20260205 (6).jpg', 'uploads/mou/mou_69844543ce9a4_1770276163.jpg', 153381, '2026-02-05 07:22:43', 0),
(20, 93, 'Scan_20260205 (7).jpg', 'uploads/mou/mou_69844543cee62_1770276163.jpg', 167040, '2026-02-05 07:22:43', 0),
(21, 93, 'Scan_20260205 (8).jpg', 'uploads/mou/mou_69844543cf2c3_1770276163.jpg', 161847, '2026-02-05 07:22:43', 0),
(22, 93, 'Scan_20260205 (9).jpg', 'uploads/mou/mou_69844543cf667_1770276163.jpg', 165281, '2026-02-05 07:22:43', 0),
(23, 93, 'Scan_20260205.jpg', 'uploads/mou/mou_69844543d40e6_1770276163.jpg', 162577, '2026-02-05 07:22:43', 0),
(24, 94, 'Scan_20260205 (2).jpg', 'uploads/mou/mou_698446140a709_1770276372.jpg', 127406, '2026-02-05 07:26:12', 0),
(25, 95, 'Scan_20260205 (2).jpg', 'uploads/mou/mou_6984474fe2d89_1770276687.jpg', 127406, '2026-02-05 07:31:27', 0),
(26, 96, 'Scan_20260205 (2).jpg', 'uploads/mou/mou_698448e637ec0_1770277094.jpg', 198222, '2026-02-05 07:38:14', 0),
(27, 96, 'Scan_20260205 (3).jpg', 'uploads/mou/mou_698448e638399_1770277094.jpg', 162857, '2026-02-05 07:38:14', 0),
(28, 96, 'Scan_20260205 (4).jpg', 'uploads/mou/mou_698448e6387e2_1770277094.jpg', 94764, '2026-02-05 07:38:14', 0),
(29, 96, 'Scan_20260205 (5).jpg', 'uploads/mou/mou_698448e638c45_1770277094.jpg', 120168, '2026-02-05 07:38:14', 0),
(30, 97, 'Scan_20260205 (2).jpg', 'uploads/mou/mou_69844ac1e4fb6_1770277569.jpg', 126037, '2026-02-05 07:46:09', 0),
(31, 97, 'Scan_20260205 (3).jpg', 'uploads/mou/mou_69844ac1e533d_1770277569.jpg', 139520, '2026-02-05 07:46:09', 0),
(32, 98, 'Scan_20260205 (5).jpg', 'uploads/mou/mou_69844c556af4b_1770277973.jpg', 211365, '2026-02-05 07:52:53', 0),
(33, 98, 'Scan_20260205 (6).jpg', 'uploads/mou/mou_69844c556b3a4_1770277973.jpg', 167843, '2026-02-05 07:52:53', 0),
(34, 98, 'Scan_20260205 (7).jpg', 'uploads/mou/mou_69844c556b6c9_1770277973.jpg', 124524, '2026-02-05 07:52:53', 0),
(35, 99, 'Scan_20260205 (2).jpg', 'uploads/mou/mou_69844d74f1d1d_1770278260.jpg', 151481, '2026-02-05 07:57:40', 0),
(36, 99, 'Scan_20260205 (3).jpg', 'uploads/mou/mou_69844d74f20b7_1770278260.jpg', 123908, '2026-02-05 07:57:41', 0),
(37, 100, 'Scan_20260205 (2).jpg', 'uploads/mou/mou_69844f2e42826_1770278702.jpg', 229829, '2026-02-05 08:05:02', 0),
(38, 100, 'Scan_20260205 (3).jpg', 'uploads/mou/mou_69844f2e42ce6_1770278702.jpg', 184420, '2026-02-05 08:05:02', 0),
(39, 100, 'Scan_20260205 (4).jpg', 'uploads/mou/mou_69844f2e43116_1770278702.jpg', 139268, '2026-02-05 08:05:02', 0);

-- --------------------------------------------------------

--
-- Table structure for table `mou_notifications`
--

CREATE TABLE `mou_notifications` (
  `id` int(11) NOT NULL,
  `mou_id` int(11) NOT NULL,
  `type` enum('EXPIRING_SOON','EXPIRED') NOT NULL,
  `triggered_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `sound_played` tinyint(1) DEFAULT 0,
  `acknowledged` tinyint(1) DEFAULT 0,
  `acknowledged_at` timestamp NULL DEFAULT NULL
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
(29, NULL, 'event_upcoming', 'Upcoming Event: Test 1', 'The event \'Test 1\' is happening in 1 day(s) on December 10, 2025 at 12:00 AM at AVR.', 31, 'event', 0, '2025-12-09 06:14:50', NULL),
(87, NULL, 'event_today', 'Schedule Today: Test', 'The schedule \'Test\' is happening today at 9:30 AM.', 16, 'schedule', 0, '2026-02-04 01:28:24', NULL),
(89, NULL, 'event_upcoming', 'Upcoming Schedule: Courtesy Call', 'The schedule \'Courtesy Call\' is happening in 1 day(s) on February 5, 2026 at 6:00 AM.', 17, 'schedule', 0, '2026-02-04 02:37:55', NULL),
(90, NULL, 'event_upcoming', 'Upcoming Schedule: Courtesy Call', 'The schedule \'Courtesy Call\' is happening in 1 day(s) on February 5, 2026 at 5:00 AM.', 18, 'schedule', 0, '2026-02-04 02:42:05', NULL),
(91, NULL, 'event_upcoming', 'Upcoming Schedule: Test', 'The schedule \'Test\' is happening in 1 day(s) on February 5, 2026 at 5:00 AM.', 19, 'schedule', 0, '2026-02-04 02:43:33', NULL),
(92, NULL, 'event_upcoming', 'Upcoming Schedule: Meeting', 'The schedule \'Meeting\' is happening in 2 day(s) on February 6, 2026 at 2:00 PM.', 20, 'schedule', 0, '2026-02-04 08:18:38', NULL),
(93, NULL, 'event_today', 'Schedule Today: Courtesy Call', 'The schedule \'Courtesy Call\' is happening today at 9:25 AM.', 21, 'schedule', 0, '2026-02-05 01:25:34', NULL),
(94, NULL, 'event_today', 'Schedule Today: Courtesy Call', 'The schedule \'Courtesy Call\' is happening today at 9:36 AM.', 23, 'schedule', 0, '2026-02-05 01:37:00', NULL),
(95, NULL, 'event_today', 'Schedule Today: Courtesy Meeting', 'The schedule \'Courtesy Meeting\' is happening today at 9:37 AM.', 24, 'schedule', 0, '2026-02-05 01:37:00', NULL),
(96, NULL, 'event_today', 'Schedule Today: Courtesy Call', 'The schedule \'Courtesy Call\' is happening today at 12:25 PM.', 25, 'schedule', 0, '2026-02-05 04:25:59', NULL),
(384, NULL, 'event_today', 'Schedule Today: aswang', 'The schedule \'aswang\' is happening today at 8:00 PM.', 26, 'schedule', 0, '2026-02-09 08:19:35', NULL),
(406, NULL, 'event_today', 'Schedule Today: Test', 'The schedule \'Test\' is happening today at 9:26 AM.', 27, 'schedule', 0, '2026-02-10 01:52:41', NULL),
(411, NULL, 'event_today', 'Schedule Today: Test', 'The schedule \'Test\' is happening today at 9:54 AM.', 29, 'schedule', 0, '2026-02-10 01:54:03', NULL),
(449, NULL, 'event_today', 'Schedule Today: Test', 'The schedule \'Test\' is happening today at 10:14 AM.', 30, 'schedule', 0, '2026-02-10 02:13:12', NULL),
(467, NULL, 'event_upcoming', 'Upcoming Schedule: Test', 'The schedule \'Test\' is happening in 1 day(s) on February 11, 2026 at 2:00 PM.', 31, 'schedule', 0, '2026-02-10 02:30:28', NULL),
(706, NULL, 'event_upcoming', 'Upcoming Schedule: Test', 'The schedule \'Test\' is happening in 1 day(s) on February 13, 2026 at 2:00 AM.', 32, 'schedule', 0, '2026-02-12 06:39:01', NULL),
(707, NULL, 'event_upcoming', 'Upcoming Schedule: Test', 'The schedule \'Test\' is happening in 1 day(s) on February 13, 2026 at 12:53 PM.', 34, 'schedule', 0, '2026-02-12 07:17:47', NULL),
(729, NULL, 'event_today', 'Schedule Today: Test', 'The schedule \'Test\' is happening today at 2:49 AM.', 35, 'schedule', 0, '2026-02-13 07:39:04', NULL),
(730, NULL, 'event_today', 'Schedule Today: Yest', 'The schedule \'Yest\' is happening today at 10:21 AM.', 36, 'schedule', 0, '2026-02-24 02:21:05', NULL),
(731, NULL, 'event_today', 'Schedule Today: Test', 'The schedule \'Test\' is happening today at 11:46 AM.', 37, 'schedule', 0, '2026-02-24 03:46:12', NULL),
(732, NULL, 'event_today', 'Schedule Today: Test', 'The schedule \'Test\' is happening today at 11:47 AM.', 39, 'schedule', 0, '2026-02-24 03:47:15', NULL),
(733, NULL, 'event_today', 'Schedule Today: Test', 'The schedule \'Test\' is happening today at 11:52 AM.', 40, 'schedule', 0, '2026-02-24 03:51:29', NULL),
(734, NULL, 'event_today', 'Schedule Today: Test', 'The schedule \'Test\' is happening today at 11:54 AM.', 41, 'schedule', 0, '2026-02-24 03:52:47', NULL);

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
(62, 29, 13, '2026-01-21 03:03:54'),
(64, 26, 13, '2026-01-21 03:04:13'),
(65, 27, 13, '2026-01-21 03:04:13'),
(66, 28, 13, '2026-01-21 03:04:13'),
(179, 87, 13, '2026-02-04 02:24:06'),
(182, 92, 13, '2026-02-04 08:19:13'),
(185, 89, 13, '2026-02-09 02:27:44'),
(186, 90, 13, '2026-02-09 02:27:44'),
(187, 91, 13, '2026-02-09 02:27:44'),
(188, 93, 13, '2026-02-09 02:27:44'),
(189, 94, 13, '2026-02-09 02:27:44'),
(190, 95, 13, '2026-02-09 02:27:44'),
(191, 96, 13, '2026-02-09 02:27:44'),
(251, 384, 13, '2026-02-09 08:19:37'),
(254, 467, 13, '2026-02-10 05:24:02');

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
-- Table structure for table `partners`
--

CREATE TABLE `partners` (
  `id` int(11) NOT NULL,
  `canonical_name` varchar(255) NOT NULL,
  `aliases` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`aliases`)),
  `status` enum('active','inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `partners`
--

INSERT INTO `partners` (`id`, `canonical_name`, `aliases`, `status`) VALUES
(1, 'Tra Vinh University', '[\"Tra Vinh University\", \"Tra Vinh Univ\", \"TVU\", \"Tra Vinh\"]', 'active'),
(2, 'Nagoya Gakuin University', '[\"Nagoya Gakuin University\", \"Nagoya Gakuin\", \"NGU\"]', 'active'),
(3, 'Universitas Kristen Indonesia', '[\"Universitas Kristen Indonesia\", \"UKI\"]', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `partner_suggestions`
--

CREATE TABLE `partner_suggestions` (
  `id` int(11) NOT NULL,
  `detected_name` varchar(255) NOT NULL,
  `last_seen_text` text DEFAULT NULL,
  `occurrences` int(11) DEFAULT 1,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
-- Table structure for table `schedule_attachments`
--

CREATE TABLE `schedule_attachments` (
  `id` int(11) NOT NULL,
  `schedule_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `schedule_attachments`
--

INSERT INTO `schedule_attachments` (`id`, `schedule_id`, `user_id`, `file_name`, `original_name`, `file_path`, `mime_type`, `file_size`, `created_at`) VALUES
(1, 20, 13, 'sched_att_69830005e4b7b_1770192901.jpg', 'Ex-MOU.jpg', 'uploads/schedule_attachments/sched_att_69830005e4b7b_1770192901.jpg', 'image/jpeg', 174130, '2026-02-04 08:15:01');

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
(34, 13, 'c7ea509e4a9fee37d8be1b7791c365d3a5abd8e46102dbfc7e4253bce50f5c8d', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-18 23:01:43', '2025-11-19 16:01:43', 1),
(35, 13, '557a17a1f8740bd56f0d1a37ee4c35b9cc76d3ed392189cc2a5d00dc1310a86a', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-18 23:11:06', '2025-11-19 16:11:06', 1),
(36, 13, 'e77e139a8d62bd66f3000c59647fa3eab0f7d987fec58198c7526f8d2e5d12d2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 04:08:42', '2025-11-19 21:08:42', 1),
(37, 13, '3f4b8e64562bbd5ba2933472a070f0283faa0725fbe395671f7fd5b3e82c427e', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 04:44:41', '2025-11-19 21:44:41', 1),
(38, 13, '30dd058e5e8fc191d58eaa0e4f4a65ff759d7b343d5eeb8fa97222b235143b06', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-24 00:52:30', '2025-11-24 17:52:30', 1),
(39, 13, '5188e86de5770527653dbefa1d8daf595c8725953d8f63d0dfd04171a989fe89', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-01 04:07:59', '2025-12-01 21:07:59', 1),
(40, 13, '3e3e953c8ce6472fa5bc9fd022af0e91b07c30c638ab52bc74da8876ff21b00a', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-03 00:43:38', '2025-12-03 17:43:38', 1),
(41, 13, 'b1766de8b39d6a87553e6f24ed06c640420e3fbc0bb4dde72d07cb1ce19288a0', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-07 07:03:17', '2026-01-08 00:03:17', 1),
(42, 13, '23f3ed530d64bc2d92d2fc109d3793aab85255dd9a3fef30f3424bb459055612', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-07 07:59:03', '2026-02-06 00:59:03', 1),
(43, 13, '363512c1791dbe923381a27e4aa2204c3d045bf629c17c2ac25064489f6005d3', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-20 05:55:23', '2026-01-20 22:55:23', 1),
(44, 13, 'd090962c53b6f476a15d0664bfd10d5738d69b46a02cbe399d4a073b601621aa', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-20 06:20:39', '2026-01-20 23:20:39', 1),
(45, 13, '0cd8edaa01fda92c7dfa1081ea7e53451489a70025c6621dc429f4fd39c88a8a', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-22 07:34:25', '2026-01-23 00:34:25', 1),
(46, 13, 'd46a250c69823505928ffbd307831a2a8d23dff701a2244642946d900211361b', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-01-27 01:10:48', '2026-01-27 18:10:48', 1),
(47, 13, 'a3c085d03febd7894f8fc877b1e4cdbf00ded4344ecf771f8a6417fc9c77123c', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-01-27 02:30:58', '2026-01-27 19:30:58', 1),
(48, 13, '6459fce84b09bf6d0b30ffc68ed26c5281e890def48d6f5b40655c6f7f354164', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-01-27 05:39:25', '2026-01-27 22:39:25', 1),
(49, 13, '7a45e339092562097b14cef615315f8f721af87a2407117d44f0ba37924363d5', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-01-27 05:42:17', '2026-01-27 22:42:17', 1),
(50, 13, 'f7f8d67bb1bd3b869dd21e9b4461acd6f3c821228e0833c7b2ce641bb0266a19', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-01-27 08:33:40', '2026-01-28 01:33:40', 1),
(51, 13, '3ac02ab1bbb4dc09f412c6b5879de84081e8b6b726004c5e5a582dc501543aee', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-27 14:35:33', '2026-01-28 07:35:33', 1),
(52, 13, '7c9ea109b02fa2353c79a5d4beacf23d383b7d1fab47a98cd2327ebabf193483', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-01-28 03:24:58', '2026-01-28 20:24:58', 1),
(53, 13, '3b7b95eca532686d4c41b82dab7408f988ed4745e3fc31da33253144c0d31102', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-01-28 03:39:17', '2026-01-28 20:39:17', 1),
(54, 13, 'ab9faac8cea5085c338aa284502200ee3b202a73b23d37735214e5be98fb3896', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-01-28 04:28:29', '2026-01-28 21:28:29', 1),
(55, 13, '0f8e04ff201b5a8e0c42f2638875f8699ef178c3e99917fae66c56d52af0f426', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-01-28 04:31:12', '2026-01-28 21:31:12', 1),
(57, 13, '193283de1f09e67f54fe7e2a9dafd97d5dab6cb99b86714e9e5bf0dfa2f9c2cf', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-01-28 05:27:56', '2026-01-28 22:27:56', 1),
(58, 13, '7bcc4ffe45a33247cc51b51636073a2321f3e4c74593fcf58d83c64c414fb8a3', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-01-30 03:41:23', '2026-01-30 20:41:23', 1),
(59, 13, '12d9cf808de2a7334b950d99eca5050b635ab63f5e5701bad68280c47af73081', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-03 05:54:17', '2026-02-03 22:54:17', 1),
(60, 13, '0372f06eb955af10a68325becfd33ff2705ef52d765042dfc001ab33684db3a1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-03 06:52:22', '2026-02-03 23:52:22', 1),
(61, 13, '7766ab70dd3da71d1fbf44be348bd336a90e717038bf9576fc257c10713d5c35', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-03 07:49:17', '2026-02-04 00:49:17', 1),
(62, 13, '14104c6079cde999f59838d5e8069e86f344474ae2c91d99e69cca8946292dce', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-04 01:01:12', '2026-02-04 18:01:12', 1),
(63, 13, '83723f19fdfdd009b91faee18ed40dc6779180854664f48ecc0cdf053c708483', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-05 01:25:43', '2026-02-05 18:25:43', 1),
(64, 13, '061fa6c1bbc42d4cc1793e486f962cd883f956b1aff7405558bcd8983897677c', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-09 05:50:36', '2026-02-09 22:50:36', 1),
(65, 13, '59c4a4065e0f3a689a00c7fa542bcfa4e102188035d6543de9921b7ae8864ded', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-10 01:24:01', '2026-02-10 18:24:01', 1),
(66, 13, '9484e401a8e2e2f32a803735a1bf9e47ba308cb0cc5e1bd3da8f903c58d64efa', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-10 02:24:57', '2026-02-10 19:24:57', 1),
(67, 13, '5c7de0bbee91f579bb17b3270e146e6255309d280963580c417f50996aaece38', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 06:21:45', '2026-02-24 23:21:45', 1),
(68, 13, '80fa3fae8bf6c941ee7b4cb33b72e42dc6a571dc11375aaeae9a13a90edadbb5', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 06:22:20', '2026-02-24 23:22:20', 1),
(69, 13, '36901d52463610695240a9a52ff9c7e775905d1777b5de5a99a959acb5be6a2a', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-25 01:28:08', '2026-03-25 18:28:08', 1),
(70, 13, 'ae21754799002347bc8e5e4e83adc643cc425ef80488dde589593cae792ba83b', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-24 22:53:47', '2026-04-25 16:53:47', 1);

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
(13, 'lesley', 'lesley.dignadice@cpu.edu.ph', NULL, '$2y$10$PaQ81.pubzMkHZm52nOx3eDmwa0ENZkhG0Ze5Pb9Q.E4WxAP5dFzK', NULL, NULL, 'Lesley Dignadice', NULL, 'Administration', 'admin', 'active', '2025-11-18 23:01:32', '2026-04-24 22:53:47', '2026-04-24 22:53:47');

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
(23, 13, 'ched_requirement_tracker', '{\"videos\":false,\"supporting-docs\":false,\"nomination-form\":false,\"impact-metrics\":false,\"deadline-plan\":false}', '2025-11-18 23:12:31', '2025-11-24 01:56:19'),
(28, 13, 'mou_notification_period_days', '180', '2026-01-27 01:34:58', '2026-02-09 05:43:18'),
(29, 13, 'notifications_enabled', '1', '2026-01-30 07:21:16', '2026-02-24 06:19:27'),
(72, 13, 'dashboard_awards_filter', 'YTD', '2026-02-05 01:39:54', '2026-02-05 01:39:55'),
(85, 13, 'days_before_expiry', '7', '2026-02-09 05:30:11', '2026-02-09 05:43:57');

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
-- Indexes for table `forms`
--
ALTER TABLE `forms`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `institutional_memberships`
--
ALTER TABLE `institutional_memberships`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_deleted_at` (`deleted_at`);

--
-- Indexes for table `mou_moa`
--
ALTER TABLE `mou_moa`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indexes for table `mou_moa_files`
--
ALTER TABLE `mou_moa_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_mou_moa_id` (`mou_moa_id`);

--
-- Indexes for table `mou_notifications`
--
ALTER TABLE `mou_notifications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_mou_type` (`mou_id`,`type`),
  ADD KEY `idx_mou_id` (`mou_id`),
  ADD KEY `idx_type` (`type`),
  ADD KEY `idx_acknowledged` (`acknowledged`),
  ADD KEY `idx_triggered_at` (`triggered_at`);

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
-- Indexes for table `partners`
--
ALTER TABLE `partners`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `canonical_name` (`canonical_name`);

--
-- Indexes for table `partner_suggestions`
--
ALTER TABLE `partner_suggestions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `detected_name` (`detected_name`);

--
-- Indexes for table `schedules`
--
ALTER TABLE `schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indexes for table `schedule_attachments`
--
ALTER TABLE `schedule_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_schedule_id` (`schedule_id`),
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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=92;

--
-- AUTO_INCREMENT for table `awards`
--
ALTER TABLE `awards`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=189;

--
-- AUTO_INCREMENT for table `award_analysis`
--
ALTER TABLE `award_analysis`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=185;

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
-- AUTO_INCREMENT for table `forms`
--
ALTER TABLE `forms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `institutional_memberships`
--
ALTER TABLE `institutional_memberships`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `mou_moa`
--
ALTER TABLE `mou_moa`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=114;

--
-- AUTO_INCREMENT for table `mou_moa_files`
--
ALTER TABLE `mou_moa_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `mou_notifications`
--
ALTER TABLE `mou_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=807;

--
-- AUTO_INCREMENT for table `notification_confirmations`
--
ALTER TABLE `notification_confirmations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `notification_reads`
--
ALTER TABLE `notification_reads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=263;

--
-- AUTO_INCREMENT for table `other_documents`
--
ALTER TABLE `other_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=70;

--
-- AUTO_INCREMENT for table `partners`
--
ALTER TABLE `partners`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `partner_suggestions`
--
ALTER TABLE `partner_suggestions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `schedules`
--
ALTER TABLE `schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `schedule_attachments`
--
ALTER TABLE `schedule_attachments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `sessions`
--
ALTER TABLE `sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=71;

--
-- AUTO_INCREMENT for table `site_settings`
--
ALTER TABLE `site_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `user_preferences`
--
ALTER TABLE `user_preferences`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=97;

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
-- Constraints for table `mou_notifications`
--
ALTER TABLE `mou_notifications`
  ADD CONSTRAINT `fk_mou_notifications_mou_id` FOREIGN KEY (`mou_id`) REFERENCES `mou_moa` (`id`) ON DELETE CASCADE;

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
-- Constraints for table `schedule_attachments`
--
ALTER TABLE `schedule_attachments`
  ADD CONSTRAINT `fk_schedule_attachments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

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

--
-- Table structure for table `mobility_international_awards`
--
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

--
-- Table structure for table `mobility_program_counters`
--
CREATE TABLE IF NOT EXISTS `mobility_program_counters` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `metric_key` varchar(100) NOT NULL,
  `metric_value` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_metric_key` (`metric_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `mobility_program_counters`
--
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
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
