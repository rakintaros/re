-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 07, 2025 at 03:43 PM
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
-- Database: `equipment_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `name`, `description`, `created_at`, `updated_at`) VALUES
(1, 'ฝ่ายบริหาร', 'ฝ่ายบริหารและจัดการ', '2025-06-06 01:22:42', '2025-06-06 01:22:42'),
(2, 'ฝ่ายไอที', 'ฝ่ายเทคโนโลยีสารสนเทศ', '2025-06-06 01:22:42', '2025-06-06 01:22:42'),
(3, 'ฝ่ายบัญชี', 'ฝ่ายบัญชีและการเงิน', '2025-06-06 01:22:42', '2025-06-06 01:22:42');

-- --------------------------------------------------------

--
-- Table structure for table `equipment`
--

CREATE TABLE `equipment` (
  `id` int(11) NOT NULL,
  `code` varchar(100) NOT NULL,
  `name` varchar(255) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `brand` varchar(255) DEFAULT NULL,
  `model` varchar(255) DEFAULT NULL,
  `serial_number` varchar(255) DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `warranty_expire` date DEFAULT NULL,
  `status` enum('normal','repairing','damaged','retired') DEFAULT 'normal',
  `location` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `equipment`
--

INSERT INTO `equipment` (`id`, `code`, `name`, `category_id`, `department_id`, `brand`, `model`, `serial_number`, `purchase_date`, `warranty_expire`, `status`, `location`, `description`, `created_at`, `updated_at`) VALUES
(1, 'EQ2025060001', 'Hiview 360', 2, 2, 'Hiview 360', 'Hiview 360', '150112', '2025-06-06', '2025-06-30', 'repairing', 'ห้องประชุม', 'ทดสอบ Hiview 360', '2025-06-06 04:29:57', '2025-06-06 06:05:31'),
(2, 'EQ2025060002', 'COM-01', 1, 2, 'Dell', 'Lititude', '25498', '2025-06-06', '2025-07-06', 'normal', 'ห้องไอที', 'รายละเอียด COM-01', '2025-06-06 04:31:14', '2025-06-06 06:49:19'),
(3, 'EQ2025060003', 'LQ310+', 4, 3, 'EPSON', 'LQ310+', '', '2025-06-06', '2025-08-10', 'repairing', 'บัญชี', '', '2025-06-06 05:51:16', '2025-06-06 08:56:23'),
(4, 'EQ2025060004', 'Notebook', 1, 2, 'Dell', '3560', '2658', '2025-06-06', '2025-09-28', 'normal', 'ไอที', 'ทดสอบ', '2025-06-06 07:10:39', '2025-06-06 08:58:49'),
(5, 'EQ2025060005', 'PC', 1, 2, 'Lenovo', 'M45', '574TT', '2025-06-06', '2025-06-28', 'normal', 'IT ROOM', 'Test', '2025-06-06 08:54:11', '2025-06-06 08:54:11');

-- --------------------------------------------------------

--
-- Table structure for table `equipment_categories`
--

CREATE TABLE `equipment_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `equipment_categories`
--

INSERT INTO `equipment_categories` (`id`, `name`, `description`, `created_at`, `updated_at`) VALUES
(1, 'คอมพิวเตอร์', 'อุปกรณ์คอมพิวเตอร์และอุปกรณ์ต่อพ่วง', '2025-06-06 01:22:42', '2025-06-06 01:22:42'),
(2, 'กล้องวงจรปิด', 'กล้องวงจรปิดและอุปกรณ์บันทึก', '2025-06-06 01:22:42', '2025-06-06 01:22:42'),
(3, 'เครือข่าย', 'อุปกรณ์เครือข่ายและการสื่อสาร', '2025-06-06 01:22:42', '2025-06-06 01:22:42'),
(4, 'เครื่องพิมพ์', 'เครื่องพิมพ์และสแกนเนอร์', '2025-06-06 01:22:42', '2025-06-06 01:22:42');

-- --------------------------------------------------------

--
-- Table structure for table `maintenance_teams`
--

CREATE TABLE `maintenance_teams` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `maintenance_teams`
--

INSERT INTO `maintenance_teams` (`id`, `name`, `description`, `created_at`, `updated_at`) VALUES
(1, 'ทีมซ่อมคอมพิวเตอร์', 'ดูแลและซ่อมบำรุงอุปกรณ์คอมพิวเตอร์', '2025-06-06 01:22:42', '2025-06-06 01:22:42'),
(2, 'ทีมซ่อมวงจรปิด', 'ดูแลและซ่อมบำรุงระบบกล้องวงจรปิด', '2025-06-06 01:22:42', '2025-06-06 01:22:42'),
(3, 'ทีมซ่อมเครือข่าย', 'ดูแลและซ่อมบำรุงระบบเครือข่าย', '2025-06-06 01:22:42', '2025-06-06 01:22:42');

-- --------------------------------------------------------

--
-- Table structure for table `repair_history`
--

