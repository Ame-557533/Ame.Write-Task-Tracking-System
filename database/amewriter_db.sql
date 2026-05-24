-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 24, 2026 at 07:47 PM
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
-- Database: `amewriter_db`
--

DELIMITER $$
--
-- Functions
--
CREATE DEFINER=`root`@`localhost` FUNCTION `fn_completion_rate` (`p_user_id` INT) RETURNS DECIMAL(5,2) DETERMINISTIC READS SQL DATA BEGIN
  DECLARE total INT;
  DECLARE done  INT;
  SELECT COUNT(DISTINCT project_id) INTO total FROM project_collaborators WHERE user_id = p_user_id;
  SELECT COUNT(*) INTO done FROM projects p
  JOIN project_collaborators pc ON pc.project_id = p.id
  WHERE pc.user_id = p_user_id AND p.status = 'complete';
  IF total = 0 THEN RETURN 0.00; END IF;
  RETURN ROUND((done / total) * 100, 2);
END$$

CREATE DEFINER=`root`@`localhost` FUNCTION `fn_user_completed_count` (`p_user_id` INT) RETURNS INT(11) DETERMINISTIC READS SQL DATA BEGIN
  DECLARE total INT;
  SELECT COUNT(*) INTO total
  FROM projects p
  JOIN project_collaborators pc ON pc.project_id = p.id
  WHERE pc.user_id = p_user_id AND p.status = 'complete';
  RETURN total;
END$$

CREATE DEFINER=`root`@`localhost` FUNCTION `fn_user_project_count` (`p_user_id` INT) RETURNS INT(11) DETERMINISTIC READS SQL DATA BEGIN
  DECLARE total INT;
  SELECT COUNT(DISTINCT project_id) INTO total
  FROM project_collaborators WHERE user_id = p_user_id;
  RETURN total;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `activity_log`
--

CREATE TABLE `activity_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `target` varchar(100) DEFAULT '',
  `target_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `activity_log`
--

