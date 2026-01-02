-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 30, 2025 at 12:47 PM
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
-- Database: `chairman_pos`
--

-- --------------------------------------------------------

--
-- Table structure for table `companies`
--

CREATE TABLE `companies` (
  `id` int(11) UNSIGNED NOT NULL,
  `company_code` varchar(20) NOT NULL,
  `company_name` varchar(200) NOT NULL,
  `address` text DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `receipt_header` text DEFAULT NULL,
  `receipt_footer` text DEFAULT NULL,
  `mpesa_shortcode` varchar(20) DEFAULT NULL,
  `mpesa_passkey` varchar(255) DEFAULT NULL,
  `mpesa_consumer_key` varchar(255) DEFAULT NULL,
  `mpesa_consumer_secret` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `companies`
--

INSERT INTO `companies` (`id`, `company_code`, `company_name`, `address`, `phone`, `email`, `logo`, `receipt_header`, `receipt_footer`, `mpesa_shortcode`, `mpesa_passkey`, `mpesa_consumer_key`, `mpesa_consumer_secret`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'BARAKA', 'Baraka Tele', 'Nairobi, Kenya', '+254700000000', 'info@barakatele.com', NULL, 'BARAKA TELE\nYour Trusted Partner\nTel: +254700000000', 'Thank you for shopping with us!\nPowered by Chairman POS', NULL, NULL, NULL, NULL, 1, '2025-12-30 08:39:03', '2025-12-30 08:39:03');

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) UNSIGNED NOT NULL,
  `company_id` int(11) UNSIGNED NOT NULL,
  `customer_name` varchar(200) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `balance` decimal(15,2) DEFAULT 0.00,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `company_id`, `customer_name`, `phone`, `email`, `address`, `balance`, `is_active`, `created_at`) VALUES
(1, 1, 'Walk-in Customer', '0000000000', NULL, NULL, 0.00, 1, '2025-12-30 08:39:03');

-- --------------------------------------------------------

--
-- Table structure for table `mpesa_transactions`
--

CREATE TABLE `mpesa_transactions` (
  `id` int(11) UNSIGNED NOT NULL,
  `company_id` int(11) UNSIGNED NOT NULL,
  `sale_id` int(11) UNSIGNED DEFAULT NULL,
  `checkout_request_id` varchar(100) DEFAULT NULL,
  `merchant_request_id` varchar(100) DEFAULT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `amount` decimal(15,2) DEFAULT NULL,
  `mpesa_receipt` varchar(50) DEFAULT NULL,
  `transaction_date` datetime DEFAULT NULL,
  `status` enum('pending','completed','failed','cancelled') DEFAULT 'pending',
  `result_code` varchar(10) DEFAULT NULL,
  `result_desc` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `mpesa_transactions`
--

INSERT INTO `mpesa_transactions` (`id`, `company_id`, `sale_id`, `checkout_request_id`, `merchant_request_id`, `phone_number`, `amount`, `mpesa_receipt`, `transaction_date`, `status`, `result_code`, `result_desc`, `created_at`) VALUES
(1, 1, NULL, 'ws_CO_30122025133924858729385004', NULL, '254729385004', 600.00, NULL, NULL, 'pending', NULL, NULL, '2025-12-30 10:39:25'),
(2, 1, NULL, 'ws_CO_30122025135857250713502332', NULL, '0713502332', 1.00, NULL, NULL, 'pending', NULL, NULL, '2025-12-30 10:58:57'),
(3, 1, NULL, 'ws_CO_30122025135930285729385004', NULL, '254729385004', 1.00, NULL, NULL, 'pending', NULL, NULL, '2025-12-30 10:59:30');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) UNSIGNED NOT NULL,
  `company_id` int(11) UNSIGNED NOT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `product_name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `unit` varchar(50) DEFAULT 'pc',
  `buying_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `selling_price` decimal(15,2) DEFAULT 0.00,
  `stock_quantity` decimal(15,2) DEFAULT 0.00,
  `reorder_level` decimal(15,2) DEFAULT 5.00,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `company_id`, `barcode`, `product_name`, `description`, `unit`, `buying_price`, `selling_price`, `stock_quantity`, `reorder_level`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, '5901234123457', 'Safaricom Airtime 100', NULL, 'pc', 97.00, 100.00, 99.00, 5.00, 1, '2025-12-30 08:39:03', '2025-12-30 09:58:47'),
