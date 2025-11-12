-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 06, 2025 at 12:47 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `lilac_awards`
--

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
(1, 2, 'login', NULL, '::1', NULL, '2025-11-06 00:27:33'),
(2, 2, 'login', NULL, '::1', NULL, '2025-11-06 00:33:06'),
(3, 2, 'login', NULL, '::1', NULL, '2025-11-06 00:48:00'),
(4, 1, 'login', NULL, '::1', NULL, '2025-11-06 01:16:36'),
(5, 1, 'login', NULL, '::1', NULL, '2025-11-06 01:25:19'),
(6, 2, 'login', NULL, '::1', NULL, '2025-11-06 05:14:14'),
(7, NULL, 'registration', NULL, '::1', NULL, '2025-11-06 05:57:05'),
(8, NULL, 'login', NULL, '::1', NULL, '2025-11-06 05:57:21'),
(9, 11, 'registration', NULL, '::1', NULL, '2025-11-06 06:28:46'),
(10, 11, 'login', NULL, '::1', NULL, '2025-11-06 06:29:04'),
(11, 11, 'login', NULL, '::1', NULL, '2025-11-06 07:27:43'),
(12, 11, 'login', NULL, '::1', NULL, '2025-11-06 07:56:30'),
(13, 1, 'login', NULL, '::1', NULL, '2025-11-06 08:07:34'),
(14, 1, 'login', NULL, '::1', NULL, '2025-11-06 09:52:12'),
(15, 11, 'login', NULL, '::1', NULL, '2025-11-06 10:23:29'),
(16, 1, 'login', NULL, '::1', NULL, '2025-11-06 10:30:58');

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
  `file_type` varchar(50) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `award_date` date DEFAULT NULL,
  `status` enum('pending','analyzed','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `awards`
--

INSERT INTO `awards` (`id`, `user_id`, `title`, `description`, `file_name`, `file_path`, `file_type`, `file_size`, `award_date`, `status`, `created_at`, `updated_at`) VALUES
(29, 8, 'Eco-Friendly Campus Transportation', 'Promoting bike sharing, electric shuttles, and reduced carbon emissions.', NULL, NULL, NULL, NULL, NULL, 'pending', '2025-11-06 03:38:07', '2025-11-06 03:38:07'),
(35, 6, 'ASEAN Business Leaders Network', 'Networking platform for young entrepreneurs across ASEAN countries.', NULL, NULL, NULL, NULL, NULL, 'pending', '2025-11-06 03:38:07', '2025-11-06 05:00:11'),
(36, 9, 'International Education Excellence Program', 'Comprehensive program to internationalize curriculum and enhance global competency.', NULL, NULL, NULL, NULL, NULL, 'approved', '2025-11-06 03:38:07', '2025-11-06 05:00:11'),
(37, 5, 'Global Learning Experience Program', 'Study abroad and international internship opportunities for all students.', NULL, NULL, NULL, NULL, NULL, 'pending', '2025-11-06 03:38:07', '2025-11-06 05:00:11'),
(48, 11, 'Sustainability Award', 'Our university implemented a comprehensive campus-wide sustainability program that reduced carbon emissions by 35%. We established recycling systems across all buildings, installed solar panels on 5 major facilities, and achieved ISO 14001 environmental certification. The initiative promotes green practices and renewable energy adoption throughout the campus community.', 'award_11_690c40a66a2e1.docx', 'uploads/awards/2025/11/award_11_690c40a66a2e1.docx', NULL, NULL, NULL, 'pending', '2025-11-06 06:31:02', '2025-11-06 06:44:57'),
(49, 11, 'Global Citizenship Award', 'Outstanding leadership in internationalization initiatives', 'award_11_690c4591ee608.docx', 'uploads/awards/2025/11/award_11_690c4591ee608.docx', NULL, NULL, NULL, 'pending', '2025-11-06 06:52:01', '2025-11-06 06:54:38'),
(50, 11, 'Most Promising ', 'Recognition for promising International Relations Office or community initiatives', 'award_11_690c481a45c74.docx', 'uploads/awards/2025/11/award_11_690c481a45c74.docx', NULL, NULL, NULL, 'analyzed', '2025-11-06 07:02:50', '2025-11-06 07:02:50'),
(51, 2, 'Global Citizenship Award', 'Recognizes individuals who demonstrate outstanding global citizenship through international engagement and cultural awareness.', 'award_2_690c6e7c38e43.docx', 'uploads/awards/2025/11/award_2_690c6e7c38e43.docx', NULL, NULL, NULL, 'approved', '2025-11-06 09:46:36', '2025-11-06 09:49:57'),
(52, 11, 'Outstanding International Education Program Award', 'Outstanding International Education Program Award', 'award_11_690c839114b03.docx', 'uploads/awards/2025/11/award_11_690c839114b03.docx', NULL, NULL, NULL, 'analyzed', '2025-11-06 11:16:33', '2025-11-06 11:16:33'),
(53, 11, 'Global Citizenship Award', 'Global Citizenship Award', 'award_11_690c88b4dcb41.docx', 'uploads/awards/2025/11/award_11_690c88b4dcb41.docx', NULL, NULL, NULL, 'analyzed', '2025-11-06 11:38:28', '2025-11-06 11:38:28');

-- --------------------------------------------------------

--
-- Table structure for table `award_analysis`
--

CREATE TABLE `award_analysis` (
  `id` int(11) NOT NULL,
  `award_id` int(11) NOT NULL,
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

--
-- Dumping data for table `award_analysis`
--

INSERT INTO `award_analysis` (`id`, `award_id`, `predicted_category`, `match_percentage`, `confidence`, `status`, `detected_text`, `matched_keywords`, `all_matches`, `recommendations`, `analysis_metadata`, `created_at`, `updated_at`) VALUES
(29, 29, 'Sustainability Award', 72.00, 'medium', 'Almost Eligible', NULL, NULL, '[{\"category\":\"Sustainability Award\",\"met_criteria\":[\"Environmental impact\",\"Carbon reduction\"],\"unmet_criteria\":[\"Recycling program\",\"Waste reduction\",\"Community engagement\"],\"criteria_met\":2,\"criteria_total\":5,\"similarity_score\":0.72,\"keyword_score\":0.648,\"final_score\":72}]', NULL, NULL, '2025-11-06 03:38:07', '2025-11-06 03:38:07'),
(35, 35, 'Best ASEAN Awareness Initiative Award', 84.00, 'medium', 'Almost Eligible', NULL, '[\"sustainability\",\"environmental\",\"green\",\"carbon\",\"renewable\"]', '[{\"category\":\"Best ASEAN Awareness Initiative Award\",\"met_criteria\":[\"ASEAN engagement\",\"Regional cooperation\",\"Youth leadership\"],\"unmet_criteria\":[\"Cultural exchange\",\"Policy dialogue\"],\"criteria_met\":3,\"criteria_total\":5,\"similarity_score\":0.84,\"keyword_score\":0.756,\"final_score\":84}]', NULL, NULL, '2025-11-06 03:38:07', '2025-11-06 06:32:28'),
(36, 36, 'Outstanding International Education Program Award', 93.00, 'medium', 'Eligible', NULL, '[\"sustainability\",\"environmental\",\"green\",\"carbon\",\"renewable\"]', '[{\"category\":\"Outstanding International Education Program Award\",\"met_criteria\":[\"Curriculum internationalization\",\"Faculty development\",\"Student mobility\",\"Quality assurance\",\"Partnership network\"],\"unmet_criteria\":[\"Research collaboration\"],\"criteria_met\":5,\"criteria_total\":6,\"similarity_score\":0.93,\"keyword_score\":0.8370000000000001,\"final_score\":93}]', NULL, NULL, '2025-11-06 03:38:07', '2025-11-06 06:32:26'),
(37, 37, 'Outstanding International Education Program Award', 78.00, 'medium', 'Almost Eligible', NULL, '[\"sustainability\",\"environmental\",\"green\",\"carbon\",\"renewable\"]', '[{\"category\":\"Outstanding International Education Program Award\",\"met_criteria\":[\"Student mobility\",\"Partnership network\",\"Global competency\"],\"unmet_criteria\":[\"Curriculum internationalization\",\"Faculty development\",\"Research collaboration\"],\"criteria_met\":3,\"criteria_total\":6,\"similarity_score\":0.78,\"keyword_score\":0.7020000000000001,\"final_score\":78}]', NULL, NULL, '2025-11-06 03:38:07', '2025-11-06 06:32:23'),
(44, 48, 'Sustainability Award', 83.33, 'high', 'Not Eligible', ' Global Citizenship Award 2024 CERTIFICATE OF RECOGNITION This certificate is proudly presented to John D. Cruz in recognition of outstanding commitment to global awareness, social responsibility, and active engagement in fostering cultural understanding as part of the Global Citizenship Award 2024. Your efforts exemplify the values of empathy, inclusivity, and leadership in the global community. Given this 6th day of November, 2024. Dr. Maria Santos Program Director Engr. Steven Felizardo Coordinator', '[\"sustainability\",\"environmental\",\"green\",\"carbon\",\"renewable\"]', '{\"matched\":[\"sustainability\",\"environmental\",\"green\",\"carbon\",\"renewable\"],\"missing\":[\"conservation\"],\"match_count\":5,\"total_keywords\":6}', NULL, NULL, '2025-11-06 06:31:02', '2025-11-06 06:31:02'),
(45, 49, 'Global Citizenship Award', 75.00, 'medium', 'Not Eligible', ' Global Citizenship Award 2024 CERTIFICATE OF RECOGNITION This certificate is proudly presented to John D. Cruz in recognition of outstanding commitment to global awareness, social responsibility, and active engagement in fostering cultural understanding as part of the Global Citizenship Award 2024. Your efforts exemplify the values of empathy, inclusivity, and leadership in the global community. Given this 6th day of November, 2024. Dr. Maria Santos Program Director Engr. Steven Felizardo Coordinator', '[\"global\",\"citizenship\",\"international\"]', '{\"matched\":[\"global\",\"citizenship\",\"international\"],\"missing\":[\"intercultural\"],\"match_count\":3,\"total_keywords\":4}', NULL, NULL, '2025-11-06 06:52:01', '2025-11-06 06:52:01'),
(46, 50, 'Outstanding International Education Program Award', 75.00, 'medium', 'Not Eligible', ' Most Promising IRO/Community Award 2024 CERTIFICATE OF RECOGNITION This certificate is proudly presented to John D. Cruz in recognition of exemplary potential and dedication to fostering international relations and community development. As a recipient of the  Most Promising IRO/Community Award 2024 , you have shown initiative, leadership, and passion in promoting collaboration and meaningful engagement within and beyond your community. Given this 6th day of November, 2024. Dr. Maria Santos Program Director Engr. Steven Felizardo Coordinator', '[\"education\",\"international\",\"program\"]', '{\"matched\":[\"education\",\"international\",\"program\"],\"missing\":[\"exchange\"],\"match_count\":3,\"total_keywords\":4}', NULL, NULL, '2025-11-06 07:02:50', '2025-11-06 07:02:50'),
(47, 51, 'Global Citizenship Award', 100.00, 'high', 'Not Eligible', ' Global Citizenship Award 2024 CERTIFICATE OF RECOGNITION This certificate is proudly presented to John D. Cruz in recognition of outstanding commitment to global awareness, social responsibility, and active engagement in fostering cultural understanding as part of the Global Citizenship Award 2024. Your efforts exemplify the values of empathy, inclusivity, and leadership in the global community. Given this 6th day of November, 2024. Dr. Maria Santos Program Director Engr. Steven Felizardo Coordinator', '[\"global\",\"citizenship\",\"international\",\"intercultural\"]', '{\"matched\":[\"global\",\"citizenship\",\"international\",\"intercultural\"],\"missing\":[],\"match_count\":4,\"total_keywords\":4}', NULL, NULL, '2025-11-06 09:46:36', '2025-11-06 09:46:36'),
(48, 52, 'Outstanding International Education Program Award', 75.00, 'medium', 'Not Eligible', ' Most Promising IRO/Community Award 2024 CERTIFICATE OF RECOGNITION This certificate is proudly presented to John D. Cruz in recognition of exemplary potential and dedication to fostering international relations and community development. As a recipient of the  Most Promising IRO/Community Award 2024 , you have shown initiative, leadership, and passion in promoting collaboration and meaningful engagement within and beyond your community. Given this 6th day of November, 2024. Dr. Maria Santos Program Director Engr. Steven Felizardo Coordinator', '[\"education\",\"international\",\"program\"]', '{\"matched\":[\"education\",\"international\",\"program\"],\"missing\":[\"exchange\"],\"match_count\":3,\"total_keywords\":4}', NULL, NULL, '2025-11-06 11:16:33', '2025-11-06 11:16:33'),
(49, 53, 'Global Citizenship Award', 75.00, 'medium', 'Not Eligible', ' Most Promising IRO/Community Award 2024 CERTIFICATE OF RECOGNITION This certificate is proudly presented to John D. Cruz in recognition of exemplary potential and dedication to fostering international relations and community development. As a recipient of the  Most Promising IRO/Community Award 2024 , you have shown initiative, leadership, and passion in promoting collaboration and meaningful engagement within and beyond your community. Given this 6th day of November, 2024. Dr. Maria Santos Program Director Engr. Steven Felizardo Coordinator', '[\"global\",\"citizenship\",\"international\"]', '{\"matched\":[\"global\",\"citizenship\",\"international\"],\"missing\":[\"intercultural\"],\"match_count\":3,\"total_keywords\":4}', NULL, NULL, '2025-11-06 11:38:28', '2025-11-06 11:38:28');

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
(1, 'Global Citizenship Award', 'Individual', NULL, 'Recognizes individuals who demonstrate outstanding global citizenship through international engagement and cultural awareness.', '[\"Demonstrated participation in international programs or exchanges\",\"Evidence of cross-cultural collaboration\",\"Community service with international impact\",\"Leadership in promoting global awareness\",\"Documented international partnerships or projects\"]', 'global, citizenship, international, intercultural', 60, 10, 'active', NULL, NULL, '2025-11-06 01:21:48', '2025-11-06 09:46:08'),
(2, 'Outstanding International Education Program Award', 'Institutional', NULL, 'Honors institutions with exemplary international education programs that foster global learning.', '[\"Established international education curriculum\",\"Student exchange programs with foreign institutions\",\"International faculty collaboration\",\"Research projects with global scope\",\"Cultural diversity initiatives\",\"International student support services\"]', 'education, international, exchange, program', 70, 50, 'active', NULL, NULL, '2025-11-06 01:21:48', '2025-11-06 06:06:02'),
(3, 'Sustainability Award', 'Institutional', NULL, 'Recognizes organizations committed to sustainability and environmental responsibility.', '[\"Environmental sustainability initiatives\",\"Green campus programs or practices\",\"Waste reduction and recycling programs\",\"Energy efficiency measures\",\"Community environmental outreach\"]', 'sustainability, environmental, green, carbon, renewable, conservation', 65, 50, 'active', NULL, NULL, '2025-11-06 01:21:48', '2025-11-06 06:06:02');

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
  `status` enum('planned','ongoing','completed','cancelled') DEFAULT 'planned',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `file_name` varchar(255) DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `partner` varchar(255) DEFAULT NULL,
  `type` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `mou_moa`
--

INSERT INTO `mou_moa` (`id`, `user_id`, `institution`, `location`, `contact_email`, `term`, `sign_date`, `end_date`, `status`, `file_name`, `file_path`, `title`, `partner`, `type`, `description`, `created_at`, `updated_at`) VALUES
(18, 1, 'dsad', 'Iloilo City', 'asdas@gmail.com', '1', '2025-11-21', '2026-11-21', 'Active', 'Global_Outreach_Program_2024_Certificate (2) (2).docx', 'uploads/mou/mou_690c7b195de91_1762425625.docx', 'dsad', NULL, 'MOU (Memorandum of Understanding)', NULL, '2025-11-06 10:40:25', '2025-11-06 10:40:25');

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `other_documents`
--

INSERT INTO `other_documents` (`id`, `user_id`, `title`, `description`, `file_name`, `file_path`, `category`, `created_at`, `updated_at`) VALUES
(2, 1, 'WS', 'asda', 'Global_Outreach_Program_2024_Certificate (4).docx', 'uploads/other_documents/doc_690c81c47c2fb_1762427332.docx', 'Other Documents', '2025-11-06 11:08:52', '2025-11-06 11:08:52');

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
(1, 2, '63386b57243aa27a476d96f7bda674d1366c0536c3d1bdff54427e914e98e2d2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-06 00:27:33', '2025-11-06 17:27:33', 1),
(2, 2, '728761301f789857b8e986a5883f634f46a765d1425832ad8d43de327e80cb73', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-06 00:33:06', '2025-11-06 17:33:06', 1),
(3, 2, '78d90083bb2954a2d8a70d380f65c3b8472d46e0e3e22da24a51648b3b191722', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-06 00:48:00', '2025-11-06 17:48:00', 1),
(4, 1, '9b8c32c6b4a344238f3bc021d8f2dbd611cc3d9ad4a64653144b16cf5e9f0472', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-06 01:16:36', '2025-11-06 18:16:36', 1),
(5, 1, '259ffbe84b47eeb0dcfdb2077358b01b9d5e1a182248d36b94f9f173912cf423', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-06 01:25:19', '2025-11-06 18:25:19', 1),
(6, 2, 'ac3d0efa1c644750d5b0d3a16069f04785d089ad37d2bc19d50a9aa3bd0623f0', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-06 05:14:14', '2025-11-06 22:14:14', 1),
(8, 11, '45689c3fd2547404545ab363a80d4c60ab7bc2425b5b9b7a939721ec6b33f604', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-06 06:29:04', '2025-11-06 23:29:04', 1),
(9, 11, 'ed2b5606a214be621758d948ba018154de81ed37821e6b74511c1dffc5250c6d', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-06 07:27:43', '2025-11-07 00:27:43', 1),
(10, 11, '90da31cd922c1473710993082e090547741738b9f6f0293a6df5129ab9bf946e', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-06 07:56:30', '2025-11-07 00:56:30', 1),
(11, 1, '467abb84b1889b45ca583976943f869e48ab8026e0a6934e60af3ee04031653a', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-06 08:07:34', '2025-11-07 01:07:34', 1),
(12, 1, '352c385365f40817cf7c62b3b3bb87175c5a2a38cbc4d889c8a28754041158ab', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-06 09:52:12', '2025-11-07 02:52:12', 1),
(13, 11, '6832bcbe63ba1e8032930a41c0e3f037040c7ad99fd59b15f89a6bcb290500a8', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-06 10:23:29', '2025-11-07 03:23:29', 1),
(14, 1, 'd71e312c43f242277e9be1a8a8529512ed763e919c04defda9476a1861ae9033', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-06 10:30:58', '2025-11-07 03:30:58', 1);

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
  `full_name` varchar(100) DEFAULT NULL,
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

INSERT INTO `users` (`id`, `username`, `email`, `phone`, `password_hash`, `full_name`, `department`, `role`, `status`, `created_at`, `updated_at`, `last_login`) VALUES
(1, 'admin', 'admin@cpu.edu.ph', NULL, '$2y$10$sVoVWIn8azmDdAgCBHXR3eTQSbbVqqBoYFs6SJ8THBXahh9kqaWiq', 'System Administrator', 'College of Arts and Sciences', 'admin', 'active', '2025-11-06 00:27:13', '2025-11-06 10:30:58', '2025-11-06 10:30:58'),
(2, 'user', 'user@cpu.edu.ph', NULL, '$2y$10$pC5K0MZoigkREey47sRHwePVpcTCv8GmAc2UZA7EueEIt2IWDU7j2', 'Regular User', 'College of Engineering', 'user', 'active', '2025-11-06 00:27:13', '2025-11-06 05:42:17', '2025-11-06 05:14:14'),
(5, 'john_doe', 'john.doe@cpu.edu.ph', NULL, '', NULL, 'College of Medicine', 'user', 'active', '2025-11-06 03:38:07', '2025-11-06 05:42:17', NULL),
(6, 'maria_santos', 'maria.santos@cpu.edu.ph', NULL, '', NULL, 'College of Business Administration', 'user', 'active', '2025-11-06 03:38:07', '2025-11-06 05:42:17', NULL),
(7, 'robert_lee', 'robert.lee@cpu.edu.ph', NULL, '', NULL, 'Graduate School', 'user', 'active', '2025-11-06 03:38:07', '2025-11-06 05:42:17', NULL),
(8, 'sarah_kim', 'sarah.kim@cpu.edu.ph', NULL, '', NULL, 'College of Arts and Sciences', 'user', 'active', '2025-11-06 03:38:07', '2025-11-06 05:42:17', NULL),
(9, 'david_chen', 'david.chen@cpu.edu.ph', NULL, '', NULL, 'Graduate School', 'user', 'active', '2025-11-06 03:38:07', '2025-11-06 05:42:17', NULL),
(11, 'juan_dela', 'juandela@cpu.edu.ph', '9234534545', '$2y$10$8sPXn0TDi2ylhyXr6sggD.7eO4fuZBT.MBKY7zweQmKsXjlZlBH7W', 'Juan Dela Cruz', 'College of Arts and Sciences', 'user', 'active', '2025-11-06 06:28:46', '2025-11-06 11:45:31', '2025-11-06 10:23:29');

--
-- Indexes for dumped tables
--

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
  ADD KEY `idx_match_percentage` (`match_percentage`);

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
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `mou_moa`
--
ALTER TABLE `mou_moa`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indexes for table `other_documents`
--
ALTER TABLE `other_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`);

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
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `awards`
--
ALTER TABLE `awards`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;

--
-- AUTO_INCREMENT for table `award_analysis`
--
ALTER TABLE `award_analysis`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT for table `award_categories`
--
ALTER TABLE `award_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `award_criteria`
--
ALTER TABLE `award_criteria`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `mou_moa`
--
ALTER TABLE `mou_moa`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `other_documents`
--
ALTER TABLE `other_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `schedules`
--
ALTER TABLE `schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sessions`
--
ALTER TABLE `sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Constraints for dumped tables
--

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
  ADD CONSTRAINT `award_analysis_ibfk_1` FOREIGN KEY (`award_id`) REFERENCES `awards` (`id`) ON DELETE CASCADE;

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
  ADD CONSTRAINT `events_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `mou_moa`
--
ALTER TABLE `mou_moa`
  ADD CONSTRAINT `mou_moa_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `other_documents`
--
ALTER TABLE `other_documents`
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
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
