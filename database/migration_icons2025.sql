-- ICONS 2025 Integration Migration
-- Adds event evidence tracking and enhanced criteria support
-- Run this migration in phpMyAdmin or MySQL CLI

-- ============================================
-- 1. Add ICONS-specific columns to events table
-- ============================================

-- Add award-relevant metadata to events
ALTER TABLE `events` 
ADD COLUMN IF NOT EXISTS `document_id` INT(11) DEFAULT NULL AFTER `status`,
ADD COLUMN IF NOT EXISTS `category_tags` JSON DEFAULT NULL COMMENT 'JSON array of category tags: sustainability, asean, intercultural, etc.' AFTER `document_id`,
ADD COLUMN IF NOT EXISTS `partners` JSON DEFAULT NULL COMMENT 'JSON array of partner institutions/organizations' AFTER `category_tags`,
ADD COLUMN IF NOT EXISTS `outcomes` TEXT DEFAULT NULL COMMENT 'Description of event outcomes and impact' AFTER `partners`,
ADD COLUMN IF NOT EXISTS `participant_count` INT(11) DEFAULT NULL COMMENT 'Number of participants' AFTER `outcomes`,
ADD COLUMN IF NOT EXISTS `is_award_evidence` TINYINT(1) DEFAULT 0 COMMENT 'Flag if marked as award evidence' AFTER `participant_count`,
ADD COLUMN IF NOT EXISTS `sdg_alignment` JSON DEFAULT NULL COMMENT 'JSON array of aligned SDG numbers (1-17)' AFTER `is_award_evidence`;

-- Add foreign key constraint for document_id if not exists
-- (May already exist from original schema)
-- ALTER TABLE `events` ADD CONSTRAINT `fk_events_document` FOREIGN KEY (`document_id`) REFERENCES `other_documents`(`id`) ON DELETE SET NULL;

-- ============================================
-- 2. Create award_event_evidence table (enhanced linking)
-- ============================================

CREATE TABLE IF NOT EXISTS `award_event_evidence` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `event_id` INT(11) NOT NULL COMMENT 'FK to events table',
    `award_criteria_id` INT(11) DEFAULT NULL COMMENT 'FK to award_criteria table',
    `award_category` VARCHAR(255) NOT NULL COMMENT 'Award category name e.g. Global Citizenship Award',
    `criterion_matched` VARCHAR(255) DEFAULT NULL COMMENT 'Specific criterion that was matched',
    `match_type` ENUM('automatic', 'manual') DEFAULT 'automatic' COMMENT 'How the match was determined',
    `match_score` DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Match confidence score 0-100',
    `evidence_text` TEXT DEFAULT NULL COMMENT 'Snippet of text that matched',
    `matched_keywords` JSON DEFAULT NULL COMMENT 'JSON array of matched keywords',
    `status` ENUM('pending_review', 'confirmed', 'rejected') DEFAULT 'pending_review',
    `reviewed_by` INT(11) DEFAULT NULL COMMENT 'User ID who reviewed this evidence',
    `reviewed_at` TIMESTAMP NULL DEFAULT NULL,
    `notes` TEXT DEFAULT NULL COMMENT 'Admin notes about this evidence',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_event_id` (`event_id`),
    KEY `idx_award_category` (`award_category`),
    KEY `idx_status` (`status`),
    KEY `idx_match_score` (`match_score`),
    CONSTRAINT `fk_award_event_evidence_event` FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_award_event_evidence_criteria` FOREIGN KEY (`award_criteria_id`) REFERENCES `award_criteria`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_award_event_evidence_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 3. Create icons2025_documentary_requirements table
-- ============================================

CREATE TABLE IF NOT EXISTS `icons2025_requirements` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `award_category` VARCHAR(255) NOT NULL COMMENT 'Award category name',
    `award_type` ENUM('Institutional', 'Individual', 'Special') DEFAULT 'Institutional',
    `requirement_type` ENUM('video', 'document', 'certificate', 'mou_moa', 'report', 'other') NOT NULL,
    `requirement_name` VARCHAR(255) NOT NULL COMMENT 'e.g. 3-minute video, MOA with partner',
    `requirement_description` TEXT DEFAULT NULL,
    `max_count` INT(11) DEFAULT 10 COMMENT 'Max documents allowed',
    `is_required` TINYINT(1) DEFAULT 1,
    `guide_questions` JSON DEFAULT NULL COMMENT 'JSON array of guide questions for video',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_award_category` (`award_category`),
    KEY `idx_requirement_type` (`requirement_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 4. Insert ICONS 2025 Documentary Requirements