(2, 1, '5901234123458', 'Safaricom Airtime 50', NULL, 'pc', 48.50, 50.00, 99.00, 5.00, 1, '2025-12-30 08:39:03', '2025-12-30 09:49:37'),
(3, 1, '5901234123459', 'Safaricom Airtime 20', NULL, 'pc', 19.40, 20.00, 100.00, 5.00, 1, '2025-12-30 08:39:03', '2025-12-30 08:39:03'),
(4, 1, '5901234123460', 'Airtel Airtime 100', NULL, 'pc', 96.00, 100.00, 38.00, 5.00, 1, '2025-12-30 08:39:03', '2025-12-30 10:26:44'),
(5, 1, '5901234123461', 'Airtel Airtime 50', NULL, 'pc', 48.00, 50.00, 49.00, 5.00, 1, '2025-12-30 08:39:03', '2025-12-30 09:48:40'),
(6, 1, '5901234123462', 'Telkom Airtime 100', NULL, 'pc', 95.00, 100.00, 30.00, 5.00, 1, '2025-12-30 08:39:03', '2025-12-30 08:39:03'),
(7, 1, '5901234123463', 'USB Cable Type-C', NULL, 'pc', 150.00, 250.00, 19.00, 5.00, 1, '2025-12-30 08:39:03', '2025-12-30 10:26:44'),
(8, 1, '5901234123464', 'Phone Charger Fast', NULL, 'pc', 300.00, 500.00, 15.00, 5.00, 1, '2025-12-30 08:39:03', '2025-12-30 08:39:03'),
(9, 1, '5901234123465', 'Earphones Wired', NULL, 'pc', 80.00, 150.00, 40.00, 5.00, 1, '2025-12-30 08:39:03', '2025-12-30 08:39:03'),
(10, 1, '5901234123466', 'Screen Protector', NULL, 'pc', 50.00, 100.00, 60.00, 5.00, 1, '2025-12-30 08:39:03', '2025-12-30 08:39:03'),
(11, 1, '5901234123467', 'Phone Case Universal', NULL, 'pc', 100.00, 200.00, 35.00, 5.00, 1, '2025-12-30 08:39:03', '2025-12-30 08:39:03'),
(12, 1, '5901234123468', 'Memory Card 32GB', NULL, 'pc', 400.00, 600.00, 9.00, 5.00, 1, '2025-12-30 08:39:03', '2025-12-30 09:49:37'),
(13, 1, '5901234123469', 'Power Bank 10000mAh', NULL, 'pc', 800.00, 1200.00, 8.00, 5.00, 1, '2025-12-30 08:39:03', '2025-12-30 08:39:03'),
(14, 1, '5901234123470', 'Bluetooth Speaker Mini', NULL, 'pc', 500.00, 800.00, 12.00, 5.00, 1, '2025-12-30 08:39:03', '2025-12-30 08:39:03'),
(15, 1, '6001234567890', 'milk', NULL, 'pc', 1.00, 1.00, 10.00, 5.00, 1, '2025-12-30 10:43:45', '2025-12-30 10:43:45');

-- --------------------------------------------------------

--
-- Table structure for table `receipts`
--

