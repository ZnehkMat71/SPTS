-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 25, 2025 at 05:27 PM
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
-- Database: `phpmyadmin`
--
CREATE DATABASE IF NOT EXISTS `phpmyadmin` DEFAULT CHARACTER SET utf8 COLLATE utf8_bin;
USE `phpmyadmin`;

-- --------------------------------------------------------

--
-- Table structure for table `pma__bookmark`
--

CREATE TABLE `pma__bookmark` (
  `id` int(10) UNSIGNED NOT NULL,
  `dbase` varchar(255) NOT NULL DEFAULT '',
  `user` varchar(255) NOT NULL DEFAULT '',
  `label` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '',
  `query` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Bookmarks';

-- --------------------------------------------------------

--
-- Table structure for table `pma__central_columns`
--

CREATE TABLE `pma__central_columns` (
  `db_name` varchar(64) NOT NULL,
  `col_name` varchar(64) NOT NULL,
  `col_type` varchar(64) NOT NULL,
  `col_length` text DEFAULT NULL,
  `col_collation` varchar(64) NOT NULL,
  `col_isNull` tinyint(1) NOT NULL,
  `col_extra` varchar(255) DEFAULT '',
  `col_default` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Central list of columns';

-- --------------------------------------------------------

--
-- Table structure for table `pma__column_info`
--

CREATE TABLE `pma__column_info` (
  `id` int(5) UNSIGNED NOT NULL,
  `db_name` varchar(64) NOT NULL DEFAULT '',
  `table_name` varchar(64) NOT NULL DEFAULT '',
  `column_name` varchar(64) NOT NULL DEFAULT '',
  `comment` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '',
  `mimetype` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '',
  `transformation` varchar(255) NOT NULL DEFAULT '',
  `transformation_options` varchar(255) NOT NULL DEFAULT '',
  `input_transformation` varchar(255) NOT NULL DEFAULT '',
  `input_transformation_options` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Column information for phpMyAdmin';

-- --------------------------------------------------------

--
-- Table structure for table `pma__designer_settings`
--

CREATE TABLE `pma__designer_settings` (
  `username` varchar(64) NOT NULL,
  `settings_data` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Settings related to Designer';

-- --------------------------------------------------------

--
-- Table structure for table `pma__export_templates`
--

CREATE TABLE `pma__export_templates` (
  `id` int(5) UNSIGNED NOT NULL,
  `username` varchar(64) NOT NULL,
  `export_type` varchar(10) NOT NULL,
  `template_name` varchar(64) NOT NULL,
  `template_data` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Saved export templates';

-- --------------------------------------------------------

--
-- Table structure for table `pma__favorite`
--

CREATE TABLE `pma__favorite` (
  `username` varchar(64) NOT NULL,
  `tables` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Favorite tables';

-- --------------------------------------------------------

--
-- Table structure for table `pma__history`
--

CREATE TABLE `pma__history` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `username` varchar(64) NOT NULL DEFAULT '',
  `db` varchar(64) NOT NULL DEFAULT '',
  `table` varchar(64) NOT NULL DEFAULT '',
  `timevalue` timestamp NOT NULL DEFAULT current_timestamp(),
  `sqlquery` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='SQL history for phpMyAdmin';

-- --------------------------------------------------------

--
-- Table structure for table `pma__navigationhiding`
--

CREATE TABLE `pma__navigationhiding` (
  `username` varchar(64) NOT NULL,
  `item_name` varchar(64) NOT NULL,
  `item_type` varchar(64) NOT NULL,
  `db_name` varchar(64) NOT NULL,
  `table_name` varchar(64) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Hidden items of navigation tree';

-- --------------------------------------------------------

--
-- Table structure for table `pma__pdf_pages`
--

CREATE TABLE `pma__pdf_pages` (
  `db_name` varchar(64) NOT NULL DEFAULT '',
  `page_nr` int(10) UNSIGNED NOT NULL,
  `page_descr` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='PDF relation pages for phpMyAdmin';

-- --------------------------------------------------------

--
-- Table structure for table `pma__recent`
--

CREATE TABLE `pma__recent` (
  `username` varchar(64) NOT NULL,
  `tables` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Recently accessed tables';

--
-- Dumping data for table `pma__recent`
--

INSERT INTO `pma__recent` (`username`, `tables`) VALUES
('root', '[{\"db\":\"student_progress_db\",\"table\":\"users\"},{\"db\":\"student_progress_db\",\"table\":\"supervisors\"},{\"db\":\"student_progress_db\",\"table\":\"students\"},{\"db\":\"student_progress_db\",\"table\":\"sip_supervisors\"},{\"db\":\"student_progress_db\",\"table\":\"time_tracking\"}]');

-- --------------------------------------------------------

--
-- Table structure for table `pma__relation`
--

CREATE TABLE `pma__relation` (
  `master_db` varchar(64) NOT NULL DEFAULT '',
  `master_table` varchar(64) NOT NULL DEFAULT '',
  `master_field` varchar(64) NOT NULL DEFAULT '',
  `foreign_db` varchar(64) NOT NULL DEFAULT '',
  `foreign_table` varchar(64) NOT NULL DEFAULT '',
  `foreign_field` varchar(64) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Relation table';

-- --------------------------------------------------------

--
-- Table structure for table `pma__savedsearches`
--

CREATE TABLE `pma__savedsearches` (
  `id` int(5) UNSIGNED NOT NULL,
  `username` varchar(64) NOT NULL DEFAULT '',
  `db_name` varchar(64) NOT NULL DEFAULT '',
  `search_name` varchar(64) NOT NULL DEFAULT '',
  `search_data` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Saved searches';

-- --------------------------------------------------------

--
-- Table structure for table `pma__table_coords`
--

CREATE TABLE `pma__table_coords` (
  `db_name` varchar(64) NOT NULL DEFAULT '',
  `table_name` varchar(64) NOT NULL DEFAULT '',
  `pdf_page_number` int(11) NOT NULL DEFAULT 0,
  `x` float UNSIGNED NOT NULL DEFAULT 0,
  `y` float UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Table coordinates for phpMyAdmin PDF output';

-- --------------------------------------------------------

--
-- Table structure for table `pma__table_info`
--

CREATE TABLE `pma__table_info` (
  `db_name` varchar(64) NOT NULL DEFAULT '',
  `table_name` varchar(64) NOT NULL DEFAULT '',
  `display_field` varchar(64) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Table information for phpMyAdmin';

-- --------------------------------------------------------

--
-- Table structure for table `pma__table_uiprefs`
--

CREATE TABLE `pma__table_uiprefs` (
  `username` varchar(64) NOT NULL,
  `db_name` varchar(64) NOT NULL,
  `table_name` varchar(64) NOT NULL,
  `prefs` text NOT NULL,
  `last_update` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Tables'' UI preferences';

-- --------------------------------------------------------

--
-- Table structure for table `pma__tracking`
--

CREATE TABLE `pma__tracking` (
  `db_name` varchar(64) NOT NULL,
  `table_name` varchar(64) NOT NULL,
  `version` int(10) UNSIGNED NOT NULL,
  `date_created` datetime NOT NULL,
  `date_updated` datetime NOT NULL,
  `schema_snapshot` text NOT NULL,
  `schema_sql` text DEFAULT NULL,
  `data_sql` longtext DEFAULT NULL,
  `tracking` set('UPDATE','REPLACE','INSERT','DELETE','TRUNCATE','CREATE DATABASE','ALTER DATABASE','DROP DATABASE','CREATE TABLE','ALTER TABLE','RENAME TABLE','DROP TABLE','CREATE INDEX','DROP INDEX','CREATE VIEW','ALTER VIEW','DROP VIEW') DEFAULT NULL,
  `tracking_active` int(1) UNSIGNED NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Database changes tracking for phpMyAdmin';

-- --------------------------------------------------------

--
-- Table structure for table `pma__userconfig`
--

CREATE TABLE `pma__userconfig` (
  `username` varchar(64) NOT NULL,
  `timevalue` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `config_data` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='User preferences storage for phpMyAdmin';

--
-- Dumping data for table `pma__userconfig`
--

INSERT INTO `pma__userconfig` (`username`, `timevalue`, `config_data`) VALUES
('root', '2025-05-13 04:32:56', '{\"Console\\/Mode\":\"collapse\"}');

-- --------------------------------------------------------

--
-- Table structure for table `pma__usergroups`
--

CREATE TABLE `pma__usergroups` (
  `usergroup` varchar(64) NOT NULL,
  `tab` varchar(64) NOT NULL,
  `allowed` enum('Y','N') NOT NULL DEFAULT 'N'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='User groups with configured menu items';

-- --------------------------------------------------------

--
-- Table structure for table `pma__users`
--

CREATE TABLE `pma__users` (
  `username` varchar(64) NOT NULL,
  `usergroup` varchar(64) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Users and their assignments to user groups';

--
-- Indexes for dumped tables
--

--
-- Indexes for table `pma__bookmark`
--
ALTER TABLE `pma__bookmark`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `pma__central_columns`
--
ALTER TABLE `pma__central_columns`
  ADD PRIMARY KEY (`db_name`,`col_name`);

--
-- Indexes for table `pma__column_info`
--
ALTER TABLE `pma__column_info`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `db_name` (`db_name`,`table_name`,`column_name`);

--
-- Indexes for table `pma__designer_settings`
--
ALTER TABLE `pma__designer_settings`
  ADD PRIMARY KEY (`username`);

--
-- Indexes for table `pma__export_templates`
--
ALTER TABLE `pma__export_templates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `u_user_type_template` (`username`,`export_type`,`template_name`);

--
-- Indexes for table `pma__favorite`
--
ALTER TABLE `pma__favorite`
  ADD PRIMARY KEY (`username`);

--
-- Indexes for table `pma__history`
--
ALTER TABLE `pma__history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `username` (`username`,`db`,`table`,`timevalue`);

--
-- Indexes for table `pma__navigationhiding`
--
ALTER TABLE `pma__navigationhiding`
  ADD PRIMARY KEY (`username`,`item_name`,`item_type`,`db_name`,`table_name`);

--
-- Indexes for table `pma__pdf_pages`
--
ALTER TABLE `pma__pdf_pages`
  ADD PRIMARY KEY (`page_nr`),
  ADD KEY `db_name` (`db_name`);

--
-- Indexes for table `pma__recent`
--
ALTER TABLE `pma__recent`
  ADD PRIMARY KEY (`username`);

--
-- Indexes for table `pma__relation`
--
ALTER TABLE `pma__relation`
  ADD PRIMARY KEY (`master_db`,`master_table`,`master_field`),
  ADD KEY `foreign_field` (`foreign_db`,`foreign_table`);

--
-- Indexes for table `pma__savedsearches`
--
ALTER TABLE `pma__savedsearches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `u_savedsearches_username_dbname` (`username`,`db_name`,`search_name`);

--
-- Indexes for table `pma__table_coords`
--
ALTER TABLE `pma__table_coords`
  ADD PRIMARY KEY (`db_name`,`table_name`,`pdf_page_number`);

--
-- Indexes for table `pma__table_info`
--
ALTER TABLE `pma__table_info`
  ADD PRIMARY KEY (`db_name`,`table_name`);

--
-- Indexes for table `pma__table_uiprefs`
--
ALTER TABLE `pma__table_uiprefs`
  ADD PRIMARY KEY (`username`,`db_name`,`table_name`);

--
-- Indexes for table `pma__tracking`
--
ALTER TABLE `pma__tracking`
  ADD PRIMARY KEY (`db_name`,`table_name`,`version`);

--
-- Indexes for table `pma__userconfig`
--
ALTER TABLE `pma__userconfig`
  ADD PRIMARY KEY (`username`);

--
-- Indexes for table `pma__usergroups`
--
ALTER TABLE `pma__usergroups`
  ADD PRIMARY KEY (`usergroup`,`tab`,`allowed`);

--
-- Indexes for table `pma__users`
--
ALTER TABLE `pma__users`
  ADD PRIMARY KEY (`username`,`usergroup`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `pma__bookmark`
--
ALTER TABLE `pma__bookmark`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pma__column_info`
--
ALTER TABLE `pma__column_info`
  MODIFY `id` int(5) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pma__export_templates`
--
ALTER TABLE `pma__export_templates`
  MODIFY `id` int(5) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pma__history`
--
ALTER TABLE `pma__history`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pma__pdf_pages`
--
ALTER TABLE `pma__pdf_pages`
  MODIFY `page_nr` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pma__savedsearches`
--
ALTER TABLE `pma__savedsearches`
  MODIFY `id` int(5) UNSIGNED NOT NULL AUTO_INCREMENT;
--
-- Database: `student_progress_db`
--
CREATE DATABASE IF NOT EXISTS `student_progress_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `student_progress_db`;

-- --------------------------------------------------------

--
-- Table structure for table `document_checklist`
--

CREATE TABLE `document_checklist` (
  `id` int(11) NOT NULL,
  `student_identifier` varchar(50) NOT NULL,
  `document_name` varchar(100) NOT NULL,
  `is_checked` tinyint(1) DEFAULT 0,
  `checked_by` int(11) NOT NULL,
  `checked_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `document_checklist`
--

INSERT INTO `document_checklist` (`id`, `student_identifier`, `document_name`, `is_checked`, `checked_by`, `checked_at`) VALUES
(1, '1234-5678-A', 'QF-SIP-01 Student Internship Program Registration Form', 0, 5, '2025-05-08 08:52:15'),
(15, '1234-5678-A', 'QF-SIP-02 Student Internship Program Agreement', 0, 5, '2025-05-08 08:52:15'),
(16, '1234-5678-A', 'QF-SIP-03 SIP Center Data Sheet', 0, 5, '2025-05-08 08:52:16'),
(17, '1234-5678-A', 'QF-SIP-04 SIP Experience Record', 0, 5, '2025-05-25 07:22:08'),
(18, '1234-5678-A', 'QF-SIP-05 Student Internship Program Evaluation Report', 0, 5, '2025-05-25 07:22:09'),
(33, '1234-5678-A', 'QF-SIP-06 SIP Intern Clearance', 0, 5, '2025-05-25 07:22:10'),
(34, '1234-5678-A', 'Letter of Endorsement 2025', 0, 5, '2025-04-03 01:09:19'),
(35, '1234-5678-A', 'Letter of Intent with Draft MOA 2025', 0, 5, '2025-05-25 07:22:11'),
(36, '1234-5678-A', 'Letter of Intent with Existing MOA 2025', 0, 5, '2025-04-03 01:09:21'),
(37, '1234-5678-A', 'MOA', 0, 5, '2025-05-25 07:22:12'),
(38, '1234-5678-A', 'MOU', 0, 5, '2025-04-03 01:09:22'),
(58, '2021-7357-A', 'QF-SIP-01 Student Internship Program Registration Form', 1, 7, '2025-05-08 14:45:14'),
(59, '2021-7357-A', 'QF-SIP-02 Student Internship Program Agreement', 1, 7, '2025-05-08 14:45:15'),
(60, '2021-7357-A', 'QF-SIP-03 SIP Center Data Sheet', 1, 7, '2025-05-08 14:45:16'),
(61, '2021-7357-A', 'QF-SIP-04 SIP Experience Record', 1, 7, '2025-05-08 14:45:16'),
(62, '2021-6668-A', 'QF-SIP-01 Student Internship Program Registration Form', 1, 5, '2025-05-08 16:23:34'),
(63, '2021-6668-A', 'QF-SIP-02 Student Internship Program Agreement', 1, 5, '2025-05-08 16:23:34'),
(64, '2021-6668-A', 'QF-SIP-03 SIP Center Data Sheet', 1, 5, '2025-05-08 16:23:35'),
(65, '2021-6668-A', 'QF-SIP-04 SIP Experience Record', 0, 5, '2025-05-10 04:52:32'),
(66, '2021-6668-A', 'QF-SIP-05 Student Internship Program Evaluation Report', 0, 5, '2025-05-10 04:52:33'),
(72, '2021-6668-A', 'QF-SIP-06 SIP Intern Clearance', 0, 5, '2025-05-10 04:52:08'),
(74, '2021-1895-A', 'QF-SIP-01 Student Internship Program Registration Form', 0, 5, '2025-05-25 10:33:07'),
(75, '2021-1895-A', 'QF-SIP-02 Student Internship Program Agreement', 0, 5, '2025-05-25 10:33:09'),
(76, '2021-1895-A', 'QF-SIP-03 SIP Center Data Sheet', 0, 5, '2025-05-21 02:33:22'),
(77, '2021-1895-A', 'QF-SIP-04 SIP Experience Record', 0, 5, '2025-05-25 10:33:09'),
(78, '2021-1895-A', 'QF-SIP-05 Student Internship Program Evaluation Report', 0, 5, '2025-05-16 02:14:54');

-- --------------------------------------------------------

--
-- Table structure for table `sip_supervisors`
--

CREATE TABLE `sip_supervisors` (
  `id` int(11) NOT NULL,
  `fullname` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `sip_center` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sip_supervisors`
--

INSERT INTO `sip_supervisors` (`id`, `fullname`, `username`, `sip_center`, `password`, `created_at`) VALUES
(1, 'Anna', 'SIP1', 'ISAT U', '$2y$10$HzEZGBg4YgOyOhjwqprOvO3O6YWj3Pgo6gLSijGQshkSjPM9jJ9vG', '2025-03-30 01:06:14'),
(6, 'Benjamin', 'benjsSIP', 'KWADRA', '$2y$10$ukawVmZg6lPYiPt1N1O0M.48IRFDwkrs7XOu.v.yRCbvX4Y0naSc2', '2025-05-25 15:07:44');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `student_id` varchar(50) NOT NULL,
  `id` int(11) NOT NULL,
  `fullname` varchar(100) NOT NULL,
  `course` varchar(50) NOT NULL,
  `hours` int(11) DEFAULT 0,
  `password` varchar(255) NOT NULL,
  `sip_center` varchar(100) NOT NULL,
  `is_approved` tinyint(1) DEFAULT 0,
  `sip_supervisor_id` int(11) DEFAULT NULL,
  `school_year` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `supervisor` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`student_id`, `id`, `fullname`, `course`, `hours`, `password`, `sip_center`, `is_approved`, `sip_supervisor_id`, `school_year`, `created_at`, `supervisor`) VALUES
('1234-5678-A', 9, 'May Anne', 'BSCS 4-A', 900, '$2y$10$BuSyyp4iy1pcrWE7M.lJKOQjXa3453hp7koMzCuPKQagY3FC2w7G6', 'ISAT U', 1, NULL, '2024-2025', '2025-05-25 07:38:00', 2),
('2021-1895-A', 12, 'Khenee Matanguihan', 'BSCS 4-A', 900, '$2y$10$VjUlywuxDDkMJgTROi24ZuOSfbv8QT3YpEqtd60JptncVgPUpF01O', 'KWADRA', 1, 1, '2024-2025', '2025-05-25 10:32:06', 2);

-- --------------------------------------------------------

--
-- Table structure for table `supervisors`
--

CREATE TABLE `supervisors` (
  `id` int(11) NOT NULL,
  `fullname` varchar(100) NOT NULL,
  `course` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `required_hours` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `supervisors`
--

INSERT INTO `supervisors` (`id`, `fullname`, `course`, `username`, `password`, `required_hours`, `created_at`) VALUES
(2, 'Dan', 'BSCS 4-A', 'superrvisor1', '$2y$10$4AeZ.MLDdvqMKWDljVyaR.67l2htNDUIoVlzHnoSmVoCCNsIdZm/.', 500, '2025-03-30 06:12:14'),
(3, 'Jay', 'BSIS 4-A', 'jaysupervisor', '$2y$10$QPBEeVXhdpL16rjeDyeWZeLEYvgAYZw//sLxtkm4CR7tUQvJqEZxa', 500, '2025-05-08 14:26:07');

-- --------------------------------------------------------

--
-- Table structure for table `time_tracking`
--

CREATE TABLE `time_tracking` (
  `log_id` int(11) NOT NULL,
  `student_id` varchar(50) NOT NULL,
  `clock_in` datetime NOT NULL,
  `clock_out` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `confirmation_status` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `fullname` varchar(100) NOT NULL,
  `role` enum('admin','student','supervisor','sip_supervisor') NOT NULL,
  `identifier` varchar(50) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `fullname`, `role`, `identifier`, `password`, `created_at`) VALUES
(1, 'Administrator', 'admin', 'admin', '$2y$10$q0zN/TS7ec9UPD3pQG2TA./uPXGY4rT5whgZDo0LC6XQXlSaZarEW', '2025-03-29 13:22:38'),
(4, 'Anna', 'sip_supervisor', 'SIP1', '$2y$10$HzEZGBg4YgOyOhjwqprOvO3O6YWj3Pgo6gLSijGQshkSjPM9jJ9vG', '2025-03-30 05:48:29'),
(5, 'Dan', 'supervisor', 'superrvisor1', '$2y$10$4AeZ.MLDdvqMKWDljVyaR.67l2htNDUIoVlzHnoSmVoCCNsIdZm/.', '2025-03-30 06:12:14'),
(7, 'Jay', 'supervisor', 'jaysupervisor', '$2y$10$QPBEeVXhdpL16rjeDyeWZeLEYvgAYZw//sLxtkm4CR7tUQvJqEZxa', '2025-05-08 14:26:07'),
(9, 'John Coolyn Jabican', 'student', '2021-6668-A', '$2y$10$KhYhmCra4Yfp9sP3He3db.DL95U4bJcYOnn0aN.0d/cNBKH3TwYkm', '2025-05-08 15:53:36'),
(11, 'marie', 'supervisor', 'mariesupervisor', '$2y$10$rvWsbo/Vb/xWqEmUsxqTXuLI3PmUfzkgz0qQaHlm3ZTI2yI5aeg1a', '2025-05-08 23:56:46'),
(16, 'Stephen', 'supervisor', 'stephensupervisor', '$2y$10$Ufx.h0Y7dXkLoRukJMV42eZIt7TLI304HSG7M1867xQTtUs08qY/m', '2025-05-22 01:16:41'),
(17, 'Micah Prandas', 'supervisor', 'micahsupervisor', '$2y$10$Ow71pkYyLXLrlJVsz.EgXOmSPEQbnJU8FZu.vBXmiT5ed8y7OAosO', '2025-05-22 09:26:55'),
(19, 'weffr', 'sip_supervisor', 'trewq', '$2y$10$Vf7SLU9JZBDV0g3j8jycrehi0VYImH3sbchcQUzNow.ryce7HMhoW', '2025-05-24 12:02:30'),
(20, 'wf', 'sip_supervisor', 'wef', '$2y$10$VfnWqrrKnQh3NS8S7SZ.gulqamNCG00RQCRQzdpZbb/DUJNUuKzfG', '2025-05-24 12:28:27'),
(22, 'May Anne', 'student', '1234-5678-A', '$2y$10$BuSyyp4iy1pcrWE7M.lJKOQjXa3453hp7koMzCuPKQagY3FC2w7G6', '2025-05-25 07:38:00'),
(24, 'Khenee Matanguihan', 'student', '2021-1895-A', '$2y$10$VjUlywuxDDkMJgTROi24ZuOSfbv8QT3YpEqtd60JptncVgPUpF01O', '2025-05-25 10:32:06'),
(25, 'Benjamin', 'sip_supervisor', 'benjsSIP', '$2y$10$ukawVmZg6lPYiPt1N1O0M.48IRFDwkrs7XOu.v.yRCbvX4Y0naSc2', '2025-05-25 15:07:44');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `document_checklist`
--
ALTER TABLE `document_checklist`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_identifier` (`student_identifier`,`document_name`);

--
-- Indexes for table `sip_supervisors`
--
ALTER TABLE `sip_supervisors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`student_id`),
  ADD UNIQUE KEY `id` (`id`),
  ADD KEY `sip_supervisor_id` (`sip_supervisor_id`),
  ADD KEY `fk_supervisor` (`supervisor`);

--
-- Indexes for table `supervisors`
--
ALTER TABLE `supervisors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `time_tracking`
--
ALTER TABLE `time_tracking`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`identifier`),
  ADD UNIQUE KEY `identifier` (`identifier`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `document_checklist`
--
ALTER TABLE `document_checklist`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=99;

--
-- AUTO_INCREMENT for table `sip_supervisors`
--
ALTER TABLE `sip_supervisors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `supervisors`
--
ALTER TABLE `supervisors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `time_tracking`
--
ALTER TABLE `time_tracking`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `fk_sip_supervisor` FOREIGN KEY (`sip_supervisor_id`) REFERENCES `sip_supervisors` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_supervisor` FOREIGN KEY (`supervisor`) REFERENCES `supervisors` (`id`),
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`sip_supervisor_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `time_tracking`
--
ALTER TABLE `time_tracking`
  ADD CONSTRAINT `fk_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_time_tracking_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `time_tracking_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`);
--
-- Database: `test`
--
CREATE DATABASE IF NOT EXISTS `test` DEFAULT CHARACTER SET latin1 COLLATE latin1_swedish_ci;
USE `test`;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
