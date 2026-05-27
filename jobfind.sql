-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: sql205.infinityfree.com
-- Generation Time: May 22, 2026 at 10:58 AM
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
-- Database: `if0_42031060_jobfind`
--

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `category_id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `icon` varchar(10) DEFAULT '?',
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`category_id`, `name`, `icon`, `description`, `created_at`) VALUES
(1, 'IT', '💻', 'งานเกี่ยวกับ Computer', '2026-02-25 12:41:43'),
(2, 'Design', '🎨', 'Graphic and UI jobs', '2026-02-25 12:42:16'),
(3, 'Marketing', '📢', 'Marketing jobs', '2026-02-25 12:42:46'),
(4, 'Accounting', '💰', 'Finance jobs', '2026-02-25 12:43:10');

-- --------------------------------------------------------

--
-- Table structure for table `chat_messages`
--

CREATE TABLE `chat_messages` (
  `message_id` int(11) NOT NULL,
  `sender_id` int(11) DEFAULT NULL,
  `receiver_id` int(11) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_read` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chat_messages`
--

INSERT INTO `chat_messages` (`message_id`, `sender_id`, `receiver_id`, `message`, `sent_at`, `is_read`) VALUES
(1, 1, 1, 'แอดดดดดดดด', '2026-02-25 14:13:46', 0),
(2, 3, 0, '', '2026-02-25 14:15:18', 0),
(3, 3, 0, '', '2026-02-25 14:15:18', 0),
(4, 1, 1, 'testtttt', '2026-02-25 14:17:53', 0),
(5, 3, 1, 'ad test', '2026-02-25 14:18:27', 0),
(6, 1, 1, 'ๅ/-ๅ/-ๅ', '2026-02-25 14:23:46', 0),
(7, 0, 3, '2342342', '2026-02-25 14:24:06', 0),
(8, 0, 3, '23423', '2026-02-25 14:24:09', 0),
(9, 2, 1, '123333', '2026-02-26 13:03:42', 0),
(10, 1, 1, 'test1', '2026-03-05 07:44:45', 0),
(11, 3, 1, 'test3', '2026-03-05 07:45:07', 0),
(12, 2, 1, '12313', '2026-03-10 04:04:23', 0),
(13, 3, 1, 'แอดพิมพ์', '2026-05-12 08:50:15', 0),
(14, 3, 1, 'แอดพิมพ์', '2026-05-13 07:33:43', 0),
(15, 2, 3, 'ะ', '2026-05-13 07:38:05', 1),
(16, 2, 3, 'test2พิมพ์', '2026-05-13 07:38:25', 1);

-- --------------------------------------------------------

--
-- Table structure for table `employer_profile`
--

CREATE TABLE `employer_profile` (
  `employer_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `employer_name` varchar(100) DEFAULT NULL,
  `employer_description` text DEFAULT NULL,
  `like_count` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employer_profile`
--

INSERT INTO `employer_profile` (`employer_id`, `user_id`, `employer_name`, `employer_description`, `like_count`, `created_at`) VALUES
(1, 2, 'Guntinan Company', 'บริษัทของกันต์', 0, '2026-05-16 13:39:30');

-- --------------------------------------------------------

--
-- Table structure for table `employer_rating`
--

CREATE TABLE `employer_rating` (
  `rating_id` int(11) NOT NULL,
  `employer_id` int(11) DEFAULT NULL,
  `freelancer_id` int(11) DEFAULT NULL,
  `score` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employer_review`
--