CREATE TABLE `receipts` (
  `id` int(11) UNSIGNED NOT NULL,
  `sale_id` int(11) UNSIGNED NOT NULL,
  `receipt_text` longtext DEFAULT NULL,
  `printed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `id` int(11) UNSIGNED NOT NULL,
  `company_id` int(11) UNSIGNED NOT NULL,
  `sale_number` varchar(50) NOT NULL,
  `user_id` int(11) UNSIGNED NOT NULL,
  `customer_id` int(11) UNSIGNED DEFAULT NULL,
  `subtotal` decimal(15,2) DEFAULT 0.00,
  `discount` decimal(15,2) DEFAULT 0.00,
  `tax` decimal(15,2) DEFAULT 0.00,
  `total` decimal(15,2) DEFAULT 0.00,
  `amount_paid` decimal(15,2) DEFAULT 0.00,
  `change_amount` decimal(15,2) DEFAULT 0.00,
  `payment_method` enum('cash','mpesa','card','credit') DEFAULT 'cash',
  `mpesa_phone` varchar(20) DEFAULT NULL,
  `mpesa_receipt` varchar(50) DEFAULT NULL,
  `status` enum('completed','pending','cancelled') DEFAULT 'completed',
  `sale_date` date NOT NULL,
  `sale_time` time NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sales`
--

INSERT INTO `sales` (`id`, `company_id`, `sale_number`, `user_id`, `customer_id`, `subtotal`, `discount`, `tax`, `total`, `amount_paid`, `change_amount`, `payment_method`, `mpesa_phone`, `mpesa_receipt`, `status`, `sale_date`, `sale_time`, `created_at`) VALUES
(4, 1, '202512300001', 1, NULL, 100.00, 0.00, 0.00, 100.00, 200.00, 100.00, 'cash', NULL, NULL, 'completed', '2025-12-30', '12:35:33', '2025-12-30 09:35:33'),
(5, 1, '202512300002', 1, NULL, 100.00, 0.00, 0.00, 100.00, 1000.00, 900.00, 'cash', NULL, NULL, 'completed', '2025-12-30', '12:46:10', '2025-12-30 09:46:10'),
(6, 1, '202512300003', 1, NULL, 100.00, 0.00, 0.00, 100.00, 1000.00, 900.00, 'cash', NULL, NULL, 'completed', '2025-12-30', '12:46:16', '2025-12-30 09:46:16'),
(7, 1, '202512300004', 1, NULL, 100.00, 0.00, 0.00, 100.00, 1000.00, 900.00, 'cash', NULL, NULL, 'completed', '2025-12-30', '12:46:18', '2025-12-30 09:46:18'),
(8, 1, '202512300005', 1, NULL, 100.00, 0.00, 0.00, 100.00, 1000.00, 900.00, 'cash', NULL, NULL, 'completed', '2025-12-30', '12:46:18', '2025-12-30 09:46:18'),
(9, 1, '202512300006', 1, NULL, 100.00, 0.00, 0.00, 100.00, 1000.00, 900.00, 'cash', NULL, NULL, 'completed', '2025-12-30', '12:46:18', '2025-12-30 09:46:18'),
(10, 1, '202512300007', 1, NULL, 100.00, 0.00, 0.00, 100.00, 1000.00, 900.00, 'cash', NULL, NULL, 'completed', '2025-12-30', '12:46:19', '2025-12-30 09:46:19'),
(11, 1, '202512300008', 1, NULL, 100.00, 0.00, 0.00, 100.00, 1000.00, 900.00, 'cash', NULL, NULL, 'completed', '2025-12-30', '12:46:20', '2025-12-30 09:46:20'),
(12, 1, '202512300009', 1, NULL, 100.00, 0.00, 0.00, 100.00, 1000.00, 900.00, 'cash', NULL, NULL, 'completed', '2025-12-30', '12:46:21', '2025-12-30 09:46:21'),
(13, 1, '202512300010', 1, NULL, 50.00, 0.00, 0.00, 50.00, 500.00, 450.00, 'cash', NULL, NULL, 'completed', '2025-12-30', '12:48:40', '2025-12-30 09:48:40'),
(14, 1, '202512300011', 1, NULL, 650.00, 0.00, 0.00, 650.00, 1000.00, 350.00, 'cash', NULL, NULL, 'completed', '2025-12-30', '12:49:37', '2025-12-30 09:49:37'),
(15, 1, '202512300012', 1, NULL, 100.00, 0.00, 0.00, 100.00, 1000.00, 900.00, 'cash', NULL, NULL, 'completed', '2025-12-30', '12:51:12', '2025-12-30 09:51:12'),
(16, 1, '202512300013', 1, NULL, 200.00, 0.00, 0.00, 200.00, 600.00, 400.00, 'cash', NULL, NULL, 'completed', '2025-12-30', '12:58:47', '2025-12-30 09:58:47'),
(18, 1, '202512300014', 1, NULL, 350.00, 0.00, 0.00, 350.00, 560.00, 210.00, 'cash', NULL, NULL, 'completed', '2025-12-30', '13:26:44', '2025-12-30 10:26:44');

-- --------------------------------------------------------

--
-- Table structure for table `sale_items`
--

CREATE TABLE `sale_items` (
  `id` int(11) UNSIGNED NOT NULL,
  `sale_id` int(11) UNSIGNED NOT NULL,
  `product_id` int(11) UNSIGNED NOT NULL,
  `quantity` decimal(15,2) NOT NULL,
  `unit_price` decimal(15,2) NOT NULL,
  `discount` decimal(15,2) DEFAULT 0.00,
  `subtotal` decimal(15,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sale_items`
--

INSERT INTO `sale_items` (`id`, `sale_id`, `product_id`, `quantity`, `unit_price`, `discount`, `subtotal`) VALUES
(1, 4, 4, 1.00, 100.00, 0.00, 100.00),
(2, 5, 4, 1.00, 100.00, 0.00, 100.00),
(3, 6, 4, 1.00, 100.00, 0.00, 100.00),
(4, 7, 4, 1.00, 100.00, 0.00, 100.00),
(5, 8, 4, 1.00, 100.00, 0.00, 100.00),
(6, 9, 4, 1.00, 100.00, 0.00, 100.00),
(7, 10, 4, 1.00, 100.00, 0.00, 100.00),
(8, 11, 4, 1.00, 100.00, 0.00, 100.00),
(9, 12, 4, 1.00, 100.00, 0.00, 100.00),
(10, 13, 5, 1.00, 50.00, 0.00, 50.00),
(11, 14, 12, 1.00, 600.00, 0.00, 600.00),
(12, 14, 2, 1.00, 50.00, 0.00, 50.00),
(13, 15, 4, 1.00, 100.00, 0.00, 100.00),
(14, 16, 4, 1.00, 100.00, 0.00, 100.00),
(15, 16, 1, 1.00, 100.00, 0.00, 100.00),
(17, 18, 4, 1.00, 100.00, 0.00, 100.00),
(18, 18, 7, 1.00, 250.00, 0.00, 250.00);

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) UNSIGNED NOT NULL,
  `company_id` int(11) UNSIGNED NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) UNSIGNED NOT NULL,
  `company_id` int(11) UNSIGNED NOT NULL,
  `email` varchar(100) NOT NULL,
  `pin` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `role` enum('superadmin','admin','manager','cashier') DEFAULT 'cashier',
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `company_id`, `email`, `pin`, `full_name`, `role`, `is_active`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 1, 'barakatele@gmail.com', 'Admin2025', 'Admin User', 'admin', 1, '2025-12-30 03:45:30', '2025-12-30 08:39:03', '2025-12-30 11:45:30'),
(2, 1, 'barakatele@gmail.com', 'cashier2025', 'Cashier User', 'cashier', 1, '2025-12-30 03:46:17', '2025-12-30 08:39:03', '2025-12-30 11:46:17'),
(3, 1, 'manager@barakatele.com', 'Manager2025', 'Manager User', 'manager', 0, NULL, '2025-12-30 08:39:03', '2025-12-30 09:53:53');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `companies`
--
ALTER TABLE `companies`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `company_code` (`company_code`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `company_id` (`company_id`);

--
-- Indexes for table `mpesa_transactions`
--
ALTER TABLE `mpesa_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `company_id` (`company_id`),
  ADD KEY `sale_id` (`sale_id`),
  ADD KEY `checkout_request_id` (`checkout_request_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `company_id` (`company_id`),
  ADD KEY `barcode` (`barcode`),
  ADD KEY `product_name` (`product_name`);

--
-- Indexes for table `receipts`
--
ALTER TABLE `receipts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sale_id` (`sale_id`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sale_number` (`company_id`,`sale_number`),
  ADD KEY `company_id` (`company_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `sale_date` (`sale_date`);

--
-- Indexes for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sale_id` (`sale_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `company_setting` (`company_id`,`setting_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email_pin` (`email`,`pin`),
  ADD KEY `company_id` (`company_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `companies`
--
ALTER TABLE `companies`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `mpesa_transactions`
--
ALTER TABLE `mpesa_transactions`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `receipts`
--
ALTER TABLE `receipts`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `sale_items`
--
ALTER TABLE `sale_items`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `customers`
--
ALTER TABLE `customers`
  ADD CONSTRAINT `customers_company_fk` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `mpesa_transactions`
--
ALTER TABLE `mpesa_transactions`
  ADD CONSTRAINT `mpesa_company_fk` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `mpesa_sale_fk` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_company_fk` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `receipts`
--
ALTER TABLE `receipts`
  ADD CONSTRAINT `receipts_sale_fk` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sales`
--
ALTER TABLE `sales`
  ADD CONSTRAINT `sales_company_fk` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sales_customer_fk` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  ADD CONSTRAINT `sales_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD CONSTRAINT `sale_items_product_fk` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `sale_items_sale_fk` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `settings`
--
ALTER TABLE `settings`
  ADD CONSTRAINT `settings_company_fk` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_company_fk` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