INSERT INTO `activity_log` (`id`, `user_id`, `action`, `target`, `target_id`, `created_at`) VALUES
(1, 1, 'created_project', 'projects', 1, '2026-05-19 11:10:56'),
(2, 1, 'deleted_project', 'projects', 1, '2026-05-19 11:11:15'),
(3, 2, 'created_project', 'projects', 2, '2026-05-19 11:16:57'),
(4, 1, 'joined_project', 'projects', 2, '2026-05-19 11:20:38'),
(5, 1, 'edited_writing', 'projects', 2, '2026-05-20 11:59:49'),
(6, 1, 'edited_writing', 'projects', 2, '2026-05-20 12:00:15'),
(7, 1, 'edited_writing', 'projects', 2, '2026-05-20 12:00:24'),
(8, 1, 'edited_writing', 'projects', 2, '2026-05-20 12:00:26'),
(9, 1, 'edited_writing', 'projects', 2, '2026-05-20 12:00:35'),
(10, 1, 'edited_writing', 'projects', 2, '2026-05-20 12:00:49'),
(11, 1, 'edited_writing', 'projects', 2, '2026-05-20 12:00:50'),
(12, 1, 'edited_writing', 'projects', 2, '2026-05-20 12:05:46'),
(13, 1, 'edited_writing', 'projects', 2, '2026-05-20 12:05:48'),
(14, 1, 'edited_writing', 'projects', 2, '2026-05-20 12:05:51'),
(15, 1, 'edited_writing', 'projects', 2, '2026-05-20 12:05:53'),
(16, 1, 'edited_writing', 'projects', 2, '2026-05-20 12:05:55'),
(17, 1, 'edited_writing', 'projects', 2, '2026-05-20 12:06:08'),
(18, 1, 'edited_writing', 'projects', 2, '2026-05-20 12:06:19'),
(19, 1, 'edited_writing', 'projects', 2, '2026-05-20 12:06:24'),
(20, 1, 'edited_writing', 'projects', 2, '2026-05-20 12:06:25'),
(21, 1, 'edited_writing', 'projects', 2, '2026-05-20 12:06:27'),
(22, 1, 'edited_writing', 'projects', 2, '2026-05-20 12:06:48'),
(23, 1, 'edited_writing', 'projects', 2, '2026-05-20 19:09:29'),
(24, 1, 'edited_writing', 'projects', 2, '2026-05-20 19:09:34'),
(25, 1, 'edited_writing', 'projects', 2, '2026-05-20 19:09:36'),
(26, 1, 'edited_writing', 'projects', 2, '2026-05-20 19:09:38'),
(27, 1, 'edited_writing', 'projects', 2, '2026-05-20 19:09:41'),
(28, 1, 'edited_writing', 'projects', 2, '2026-05-20 19:10:05'),
(29, 2, 'edited_writing', 'projects', 2, '2026-05-20 19:11:32'),
(30, 2, 'completed_project', 'projects', 2, '2026-05-20 19:11:37'),
(31, 2, 'edited_writing', 'projects', 2, '2026-05-20 22:03:50'),
(32, 2, 'edited_writing', 'projects', 2, '2026-05-20 22:04:00'),
(33, 2, 'edited_writing', 'projects', 2, '2026-05-20 22:04:04'),
(34, 2, 'edited_writing', 'projects', 2, '2026-05-20 22:04:06'),
(35, 2, 'edited_writing', 'projects', 2, '2026-05-20 22:04:08'),
(36, 2, 'edited_writing', 'projects', 2, '2026-05-20 22:04:14'),
(37, 2, 'edited_writing', 'projects', 2, '2026-05-20 22:04:15'),
(38, 2, 'created_project', 'projects', 3, '2026-05-20 22:43:09'),
(39, 1, 'joined_project', 'projects', 3, '2026-05-20 22:43:59'),
(40, 1, 'edited_writing', 'projects', 3, '2026-05-20 22:46:43'),
(41, 1, 'edited_writing', 'projects', 3, '2026-05-20 22:46:48'),
(42, 1, 'edited_writing', 'projects', 3, '2026-05-20 22:46:51'),
(43, 1, 'edited_writing', 'projects', 3, '2026-05-20 22:47:02'),
(44, 1, 'edited_writing', 'projects', 3, '2026-05-20 22:47:10'),
(45, 1, 'edited_writing', 'projects', 3, '2026-05-20 22:47:18'),
(46, 1, 'edited_writing', 'projects', 3, '2026-05-20 22:47:20'),
(47, 1, 'edited_writing', 'projects', 3, '2026-05-20 22:47:21'),
(48, 1, 'edited_writing', 'projects', 3, '2026-05-20 22:47:21'),
(49, 1, 'edited_writing', 'projects', 3, '2026-05-20 22:47:21'),
(50, 1, 'edited_writing', 'projects', 3, '2026-05-20 22:47:23'),
(51, 1, 'created_project', 'projects', 4, '2026-05-20 22:52:16'),
(52, 1, 'created_project', 'projects', 5, '2026-05-20 22:52:18'),
(53, 1, 'created_project', 'projects', 6, '2026-05-20 22:52:22'),
(54, 1, 'created_project', 'projects', 7, '2026-05-20 22:52:26'),
(55, 1, 'completed_project', 'projects', 7, '2026-05-20 22:52:29'),
(56, 1, 'deleted_project', 'projects', 6, '2026-05-20 22:52:37'),
(57, 1, 'deleted_project', 'projects', 5, '2026-05-20 22:52:39'),
(58, 1, 'deleted_project', 'projects', 4, '2026-05-20 22:52:41'),
(59, 1, 'deleted_project', 'projects', 7, '2026-05-20 22:52:43'),
(60, 1, 'edited_writing', 'projects', 2, '2026-05-24 23:08:20'),
(61, 1, 'edited_writing', 'projects', 3, '2026-05-24 23:08:35'),
(62, 1, 'joined_project', 'projects', 3, '2026-05-24 23:13:29'),
(63, 1, 'edited_writing', 'projects', 3, '2026-05-24 23:39:34');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `from_user` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `type` enum('added_to_project','removed_from_project','project_complete','status_changed','project_deleted') NOT NULL,
  `message` varchar(255) NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `from_user`, `project_id`, `type`, `message`, `is_read`, `created_at`) VALUES
