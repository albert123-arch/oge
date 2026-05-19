-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: May 08, 2026 at 08:41 AM
-- Server version: 11.8.6-MariaDB-log
-- PHP Version: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u770916388_oge_data`
--

-- --------------------------------------------------------

--
-- Table structure for table `oge_bookmarks`
--

CREATE TABLE `oge_bookmarks` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `oge_exam_blueprint_tasks`
--

CREATE TABLE `oge_exam_blueprint_tasks` (
  `id` int(11) NOT NULL,
  `exam_version_id` int(11) NOT NULL,
  `task_number` int(11) NOT NULL,
  `part_number` tinyint(4) NOT NULL DEFAULT 1,
  `title` varchar(255) NOT NULL,
  `content_area` varchar(255) DEFAULT NULL,
  `difficulty_level` varchar(30) NOT NULL DEFAULT 'low',
  `answer_format` varchar(30) NOT NULL DEFAULT 'short',
  `max_score` tinyint(4) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `oge_exam_sessions`
--

CREATE TABLE `oge_exam_sessions` (
  `id` int(11) NOT NULL,
  `exam_version_id` int(11) DEFAULT NULL,
  `session_year` int(11) NOT NULL,
  `session_type` enum('demo','march','april','may','june','reserve','early','main','training','teacher','other') NOT NULL DEFAULT 'training',
  `session_month` varchar(30) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(180) NOT NULL,
  `official_date` date DEFAULT NULL,
  `is_official` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `oge_exam_versions`
--

CREATE TABLE `oge_exam_versions` (
  `id` int(11) NOT NULL,
  `exam_year` int(11) NOT NULL,
  `level` varchar(30) NOT NULL DEFAULT 'oge',
  `title` varchar(255) NOT NULL,
  `is_current` tinyint(1) NOT NULL DEFAULT 0,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
--


-- --------------------------------------------------------

--
-- Table structure for table `oge_home_blocks`
--

CREATE TABLE `oge_home_blocks` (
  `id` int(11) NOT NULL,
  `block_key` varchar(100) NOT NULL,
  `title` varchar(255) NOT NULL,
  `body_html` text DEFAULT NULL,
  `button_text` varchar(100) DEFAULT NULL,
  `button_url` varchar(255) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
--


-- --------------------------------------------------------

--
-- Table structure for table `oge_pages`
--

CREATE TABLE `oge_pages` (
  `id` int(11) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `title` varchar(255) NOT NULL,
  `meta_description` text DEFAULT NULL,
  `h1` varchar(255) DEFAULT NULL,
  `intro_html` text DEFAULT NULL,
  `is_published` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
--


-- --------------------------------------------------------

--
-- Table structure for table `oge_questions`
--

CREATE TABLE `oge_questions` (
  `id` int(11) NOT NULL,
  `task_type_id` int(11) NOT NULL,
  `topic_id` int(11) DEFAULT NULL,
  `subtopic_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `body_html` longtext NOT NULL,
  `solution_html` longtext DEFAULT NULL,
  `marking_scheme_html` longtext DEFAULT NULL,
  `answer_text` varchar(255) DEFAULT NULL,
  `answer_type` enum('short','full') NOT NULL DEFAULT 'short',
  `max_score` tinyint(4) NOT NULL DEFAULT 1,
  `difficulty` varchar(30) NOT NULL DEFAULT 'medium',
  `source` varchar(255) DEFAULT NULL,
  `source_name` varchar(255) DEFAULT NULL,
  `source_year` int(11) DEFAULT NULL,
  `source_month` varchar(50) DEFAULT NULL,
  `source_period` enum('demo','march','april','may','june','reserve','early','main','teacher','training','other') DEFAULT NULL,
  `source_variant_code` varchar(100) DEFAULT NULL,
  `source_task_number` int(11) DEFAULT NULL,
  `source_url` varchar(500) DEFAULT NULL,
  `source_external_id` varchar(100) DEFAULT NULL,
  `auto_check_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `is_published` tinyint(1) NOT NULL DEFAULT 1,
  `checked` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
--


-- --------------------------------------------------------

--
-- Table structure for table `oge_question_attempts`
--

CREATE TABLE `oge_question_attempts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `answer_text` text DEFAULT NULL,
  `is_correct` tinyint(1) NOT NULL DEFAULT 0,
  `check_mode` enum('auto','self','teacher','ai') NOT NULL DEFAULT 'auto',
  `score` decimal(5,2) DEFAULT NULL,
  `max_score` tinyint(4) DEFAULT NULL,
  `self_marked` tinyint(1) NOT NULL DEFAULT 0,
  `feedback_html` longtext DEFAULT NULL,
  `time_spent_seconds` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `oge_question_media`