-- ============================================

INSERT INTO `icons2025_requirements` (`award_category`, `award_type`, `requirement_type`, `requirement_name`, `requirement_description`, `is_required`, `guide_questions`) VALUES

-- Global Citizenship Award
('Global Citizenship Award', 'Institutional', 'video', '3-minute Video', 'Video responding to guide questions about global citizenship initiatives', 1, 
 '["How did your institution demonstrate commitment to fostering global citizenship among students, faculty, and staff?", "How did your institution engage with local and global communities to address global challenges?", "What instances showed leadership, innovation, and impact in advancing SDGs?", "How did your institution assess the impact and measure students global competencies?"]'),
('Global Citizenship Award', 'Institutional', 'document', 'Supporting Documents', 'MOAs, MOUs, Certificates, Reports - max 10 documents', 1, NULL),

-- Outstanding International Education Program Award
('Outstanding International Education Program Award', 'Institutional', 'video', '3-minute Video', 'Video about inclusive internationalization efforts', 1,
 '["How did this initiative showcase priority towards inclusive internationalization?", "How did it facilitate participation of students from diverse backgrounds?", "How did it integrate indigenous perspectives into international education?", "How did this program positively impact students learning? Share success stories."]'),
('Outstanding International Education Program Award', 'Institutional', 'document', 'Supporting Documents', 'MOAs, MOUs, Certificates, Reports - max 10 documents', 1, NULL),

-- Sustainability Award
('Sustainability Award', 'Institutional', 'video', '3-minute Video', 'Video about sustainability initiatives aligned with UN SDGs', 1,
 '["What specific SDG/SDGs are being addressed? Describe measurable results.", "How has this initiative been integrated into core functions (operations, curriculum, community engagement)?", "What evidence demonstrates long-term viability and potential for expansion?"]'),
('Sustainability Award', 'Institutional', 'document', 'Supporting Documents', 'MOAs, MOUs, Certificates, Reports - max 10 documents', 1, NULL),

-- Best ASEAN Awareness Initiative Award
('Best ASEAN Awareness Initiative Award', 'Institutional', 'video', '3-minute Video', 'Video about ASEAN awareness and solidarity initiatives', 1,
 '["How did the initiative creatively advance ASEAN awareness and solidarity?", "What is the impact of cross-cultural programs on fostering intercultural exchange?", "What are the measurable results and evidence of impact?", "What is the plan to level-up the program for succeeding years?"]'),
('Best ASEAN Awareness Initiative Award', 'Institutional', 'document', 'Supporting Documents', 'MOAs, MOUs, Certificates, Reports - max 10 documents', 1, NULL),

-- Emerging Leadership Award
('Emerging Leadership Award', 'Individual', 'video', '3-minute Video (Interview Format)', 'Interview format video featuring nominator interviewing nominee', 1,
 '["Describe a specific initiative you spearheaded demonstrating creative approach to internationalization.", "Explain how your vision promoted diversity, equity, and accessibility.", "Describe how you served as mentor or advocate for next generation leaders."]'),
('Emerging Leadership Award', 'Individual', 'document', 'Supporting Documents', 'MOAs, MOUs, Certificates, Reports - max 10 documents', 1, NULL),

-- Internationalization Leadership Award
('Internationalization Leadership Award', 'Individual', 'video', '5-minute Video (Interview Format)', 'Interview format video for executive officials', 1,
 '["Describe the institutional strategy you spearheaded to integrate international dimensions. Provide concrete example.", "Provide evidence of ethical and inclusive leadership. Describe fiscal management system.", "Explain how your leadership built foundation for sustained impact and development."]'),
('Internationalization Leadership Award', 'Individual', 'document', 'Supporting Documents', 'MOAs, MOUs, Certificates, Reports - max 10 documents', 1, NULL),