(1, 2, 1, 2, 'status_changed', 'Emman Ame changed status to In progress on \"test\"', 1, '2026-05-20 12:05:08'),
(2, 2, 1, 2, 'status_changed', 'Emman Ame changed status to Draft on \"test\"', 1, '2026-05-20 12:05:21'),
(3, 1, 2, 2, 'status_changed', 'Joyce Arianne Mamac changed status to Complete on \"test\"', 1, '2026-05-20 19:11:37'),
(4, 1, 2, 3, 'added_to_project', 'Joyce Arianne Mamac added you to \"fdfsdfsdf\"', 1, '2026-05-20 22:43:59'),
(5, 2, 1, 3, 'status_changed', 'Emman Ame changed status to In progress on \"fdfsdfsdf\"', 1, '2026-05-20 22:46:13');

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `id` int(11) NOT NULL,
  `owner_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `type` enum('essay','journal','speech','article','blog','report','other') DEFAULT 'other',
  `status` enum('draft','in_progress','review','complete') DEFAULT 'draft',
  `is_solo` tinyint(1) DEFAULT 0,
  `due_date` date DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `projects`
--

INSERT INTO `projects` (`id`, `owner_id`, `title`, `description`, `type`, `status`, `is_solo`, `due_date`, `created_at`, `updated_at`) VALUES
(2, 2, 'test', 'test', 'other', 'complete', 0, '2026-05-19', '2026-05-19 11:16:57', '2026-05-20 19:11:37'),
(3, 2, 'fdfsdfsdf', '', 'speech', 'in_progress', 0, '2026-05-20', '2026-05-20 22:43:09', '2026-05-20 22:46:13');

--
-- Triggers `projects`
--
DELIMITER $$
CREATE TRIGGER `trg_after_project_delete` AFTER DELETE ON `projects` FOR EACH ROW BEGIN
  INSERT INTO activity_log (user_id, action, target, target_id)
  VALUES (OLD.owner_id, 'deleted_project', 'projects', OLD.id);
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_after_project_insert` AFTER INSERT ON `projects` FOR EACH ROW BEGIN
  INSERT INTO project_collaborators (project_id, user_id)
  VALUES (NEW.id, NEW.owner_id);
 
  INSERT INTO activity_log (user_id, action, target, target_id)
  VALUES (NEW.owner_id, 'created_project', 'projects', NEW.id);
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_after_project_update` AFTER UPDATE ON `projects` FOR EACH ROW BEGIN
  IF OLD.status != 'complete' AND NEW.status = 'complete' THEN
    INSERT INTO activity_log (user_id, action, target, target_id)
    VALUES (NEW.owner_id, 'completed_project', 'projects', NEW.id);
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `project_collaborators`
--

CREATE TABLE `project_collaborators` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `joined_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `project_collaborators`
--

INSERT INTO `project_collaborators` (`id`, `project_id`, `user_id`, `joined_at`) VALUES
(2, 2, 2, '2026-05-19 11:16:57'),
(3, 2, 1, '2026-05-19 11:20:38'),
(4, 3, 2, '2026-05-20 22:43:09'),
(10, 3, 1, '2026-05-24 23:13:29');