CREATE TABLE `repair_history` (
  `id` int(11) NOT NULL,
  `repair_request_id` int(11) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `repair_history`
--

INSERT INTO `repair_history` (`id`, `repair_request_id`, `action`, `description`, `created_by`, `created_at`) VALUES
(1, 1, 'สร้างใบแจ้งซ่อม', 'แจ้งซ่อม COM-01', 4, '2025-06-06 06:05:11'),
(2, 2, 'สร้างใบแจ้งซ่อม', 'แจ้งซ่อม Hiview 360', 4, '2025-06-06 06:05:31'),
(3, 1, 'รับงานซ่อม', 'ช่าง BUGpairoj รับงานซ่อม', 3, '2025-06-06 06:07:10'),
(4, 1, 'เริ่มซ่อม', 'เริ่มซ่อม โดย BUGpairoj', 3, '2025-06-06 06:07:19'),
(5, 1, 'ซ่อมเสร็จสิ้น', 'ซ่อมเสร็จสิ้น โดย BUGpairoj', 3, '2025-06-06 06:49:19'),
(6, 3, 'สร้างใบแจ้งซ่อม', 'แจ้งซ่อม ์Notebote', 4, '2025-06-06 07:12:42'),
(7, 3, 'รับงานซ่อม', 'ช่าง ช่าง 01 รับงานซ่อม', 5, '2025-06-06 07:13:33'),
(8, 3, 'เริ่มซ่อม', 'เริ่มซ่อม โดย ช่าง 01', 5, '2025-06-06 07:15:27'),
(9, 3, 'ซ่อมเสร็จสิ้น', 'ซ่อมเสร็จสิ้น โดย ช่าง 01', 5, '2025-06-06 07:16:34'),
(10, 4, 'สร้างใบแจ้งซ่อม', 'แจ้งซ่อม Notebook', 4, '2025-06-06 07:37:22'),
(11, 5, 'สร้างใบแจ้งซ่อม', 'แจ้งซ่อม LQ310+', 7, '2025-06-06 08:56:23'),
(12, 4, 'รับงานซ่อม', 'ช่าง ช่าง 01 รับงานซ่อม', 5, '2025-06-06 08:57:28'),
(13, 4, 'เริ่มซ่อม', 'เริ่มซ่อม โดย ช่าง 01', 5, '2025-06-06 08:58:03'),
(14, 4, 'ซ่อมเสร็จสิ้น', 'ซ่อมเสร็จสิ้น โดย ช่าง 01', 5, '2025-06-06 08:58:49');

-- --------------------------------------------------------

--
-- Table structure for table `repair_requests`
--

CREATE TABLE `repair_requests` (
  `id` int(11) NOT NULL,
  `request_code` varchar(50) NOT NULL,
  `equipment_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `maintenance_team_id` int(11) DEFAULT NULL,
  `technician_id` int(11) DEFAULT NULL,
  `problem_description` text NOT NULL,
  `urgency` enum('low','medium','high','urgent') DEFAULT 'medium',
  `status` enum('pending','assigned','in_progress','completed','cancelled') DEFAULT 'pending',
  `repair_start_date` datetime DEFAULT NULL,
  `repair_end_date` datetime DEFAULT NULL,
  `solution` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `repair_requests`
--

INSERT INTO `repair_requests` (`id`, `request_code`, `equipment_id`, `user_id`, `maintenance_team_id`, `technician_id`, `problem_description`, `urgency`, `status`, `repair_start_date`, `repair_end_date`, `solution`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'REQ2025060001', 2, 4, 1, 3, 'เปิดไม่ขึ้น', 'high', 'completed', '2025-06-06 13:07:10', '2025-06-06 13:49:19', 'เรียบร้อยแล้ว', 'เรียบร้อยแล้ว', '2025-06-06 06:05:11', '2025-06-06 06:49:19'),
(2, 'REQ2025060002', 1, 4, 2, NULL, 'กล้องดับ', 'urgent', 'pending', NULL, NULL, NULL, NULL, '2025-06-06 06:05:31', '2025-06-06 06:05:31'),
(3, 'REQ2025060003', 4, 4, 1, 5, 'ปิดไม่ติด', 'high', 'completed', '2025-06-06 14:13:33', '2025-06-06 14:16:34', 'ลืมเสียบปลั๊ก', 'ลืมเสียบปลั๊ก', '2025-06-06 07:12:42', '2025-06-06 07:16:34'),
(4, 'REQ2025060004', 4, 4, 1, 5, 'ปิดไม่ติด', 'urgent', 'completed', '2025-06-06 15:57:28', '2025-06-06 15:58:49', 'ลืมต่อสายแพร', 'ลืมต่อสายแพร', '2025-06-06 07:37:22', '2025-06-06 08:58:49'),
(5, 'REQ2025060005', 3, 7, 1, NULL, 'พิมพ์ไม่ออก', 'high', 'pending', NULL, NULL, NULL, NULL, '2025-06-06 08:56:23', '2025-06-06 08:56:23');

-- --------------------------------------------------------

--
-- Table structure for table `telegram_settings`
--

CREATE TABLE `telegram_settings` (
  `id` int(11) NOT NULL,
  `bot_token` varchar(255) DEFAULT NULL,
  `chat_id` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `telegram_settings`
--

INSERT INTO `telegram_settings` (`id`, `bot_token`, `chat_id`, `is_active`, `updated_at`) VALUES
(1, '8132428207:AAFIjWGfac84Vw-------ใส่ค่าตรงนี้------', '622261xx-------ใส่ค่าตรงนี้------', 1, '2025-06-07 13:39:00');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `fullname` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` enum('admin','user','technician') NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `maintenance_team_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `fullname`, `email`, `phone`, `role`, `department_id`, `maintenance_team_id`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'admin', '$2y$10$Euw/td4FaKSBOooaFrW82.wT4g9vxdYPqTDcF5gnJLQz635y8nlGC', 'ผู้ดูแลระบบ', 'admin@example.com', '0844562954', 'admin', 1, NULL, 1, '2025-06-06 01:22:42', '2025-06-06 07:24:04'),
(2, 'pairoj', '$2y$10$5VXCfOfqnHwRSn5.X0R9ge47.3lewH9FJhVNZCsJq.c4KnGYXSClq', 'Pairoj Chanda', 'pairoj@gmail.com', '0844562954', 'technician', 2, 1, 1, '2025-06-06 04:32:13', '2025-06-06 05:31:43'),
(3, 'bugpairoj', '$2y$10$n3oU.209O6THb0U1wBcaX.hmYeTMosx1vBpbEg7VZBtYfbZywD4n6', 'BUGpairoj', 'bugpairoj@gmail.com', '0844562954', 'technician', 2, 2, 1, '2025-06-06 04:33:08', '2025-06-06 07:24:25'),
(4, 'user01', '$2y$10$D6EFVyW0vsFLoV8vWRxhaupaKkrHfA68bySofIMo9iYVmOtwZH6Ky', 'user01', 'user01@gmail.com', '', 'user', 2, NULL, 1, '2025-06-06 06:04:03', '2025-06-06 06:04:03'),
(5, 'man01', '$2y$10$YJ5LAPBv5pBr9MiAlBK8ReLexfDGGZqo/Rd7hTlEqvFUmv7NqSVGG', 'ช่าง 01', 'man01@gmail.com', '', 'technician', 2, 1, 1, '2025-06-06 07:09:34', '2025-06-06 07:09:34'),
(6, 'man02', '$2y$10$ELVc9RTYv6QynkCm7IhpNey3S7EVGW1clqB3Hm48qb8xTU2AxIlPy', 'ช่าง man 02', 'man@gmail.com', '0844562954', 'technician', 2, 3, 1, '2025-06-06 07:35:37', '2025-06-06 07:35:37'),
(7, 'user02', '$2y$10$9A8R0ErBcbZbHn70unYa.eOpC5KR/nsBq/XZ1TSj99qqij3wKu83u', 'User แจ้งซ่อม 02', 'user02@gmial.com', '0844562954', 'user', 3, NULL, 1, '2025-06-06 08:55:24', '2025-06-06 08:55:24');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `equipment`
--
ALTER TABLE `equipment`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `department_id` (`department_id`);

--
-- Indexes for table `equipment_categories`
--
ALTER TABLE `equipment_categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `maintenance_teams`
--
ALTER TABLE `maintenance_teams`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `repair_history`
--
ALTER TABLE `repair_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `repair_request_id` (`repair_request_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `repair_requests`
--
ALTER TABLE `repair_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `request_code` (`request_code`),
  ADD KEY `equipment_id` (`equipment_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `maintenance_team_id` (`maintenance_team_id`),
  ADD KEY `technician_id` (`technician_id`);

--
-- Indexes for table `telegram_settings`
--
ALTER TABLE `telegram_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `department_id` (`department_id`),
  ADD KEY `maintenance_team_id` (`maintenance_team_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `equipment`
--
ALTER TABLE `equipment`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `equipment_categories`
--
ALTER TABLE `equipment_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `maintenance_teams`
--
ALTER TABLE `maintenance_teams`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `repair_history`
--
ALTER TABLE `repair_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `repair_requests`
--
ALTER TABLE `repair_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `telegram_settings`
--
ALTER TABLE `telegram_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `equipment`
--
ALTER TABLE `equipment`
  ADD CONSTRAINT `equipment_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `equipment_categories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `equipment_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `repair_history`
--
ALTER TABLE `repair_history`
  ADD CONSTRAINT `repair_history_ibfk_1` FOREIGN KEY (`repair_request_id`) REFERENCES `repair_requests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `repair_history_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `repair_requests`
--
ALTER TABLE `repair_requests`
  ADD CONSTRAINT `repair_requests_ibfk_1` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `repair_requests_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `repair_requests_ibfk_3` FOREIGN KEY (`maintenance_team_id`) REFERENCES `maintenance_teams` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `repair_requests_ibfk_4` FOREIGN KEY (`technician_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `users_ibfk_2` FOREIGN KEY (`maintenance_team_id`) REFERENCES `maintenance_teams` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
