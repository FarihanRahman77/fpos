-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 15, 2026 at 09:45 PM
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
-- Database: `fpos`
--

-- --------------------------------------------------------

--
-- Table structure for table `attributes`
--

CREATE TABLE `attributes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `deleted` enum('Yes','No') NOT NULL DEFAULT 'No',
  `deleted_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_date` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_date` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `deleted_date` date DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `attributes`
--

INSERT INTO `attributes` (`id`, `name`, `slug`, `deleted`, `deleted_by`, `created_date`, `created_by`, `status`, `updated_by`, `updated_date`, `deleted_date`, `updated_at`, `created_at`) VALUES
(1, 'White', 'white', 'Yes', NULL, '2026-09-16 00:12:09', 1, 'Active', 1, '2026-09-16 00:14:04', NULL, '2026-09-15 12:14:04', '2026-09-15 12:12:09');

-- --------------------------------------------------------

--
-- Table structure for table `attribute_types`
--

CREATE TABLE `attribute_types` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `slug` varchar(180) NOT NULL,
  `deleted` enum('Yes','No') NOT NULL DEFAULT 'No',
  `deleted_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_date` datetime DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_date` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `brands`
--

CREATE TABLE `brands` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `deleted` enum('Yes','No') NOT NULL DEFAULT 'No',
  `deleted_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_date` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_date` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `deleted_date` date DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `brands`
--

INSERT INTO `brands` (`id`, `name`, `slug`, `deleted`, `deleted_by`, `created_date`, `created_by`, `status`, `updated_by`, `updated_date`, `deleted_date`, `updated_at`, `created_at`) VALUES
(1, 'Samsung1', 'samsung1', 'Yes', NULL, '2026-09-15 23:32:41', 1, 'Active', 1, '2026-09-15 23:48:11', NULL, '2026-09-15 11:48:11', '2026-09-15 11:32:41'),
(2, 'Samsung', 'samsung', 'No', NULL, '2026-09-16 01:14:21', 1, 'Active', NULL, NULL, NULL, '2026-09-15 13:14:21', '2026-09-15 13:14:21');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `deleted` enum('Yes','No') NOT NULL DEFAULT 'No',
  `deleted_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_date` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_date` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `deleted_date` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `slug`, `deleted`, `deleted_by`, `created_date`, `created_by`, `status`, `updated_by`, `updated_date`, `deleted_date`, `created_at`, `updated_at`) VALUES
(1, 'Electronic Item', 'electronic-item', 'Yes', NULL, '2026-09-15 22:46:04', 1, 'Active', 1, '2026-09-15 22:46:25', NULL, '2026-09-15 10:46:04', '2026-09-15 10:46:25'),
(2, 'Power Tools', 'power-tools', 'Yes', 1, '2026-09-15 22:48:58', 1, 'Inactive', 1, '2026-09-15 22:50:44', '2026-09-15 16:50:44', '2026-09-15 10:48:58', '2026-09-15 10:50:44'),
(3, 'Electronics', 'electronics', 'No', NULL, '2026-09-16 01:13:59', 1, 'Active', NULL, NULL, NULL, '2026-09-15 13:13:59', '2026-09-15 13:13:59');

-- --------------------------------------------------------

--
-- Table structure for table `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '2014_10_12_000000_create_users_table', 1),
(2, '2014_10_12_100000_create_password_reset_tokens_table', 1),
(3, '2019_08_19_000000_create_failed_jobs_table', 1),
(4, '2019_12_14_000001_create_personal_access_tokens_table', 1),
(5, '2026_09_14_170116_create_permissions_table', 2),
(6, '2026_09_14_170333_create_role_permissions_table', 2);

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `slug` varchar(200) NOT NULL,
  `module` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `deleted` enum('Yes','No') NOT NULL DEFAULT 'No',
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`id`, `name`, `slug`, `module`, `description`, `status`, `deleted`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES
(1, 'Dashboard', 'dashboard', 'Dashboard', NULL, 'Active', 'Yes', 1, 1, '2026-09-14 11:59:16', '2026-09-14 12:01:26'),
(2, 'user.index', 'userindex', 'User', NULL, 'Active', 'No', 1, 1, '2026-09-14 11:59:38', '2026-09-14 12:00:52'),
(3, 'user.store', 'userstore', 'User', NULL, 'Active', 'No', 1, 1, '2026-09-14 11:59:51', '2026-09-14 11:59:51'),
(4, 'user.update', 'userupdate', 'User', NULL, 'Active', 'No', 1, 1, '2026-09-14 12:00:05', '2026-09-14 12:00:05'),
(5, 'user.delete', 'userdelete', 'User', NULL, 'Active', 'No', 1, 1, '2026-09-14 12:00:16', '2026-09-14 12:00:16');