CREATE TABLE `employer_review` (
  `review_id` int(11) NOT NULL,
  `employer_id` int(11) DEFAULT NULL,
  `freelancer_id` int(11) DEFAULT NULL,
  `job_id` int(11) DEFAULT NULL,
  `rating` int(11) NOT NULL,
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employer_review`
--

INSERT INTO `employer_review` (`review_id`, `employer_id`, `freelancer_id`, `job_id`, `rating`, `comment`, `created_at`) VALUES
(2, 2, 1, NULL, 5, 'บริษัทดี', '2026-05-22 06:20:28'),
(3, 2, 1, 8, 5, 'งานดีๆ', '2026-05-22 07:29:45');

-- --------------------------------------------------------

--
-- Table structure for table `freelancer_profile`
--

CREATE TABLE `freelancer_profile` (
  `freelancer_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `skill` text DEFAULT NULL,
  `experience` text DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `rating` float DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `freelancer_profile`
--

INSERT INTO `freelancer_profile` (`freelancer_id`, `user_id`, `skill`, `experience`, `location`, `rating`, `created_at`) VALUES
(1, 3, 'PHP, Web Developmen', '2 Years', 'Banhkok', 0, '2026-02-25 13:25:20'),
(2, 1, 'ซ่อมท่อ, PHP, Java', '1 years', 'Bangkok', 0, '2026-02-25 13:41:09'),
(3, 4, 'ไม่มี', '-', 'Chiang Mai', 0, '2026-02-25 13:53:33'),
(4, 5, '', '', '', 0, '2026-02-26 12:51:18'),
(5, 6, '', '', '', 0, '2026-05-18 05:59:00');

-- --------------------------------------------------------

--
-- Table structure for table `freelancer_rating`
--

CREATE TABLE `freelancer_rating` (
  `rating_id` int(11) NOT NULL,
  `freelancer_id` int(11) DEFAULT NULL,
  `employer_id` int(11) DEFAULT NULL,
  `score` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `freelancer_rating`
--

INSERT INTO `freelancer_rating` (`rating_id`, `freelancer_id`, `employer_id`, `score`, `created_at`) VALUES
(1, 1, 2, 5, '2026-02-25 13:05:37');

-- --------------------------------------------------------

--
-- Table structure for table `freelancer_review`
--

CREATE TABLE `freelancer_review` (
  `review_id` int(11) NOT NULL,
  `freelancer_id` int(11) DEFAULT NULL,
  `job_id` int(11) NOT NULL,
  `employer_id` int(11) DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `rating` int(11) DEFAULT NULL,
  `review` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `freelancer_review`
--

INSERT INTO `freelancer_review` (`review_id`, `freelancer_id`, `job_id`, `employer_id`, `comment`, `created_at`, `rating`, `review`) VALUES
(2, 1, 0, 2, NULL, '2026-02-25 17:05:14', 5, 'dddddd'),
(3, 1, 6, 2, 'เก่ง', '2026-05-17 06:45:28', 5, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `job`
--

CREATE TABLE `job` (
  `job_id` int(11) NOT NULL,
  `employer_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `location` varchar(255) NOT NULL,
  `salary` decimal(10,2) DEFAULT 0.00,
  `latitude` double DEFAULT NULL,
  `longitude` double DEFAULT NULL,
  `deadline` datetime DEFAULT NULL,
  `status` enum('open','in_progress','completed','closed') DEFAULT 'open',
  `admin_status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `category` varchar(100) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `job`
--

INSERT INTO `job` (`job_id`, `employer_id`, `title`, `description`, `location`, `salary`, `latitude`, `longitude`, `deadline`, `status`, `admin_status`, `created_at`, `category`, `image_path`, `updated_at`) VALUES
(8, 2, 'แก้ไขข้อมูลการรับสมัครงาน', 'เพิ่มปุ่มแก้ไขที่ตัวงาน', '', 2000.00, NULL, NULL, '2026-05-26 00:00:00', 'closed', 'approved', '2026-05-21 05:41:22', 'IT', NULL, '2026-05-21 06:39:14'),
(9, 2, 'Graphic', 'กินเงินเดือน', '', 20000.00, NULL, NULL, '2026-05-25 00:00:00', 'closed', 'approved', '2026-05-22 05:41:27', 'Design', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `job_images`
--

CREATE TABLE `job_images` (
  `image_id` int(11) NOT NULL,
  `job_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_application`
--

CREATE TABLE `job_application` (
  `application_id` int(11) NOT NULL,
  `job_id` int(11) DEFAULT NULL,
  `freelancer_id` int(11) DEFAULT NULL,
  `apply_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` varchar(50) DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `job_application`
--

INSERT INTO `job_application` (`application_id`, `job_id`, `freelancer_id`, `apply_date`, `status`) VALUES
(1, 5, 1, '2026-05-17 05:28:17', 'accepted'),
(2, 5, 4, '2026-05-17 05:46:56', 'rejected'),
(3, 6, 1, '2026-05-17 06:43:42', 'accepted'),
(4, 6, 4, '2026-05-17 06:43:58', 'rejected'),
(5, 7, 1, '2026-05-19 03:53:27', 'pending'),
(6, 8, 1, '2026-05-21 06:55:45', 'accepted'),
(7, 9, 1, '2026-05-22 08:55:26', 'accepted');

-- --------------------------------------------------------

--
-- Table structure for table `like_employer`
--

CREATE TABLE `like_employer` (
  `like_id` int(11) NOT NULL,
  `freelancer_id` int(11) DEFAULT NULL,
  `employer_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `like_employer`
--

INSERT INTO `like_employer` (`like_id`, `freelancer_id`, `employer_id`, `created_at`) VALUES
(1, 1, 2, '2026-02-25 13:11:18');

-- --------------------------------------------------------

--
-- Table structure for table `resume`
--

CREATE TABLE `resume` (
  `resume_id` int(11) NOT NULL,
  `freelancer_id` int(11) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `upload_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `resume`
--

INSERT INTO `resume` (`resume_id`, `freelancer_id`, `file_name`, `upload_date`) VALUES
(1, 1, '1131w-xkDELtpQH94.webp', '2026-02-25 10:36:50'),
(3, 1, '1772095318_004 กันตินันท์.pdf', '2026-02-26 08:41:58');

-- --------------------------------------------------------

--
-- Table structure for table `saved_freelancers`
--

CREATE TABLE `saved_freelancers` (
  `id` int(11) NOT NULL,
  `employer_id` int(11) NOT NULL,
  `freelancer_id` int(11) NOT NULL,
  `saved_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `fullname` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` enum('admin','employer','freelancer') DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `latitude` double DEFAULT NULL,
  `longitude` double DEFAULT NULL,
  `company_details` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `email`, `password`, `fullname`, `phone`, `role`, `created_at`, `latitude`, `longitude`, `company_details`) VALUES
(1, 'NonFreelance', 'non@non.com', '1234', 'Non', '1234567890', 'freelancer', '2026-02-25 10:05:27', 13.7563, 100.5018, NULL),
(2, 'GuntinanCompany', 'gun@company.com', '1234', 'Guntinan Company', '1234567890', 'employer', '2026-02-25 10:08:15', NULL, NULL, NULL),
(3, 'Admin', 'admin@admin.com', '1234', 'tester3', '1234567890', 'admin', '2026-02-25 10:13:45', NULL, NULL, NULL),
(4, 'test4', 'test@test4.com', '1234', 'tester4', '1234567890', 'freelancer', '2026-02-25 13:53:32', NULL, NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`category_id`);

--
-- Indexes for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD PRIMARY KEY (`message_id`);

--
-- Indexes for table `employer_profile`
--
ALTER TABLE `employer_profile`
  ADD PRIMARY KEY (`employer_id`);

--
-- Indexes for table `employer_rating`
--
ALTER TABLE `employer_rating`
  ADD PRIMARY KEY (`rating_id`);

--
-- Indexes for table `employer_review`
--
ALTER TABLE `employer_review`
  ADD PRIMARY KEY (`review_id`),
  ADD KEY `job_id` (`job_id`);

--
-- Indexes for table `freelancer_profile`
--
ALTER TABLE `freelancer_profile`
  ADD PRIMARY KEY (`freelancer_id`);

--
-- Indexes for table `freelancer_rating`
--
ALTER TABLE `freelancer_rating`
  ADD PRIMARY KEY (`rating_id`);

--
-- Indexes for table `freelancer_review`
--
ALTER TABLE `freelancer_review`
  ADD PRIMARY KEY (`review_id`);

--
-- Indexes for table `job`
--
ALTER TABLE `job`
  ADD PRIMARY KEY (`job_id`),
  ADD KEY `employer_id` (`employer_id`);

--
-- Indexes for table `job_images`
--
ALTER TABLE `job_images`
  ADD PRIMARY KEY (`image_id`),
  ADD KEY `job_id` (`job_id`);

--
-- Indexes for table `job_application`
--
ALTER TABLE `job_application`
  ADD PRIMARY KEY (`application_id`);

--
-- Indexes for table `like_employer`
--
ALTER TABLE `like_employer`
  ADD PRIMARY KEY (`like_id`);

--
-- Indexes for table `resume`
--
ALTER TABLE `resume`
  ADD PRIMARY KEY (`resume_id`);

--
-- Indexes for table `saved_freelancers`
--
ALTER TABLE `saved_freelancers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_save` (`employer_id`,`freelancer_id`),
  ADD KEY `freelancer_id` (`freelancer_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `message_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `employer_profile`
--
ALTER TABLE `employer_profile`
  MODIFY `employer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `employer_rating`
--
ALTER TABLE `employer_rating`
  MODIFY `rating_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employer_review`
--
ALTER TABLE `employer_review`
  MODIFY `review_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `freelancer_profile`
--
ALTER TABLE `freelancer_profile`
  MODIFY `freelancer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `freelancer_rating`
--
ALTER TABLE `freelancer_rating`
  MODIFY `rating_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `freelancer_review`
--
ALTER TABLE `freelancer_review`
  MODIFY `review_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `job`
--
ALTER TABLE `job`
  MODIFY `job_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `job_images`
--
ALTER TABLE `job_images`
  MODIFY `image_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `job_application`
--
ALTER TABLE `job_application`
  MODIFY `application_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `like_employer`
--
ALTER TABLE `like_employer`
  MODIFY `like_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `resume`
--
ALTER TABLE `resume`
  MODIFY `resume_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `saved_freelancers`
--
ALTER TABLE `saved_freelancers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `employer_review`
--
ALTER TABLE `employer_review`
  ADD CONSTRAINT `employer_review_ibfk_1` FOREIGN KEY (`job_id`) REFERENCES `job` (`job_id`) ON DELETE CASCADE;

--
-- Constraints for table `job`
--
ALTER TABLE `job`
  ADD CONSTRAINT `job_ibfk_1` FOREIGN KEY (`employer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `job_images`
--
ALTER TABLE `job_images`
  ADD CONSTRAINT `job_images_ibfk_1` FOREIGN KEY (`job_id`) REFERENCES `job` (`job_id`) ON DELETE CASCADE;

--
-- Constraints for table `saved_freelancers`
--
ALTER TABLE `saved_freelancers`
  ADD CONSTRAINT `saved_freelancers_ibfk_1` FOREIGN KEY (`employer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `saved_freelancers_ibfk_2` FOREIGN KEY (`freelancer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
