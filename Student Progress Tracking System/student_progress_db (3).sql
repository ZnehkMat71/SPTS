-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 03, 2025 at 05:52 PM
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
-- Database: `student_progress_db`
--

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
(129, '2021-1895-A', 'QF-SIP-01 Student Internship Program Registration Form', 1, 43, '2025-05-30 02:30:04'),
(130, '2021-1895-A', 'QF-SIP-02 Student Internship Program Agreement', 1, 43, '2025-05-30 02:30:05');

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
(13, 'John', 'johnSIP', 'ISAT U', '$2y$10$89YjPT8i8zjPZSYHnnYjO.jMYkTzJpEhSNngYt5auH7Vx0rwgFhbG', '2025-05-30 02:14:55'),
(14, 'Dan', 'dansip1', 'KWADRA', '$2y$10$POLNaLZ7/cXANR1L30ZiFO.DWXirav0FevtVuhoMYMLRc8xi3UkDC', '2025-06-01 12:46:50');

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
('1234-5778-A', 37, 'Carlo', 'BSCS 4-B', 700, '$2y$10$oy1AIlK2HgWXCqcXf1I7zuE5BaIyutxwZP2uaGmb9pVdIGT97ckYW', 'ISAT U', 0, NULL, '2024-2025', '2025-06-03 15:47:48', 20),
('2021-1895-A', 29, 'Khenee Matanguihan', 'BSCS 4-A', 0, '$2y$10$HEljAwXWOGARNBVQVARgWO43ta4OPyUkONL7GEfTWBGiHD4DH9VXy', 'KWADRA', 1, 13, '2024-2025', '2025-05-30 02:24:09', 19),
('2021-3401-A', 34, 'Benjamin', 'BSCS 4-B', 0, '$2y$10$sCdPFrRPxIwpUMVcyet6DO/clX7jp/heb8HCVpP8q2Z/j54L/lbuu', 'ISAT U', 0, 14, '2024-2025', '2025-06-02 14:19:26', 20),
('9876-4321-A', 36, 'Allison', 'BSCS 4-A', 900, '$2y$10$xxsN0QzPZW7VBo42cQFIXer/1nr3rWdW7gxyc6qtED4zCoSQnmWh.', 'KWADRA', 0, 13, '2024-2025', '2025-06-03 06:32:54', 19);

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
(19, 'Junnie', 'BSCS 4-A', 'junniesupervisor', '$2y$10$U85ESC/5UKfy7G/Iv.x1ye5AWXlSo6AuF1cP96DabfJhmVk4.1J6W', 900, '2025-05-30 13:39:24'),
(20, 'Kath', 'BSCS 4-B', 'kathbscs4', '$2y$10$XVRy554OrG5eJSLi76ZhlubGnM9rXyULtFYR32XN/z5R7TcFKohEC', 700, '2025-05-31 13:52:29');

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

--
-- Dumping data for table `time_tracking`
--

INSERT INTO `time_tracking` (`log_id`, `student_id`, `clock_in`, `clock_out`, `created_at`, `confirmation_status`) VALUES
(26, '2021-1895-A', '2025-05-30 10:30:30', NULL, '2025-05-30 02:30:30', 0),
(27, '2021-1895-A', '2025-06-01 14:10:31', NULL, '2025-06-01 06:10:31', 0),
(28, '2021-1895-A', '2025-06-02 21:58:07', NULL, '2025-06-02 13:58:07', 0);

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
(1, 'Administrator', 'admin', 'admin', '$2y$10$q0zN/TS7ec9UPD3pQG2TA./uPXGY4rT5whgZDo0LC6XQXlSaZarEW', '2025-03-29 05:22:38'),
(44, 'John', 'sip_supervisor', 'johnSIP', '$2y$10$89YjPT8i8zjPZSYHnnYjO.jMYkTzJpEhSNngYt5auH7Vx0rwgFhbG', '2025-05-30 02:14:55'),
(45, 'Khenee Matanguihan', 'student', '2021-1895-A', '$2y$10$HEljAwXWOGARNBVQVARgWO43ta4OPyUkONL7GEfTWBGiHD4DH9VXy', '2025-05-30 02:24:09'),
(47, 'Junnie', 'supervisor', 'junniesupervisor', '$2y$10$U85ESC/5UKfy7G/Iv.x1ye5AWXlSo6AuF1cP96DabfJhmVk4.1J6W', '2025-05-30 13:39:24'),
(50, 'Kath', 'supervisor', 'kathbscs4', '$2y$10$XVRy554OrG5eJSLi76ZhlubGnM9rXyULtFYR32XN/z5R7TcFKohEC', '2025-05-31 13:52:29'),
(51, 'Dan', 'sip_supervisor', 'dansip1', '$2y$10$POLNaLZ7/cXANR1L30ZiFO.DWXirav0FevtVuhoMYMLRc8xi3UkDC', '2025-06-01 12:46:50'),
(53, 'Benjamin', 'student', '2021-3401-A', '$2y$10$sCdPFrRPxIwpUMVcyet6DO/clX7jp/heb8HCVpP8q2Z/j54L/lbuu', '2025-06-02 14:19:26'),
(55, 'Allison', 'student', '9876-4321-A', '$2y$10$xxsN0QzPZW7VBo42cQFIXer/1nr3rWdW7gxyc6qtED4zCoSQnmWh.', '2025-06-03 06:32:54'),
(56, 'Carlo', 'student', '1234-5778-A', '$2y$10$oy1AIlK2HgWXCqcXf1I7zuE5BaIyutxwZP2uaGmb9pVdIGT97ckYW', '2025-06-03 15:47:48');

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=131;

--
-- AUTO_INCREMENT for table `sip_supervisors`
--
ALTER TABLE `sip_supervisors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `supervisors`
--
ALTER TABLE `supervisors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `time_tracking`
--
ALTER TABLE `time_tracking`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `fk_sip_supervisor` FOREIGN KEY (`sip_supervisor_id`) REFERENCES `sip_supervisors` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_supervisor` FOREIGN KEY (`supervisor`) REFERENCES `supervisors` (`id`),
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`sip_supervisor_id`) REFERENCES `sip_supervisors` (`id`);

--
-- Constraints for table `time_tracking`
--
ALTER TABLE `time_tracking`
  ADD CONSTRAINT `fk_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_time_tracking_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `time_tracking_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