-- Best CHED Regional Office for Internationalization Award
('Best CHED Regional Office for Internationalization Award', 'Special', 'video', '3-minute Video', 'Video about regional internationalization efforts', 1,
 '["Provide examples of how initiatives increased access for underrepresented groups.", "Highlight a key event between July 2024-July 2025 with CHED IAS.", "How does your office maintain operational excellence and governance?", "What are measurable results and plans for expansion?"]'),
('Best CHED Regional Office for Internationalization Award', 'Special', 'document', 'Supporting Documents', 'MOAs, MOUs, Certificates, Reports - max 10 documents', 1, NULL),

-- Most Promising Regional IRO Community Award
('Most Promising Regional IRO Community Award', 'Special', 'video', '3-minute Video', 'Video about Regional IRO Community vision and progress', 1,
 '["Describe the core vision and organizational structure of your Regional IRO Community.", "What is the most successful collaborative initiative implemented?", "What is the most promising future initiative planned for 2025-2026?"]'),
('Most Promising Regional IRO Community Award', 'Special', 'document', 'Supporting Documents', 'MOAs, MOUs, Certificates, Reports - max 10 documents', 1, NULL);

-- ============================================
-- 5. Add award evidence summary view
-- ============================================

CREATE OR REPLACE VIEW `v_award_evidence_summary` AS
SELECT 
    ae.award_category,
    ae.event_id,
    e.title AS event_title,
    e.description AS event_description,
    e.event_date,
    e.category_tags,
    e.partners,
    ae.criterion_matched,
    ae.match_score,
    ae.match_type,
    ae.status AS evidence_status,
    ae.created_at AS detected_at,
    u.full_name AS created_by
FROM award_event_evidence ae
JOIN events e ON ae.event_id = e.id
LEFT JOIN users u ON e.user_id = u.id
ORDER BY ae.match_score DESC, ae.created_at DESC;

-- ============================================
-- 6. Update award_criteria with ICONS 2025 detailed criteria
-- ============================================

-- Delete old criteria and insert new ICONS 2025 criteria
DELETE FROM `award_criteria` WHERE `category_name` IN (
    'Global Citizenship Award',
    'Outstanding International Education Program Award', 
    'Sustainability Award',
    'Best ASEAN Awareness Initiative Award',
    'Emerging Leadership Award',
    'Internationalization Leadership Award',
    'Best CHED Regional Office for Internationalization Award',
    'Most Promising Regional IRO Community Award'
);

INSERT INTO `award_criteria` (`category_name`, `award_type`, `description`, `requirements`, `keywords`, `min_match_percentage`, `weight`, `status`) VALUES

('Global Citizenship Award', 'Institutional', 
 'Honors HEIs that foster global awareness, intercultural understanding, and responsible leadership.',
 '["Ignite Intercultural Understanding: Programs creating inclusive learning experiences fostering mutual respect across cultures", "Empower Changemakers: Students gain knowledge and skills to tackle challenges aligned with SDGs", "Cultivate Active Engagement: Platforms for students to translate global awareness into concrete action"]',
 'global citizenship, intercultural understanding, student mobility, global collaboration, community engagement, leadership, cross-border partnerships, sustainable development goals, SDG, inclusive learning, changemakers, global awareness, cultural understanding',
 60, 50, 'active'),

('Outstanding International Education Program Award', 'Institutional',
 'Honors HEIs that champion inclusive internationalization with programs that expand access to global opportunities.',
 '["Expand Access to Global Opportunities: Break down barriers, include diverse students from various backgrounds and financial situations", "Foster Collaborative Innovation: Partnerships with local and international partners fuel culturally-rich experiences", "Embrace Inclusivity and Beyond: Actively dismantle barriers, advocate that international education benefits everyone"]',
 'international education, exchange, cross-border collaboration, inclusive, academic partnership, mobility, internationalization, study abroad, global opportunities, diverse backgrounds, accessibility, indigenous perspectives',
 60, 50, 'active'),

('Sustainability Award', 'Institutional',
 'Recognizes HEIs demonstrating outstanding commitment to sustainability aligned with UN SDGs.',
 '["Pioneering Integration: Strategic integration of UN SDGs across institutional operations, academic curriculum, and community engagement", "Impactful Projects: Specific measurable projects in energy efficiency, waste management, social well-being aligned with SDGs", "Long-Term Commitment: Scalability, financial viability, and institutional commitment for longevity beyond initial phase"]',
 'sustainability, sustainable development, SDG, environment, green campus, eco-friendly, climate action, renewable energy, waste management, recycling, carbon reduction, energy efficiency, conservation',
 60, 50, 'active'),

