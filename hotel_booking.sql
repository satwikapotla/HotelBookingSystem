-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 27, 2025 at 07:43 AM
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
-- Database: `hotel_booking`
--

-- --------------------------------------------------------

--
-- Table structure for table `amenities`
--

CREATE TABLE `amenities` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `icon` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `amenities`
--

INSERT INTO `amenities` (`id`, `name`, `icon`, `created_at`, `updated_at`) VALUES
(1, 'Free Wi-Fi', 'wifi', '2025-04-27 02:36:29', '2025-04-27 02:36:29'),
(2, 'Air Conditioning', 'snowflake', '2025-04-27 02:36:29', '2025-04-27 04:41:00'),
(3, 'Flat-screen TV', 'tv', '2025-04-27 02:36:29', '2025-04-27 02:36:29'),
(4, 'Mini Bar', 'wine-glass', '2025-04-27 02:36:29', '2025-04-27 02:36:29'),
(5, 'Coffee Maker', 'mug-hot', '2025-04-27 02:36:29', '2025-04-27 04:41:00'),
(6, 'Room Service', 'concierge-bell', '2025-04-27 02:36:29', '2025-04-27 04:41:00'),
(7, 'Swimming Pool', 'swimming-pool', '2025-04-27 02:36:29', '2025-04-27 04:41:00'),
(8, 'Fitness Center', 'dumbbell', '2025-04-27 02:36:29', '2025-04-27 04:41:00'),
(9, 'Spa', 'spa', '2025-04-27 02:36:29', '2025-04-27 02:36:29'),
(10, 'Restaurant', 'utensils', '2025-04-27 02:36:29', '2025-04-27 04:41:00');

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `booking_reference` varchar(20) NOT NULL,
  `user_id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `check_in_date` date NOT NULL,
  `check_out_date` date NOT NULL,
  `adults` int(11) NOT NULL DEFAULT 1,
  `children` int(11) NOT NULL DEFAULT 0,
  `total_price` decimal(10,2) NOT NULL,
  `status` enum('pending','confirmed','cancelled','completed') DEFAULT 'pending',
  `payment_status` enum('pending','paid','refunded') DEFAULT 'pending',
  `special_requests` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `booking_reference`, `user_id`, `room_id`, `check_in_date`, `check_out_date`, `adults`, `children`, `total_price`, `status`, `payment_status`, `special_requests`, `created_at`, `updated_at`) VALUES
(1, 'LUXEA14118C0', 2, 1, '2025-04-29', '2025-04-30', 1, 0, 99.99, 'confirmed', 'pending', NULL, '2025-04-27 05:10:59', '2025-04-27 05:10:59'),
(2, 'LUXE70A38E07', 2, 2, '2025-04-28', '2025-04-30', 1, 0, 199.98, 'confirmed', 'pending', NULL, '2025-04-27 05:39:26', '2025-04-27 05:39:26');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` enum('credit_card','debit_card','paypal','cash') NOT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `status` enum('pending','completed','failed','refunded') DEFAULT 'pending',
  `payment_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `rating` int(11) NOT NULL CHECK (`rating` between 1 and 5),
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rooms`
--