--

CREATE TABLE `oge_question_media` (
  `id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `role` enum('question','solution','hint','extra') NOT NULL DEFAULT 'question',
  `file_path` varchar(500) NOT NULL,
  `file_type` varchar(50) DEFAULT NULL,
  `alt_text` varchar(255) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
--


-- --------------------------------------------------------

--
-- Table structure for table `oge_question_options`
--

CREATE TABLE `oge_question_options` (
  `id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `body_html` text NOT NULL,
  `is_correct` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `oge_question_tags`
--

CREATE TABLE `oge_question_tags` (
  `question_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `oge_tags`
--

CREATE TABLE `oge_tags` (
  `id` int(11) NOT NULL,
  `tag_key` varchar(100) NOT NULL,
  `title` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `oge_task_subtopics`
--

CREATE TABLE `oge_task_subtopics` (
  `id` int(11) NOT NULL,
  `task_type_id` int(11) NOT NULL,
  `topic_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(180) NOT NULL,
  `short_description` text DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
--


-- --------------------------------------------------------

--
-- Table structure for table `oge_task_types`
--

CREATE TABLE `oge_task_types` (
  `id` int(11) NOT NULL,
  `task_number` int(11) NOT NULL,
  `part_number` tinyint(4) NOT NULL DEFAULT 1,
  `title` varchar(255) NOT NULL,
  `difficulty_level` varchar(30) NOT NULL DEFAULT 'low',
  `answer_format` varchar(30) NOT NULL DEFAULT 'short',
  `max_score` tinyint(4) NOT NULL DEFAULT 1,
  `content_area` varchar(255) DEFAULT NULL,
  `short_description` text DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
--


-- --------------------------------------------------------

--
-- Table structure for table `oge_teacher_students`
--

CREATE TABLE `oge_teacher_students` (
  `teacher_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `oge_topics`
--

CREATE TABLE `oge_topics` (
  `id` int(11) NOT NULL,
  `slug` varchar(120) NOT NULL,
  `title` varchar(255) NOT NULL,
  `short_description` text DEFAULT NULL,
  `icon` varchar(20) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
--


-- --------------------------------------------------------

--
-- Table structure for table `oge_users`
--

CREATE TABLE `oge_users` (
  `id` int(11) NOT NULL,
  `email` varchar(190) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(255) DEFAULT NULL,
  `role` enum('admin','teacher','student') NOT NULL DEFAULT 'student',
  `status` enum('active','blocked') NOT NULL DEFAULT 'active',
  `last_login_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
--


-- --------------------------------------------------------

--
-- Table structure for table `oge_variants`
--

CREATE TABLE `oge_variants` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(180) NOT NULL,
  `variant_type` enum('official','training','teacher','random','diagnostic') NOT NULL DEFAULT 'training',
  `source_name` varchar(255) DEFAULT NULL,
  `source_year` int(11) DEFAULT NULL,
  `source_month` varchar(50) DEFAULT NULL,
  `source_period` enum('demo','march','april','may','june','reserve','early','main','teacher','random','training','other') NOT NULL DEFAULT 'training',
  `source_variant_code` varchar(100) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `is_published` tinyint(1) NOT NULL DEFAULT 1,
  `is_official` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `oge_variant_questions`
--

CREATE TABLE `oge_variant_questions` (
  `id` int(11) NOT NULL,
  `variant_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `task_number` int(11) NOT NULL,
  `position_in_variant` int(11) NOT NULL DEFAULT 0,
  `max_score` tinyint(4) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `oge_bookmarks`
--
ALTER TABLE `oge_bookmarks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_user_question` (`user_id`,`question_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_question_id` (`question_id`);

--
-- Indexes for table `oge_exam_blueprint_tasks`
--
ALTER TABLE `oge_exam_blueprint_tasks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_blueprint_task` (`exam_version_id`,`task_number`),
  ADD KEY `idx_exam_version` (`exam_version_id`),
  ADD KEY `idx_task_number` (`task_number`),
  ADD KEY `idx_part_number` (`part_number`);

--
-- Indexes for table `oge_exam_sessions`
--
ALTER TABLE `oge_exam_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_session_slug` (`slug`),
  ADD KEY `exam_version_id` (`exam_version_id`),
  ADD KEY `idx_session_year` (`session_year`),
  ADD KEY `idx_session_type` (`session_type`),
  ADD KEY `idx_active` (`is_active`);

--
-- Indexes for table `oge_exam_versions`
--
ALTER TABLE `oge_exam_versions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_exam_year_level` (`exam_year`,`level`),
  ADD KEY `idx_current` (`is_current`);

--
-- Indexes for table `oge_home_blocks`
--
ALTER TABLE `oge_home_blocks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `block_key` (`block_key`);

--
-- Indexes for table `oge_pages`
--
ALTER TABLE `oge_pages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `oge_questions`
--
ALTER TABLE `oge_questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_task_type` (`task_type_id`),
  ADD KEY `idx_topic` (`topic_id`),
  ADD KEY `idx_created_by` (`created_by`),
  ADD KEY `idx_published` (`is_published`),
  ADD KEY `idx_checked` (`checked`),
  ADD KEY `idx_difficulty` (`difficulty`),
  ADD KEY `idx_subtopic_id` (`subtopic_id`),
  ADD KEY `idx_source_year` (`source_year`),
  ADD KEY `idx_source_period` (`source_period`),
  ADD KEY `idx_source_task_number` (`source_task_number`),
  ADD KEY `idx_answer_type` (`answer_type`);

--
-- Indexes for table `oge_question_attempts`
--
ALTER TABLE `oge_question_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_question_id` (`question_id`),
  ADD KEY `idx_user_question` (`user_id`,`question_id`),
  ADD KEY `idx_correct` (`is_correct`);

--
-- Indexes for table `oge_question_media`
--
ALTER TABLE `oge_question_media`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_question_id` (`question_id`),
  ADD KEY `idx_role` (`role`);

--
-- Indexes for table `oge_question_options`
--
ALTER TABLE `oge_question_options`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_question_id` (`question_id`),
  ADD KEY `idx_correct` (`is_correct`);

--
-- Indexes for table `oge_question_tags`
--
ALTER TABLE `oge_question_tags`
  ADD PRIMARY KEY (`question_id`,`tag_id`),
  ADD KEY `tag_id` (`tag_id`);

--
-- Indexes for table `oge_tags`
--
ALTER TABLE `oge_tags`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tag_key` (`tag_key`);

--
-- Indexes for table `oge_task_subtopics`
--
ALTER TABLE `oge_task_subtopics`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_task_subtopic_slug` (`task_type_id`,`slug`),
  ADD KEY `idx_task_type_id` (`task_type_id`),
  ADD KEY `idx_topic_id` (`topic_id`);

--
-- Indexes for table `oge_task_types`
--
ALTER TABLE `oge_task_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `task_number` (`task_number`),
  ADD UNIQUE KEY `uq_task_number` (`task_number`);

--
-- Indexes for table `oge_teacher_students`
--
ALTER TABLE `oge_teacher_students`
  ADD PRIMARY KEY (`teacher_id`,`student_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `oge_topics`
--
ALTER TABLE `oge_topics`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `oge_users`
--
ALTER TABLE `oge_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `oge_variants`
--
ALTER TABLE `oge_variants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_variant_slug` (`slug`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_variant_type` (`variant_type`),
  ADD KEY `idx_source_year` (`source_year`),
  ADD KEY `idx_source_period` (`source_period`),
  ADD KEY `idx_published` (`is_published`);

--
-- Indexes for table `oge_variant_questions`
--
ALTER TABLE `oge_variant_questions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_variant_question` (`variant_id`,`question_id`),
  ADD KEY `idx_variant_id` (`variant_id`),
  ADD KEY `idx_question_id` (`question_id`),
  ADD KEY `idx_task_number` (`task_number`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `oge_bookmarks`
--
ALTER TABLE `oge_bookmarks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `oge_exam_blueprint_tasks`
--
ALTER TABLE `oge_exam_blueprint_tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `oge_exam_sessions`
--
ALTER TABLE `oge_exam_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `oge_exam_versions`
--
ALTER TABLE `oge_exam_versions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `oge_home_blocks`
--
ALTER TABLE `oge_home_blocks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `oge_pages`
--
ALTER TABLE `oge_pages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `oge_questions`
--
ALTER TABLE `oge_questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `oge_question_attempts`
--
ALTER TABLE `oge_question_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `oge_question_media`
--
ALTER TABLE `oge_question_media`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `oge_question_options`
--
ALTER TABLE `oge_question_options`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `oge_tags`
--
ALTER TABLE `oge_tags`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `oge_task_subtopics`
--
ALTER TABLE `oge_task_subtopics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `oge_task_types`
--
ALTER TABLE `oge_task_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `oge_topics`
--
ALTER TABLE `oge_topics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `oge_users`
--
ALTER TABLE `oge_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `oge_variants`
--
ALTER TABLE `oge_variants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `oge_variant_questions`
--
ALTER TABLE `oge_variant_questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `oge_bookmarks`
--
ALTER TABLE `oge_bookmarks`
  ADD CONSTRAINT `oge_bookmarks_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `oge_users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `oge_bookmarks_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `oge_questions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `oge_exam_blueprint_tasks`
--
ALTER TABLE `oge_exam_blueprint_tasks`
  ADD CONSTRAINT `oge_exam_blueprint_tasks_ibfk_1` FOREIGN KEY (`exam_version_id`) REFERENCES `oge_exam_versions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `oge_exam_sessions`
--
ALTER TABLE `oge_exam_sessions`
  ADD CONSTRAINT `oge_exam_sessions_ibfk_1` FOREIGN KEY (`exam_version_id`) REFERENCES `oge_exam_versions` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `oge_questions`
--
ALTER TABLE `oge_questions`
  ADD CONSTRAINT `oge_questions_ibfk_1` FOREIGN KEY (`task_type_id`) REFERENCES `oge_task_types` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `oge_questions_ibfk_2` FOREIGN KEY (`topic_id`) REFERENCES `oge_topics` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `oge_questions_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `oge_users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_oge_questions_subtopic` FOREIGN KEY (`subtopic_id`) REFERENCES `oge_task_subtopics` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `oge_question_attempts`
--
ALTER TABLE `oge_question_attempts`
  ADD CONSTRAINT `oge_question_attempts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `oge_users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `oge_question_attempts_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `oge_questions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `oge_question_media`
--
ALTER TABLE `oge_question_media`
  ADD CONSTRAINT `oge_question_media_ibfk_1` FOREIGN KEY (`question_id`) REFERENCES `oge_questions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `oge_question_options`
--
ALTER TABLE `oge_question_options`
  ADD CONSTRAINT `oge_question_options_ibfk_1` FOREIGN KEY (`question_id`) REFERENCES `oge_questions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `oge_question_tags`
--
ALTER TABLE `oge_question_tags`
  ADD CONSTRAINT `oge_question_tags_ibfk_1` FOREIGN KEY (`question_id`) REFERENCES `oge_questions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `oge_question_tags_ibfk_2` FOREIGN KEY (`tag_id`) REFERENCES `oge_tags` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `oge_task_subtopics`
--
ALTER TABLE `oge_task_subtopics`
  ADD CONSTRAINT `oge_task_subtopics_ibfk_1` FOREIGN KEY (`task_type_id`) REFERENCES `oge_task_types` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `oge_task_subtopics_ibfk_2` FOREIGN KEY (`topic_id`) REFERENCES `oge_topics` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `oge_teacher_students`
--
ALTER TABLE `oge_teacher_students`
  ADD CONSTRAINT `oge_teacher_students_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `oge_users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `oge_teacher_students_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `oge_users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `oge_variants`
--
ALTER TABLE `oge_variants`
  ADD CONSTRAINT `oge_variants_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `oge_users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `oge_variant_questions`
--
ALTER TABLE `oge_variant_questions`
  ADD CONSTRAINT `oge_variant_questions_ibfk_1` FOREIGN KEY (`variant_id`) REFERENCES `oge_variants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `oge_variant_questions_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `oge_questions` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