--
-- Triggers `project_collaborators`
--
DELIMITER $$
CREATE TRIGGER `trg_after_collaborator_insert` AFTER INSERT ON `project_collaborators` FOR EACH ROW BEGIN
  DECLARE owner INT;
  SELECT owner_id INTO owner FROM projects WHERE id = NEW.project_id;
  IF NEW.user_id != owner THEN
    INSERT INTO activity_log (user_id, action, target, target_id)
    VALUES (NEW.user_id, 'joined_project', 'projects', NEW.project_id);
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('speechwriter','ghostwriter','copywriter','journalist') DEFAULT 'copywriter',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `settings` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password_hash`, `role`, `is_active`, `created_at`, `updated_at`, `settings`) VALUES
(1, 'Emman Ame', 'ameEmman@gmail.com', '$2y$10$zfYFzjUoTNNLfEQ08LrBg.vlbK2VvaDUiSWOJKYyKgENcDpIJiAlC', 'speechwriter', 1, '2026-05-19 10:50:12', '2026-05-24 23:46:28', '{\"notif_enabled\":0,\"theme\":\"light\"}'),
(2, 'Joyce Arianne Mamac', 'joyceMamac@gmail.com', '$2y$10$YKQuFV5ZAhJHjmVOEhVWseGzJ9ResikfxjrX5BypdR2kB1YHusHRW', 'speechwriter', 1, '2026-05-19 11:15:52', '2026-05-24 23:46:44', '{\"notif_enabled\":0,\"theme\":\"dark\"}'),
(3, 'Decoy Acc', 'decoy@gmail.com', '$2y$10$qWrJVlLoOTjAFGspK046VeZm3VlMeGY0ahUWqfrNJvHPv8QW.7m2O', 'copywriter', 0, '2026-05-20 22:09:00', '2026-05-20 22:09:48', NULL);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_overdue_projects`
-- (See below for the actual view)
--
CREATE TABLE `vw_overdue_projects` (
`id` int(11)
,`title` varchar(255)
,`type` enum('essay','journal','speech','article','blog','report','other')
,`due_date` date
,`days_overdue` int(7)
,`owner_name` varchar(100)
,`owner_email` varchar(150)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_projects_detail`
-- (See below for the actual view)
--
CREATE TABLE `vw_projects_detail` (
`id` int(11)
,`title` varchar(255)
,`description` text
,`type` enum('essay','journal','speech','article','blog','report','other')
,`status` enum('draft','in_progress','review','complete')
,`is_solo` tinyint(1)
,`due_date` date
,`created_at` datetime
,`updated_at` datetime
,`owner_id` int(11)
,`owner_name` varchar(100)
,`owner_email` varchar(150)
,`owner_role` enum('speechwriter','ghostwriter','copywriter','journalist')
,`collaborator_count` bigint(21)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_recent_activity`
-- (See below for the actual view)
--
CREATE TABLE `vw_recent_activity` (
`id` int(11)
,`action` varchar(100)
,`target` varchar(100)
,`target_id` int(11)
,`created_at` datetime
,`user_name` varchar(100)
,`user_email` varchar(150)
);

-- --------------------------------------------------------

--
-- Table structure for table `writing_content`
--

CREATE TABLE `writing_content` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `content` longtext DEFAULT NULL,
  `last_edited_by` int(11) DEFAULT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `writing_content`
--

INSERT INTO `writing_content` (`id`, `project_id`, `content`, `last_edited_by`, `updated_at`) VALUES
(1, 2, '<h2><b>I love speech writing</b>!</h2><p>Everyday I see myself....</p>', 1, '2026-05-24 23:08:20'),
(33, 3, '<h2>helloo</h2><p>i am black</p>', 1, '2026-05-24 23:39:34');

-- --------------------------------------------------------

--
-- Structure for view `vw_overdue_projects`
--
DROP TABLE IF EXISTS `vw_overdue_projects`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_overdue_projects`  AS SELECT `p`.`id` AS `id`, `p`.`title` AS `title`, `p`.`type` AS `type`, `p`.`due_date` AS `due_date`, to_days(curdate()) - to_days(`p`.`due_date`) AS `days_overdue`, `u`.`name` AS `owner_name`, `u`.`email` AS `owner_email` FROM (`projects` `p` join `users` `u` on(`p`.`owner_id` = `u`.`id`)) WHERE `p`.`status` <> 'complete' AND `p`.`due_date` is not null AND `p`.`due_date` < curdate() ;

-- --------------------------------------------------------

--
-- Structure for view `vw_projects_detail`
--
DROP TABLE IF EXISTS `vw_projects_detail`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_projects_detail`  AS SELECT `p`.`id` AS `id`, `p`.`title` AS `title`, `p`.`description` AS `description`, `p`.`type` AS `type`, `p`.`status` AS `status`, `p`.`is_solo` AS `is_solo`, `p`.`due_date` AS `due_date`, `p`.`created_at` AS `created_at`, `p`.`updated_at` AS `updated_at`, `u`.`id` AS `owner_id`, `u`.`name` AS `owner_name`, `u`.`email` AS `owner_email`, `u`.`role` AS `owner_role`, (select count(0) from `project_collaborators` `pc` where `pc`.`project_id` = `p`.`id`) AS `collaborator_count` FROM (`projects` `p` join `users` `u` on(`p`.`owner_id` = `u`.`id`)) ;

-- --------------------------------------------------------

--
-- Structure for view `vw_recent_activity`
--
DROP TABLE IF EXISTS `vw_recent_activity`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_recent_activity`  AS SELECT `a`.`id` AS `id`, `a`.`action` AS `action`, `a`.`target` AS `target`, `a`.`target_id` AS `target_id`, `a`.`created_at` AS `created_at`, `u`.`name` AS `user_name`, `u`.`email` AS `user_email` FROM (`activity_log` `a` join `users` `u` on(`a`.`user_id` = `u`.`id`)) ORDER BY `a`.`created_at` DESC ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `from_user` (`from_user`),
  ADD KEY `project_id` (`project_id`);

--
-- Indexes for table `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `owner_id` (`owner_id`);

--
-- Indexes for table `project_collaborators`
--
ALTER TABLE `project_collaborators`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_project_user` (`project_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `writing_content`
--
ALTER TABLE `writing_content`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `project_id` (`project_id`),
  ADD KEY `last_edited_by` (`last_edited_by`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=64;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `project_collaborators`
--
ALTER TABLE `project_collaborators`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `writing_content`
--
ALTER TABLE `writing_content`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD CONSTRAINT `activity_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`from_user`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notifications_ibfk_3` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `projects`
--
ALTER TABLE `projects`
  ADD CONSTRAINT `projects_ibfk_1` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `project_collaborators`
--
ALTER TABLE `project_collaborators`
  ADD CONSTRAINT `project_collaborators_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `project_collaborators_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `writing_content`
--
ALTER TABLE `writing_content`
  ADD CONSTRAINT `writing_content_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `writing_content_ibfk_2` FOREIGN KEY (`last_edited_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