CREATE TABLE `rooms` (
  `id` int(11) NOT NULL,
  `room_number` varchar(20) NOT NULL,
  `room_type_id` int(11) NOT NULL,
  `status` enum('available','occupied','maintenance') DEFAULT 'available',
  `floor` int(11) NOT NULL,
  `max_occupancy` int(11) NOT NULL DEFAULT 2,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rooms`
--

INSERT INTO `rooms` (`id`, `room_number`, `room_type_id`, `status`, `floor`, `max_occupancy`, `created_at`, `updated_at`) VALUES
(1, '101', 1, 'available', 1, 2, '2025-04-27 02:36:29', '2025-04-27 02:36:29'),
(2, '102', 1, 'available', 1, 2, '2025-04-27 02:36:29', '2025-04-27 02:36:29'),
(3, '103', 1, 'available', 1, 2, '2025-04-27 02:36:29', '2025-04-27 02:36:29'),
(4, '104', 1, 'available', 1, 2, '2025-04-27 02:36:29', '2025-04-27 02:36:29'),
(5, '105', 1, 'available', 1, 2, '2025-04-27 02:36:29', '2025-04-27 02:36:29'),
(6, '201', 2, 'available', 2, 2, '2025-04-27 02:36:29', '2025-04-27 02:36:29'),
(7, '202', 2, 'available', 2, 2, '2025-04-27 02:36:29', '2025-04-27 02:36:29'),
(8, '203', 2, 'available', 2, 3, '2025-04-27 02:36:29', '2025-04-27 02:36:29'),
(9, '204', 2, 'available', 2, 3, '2025-04-27 02:36:29', '2025-04-27 02:36:29'),
(10, '205', 2, 'available', 2, 3, '2025-04-27 02:36:29', '2025-04-27 02:36:29'),
(11, '301', 3, 'available', 3, 3, '2025-04-27 02:36:29', '2025-04-27 02:36:29'),
(12, '302', 3, 'available', 3, 3, '2025-04-27 02:36:29', '2025-04-27 02:36:29'),
(13, '303', 3, 'available', 3, 4, '2025-04-27 02:36:29', '2025-04-27 02:36:29'),
(14, '401', 4, 'available', 4, 4, '2025-04-27 02:36:29', '2025-04-27 02:36:29'),
(15, '402', 4, 'available', 4, 6, '2025-04-27 02:36:29', '2025-04-27 02:36:29');

-- --------------------------------------------------------

--
-- Table structure for table `room_amenities`
--

CREATE TABLE `room_amenities` (
  `room_type_id` int(11) NOT NULL,
  `amenity_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `room_amenities`
--

INSERT INTO `room_amenities` (`room_type_id`, `amenity_id`) VALUES
(1, 1),
(1, 2),
(1, 3),
(2, 1),
(2, 2),
(2, 3),
(2, 4),
(2, 5),
(3, 1),
(3, 2),
(3, 3),
(3, 4),
(3, 5),
(3, 6),
(3, 7),
(3, 8),
(4, 1),
(4, 2),
(4, 3),
(4, 4),
(4, 5),
(4, 6),
(4, 7),
(4, 8),
(4, 9),
(4, 10);

-- --------------------------------------------------------

--
-- Table structure for table `room_images`
--

CREATE TABLE `room_images` (
  `id` int(11) NOT NULL,
  `room_type_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `room_images`
--

INSERT INTO `room_images` (`id`, `room_type_id`, `image_path`, `is_primary`, `created_at`) VALUES
(1, 1, 'https://images.unsplash.com/photo-1611892440504-42a792e24d32', 1, '2025-04-27 02:36:29'),
(2, 1, 'https://images.unsplash.com/photo-1591088398332-8a7791972843', 0, '2025-04-27 02:36:29'),
(3, 1, 'https://images.unsplash.com/photo-1590490360182-c33d57733427', 0, '2025-04-27 02:36:29'),
(4, 2, 'https://images.unsplash.com/photo-1591088398332-8a7791972843', 1, '2025-04-27 02:36:29'),
(5, 2, 'https://images.unsplash.com/photo-1618773928121-c32242e63f39', 0, '2025-04-27 02:36:29'),
(6, 2, 'https://images.unsplash.com/photo-1621293954908-907159247fc8', 0, '2025-04-27 02:36:29'),
(7, 3, 'https://images.unsplash.com/photo-1618773928121-c32242e63f39', 1, '2025-04-27 02:36:29'),
(8, 3, 'https://images.unsplash.com/photo-1598928636135-d146006ff4be', 0, '2025-04-27 02:36:29'),
(9, 3, 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c', 0, '2025-04-27 02:36:29'),
(10, 4, 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c', 1, '2025-04-27 02:36:29'),
(11, 4, 'https://images.unsplash.com/photo-1618220179428-22790b461013', 0, '2025-04-27 02:36:29'),
(12, 4, 'https://images.unsplash.com/photo-1608198396599-3118a6a6f842', 0, '2025-04-27 02:36:29');

-- --------------------------------------------------------

--
-- Table structure for table `room_types`
--

CREATE TABLE `room_types` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `base_price` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `room_types`
--

INSERT INTO `room_types` (`id`, `name`, `description`, `base_price`, `created_at`, `updated_at`) VALUES
(1, 'Standard Room', 'Comfortable room with all basic amenities', 99.99, '2025-04-27 02:36:29', '2025-04-27 02:36:29'),
(2, 'Deluxe Room', 'Spacious room with enhanced furnishings and amenities', 149.99, '2025-04-27 02:36:29', '2025-04-27 02:36:29'),
(3, 'Executive Suite', 'Luxurious suite with separate living area and premium amenities', 249.99, '2025-04-27 02:36:29', '2025-04-27 02:36:29'),
(4, 'Presidential Suite', 'Our most luxurious accommodation with multiple rooms and exclusive services', 499.99, '2025-04-27 02:36:29', '2025-04-27 02:36:29');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `role` enum('admin','user') DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `first_name`, `last_name`, `email`, `password`, `phone`, `address`, `city`, `country`, `role`, `created_at`, `updated_at`) VALUES
(1, 'Admin', 'User', 'satwikapotla@gmail.com', '$2y$10$3z7zQz8oZ6n7z3k9y5x3O.v3Qz9x8w7y2k9y5x3O.v3Qz9x8w7y2k', NULL, NULL, NULL, NULL, 'admin', '2025-04-27 02:36:29', '2025-04-27 04:20:28'),
(2, 'Akhila', 'Gandru', 'akhila@gmail.com', '$2y$10$8l.Z26K5YgWfwKxSBBgd8OO7p5jIpy0eQrWYNxcxoq/hVPf5CDOrq', '6304194629', NULL, NULL, NULL, 'user', '2025-04-27 04:29:55', '2025-04-27 04:29:55'),
(3, 'Satwika', 'Potla', 'avikigai2004@gmail.com', '$2y$10$IpgOg3IWNrg72ORe8uKvh.COHgQnVaUmpAGLXHBbzUZopMIUaSCMK', '+916304194629', NULL, NULL, NULL, 'user', '2025-04-27 04:36:11', '2025-04-27 04:36:11');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `amenities`
--
ALTER TABLE `amenities`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `booking_reference` (`booking_reference`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `room_id` (`room_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_id` (`booking_id`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_id` (`booking_id`);

--
-- Indexes for table `rooms`
--
ALTER TABLE `rooms`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `room_number` (`room_number`),
  ADD KEY `room_type_id` (`room_type_id`);

--
-- Indexes for table `room_amenities`
--
ALTER TABLE `room_amenities`
  ADD PRIMARY KEY (`room_type_id`,`amenity_id`),
  ADD KEY `amenity_id` (`amenity_id`);

--
-- Indexes for table `room_images`
--
ALTER TABLE `room_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `room_type_id` (`room_type_id`);

--
-- Indexes for table `room_types`
--
ALTER TABLE `room_types`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `amenities`
--
ALTER TABLE `amenities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rooms`
--
ALTER TABLE `rooms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `room_images`
--
ALTER TABLE `room_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `room_types`
--
ALTER TABLE `room_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `rooms`
--
ALTER TABLE `rooms`
  ADD CONSTRAINT `rooms_ibfk_1` FOREIGN KEY (`room_type_id`) REFERENCES `room_types` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `room_amenities`
--
ALTER TABLE `room_amenities`
  ADD CONSTRAINT `room_amenities_ibfk_1` FOREIGN KEY (`room_type_id`) REFERENCES `room_types` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `room_amenities_ibfk_2` FOREIGN KEY (`amenity_id`) REFERENCES `amenities` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `room_images`
--
ALTER TABLE `room_images`
  ADD CONSTRAINT `room_images_ibfk_1` FOREIGN KEY (`room_type_id`) REFERENCES `room_types` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