-- --------------------------------------------------------

--
-- Table structure for table `personal_access_tokens`
--

CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(280) NOT NULL,
  `brand_id` bigint(20) UNSIGNED DEFAULT NULL,
  `category_id` bigint(20) UNSIGNED DEFAULT NULL,
  `unit_id` bigint(20) UNSIGNED DEFAULT NULL,
  `sku` varchar(150) DEFAULT NULL,
  `barcode` varchar(150) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `stock_alert` decimal(15,3) NOT NULL DEFAULT 0.000,
  `purchase_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `sale_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `old_sale_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `description` text DEFAULT NULL,
  `deleted` enum('Yes','No') NOT NULL DEFAULT 'No',
  `deleted_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_date` datetime DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_date` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_attributes`
--

CREATE TABLE `product_attributes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `attribute_type_id` bigint(20) UNSIGNED NOT NULL,
  `attribute_id` bigint(20) UNSIGNED NOT NULL,
  `deleted` enum('Yes','No') NOT NULL DEFAULT 'No',
  `deleted_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_date` datetime DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_date` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_specs`
--

CREATE TABLE `product_specs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `spec_name` varchar(150) NOT NULL,
  `spec_value` text DEFAULT NULL,
  `deleted` enum('Yes','No') NOT NULL DEFAULT 'No',
  `deleted_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_date` datetime DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_date` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `deleted` enum('Yes','No') NOT NULL DEFAULT 'No',
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `deleted_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_date` datetime DEFAULT NULL,
  `updated_date` datetime DEFAULT NULL,
  `deleted_date` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`, `slug`, `description`, `status`, `deleted`, `created_by`, `updated_by`, `deleted_by`, `created_date`, `updated_date`, `deleted_date`, `created_at`, `updated_at`) VALUES
(1, 'Superadmin', 'superadmin', NULL, 'Active', 'No', NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-13 18:36:45', '2026-09-13 18:36:45'),
(2, 'Dashboard', 'dashboard', NULL, 'Active', 'Yes', 1, 1, NULL, NULL, NULL, NULL, '2026-09-14 11:35:09', '2026-09-14 12:01:33'),
(3, 'Admin', 'admin', NULL, 'Active', 'No', 1, 1, NULL, NULL, NULL, NULL, '2026-09-14 11:47:33', '2026-09-14 11:47:33'),
(4, 'Executive', 'executive', NULL, 'Inactive', 'No', 1, 1, NULL, NULL, NULL, NULL, '2026-09-14 11:50:20', '2026-09-14 11:52:02');

-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `role_id` bigint(20) UNSIGNED NOT NULL,
  `permission_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `role_permissions`
--

INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`, `updated_at`) VALUES
(1, 1, 1, '2026-09-14 12:01:02', '2026-09-14 12:01:02'),
(2, 1, 2, '2026-09-14 12:01:02', '2026-09-14 12:01:02'),
(3, 1, 3, '2026-09-14 12:01:02', '2026-09-14 12:01:02'),
(4, 1, 4, '2026-09-14 12:01:02', '2026-09-14 12:01:02'),
(5, 1, 5, '2026-09-14 12:01:02', '2026-09-14 12:01:02'),
(8, 3, 3, '2026-09-14 12:03:48', '2026-09-14 12:03:48');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `company_name` varchar(255) DEFAULT NULL,
  `company_short_name` varchar(255) DEFAULT NULL,
  `company_tagline` varchar(255) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `logo_dark` varchar(255) DEFAULT NULL,
  `favicon` varchar(255) DEFAULT NULL,
  `signature` varchar(255) DEFAULT NULL,
  `watermark` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(255) DEFAULT NULL,
  `state` varchar(255) DEFAULT NULL,
  `country` varchar(255) DEFAULT NULL,
  `postal_code` varchar(50) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `phone_two` varchar(50) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `email_two` varchar(255) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `trade_license` varchar(255) DEFAULT NULL,
  `tin` varchar(255) DEFAULT NULL,
  `bin` varchar(255) DEFAULT NULL,
  `gst` varchar(255) DEFAULT NULL,
  `tax_rate` decimal(10,2) NOT NULL DEFAULT 0.00,
  `currency` varchar(20) NOT NULL DEFAULT 'BDT',
  `currency_symbol` varchar(20) NOT NULL DEFAULT '৳',
  `currency_position` enum('before','after') NOT NULL DEFAULT 'before',
  `header_text` text DEFAULT NULL,
  `footer_text` text DEFAULT NULL,
  `invoice_note` text DEFAULT NULL,
  `terms_conditions` text DEFAULT NULL,
  `facebook` varchar(255) DEFAULT NULL,
  `instagram` varchar(255) DEFAULT NULL,
  `youtube` varchar(255) DEFAULT NULL,
  `linkedin` varchar(255) DEFAULT NULL,
  `timezone` varchar(100) NOT NULL DEFAULT 'Asia/Dhaka',
  `date_format` varchar(50) NOT NULL DEFAULT 'd-m-Y',
  `time_format` varchar(50) NOT NULL DEFAULT 'h:i A',
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `deleted` enum('Yes','No') NOT NULL DEFAULT 'No',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `company_name`, `company_short_name`, `company_tagline`, `logo`, `logo_dark`, `favicon`, `signature`, `watermark`, `address`, `city`, `state`, `country`, `postal_code`, `phone`, `phone_two`, `email`, `email_two`, `website`, `trade_license`, `tin`, `bin`, `gst`, `tax_rate`, `currency`, `currency_symbol`, `currency_position`, `header_text`, `footer_text`, `invoice_note`, `terms_conditions`, `facebook`, `instagram`, `youtube`, `linkedin`, `timezone`, `date_format`, `time_format`, `status`, `deleted`, `created_at`, `updated_at`) VALUES
(1, 'FPOS', 'fpos', 'FPOS', 'uploads/settings/logo_1789322015.png', 'uploads/settings/logo_dark_1789322015.png', 'uploads/settings/favicon_1789322015.png', NULL, NULL, '176,M-block,Bishawcolony,chittagong', 'Chittagong', 'Chittagong Town', 'Bangladesh', '5545', '01887922063', '01887922064', 'admin@gmail.com', 'admin2@gmail.com', 'http://localhost:8000/', '54353453453', '5435345345', '43543534534', '543534534534534', 5.00, 'BDT', '৳', 'before', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Asia/Dhaka', 'd-m-Y', 'h:i A', 'Active', 'No', '2026-09-13 11:53:35', '2026-09-13 11:53:35');

-- --------------------------------------------------------

--
-- Table structure for table `stocks`
--

CREATE TABLE `stocks` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `in_qty` decimal(15,3) NOT NULL DEFAULT 0.000,
  `out_qty` decimal(15,3) NOT NULL DEFAULT 0.000,
  `purchase_id` bigint(20) UNSIGNED DEFAULT NULL,
  `sale_id` bigint(20) UNSIGNED DEFAULT NULL,
  `damage_id` bigint(20) UNSIGNED DEFAULT NULL,
  `purchase_return_id` bigint(20) UNSIGNED DEFAULT NULL,
  `sale_return_id` bigint(20) UNSIGNED DEFAULT NULL,
  `adjustment_id` bigint(20) UNSIGNED DEFAULT NULL,
  `reference_type` varchar(100) DEFAULT NULL,
  `reference_id` bigint(20) UNSIGNED DEFAULT NULL,
  `date` datetime NOT NULL,
  `remarks` varchar(500) DEFAULT NULL,
  `deleted` enum('Yes','No') NOT NULL DEFAULT 'No',
  `deleted_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_date` datetime DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_date` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `units`
--

