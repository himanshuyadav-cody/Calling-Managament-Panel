-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 11, 2025 at 09:19 AM
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
-- Database: `leads`
--

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `status` tinyint(1) DEFAULT 1 COMMENT '1=active, 0=inactive',
  `is_deleted` tinyint(1) DEFAULT 0 COMMENT '0=false, 1=true',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `status`, `is_deleted`, `created_at`, `updated_at`, `deleted_at`) VALUES
(5, 'IT', 1, 0, '2025-10-09 12:37:35', '2025-10-09 12:37:35', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `comments`
--

CREATE TABLE `comments` (
  `id` int(11) NOT NULL,
  `leadid` int(11) NOT NULL,
  `comments` text NOT NULL,
  `datetime` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('active','inactive') DEFAULT 'active',
  `is_deleted` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `comments`
--

INSERT INTO `comments` (`id`, `leadid`, `comments`, `datetime`, `status`, `is_deleted`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES
(1, 1, 'Hi i will call him tommorrow', '2025-10-09 08:34:00', 'active', 0, '2025-10-09 12:05:24', '2025-10-09 12:05:24', 451, NULL),
(2, 1, 'Hi i will connect', '2025-10-09 12:20:27', 'active', 0, '2025-10-09 12:20:27', '2025-10-09 12:20:27', 451, NULL),
(3, 2, 'i will call him', '2025-10-09 12:28:11', 'active', 0, '2025-10-09 12:28:11', '2025-10-09 12:28:11', 451, NULL),
(4, 1, 'Rejrcted', '2025-10-09 12:33:09', 'active', 0, '2025-10-09 12:33:09', '2025-10-09 12:33:09', 451, NULL),
(5, 2, 'I will call him', '2025-10-09 12:55:41', 'active', 0, '2025-10-09 12:55:41', '2025-10-09 12:55:41', 451, NULL),
(6, 2, 'I will call to', '2025-10-09 13:06:05', 'active', 0, '2025-10-09 13:06:05', '2025-10-09 13:06:05', 451, NULL),
(7, 2, 'Will cll you', '2025-10-09 13:08:28', 'active', 0, '2025-10-09 13:08:28', '2025-10-09 13:08:28', 452, NULL),
(8, 2, 'hm meri baat hogi h', '2025-10-09 14:58:52', 'active', 0, '2025-10-09 14:58:52', '2025-10-09 14:58:52', 452, NULL),
(9, 2, '10 oct ko aat karin h', '2025-10-09 15:00:00', 'active', 0, '2025-10-09 15:00:00', '2025-10-09 15:00:00', 452, NULL),
(10, 2, 'payment done', '2025-10-09 15:00:57', 'active', 0, '2025-10-09 15:00:57', '2025-10-09 15:00:57', 452, NULL),
(11, 4, 'fc', '2025-10-11 07:15:24', 'active', 0, '2025-10-11 07:15:24', '2025-10-11 07:15:24', 451, NULL),
(12, 4, 'uyt', '2025-10-11 07:16:22', 'active', 0, '2025-10-11 07:16:22', '2025-10-11 07:16:22', 451, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `leads`
--

CREATE TABLE `leads` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phoneno` varchar(20) DEFAULT NULL,
  `type` enum('hot','warm','cold','new') DEFAULT 'new',
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `category` varchar(255) NOT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `source` varchar(100) DEFAULT 'bulk_upload',
  `status` enum('new','contacted','follow_up','confirmed','converted','rejected') DEFAULT 'new',
  `followup_date` datetime DEFAULT NULL,
  `is_deleted` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leads`
--

INSERT INTO `leads` (`id`, `name`, `email`, `phoneno`, `type`, `address`, `city`, `state`, `category`, `assigned_to`, `source`, `status`, `followup_date`, `is_deleted`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES
(1, 'John Doe', 'john@example.com', '1234567890', 'new', '123 Main St', 'Mumbai', 'Maharashtra', '', 1, 'bulk_upload', 'new', NULL, 0, '2025-10-09 11:49:28', '2025-10-09 14:58:05', 1, 1),
(2, 'Jane Smith', 'jane@example.com', '987654321', 'new', '456 Park Ave', 'Delhi', 'Delhi', '', 452, 'bulk_upload', 'converted', '2025-10-10 20:29:00', 0, '2025-10-09 11:49:28', '2025-10-09 15:00:57', 1, 1),
(3, 'Raj Kumar', 'raj@example.com', '1122334455', 'new', '789 MG Road', 'Bangalore', 'Karnataka', '', 452, 'bulk_upload', 'new', NULL, 0, '2025-10-09 11:49:28', '2025-10-09 14:09:08', 1, 1),
(4, 'Rsnjit', 'ranjit\"fm.com', '45436363', 'new', 'adssdf sdf', 'sdfs', 'mumbai', 'Real estate', 452, 'bulk_upload', 'new', NULL, 0, '2025-10-11 07:10:12', '2025-10-11 07:16:43', 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `role` tinyint(2) NOT NULL COMMENT '	0 - Super Admin / 1 - Admin',
  `username` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(15) NOT NULL,
  `password` varchar(255) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `status` enum('1','2') DEFAULT '1',
  `is_deleted` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `role`, `username`, `name`, `email`, `phone`, `password`, `category`, `status`, `is_deleted`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES
(1, 1, '', 'Rahul', 'admin@admin.com', '8976452318', 'S1ts2ICdHkAn8jTG/DlLKQ==', '5', '1', 0, '2025-10-05 21:26:34', '2025-10-09 13:01:40', NULL, NULL),
(451, 0, 'superadmin007', '', '', '', 'S1ts2ICdHkAn8jTG/DlLKQ==', NULL, '', 0, '2025-08-30 01:31:01', '2025-08-30 01:42:24', NULL, NULL),
(452, 1, '', 'Piyush kumar', 'piyushseo893@gmail.com', '78564778765', 'eA+Bpipzu7CCnKQ/K5n54Q==', '5', '1', 0, '2025-10-09 12:43:17', '2025-10-09 13:01:35', NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `comments`
--
ALTER TABLE `comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `updated_by` (`updated_by`),
  ADD KEY `idx_comments_leadid` (`leadid`);

--
-- Indexes for table `leads`
--
ALTER TABLE `leads`
  ADD PRIMARY KEY (`id`),
  ADD KEY `updated_by` (`updated_by`),
  ADD KEY `idx_leads_status` (`status`),
  ADD KEY `idx_leads_type` (`type`),
  ADD KEY `idx_leads_created_by` (`created_by`),
  ADD KEY `assigned_to` (`assigned_to`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `updated_by` (`updated_by`),
  ADD KEY `idx_users_email` (`email`),
  ADD KEY `idx_users_status` (`status`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `comments`
--
ALTER TABLE `comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `leads`
--
ALTER TABLE `leads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=453;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `comments`
--
ALTER TABLE `comments`
  ADD CONSTRAINT `comments_ibfk_1` FOREIGN KEY (`leadid`) REFERENCES `leads` (`id`),
  ADD CONSTRAINT `comments_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `comments_ibfk_3` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `leads`
--
ALTER TABLE `leads`
  ADD CONSTRAINT `leads_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `leads_ibfk_2` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `leads_ibfk_3` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `users_ibfk_2` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