('Best ASEAN Awareness Initiative Award', 'Institutional',
 'Honors HEIs that implemented initiatives boosting understanding and awareness of ASEAN.',
 '["Promote Regional Identity and Solidarity: Advance ASEAN awareness through curriculum integration, cultural festivals, youth dialogue", "Cross-Cultural Initiative Programs: Exchange and collaboration programs fostering genuine intercultural exchange toward integrated ASEAN community", "Measurable Outreach and Sustained Commitment: Clear evidence of reach and impact with long-term commitment to ASEAN narrative"]',
 'ASEAN, regional cooperation, Southeast Asia, ASEAN integration, regional solidarity, cultural understanding, ASEAN community, regional identity, youth dialogue, ASEAN awareness, cultural festival',
 60, 50, 'active'),

('Emerging Leadership Award', 'Individual',
 'Recognizes rising stars in internationalization - IRO Directors, Officers, and junior leaders.',
 '["Practice Innovation: Spearhead creative approaches to internationalization, fostering global collaboration", "Drive Strategic and Inclusive Growth: Promote diversity, equity, accessibility, expanding access and impact", "Empower Others: Serve as mentors and advocates, cultivating future leaders in international education"]',
 'emerging leader, young leader, mentorship, innovation, collaboration, initiative, leadership growth, IRO, international relations, professional development, diversity, equity',
 60, 50, 'active'),

('Internationalization Leadership Award', 'Individual',
 'Honors transformative executive officials (President, VP level) shaping Philippine Higher Education.',
 '["Strategic Vision and Integration: Champion bold innovation integrating international dimensions across planning, programs, research", "Ethical Leadership and Governance: Exemplify integrity, ethical decision-making, responsible fiscal management with timely liquidation", "Sustained Impact and Development: Encourage lifelong learning culture, commitment to equity, sustainability, and excellence"]',
 'strategic vision, governance, institutional leadership, ethical leadership, resilience, mentorship, global excellence, executive leadership, policy, internationalization strategy, fiscal management',
 60, 50, 'active'),

('Best CHED Regional Office for Internationalization Award', 'Special',
 'Recognizes CHED Regional Office demonstrating exceptional commitment to sustainable and inclusive internationalization.',
 '["Comprehensive Internationalization Efforts: Strategy and initiatives embedding international dimension across regional HEIs with sustainability focus", "Cooperation with CHED IAS: Proactive execution of national programs, consistent support, effective communication July 2024-July 2025", "Operational Excellence and Compliance: Timely submission of mandated reports, full compliance with IAS tasks", "Measurable Impact: Evidence through rankings, international enrollment, faculty exchange, survey results"]',
 'CHED, regional office, policy, coordination, regional program, IZN promotion, administrative leadership, compliance, governance, operational excellence',
 60, 50, 'active'),

('Most Promising Regional IRO Community Award', 'Special',
 'Recognizes Regional IRO Community demonstrating significant vision and potential to strengthen internationalization.',
 '["Vision and Strategic Plan: Clear compelling vision and structure addressing specific regional needs", "Early Progress and Collaboration: Successful high-impact collaborative activity with member HEIs or CHED Regional Office", "Promising Future and Value: Promising initiative for next academic year creating exceptional lasting value"]',
 'IRO network, collaboration, regional partnership, international relations office, shared initiative, community, regional cooperation, capacity building, professional development',
 60, 50, 'active');

-- ============================================
-- 7. Add indexes for performance
-- ============================================

-- Index on events for category_tags search (requires MySQL 8.0+ for JSON indexes)
-- CREATE INDEX idx_events_category_tags ON events ((CAST(category_tags AS CHAR(255) ARRAY)));

-- Standard indexes
CREATE INDEX IF NOT EXISTS idx_events_is_award_evidence ON events(is_award_evidence);
CREATE INDEX IF NOT EXISTS idx_award_event_evidence_category_score ON award_event_evidence(award_category, match_score DESC);

COMMIT;