CREATE TABLE `units` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `deleted` enum('Yes','No') NOT NULL DEFAULT 'No',
  `deleted_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_date` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_date` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `deleted_date` date DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `units`
--

INSERT INTO `units` (`id`, `name`, `slug`, `deleted`, `deleted_by`, `created_date`, `created_by`, `status`, `updated_by`, `updated_date`, `deleted_date`, `updated_at`, `created_at`) VALUES
(1, 'Kilogram', 'kilogram', 'Yes', NULL, '2026-09-16 00:08:25', 1, 'Inactive', 1, '2026-09-16 00:09:59', NULL, '2026-09-15 12:09:59', '2026-09-15 12:08:25'),
(2, 'Kg', 'kg', 'No', NULL, '2026-09-16 00:10:07', 1, 'Active', 1, '2026-09-16 00:10:20', NULL, '2026-09-15 12:10:20', '2026-09-15 12:10:07');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `designation` varchar(255) DEFAULT NULL,
  `role_id` bigint(20) UNSIGNED DEFAULT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `deleted` enum('Yes','No') NOT NULL DEFAULT 'No',
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `deleted_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_date` datetime DEFAULT NULL,
  `updated_date` datetime DEFAULT NULL,
  `deleted_date` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `image`, `name`, `designation`, `role_id`, `status`, `deleted`, `email`, `email_verified_at`, `password`, `remember_token`, `created_at`, `updated_at`, `created_by`, `updated_by`, `deleted_by`, `created_date`, `updated_date`, `deleted_date`) VALUES
(1, 'uploads/users/1789401688_6aa81a589ad00.jpg', 'Farhan', 'Superadmin', 1, 'Active', 'No', 'admin@gmail.com', NULL, '$2y$12$mcZoP9WCe7p2YBzmE901MOg.ysj87VTyLwxCXbq7zWQfotNEm405m', '0nhMzPaljaLlM9uoRKeNNskHlHHq6T2bSdLxelYYK9srKENjdZsCY5eo6PiS', '2026-09-13 10:48:50', '2026-09-14 10:01:28', NULL, 1, NULL, NULL, '2026-09-14 16:01:28', NULL),
(2, 'uploads/users/1789324656_6aa6ed7084d5f.jpg', 'Mahrab', NULL, 1, 'Active', 'Yes', 'mahrab@gmail.com', NULL, '$2y$12$02r2YpEUOncSyMn.3aWKW.xmpYE8f/8YLsV4NddrlKtwpA3Ne6NSm', NULL, '2026-09-13 12:37:36', '2026-09-14 09:55:30', 1, NULL, 1, '2026-09-13 18:37:36', NULL, '2026-09-14 15:55:30');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attributes`
--
ALTER TABLE `attributes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `attribute_types`
--
ALTER TABLE `attribute_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `brands`
--
ALTER TABLE `brands`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`email`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `permissions_slug_unique` (`slug`);

--
-- Indexes for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  ADD KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD UNIQUE KEY `sku` (`sku`),
  ADD UNIQUE KEY `barcode` (`barcode`),
  ADD KEY `idx_product_brand` (`brand_id`),
  ADD KEY `idx_product_category` (`category_id`),
  ADD KEY `idx_product_unit` (`unit_id`),
  ADD KEY `idx_product_status` (`status`),
  ADD KEY `idx_product_deleted` (`deleted`);

--
-- Indexes for table `product_attributes`
--
ALTER TABLE `product_attributes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pa_product` (`product_id`),
  ADD KEY `idx_pa_type` (`attribute_type_id`),
  ADD KEY `idx_pa_attribute` (`attribute_id`);

--
-- Indexes for table `product_specs`
--
ALTER TABLE `product_specs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_product_specs_product` (`product_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `stocks`
--
ALTER TABLE `stocks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_stock_product` (`product_id`),
  ADD KEY `idx_stock_date` (`date`),
  ADD KEY `idx_stock_purchase` (`purchase_id`),
  ADD KEY `idx_stock_sale` (`sale_id`),
  ADD KEY `idx_stock_damage` (`damage_id`),
  ADD KEY `idx_stock_purchase_return` (`purchase_return_id`),
  ADD KEY `idx_stock_sale_return` (`sale_return_id`);

--
-- Indexes for table `units`
--
ALTER TABLE `units`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_email_unique` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attributes`
--
ALTER TABLE `attributes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `attribute_types`
--
ALTER TABLE `attribute_types`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `brands`
--
ALTER TABLE `brands`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_attributes`
--
ALTER TABLE `product_attributes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_specs`
--
ALTER TABLE `product_specs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `role_permissions`
--
ALTER TABLE `role_permissions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `stocks`
--
ALTER TABLE `stocks`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `units`
--
ALTER TABLE `units`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
