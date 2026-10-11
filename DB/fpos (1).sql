-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 11, 2026 at 05:58 PM
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
  `attribute_type_id` int(11) DEFAULT NULL,
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

INSERT INTO `attributes` (`id`, `name`, `slug`, `attribute_type_id`, `deleted`, `deleted_by`, `created_date`, `created_by`, `status`, `updated_by`, `updated_date`, `deleted_date`, `updated_at`, `created_at`) VALUES
(1, 'White', 'white', 1, 'Yes', NULL, '2026-09-16 00:12:09', 1, 'Active', 1, '2026-09-19 01:33:53', NULL, '2026-09-15 12:14:04', '2026-09-15 12:12:09'),
(2, '128', '128', 3, 'Yes', NULL, '2026-09-19 01:33:35', 1, 'Active', 1, '2026-09-19 01:59:59', NULL, '2026-09-18 13:59:59', '2026-09-18 13:33:35'),
(3, 'Black', 'black', 1, 'No', NULL, '2026-09-19 01:59:53', 1, 'Active', NULL, NULL, NULL, '2026-09-18 13:59:53', '2026-09-18 13:59:53'),
(4, 'Purple', 'purple', 1, 'No', NULL, '2026-09-19 02:00:22', 1, 'Active', NULL, NULL, NULL, '2026-09-18 14:00:22', '2026-09-18 14:00:22'),
(5, 'White', 'white', 1, 'No', NULL, '2026-09-19 02:00:30', 1, 'Active', NULL, NULL, NULL, '2026-09-18 14:00:30', '2026-09-18 14:00:30');

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

--
-- Dumping data for table `attribute_types`
--

INSERT INTO `attribute_types` (`id`, `name`, `slug`, `deleted`, `deleted_by`, `created_date`, `created_by`, `status`, `updated_by`, `updated_date`, `created_at`, `updated_at`) VALUES
(1, 'Color', 'color', 'No', NULL, '2026-09-18 19:02:08', NULL, 'Active', NULL, NULL, '2026-09-18 13:02:08', '2026-09-18 13:02:08'),
(2, 'Sizes', 'sizes', 'Yes', NULL, '2026-09-18 19:11:18', NULL, 'Active', NULL, '2026-09-18 19:15:28', '2026-09-18 13:11:18', '2026-09-18 13:15:28'),
(3, 'Size', 'size', 'No', NULL, '2026-09-18 19:15:47', NULL, 'Active', NULL, NULL, '2026-09-18 13:15:47', '2026-09-18 13:15:47');

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
(3, 'Electronics', 'electronics', 'No', NULL, '2026-09-16 01:13:59', 1, 'Active', NULL, NULL, NULL, '2026-09-15 13:13:59', '2026-09-15 13:13:59'),
(4, 'Mobile', 'mobile', 'No', NULL, '2026-09-18 21:48:45', 1, 'Active', NULL, NULL, NULL, '2026-09-18 09:48:45', '2026-09-18 09:48:45');

-- --------------------------------------------------------

--
-- Table structure for table `chart_of_accounts`
--

CREATE TABLE `chart_of_accounts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `account_code` varchar(50) NOT NULL,
  `account_name` varchar(150) NOT NULL,
  `layer_level` tinyint(3) UNSIGNED NOT NULL COMMENT '1 = Root, 2 = Group, 3 = Ledger',
  `parent_id` bigint(20) UNSIGNED DEFAULT NULL,
  `account_type` enum('Asset','Liability','Equity','Income','Expense','Liquid Asset') NOT NULL,
  `balance` enum('Debit','Credit') NOT NULL,
  `current_balance` decimal(18,2) DEFAULT NULL,
  `opening_balance` decimal(12,2) DEFAULT 0.00,
  `opening_balance_entry_datetime` datetime DEFAULT NULL,
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `deleted` enum('Yes','No') NOT NULL DEFAULT 'No',
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `deleted_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_date` date DEFAULT NULL,
  `updated_date` date DEFAULT NULL,
  `deleted_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `chart_of_accounts`
--

INSERT INTO `chart_of_accounts` (`id`, `account_code`, `account_name`, `layer_level`, `parent_id`, `account_type`, `balance`, `current_balance`, `opening_balance`, `opening_balance_entry_datetime`, `is_system`, `deleted`, `status`, `created_by`, `updated_by`, `deleted_by`, `created_date`, `updated_date`, `deleted_date`, `created_at`, `updated_at`) VALUES
(1, '10000000', 'Assets', 1, NULL, 'Asset', 'Debit', NULL, 0.00, NULL, 1, 'No', 'Active', NULL, NULL, NULL, NULL, NULL, NULL, '2026-10-09 19:42:06', '2026-10-09 19:42:06'),
(2, '20000000', 'Liabilities', 1, NULL, 'Liability', 'Credit', NULL, 0.00, NULL, 1, 'No', 'Active', NULL, NULL, NULL, NULL, NULL, NULL, '2026-10-09 19:42:06', '2026-10-09 19:42:06'),
(3, '30000000', 'Equity', 1, NULL, 'Equity', 'Credit', NULL, 0.00, NULL, 1, 'No', 'Active', NULL, NULL, NULL, NULL, NULL, NULL, '2026-10-09 19:42:06', '2026-10-09 19:42:06'),
(4, '40000000', 'Income', 1, NULL, 'Income', 'Debit', NULL, 0.00, NULL, 1, 'No', 'Active', NULL, NULL, NULL, NULL, NULL, NULL, '2026-10-09 19:42:06', '2026-10-10 15:58:45'),
(5, '50000000', 'Expense', 1, NULL, 'Expense', 'Credit', NULL, 0.00, NULL, 1, 'No', 'Active', NULL, NULL, NULL, NULL, NULL, NULL, '2026-10-09 19:42:06', '2026-10-10 15:58:51'),
(6, '60000000', 'Liquid Asset', 1, NULL, 'Liquid Asset', 'Debit', NULL, 0.00, NULL, 1, 'No', 'Active', NULL, NULL, NULL, NULL, NULL, NULL, '2026-10-09 19:42:06', '2026-10-09 19:42:06'),
(9, '10100000', 'Current Asset', 2, 1, 'Asset', 'Debit', NULL, 1500000.00, '2026-10-10 21:57:00', 0, 'No', 'Active', 1, 1, NULL, '2026-10-10', '2026-10-10', NULL, '2026-10-10 09:57:25', '2026-10-10 09:57:25'),
(10, '50100000', 'Utility', 2, 5, 'Expense', 'Credit', NULL, 0.00, '2026-10-10 22:54:00', 0, 'No', 'Active', 1, 1, NULL, '2026-10-10', '2026-10-10', NULL, '2026-10-10 10:54:48', '2026-10-10 10:54:48'),
(11, '50200000', 'COGS', 2, 5, 'Expense', 'Credit', NULL, 0.00, '2026-10-10 22:55:00', 0, 'No', 'Active', 1, 1, NULL, '2026-10-10', '2026-10-10', NULL, '2026-10-10 10:55:22', '2026-10-10 10:55:22'),
(12, '50101000', 'Electric', 3, 10, 'Expense', 'Credit', NULL, 0.00, NULL, 0, 'No', 'Active', 1, 1, NULL, '2026-10-10', '2026-10-10', NULL, '2026-10-10 10:55:33', '2026-10-10 10:55:33'),
(13, '50102000', 'Wifi', 3, 10, 'Expense', 'Credit', NULL, 0.00, NULL, 0, 'No', 'Active', 1, 1, NULL, '2026-10-10', '2026-10-10', NULL, '2026-10-10 10:55:44', '2026-10-10 10:55:44'),
(14, '50201000', 'Packaging', 3, 11, 'Expense', 'Credit', NULL, 0.00, NULL, 0, 'No', 'Active', 1, 1, NULL, '2026-10-10', '2026-10-10', NULL, '2026-10-10 10:56:16', '2026-10-10 10:56:16'),
(15, '50202000', 'Transport Cost', 3, 11, 'Expense', 'Credit', NULL, 0.00, NULL, 0, 'No', 'Active', 1, 1, NULL, '2026-10-10', '2026-10-10', NULL, '2026-10-10 10:56:37', '2026-10-10 10:56:37'),
(16, '50203000', 'Payment Transaction Fee', 3, 11, 'Expense', 'Credit', NULL, 0.00, NULL, 0, 'No', 'Active', 1, 1, NULL, '2026-10-10', '2026-10-10', NULL, '2026-10-10 10:56:59', '2026-10-10 10:56:59'),
(17, '60100000', 'Bank', 2, 6, 'Liquid Asset', 'Debit', NULL, 0.00, NULL, 0, 'No', 'Active', 1, 1, NULL, '2026-10-10', '2026-10-10', NULL, '2026-10-10 12:22:51', '2026-10-10 12:22:51'),
(18, '60101000', 'NCC Bank AC-123456', 3, 17, 'Liquid Asset', 'Debit', NULL, 0.00, NULL, 0, 'No', 'Active', 1, 1, NULL, '2026-10-10', '2026-10-10', NULL, '2026-10-10 12:23:12', '2026-10-10 12:23:12'),
(19, '60200000', 'Cash', 2, 6, 'Liquid Asset', 'Debit', NULL, 0.00, NULL, 0, 'No', 'Active', 1, 1, NULL, '2026-10-10', '2026-10-10', NULL, '2026-10-10 12:23:26', '2026-10-10 12:23:26'),
(20, '60201000', 'Shop Cash', 3, 19, 'Liquid Asset', 'Debit', NULL, 0.00, NULL, 0, 'No', 'Active', 1, 1, NULL, '2026-10-10', '2026-10-10', NULL, '2026-10-10 12:25:02', '2026-10-10 12:25:02');

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `employee_code` varchar(30) NOT NULL,
  `name` varchar(150) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `designation` varchar(100) DEFAULT NULL,
  `description` longtext DEFAULT NULL,
  `deleted` enum('Yes','No') NOT NULL DEFAULT 'No',
  `deleted_by` bigint(20) UNSIGNED DEFAULT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_date` datetime DEFAULT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_date` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`id`, `employee_code`, `name`, `phone`, `email`, `designation`, `description`, `deleted`, `deleted_by`, `status`, `created_by`, `created_date`, `updated_by`, `updated_date`, `created_at`, `updated_at`) VALUES
(1, '00001', 'Shahed', '01774577458', 'shahed@gmail.com', 'Sales Man', NULL, 'No', NULL, 'Active', 1, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `expense_no` varchar(30) DEFAULT NULL,
  `expense_date` date NOT NULL,
  `payment_group_id` int(20) DEFAULT NULL,
  `payment_account_id` bigint(20) UNSIGNED NOT NULL,
  `employee_id` int(20) DEFAULT NULL,
  `reference_no` varchar(100) DEFAULT NULL,
  `total_amount` decimal(18,2) NOT NULL,
  `remarks` longtext DEFAULT NULL,
  `deleted` enum('Yes','No') NOT NULL DEFAULT 'No',
  `deleted_by` bigint(20) UNSIGNED DEFAULT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_date` datetime DEFAULT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_date` datetime DEFAULT NULL,
  `deleted_datetime` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `expenses`
--

INSERT INTO `expenses` (`id`, `expense_no`, `expense_date`, `payment_group_id`, `payment_account_id`, `employee_id`, `reference_no`, `total_amount`, `remarks`, `deleted`, `deleted_by`, `status`, `created_by`, `created_date`, `updated_by`, `updated_date`, `deleted_datetime`, `created_at`, `updated_at`) VALUES
(1, '000001', '2026-10-11', 17, 18, 1, NULL, 780.00, NULL, 'Yes', 1, 'Active', 1, NULL, 1, NULL, '2026-10-10 19:36:07', '2026-10-10 13:11:04', '2026-10-10 13:11:04'),
(2, '000002', '2026-10-11', 17, 18, 1, '5343656', 900.00, 'tde gfd gfds gfds g', 'Yes', 1, 'Inactive', 1, NULL, 1, NULL, '2026-10-10 19:40:53', '2026-10-10 13:39:33', '2026-10-10 13:39:33'),
(3, '000003', '2026-10-11', 19, 20, 1, NULL, 1000.00, NULL, 'Yes', 1, 'Inactive', 1, NULL, 1, NULL, '2026-10-10 19:42:06', '2026-10-10 13:41:50', '2026-10-10 13:41:50');

-- --------------------------------------------------------

--
-- Table structure for table `expense_details`
--

CREATE TABLE `expense_details` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `expense_id` bigint(20) UNSIGNED NOT NULL,
  `expense_group_id` bigint(20) UNSIGNED NOT NULL,
  `expense_account_id` bigint(20) UNSIGNED NOT NULL,
  `description` longtext DEFAULT NULL,
  `amount` decimal(18,2) NOT NULL,
  `deleted` enum('Yes','No') NOT NULL DEFAULT 'No',
  `deleted_by` bigint(20) UNSIGNED DEFAULT NULL,
  `deleted_datetime` datetime DEFAULT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_date` datetime DEFAULT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_date` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `expense_details`
--

INSERT INTO `expense_details` (`id`, `expense_id`, `expense_group_id`, `expense_account_id`, `description`, `amount`, `deleted`, `deleted_by`, `deleted_datetime`, `status`, `created_by`, `created_date`, `updated_by`, `updated_date`, `created_at`, `updated_at`) VALUES
(1, 1, 10, 12, NULL, 500.00, 'No', NULL, NULL, 'Active', NULL, NULL, NULL, NULL, '2026-10-10 13:11:04', '2026-10-10 13:11:04'),
(2, 1, 11, 15, NULL, 280.00, 'No', NULL, NULL, 'Active', NULL, NULL, NULL, NULL, '2026-10-10 13:11:04', '2026-10-10 13:11:04'),
(3, 2, 10, 12, NULL, 680.00, 'No', NULL, NULL, 'Active', NULL, NULL, NULL, NULL, '2026-10-10 13:39:33', '2026-10-10 13:39:33'),
(4, 2, 11, 15, NULL, 220.00, 'No', NULL, NULL, 'Active', NULL, NULL, NULL, NULL, '2026-10-10 13:39:33', '2026-10-10 13:39:33'),
(5, 3, 10, 12, NULL, 1000.00, 'Yes', 1, '2026-10-10 19:42:06', 'Inactive', NULL, NULL, NULL, NULL, '2026-10-10 13:41:50', '2026-10-10 13:41:50');

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
(6, '2026_09_14_170333_create_role_permissions_table', 2),
(7, '2026_09_28_162331_create_payments_table', 3),
(8, '2026_09_28_195640_create_purchase_returns_table', 4),
(9, '2026_09_28_195653_create_purchase_return_items_table', 4),
(10, '2026_09_29_165818_create_parties_table', 5),
(11, '2026_09_30_010001_create_sales_table', 6),
(12, '2026_09_30_010002_create_sale_items_table', 6),
(13, '2026_09_30_010003_create_sale_returns_table', 6),
(14, '2026_09_30_010004_create_sale_return_items_table', 6),
(15, '2026_09_30_010005_create_sale_orders_table', 6),
(16, '2026_09_30_010006_create_sale_order_items_table', 6),
(17, '2026_09_29_193531_create_sales_table', 7),
(18, '2026_09_29_193539_create_sale_items_table', 7),
(19, '2026_09_29_193552_create_sale_returns_table', 7),
(20, '2026_09_29_193559_create_sale_return_items_table', 7),
(21, '2026_09_29_193752_create_sale_orders_table', 7),
(22, '2026_09_29_193802_create_sale_order_items_table', 7),
(23, '2026_10_10_160129_create_expenses_table', 8),
(24, '2026_10_10_181009_create_expense_details_table', 9),
(25, '2026_10_10_181251_create_employees_table', 10);

-- --------------------------------------------------------

--
-- Table structure for table `parties`
--

CREATE TABLE `parties` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `code` varchar(30) DEFAULT NULL,
  `type` enum('Customer','Walk-In Customer','Supplier') NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `alt_phone` varchar(30) DEFAULT NULL,
  `address` longtext DEFAULT NULL,
  `opening_balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `opening_balance_type` enum('Due','Advance') NOT NULL DEFAULT 'Due',
  `opening_balance_date` date DEFAULT NULL,
  `credit_limit` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `deleted` enum('Yes','No') NOT NULL DEFAULT 'No',
  `created_by` bigint(20) DEFAULT NULL,
  `created_date` datetime DEFAULT NULL,
  `updated_by` bigint(20) DEFAULT NULL,
  `updated_date` datetime DEFAULT NULL,
  `deleted_by` bigint(20) DEFAULT NULL,
  `deleted_date` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `parties`
--

INSERT INTO `parties` (`id`, `code`, `type`, `name`, `email`, `phone`, `alt_phone`, `address`, `opening_balance`, `opening_balance_type`, `opening_balance_date`, `credit_limit`, `status`, `deleted`, `created_by`, `created_date`, `updated_by`, `updated_date`, `deleted_by`, `deleted_date`, `created_at`, `updated_at`) VALUES
(1, 'CUS-00001', 'Customer', 'Rashed Munir1', 'rashed@gmail.com', '01887922061', '01554255451', 'gec', 501.00, 'Due', '2026-09-01', 100001.00, 'Active', 'Yes', 1, '2026-09-29 17:32:50', 1, '2026-09-29 17:35:13', 1, '2026-09-29 17:35:20', '2026-09-29 11:32:50', '2026-09-29 11:35:20'),
(2, 'CUS-00002', 'Customer', 'Hamidul Islam', NULL, NULL, NULL, NULL, 0.00, 'Due', NULL, 0.00, 'Active', 'No', 1, '2026-09-29 17:43:25', NULL, NULL, NULL, NULL, '2026-09-29 11:43:25', '2026-09-29 11:43:25'),
(3, 'CUS-00003', 'Customer', 'Selim', NULL, '01887455236', NULL, 'gec', 1100.00, 'Due', NULL, 0.00, 'Active', 'No', 1, '2026-09-29 17:44:29', 1, '2026-09-29 17:52:41', NULL, NULL, '2026-09-29 11:44:29', '2026-09-29 11:52:41'),
(4, 'WIC-00004', 'Walk-In Customer', 'Farihan Priom', NULL, '01887922063', NULL, 'gec', 5000.00, 'Due', '2026-09-01', 0.00, 'Active', 'No', 1, '2026-09-29 17:53:52', NULL, NULL, NULL, NULL, '2026-09-29 11:53:52', '2026-09-29 11:53:52'),
(5, 'SUP-00005', 'Supplier', 'Tonmoy Dalal', 'tonmoy@dalali.com', '01774588569', NULL, 'market', 0.00, 'Due', NULL, 0.00, 'Active', 'No', 1, '2026-10-04 16:45:15', NULL, NULL, NULL, NULL, '2026-10-04 10:45:15', '2026-10-04 10:45:15');

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
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `party_id` bigint(20) DEFAULT NULL,
  `party_type` enum('Customer','Supplier','Walk-In Customer','Employee') DEFAULT NULL,
  `code` varchar(55) DEFAULT NULL,
  `payment_type` enum('Walk-In','Wholesale','Purchase','Expense','Walk-In Sale Return','Wholesale Return','Sale Order','Payment','Payment Receive','Discount','Purchase Payment','Wholesale Payment Receive','Walk-In Payment Receive','Sale Order Payment Receive') DEFAULT NULL,
  `datetime` datetime DEFAULT NULL,
  `debit` decimal(10,2) DEFAULT NULL,
  `credit` decimal(10,2) DEFAULT NULL,
  `purchase_id` bigint(20) DEFAULT NULL,
  `purchase_return_id` bigint(20) DEFAULT NULL,
  `sale_id` bigint(20) DEFAULT NULL,
  `sale_return_id` bigint(20) DEFAULT NULL,
  `expense_id` bigint(20) DEFAULT NULL,
  `bill_id` bigint(20) DEFAULT NULL,
  `payment_method` varchar(191) DEFAULT NULL,
  `payment_method_id` bigint(20) DEFAULT NULL,
  `payment_method_source` varchar(191) DEFAULT NULL,
  `payment_method_source_id` bigint(20) DEFAULT NULL,
  `payment_method_account` varchar(191) DEFAULT NULL,
  `payment_method_account_id` bigint(20) DEFAULT NULL,
  `order_id` bigint(20) DEFAULT NULL,
  `remarks` longtext DEFAULT NULL,
  `deleted` enum('Yes','No') DEFAULT NULL,
  `status` enum('Active','Inactive') DEFAULT NULL,
  `created_by` bigint(20) DEFAULT NULL,
  `updated_by` bigint(20) DEFAULT NULL,
  `deleted_by` bigint(20) DEFAULT NULL,
  `created_datetime` datetime DEFAULT NULL,
  `updated_datetime` datetime DEFAULT NULL,
  `deleted_datetime` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `party_id`, `party_type`, `code`, `payment_type`, `datetime`, `debit`, `credit`, `purchase_id`, `purchase_return_id`, `sale_id`, `sale_return_id`, `expense_id`, `bill_id`, `payment_method`, `payment_method_id`, `payment_method_source`, `payment_method_source_id`, `payment_method_account`, `payment_method_account_id`, `order_id`, `remarks`, `deleted`, `status`, `created_by`, `updated_by`, `deleted_by`, `created_datetime`, `updated_datetime`, `deleted_datetime`, `created_at`, `updated_at`) VALUES
(1, 5, 'Supplier', 'VO-000001', 'Purchase', '2026-10-09 00:00:00', 150000.00, 0.00, 14, NULL, NULL, NULL, NULL, NULL, 'Cash', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'No', 'Active', 1, NULL, NULL, '2026-10-09 17:19:51', NULL, NULL, '2026-10-09 11:19:51', '2026-10-09 11:19:51'),
(2, 5, 'Supplier', 'VO-000002', 'Purchase Payment', '2026-10-09 00:00:00', 0.00, 100000.00, 14, NULL, NULL, NULL, NULL, NULL, 'Cash', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'No', 'Active', 1, NULL, NULL, '2026-10-09 17:19:51', NULL, NULL, '2026-10-09 11:19:51', '2026-10-09 11:19:51'),
(3, 5, 'Supplier', 'VO-000003', 'Payment', '2026-10-09 17:24:15', 0.00, 20000.00, NULL, NULL, NULL, NULL, NULL, NULL, 'Cash', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'No', 'Active', 1, NULL, NULL, '2026-10-09 17:24:15', NULL, NULL, '2026-10-09 11:24:15', '2026-10-09 11:24:15'),
(4, 4, 'Walk-In Customer', 'VO-000004', 'Walk-In', '2026-10-09 17:26:32', 0.00, 22000.00, NULL, NULL, 10, NULL, NULL, NULL, 'Cash', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'No', 'Active', 1, NULL, NULL, '2026-10-09 17:26:32', NULL, NULL, '2026-10-09 11:26:32', '2026-10-09 11:26:32'),
(5, 4, 'Walk-In Customer', 'VO-000005', 'Walk-In Payment Receive', '2026-10-09 17:26:32', 10000.00, 0.00, NULL, NULL, 10, NULL, NULL, NULL, 'Cash', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'No', 'Active', 1, NULL, NULL, '2026-10-09 17:26:32', NULL, NULL, '2026-10-09 11:26:32', '2026-10-09 11:26:32'),
(6, 3, 'Customer', 'VO-000006', 'Wholesale', '2026-10-09 17:56:08', 0.00, 28000.00, NULL, NULL, 11, NULL, NULL, NULL, 'Cash', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'No', 'Active', 1, NULL, NULL, '2026-10-09 17:56:08', NULL, NULL, '2026-10-09 11:56:08', '2026-10-09 11:56:08'),
(7, 3, 'Customer', 'VO-000007', 'Wholesale Payment Receive', '2026-10-09 17:56:08', 11100.00, 0.00, NULL, NULL, 11, NULL, NULL, NULL, 'Cash', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'No', 'Active', 1, NULL, NULL, '2026-10-09 17:56:08', NULL, NULL, '2026-10-09 11:56:08', '2026-10-09 11:56:08'),
(8, 3, 'Customer', 'VO-000008', 'Payment Receive', '2026-10-09 17:58:45', 18000.00, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, 'Cash', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Yes', 'Active', 1, NULL, 1, '2026-10-09 17:58:45', NULL, '2026-10-09 18:09:03', '2026-10-09 11:58:45', '2026-10-09 12:09:03'),
(9, 3, 'Customer', 'VO-000009', 'Discount', '2026-10-09 18:09:35', 500.00, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'No', 'Active', 1, NULL, NULL, '2026-10-09 18:09:35', NULL, NULL, '2026-10-09 12:09:35', '2026-10-09 12:09:35'),
(10, 3, 'Customer', 'VO-000010', 'Payment Receive', '2026-10-09 18:09:49', 17500.00, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, 'Cash', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'No', 'Active', 1, NULL, NULL, '2026-10-09 18:09:49', NULL, NULL, '2026-10-09 12:09:49', '2026-10-09 12:09:49'),
(11, 5, 'Supplier', 'VO-000011', 'Payment', '2026-10-09 18:10:23', 0.00, 29500.00, NULL, NULL, NULL, NULL, NULL, NULL, 'Cash', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'No', 'Active', 1, NULL, NULL, '2026-10-09 18:10:23', NULL, NULL, '2026-10-09 12:10:23', '2026-10-09 12:10:23'),
(13, 5, 'Supplier', 'VO-000012', 'Discount', '2026-10-09 18:11:32', 0.00, 500.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'No', 'Active', 1, NULL, NULL, '2026-10-09 18:11:32', NULL, NULL, '2026-10-09 12:11:32', '2026-10-09 12:11:32'),
(14, 1, 'Employee', 'VO-000013', 'Expense', '2026-10-11 00:00:00', 0.00, 780.00, NULL, NULL, NULL, NULL, 1, NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Yes', 'Active', 1, NULL, 1, '2026-10-10 19:11:04', NULL, '2026-10-10 19:36:07', '2026-10-10 13:11:04', '2026-10-10 13:11:04'),
(15, 1, 'Employee', 'VO-000014', 'Expense', '2026-10-11 00:00:00', 0.00, 900.00, NULL, NULL, NULL, NULL, 2, NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'No', 'Active', 1, NULL, NULL, '2026-10-10 19:39:33', NULL, NULL, '2026-10-10 13:39:33', '2026-10-10 13:39:33'),
(16, 1, 'Employee', 'VO-000015', 'Expense', '2026-10-11 00:00:00', 0.00, 1000.00, NULL, NULL, NULL, NULL, 3, NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Yes', 'Inactive', 1, NULL, 1, '2026-10-10 19:41:50', NULL, '2026-10-10 19:42:06', '2026-10-10 13:41:50', '2026-10-10 13:41:50');

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

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `slug`, `brand_id`, `category_id`, `unit_id`, `sku`, `barcode`, `image`, `stock_alert`, `purchase_price`, `sale_price`, `old_sale_price`, `discount`, `description`, `deleted`, `deleted_by`, `created_date`, `created_by`, `status`, `updated_by`, `updated_date`, `created_at`, `updated_at`) VALUES
(1, 'Samsung Galaxy F70 Pro', 'samsung-galaxy-f70-pro', 2, 4, 3, 'SAMSUNG-GALAXY-F70-PRO-MOBILE-SAMSUNG', '2603288999439', '1790096898_Ed2kQZaCYe.webp', 10.000, 20000.00, 28000.00, 32000.00, 4000.00, 'General\r\nBrand	Samsung\r\nModel	Galaxy F70 Pro\r\nDevice Type	Smartphone\r\nRelease Date	Exp. 03 August 2026\r\nStatus	 Upcoming\r\nHardware & Software\r\n8\r\nOperating System	Android\r\nOS Version	v16\r\nUser Interface	One UI 8.5\r\nChipset	Qualcomm SM6475-AB Snapdragon 6 Gen 3\r\nCPU	Octa-core (4x2.4 GHz Cortex-A78 & 4x1.8 GHz Cortex-A55)\r\nCPU Cores	8 Cores\r\nArchitecture	64 bit\r\nFabrication	4 nm\r\nGPU	Adreno 710', 'No', NULL, '2026-09-22 17:08:18', 1, 'Active', 1, '2026-09-28 16:59:25', NULL, NULL),
(2, 'Samsung Galaxy A17 4G (256GB)', 'samsung-galaxy-a17-4g-256gb', 2, 4, 2, 'SAMSUNG-GALAXY-A17-4G-256GB-MOBILE-SAMSUNG', '2954417550879', NULL, 50.000, 15000.00, 22000.00, 27000.00, 5000.00, NULL, 'No', NULL, '2026-09-22 17:41:33', 1, 'Active', 1, '2026-09-22 17:41:33', NULL, NULL),
(3, 'Samsung Galaxy Z Fold8 Ultra', 'samsung-galaxy-z-fold8-ultra', 2, 4, 3, 'SAMSUNG-GALAXY-Z-FOLD8-ULTRA-MOBILE-SAMSUNG', '2282133021355', NULL, 30.000, 25000.00, 28599.00, 32599.00, 0.00, NULL, 'No', NULL, '2026-09-22 17:58:29', 1, 'Active', 1, '2026-09-22 17:58:29', NULL, NULL);

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

--
-- Dumping data for table `product_attributes`
--

INSERT INTO `product_attributes` (`id`, `product_id`, `attribute_type_id`, `attribute_id`, `deleted`, `deleted_by`, `created_date`, `created_by`, `status`, `updated_by`, `updated_date`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 3, 'No', NULL, '2026-09-22 17:08:18', 1, 'Active', 1, '2026-09-22 17:08:18', NULL, NULL),
(2, 1, 1, 4, 'No', NULL, '2026-09-22 17:08:18', 1, 'Active', 1, '2026-09-22 17:08:18', NULL, NULL),
(3, 1, 1, 5, 'No', NULL, '2026-09-22 17:08:18', 1, 'Active', 1, '2026-09-22 17:08:18', NULL, NULL),
(4, 3, 1, 3, 'No', NULL, '2026-09-22 17:58:29', 1, 'Active', 1, '2026-09-22 17:58:29', NULL, NULL),
(5, 3, 1, 4, 'No', NULL, '2026-09-22 17:58:29', 1, 'Active', 1, '2026-09-22 17:58:29', NULL, NULL),
(6, 3, 1, 5, 'No', NULL, '2026-09-22 17:58:29', 1, 'Active', 1, '2026-09-22 17:58:29', NULL, NULL);

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

--
-- Dumping data for table `product_specs`
--

INSERT INTO `product_specs` (`id`, `product_id`, `spec_name`, `spec_value`, `deleted`, `deleted_by`, `created_date`, `created_by`, `status`, `updated_by`, `updated_date`, `created_at`, `updated_at`) VALUES
(1, 1, 'Storage', '128GB', 'No', NULL, '2026-09-22 17:08:18', 1, 'Active', 1, '2026-09-22 17:08:18', NULL, NULL),
(2, 1, 'RAM', '4GB', 'No', NULL, '2026-09-22 17:08:18', 1, 'Active', 1, '2026-09-22 17:08:18', NULL, NULL),
(3, 1, 'Camera', '50MP', 'No', NULL, '2026-09-22 17:08:18', 1, 'Active', 1, '2026-09-22 17:08:18', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `product_variants`
--

CREATE TABLE `product_variants` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `attribute_type_id` bigint(20) UNSIGNED NOT NULL,
  `attribute_id` bigint(20) UNSIGNED NOT NULL,
  `barcode` varchar(255) DEFAULT NULL,
  `sale_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `old_sale_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `opening_stock` decimal(15,3) NOT NULL DEFAULT 0.000,
  `deleted` enum('Yes','No') NOT NULL DEFAULT 'No',
  `deleted_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_date` datetime DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_date` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_variants`
--

INSERT INTO `product_variants` (`id`, `product_id`, `attribute_type_id`, `attribute_id`, `barcode`, `sale_price`, `old_sale_price`, `opening_stock`, `deleted`, `deleted_by`, `created_date`, `created_by`, `status`, `updated_by`, `updated_date`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 3, '2683699276107', 28000.00, 32000.00, 0.000, 'No', NULL, '2026-09-22 17:08:18', 1, 'Active', 1, '2026-09-22 17:08:18', '2026-09-22 11:08:18', '2026-09-22 11:08:18'),
(2, 1, 1, 4, '2526282683072', 28000.00, 32000.00, 0.000, 'No', NULL, '2026-09-22 17:08:18', 1, 'Active', 1, '2026-09-22 17:08:18', '2026-09-22 11:08:18', '2026-09-22 11:08:18'),
(3, 1, 1, 5, '2914888643445', 28000.00, 32000.00, 0.000, 'No', NULL, '2026-09-22 17:08:18', 1, 'Active', 1, '2026-09-22 17:08:18', '2026-09-22 11:08:18', '2026-09-22 11:08:18'),
(4, 3, 1, 3, '2998131060867', 28599.00, 32599.00, 10.000, 'No', NULL, '2026-09-22 17:58:29', 1, 'Active', 1, '2026-09-22 17:58:29', '2026-09-22 11:58:29', '2026-09-22 11:58:29'),
(5, 3, 1, 4, '2340333053905', 28599.00, 32599.00, 15.000, 'No', NULL, '2026-09-22 17:58:29', 1, 'Active', 1, '2026-09-22 17:58:29', '2026-09-22 11:58:29', '2026-09-22 11:58:29'),
(6, 3, 1, 5, '2913740567887', 28599.00, 32599.00, 13.000, 'No', NULL, '2026-09-22 17:58:29', 1, 'Active', 1, '2026-09-22 17:58:29', '2026-09-22 11:58:29', '2026-09-22 11:58:29');

-- --------------------------------------------------------

--
-- Table structure for table `purchases`
--

CREATE TABLE `purchases` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `supplier_id` bigint(20) UNSIGNED NOT NULL,
  `invoice_no` varchar(100) DEFAULT NULL,
  `purchase_date` date NOT NULL,
  `subtotal` decimal(18,2) NOT NULL DEFAULT 0.00,
  `discount_type` enum('Percentage','Amount') NOT NULL DEFAULT 'Amount',
  `discount_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `grand_total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `paid_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `due_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `payment_method` varchar(100) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `deleted` enum('Yes','No') NOT NULL DEFAULT 'No',
  `deleted_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_date` datetime DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_date` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchases`
--

INSERT INTO `purchases` (`id`, `supplier_id`, `invoice_no`, `purchase_date`, `subtotal`, `discount_type`, `discount_value`, `discount_amount`, `grand_total`, `paid_amount`, `due_amount`, `payment_method`, `remarks`, `deleted`, `deleted_by`, `created_date`, `created_by`, `status`, `updated_by`, `updated_date`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, '2026-09-28', 200000.00, 'Amount', 0.00, 0.00, 200000.00, 180000.00, 20000.00, 'Cash', NULL, 'Yes', 1, '2026-09-28 17:05:33', 1, 'Inactive', 1, '2026-09-28 19:41:13', '2026-09-28 11:05:33', '2026-09-28 13:41:13'),
(6, 1, 'PUR-000001', '2026-09-28', 250000.00, 'Amount', 0.00, 0.00, 250000.00, 150000.00, 100000.00, 'Cash', NULL, 'Yes', 1, '2026-09-28 19:48:40', 1, 'Inactive', 1, '2026-09-28 19:49:01', '2026-09-28 13:48:40', '2026-09-28 13:49:01'),
(7, 1, 'PUR-000001', '2026-09-28', 260000.00, 'Amount', 0.00, 0.00, 200000.00, 190000.00, 10000.00, 'Cash', NULL, 'No', NULL, '2026-09-28 19:49:40', 1, 'Active', 1, '2026-09-30 16:45:58', '2026-09-28 13:49:40', '2026-09-30 10:45:58'),
(8, 1, 'PUR-000002', '2026-09-28', 75000.00, 'Amount', 0.00, 0.00, 15000.00, 15000.00, 0.00, 'Cash', NULL, 'Yes', 1, '2026-09-28 19:50:15', 1, 'Inactive', 1, '2026-09-29 16:33:20', '2026-09-28 13:50:15', '2026-09-29 10:33:20'),
(9, 1, 'PUR-000003', '2026-09-29', 390600.00, 'Amount', 0.00, 0.00, 390600.00, 0.00, 390600.00, NULL, NULL, 'Yes', 1, '2026-09-29 16:23:33', 1, 'Inactive', 1, '2026-09-29 16:31:12', '2026-09-29 10:23:33', '2026-09-29 10:31:12'),
(10, 1, 'PUR-000004', '2026-09-29', 450000.00, 'Amount', 0.00, 0.00, 450000.00, 0.00, 450000.00, NULL, NULL, 'Yes', 1, '2026-09-29 16:28:07', 1, 'Inactive', 1, '2026-09-29 16:31:02', '2026-09-29 10:28:07', '2026-09-29 10:31:02'),
(11, 5, 'PUR-000002', '2026-10-04', 8520000.00, 'Amount', 0.00, 0.00, 8520000.00, 0.00, 8520000.00, 'Cash', NULL, 'No', NULL, '2026-10-04 16:46:08', 1, 'Active', NULL, NULL, '2026-10-04 10:46:08', '2026-10-04 10:46:08'),
(12, 5, 'PUR-000003', '2026-10-09', 125000.00, 'Amount', 0.00, 0.00, 125000.00, 100000.00, 25000.00, NULL, NULL, 'No', NULL, '2026-10-09 14:19:28', 1, 'Active', NULL, NULL, '2026-10-09 08:19:28', '2026-10-09 08:19:28'),
(13, 5, 'PUR-000004', '2026-10-09', 150000.00, 'Amount', 0.00, 0.00, 150000.00, 100000.00, 50000.00, 'Cash', NULL, 'No', NULL, '2026-10-09 17:17:16', 1, 'Active', NULL, NULL, '2026-10-09 11:17:16', '2026-10-09 11:17:16'),
(14, 5, 'PUR-000005', '2026-10-09', 150000.00, 'Amount', 0.00, 0.00, 150000.00, 100000.00, 50000.00, 'Cash', NULL, 'No', NULL, '2026-10-09 17:19:51', 1, 'Active', NULL, NULL, '2026-10-09 11:19:51', '2026-10-09 11:19:51');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_items`
--

CREATE TABLE `purchase_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `purchase_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `product_variant_id` bigint(20) UNSIGNED DEFAULT NULL,
  `qty` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `purchase_price` decimal(18,2) NOT NULL DEFAULT 0.00,
  `discount_type` enum('Percentage','Amount') NOT NULL DEFAULT 'Amount',
  `discount_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `subtotal` decimal(18,2) NOT NULL DEFAULT 0.00,
  `total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `deleted` enum('Yes','No') NOT NULL DEFAULT 'No',
  `deleted_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_date` datetime DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_date` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchase_items`
--

INSERT INTO `purchase_items` (`id`, `purchase_id`, `product_id`, `product_variant_id`, `qty`, `purchase_price`, `discount_type`, `discount_value`, `discount_amount`, `subtotal`, `total`, `deleted`, `deleted_by`, `created_date`, `created_by`, `status`, `updated_by`, `updated_date`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 1, 10.0000, 20000.00, 'Amount', 0.00, 0.00, 200000.00, 200000.00, 'Yes', 1, '2026-09-28 17:05:33', 1, 'Active', 1, '2026-09-28 19:41:13', '2026-09-28 11:05:33', '2026-09-28 13:41:13'),
(2, 6, 3, 4, 10.0000, 25000.00, 'Amount', 0.00, 0.00, 250000.00, 250000.00, 'Yes', 1, '2026-09-28 19:48:40', 1, 'Inactive', 1, '2026-09-28 19:49:01', '2026-09-28 13:48:40', '2026-09-28 13:49:01'),
(3, 7, 1, 1, 13.0000, 20000.00, 'Amount', 0.00, 0.00, 260000.00, 260000.00, 'No', NULL, '2026-09-28 19:49:40', 1, 'Active', NULL, NULL, '2026-09-28 13:49:40', '2026-09-28 13:49:40'),
(4, 8, 2, NULL, 5.0000, 15000.00, 'Amount', 0.00, 0.00, 75000.00, 75000.00, 'Yes', 1, '2026-09-28 19:50:15', 1, 'Inactive', 1, '2026-09-29 16:33:20', '2026-09-28 13:50:15', '2026-09-29 10:33:20'),
(5, 9, 1, 2, 20.0000, 21000.00, 'Percentage', 7.00, 29400.00, 420000.00, 390600.00, 'Yes', 1, '2026-09-29 16:23:33', 1, 'Inactive', 1, '2026-09-29 16:31:12', '2026-09-29 10:23:33', '2026-09-29 10:31:12'),
(6, 10, 1, 1, 20.5000, 22000.00, 'Amount', 1000.00, 1000.00, 451000.00, 450000.00, 'Yes', 1, '2026-09-29 16:28:07', 1, 'Inactive', 1, '2026-09-29 16:31:02', '2026-09-29 10:28:07', '2026-09-29 10:31:02'),
(7, 11, 1, 1, 101.0000, 20000.00, 'Amount', 0.00, 0.00, 2020000.00, 2020000.00, 'No', NULL, '2026-10-04 16:46:08', 1, 'Active', NULL, NULL, '2026-10-04 10:46:08', '2026-10-04 10:46:08'),
(8, 11, 2, NULL, 100.0000, 15000.00, 'Amount', 0.00, 0.00, 1500000.00, 1500000.00, 'No', NULL, '2026-10-04 16:46:08', 1, 'Active', NULL, NULL, '2026-10-04 10:46:08', '2026-10-04 10:46:08'),
(9, 11, 3, 5, 100.0000, 25000.00, 'Amount', 0.00, 0.00, 2500000.00, 2500000.00, 'No', NULL, '2026-10-04 16:46:08', 1, 'Active', NULL, NULL, '2026-10-04 10:46:08', '2026-10-04 10:46:08'),
(10, 11, 3, 4, 100.0000, 25000.00, 'Amount', 0.00, 0.00, 2500000.00, 2500000.00, 'No', NULL, '2026-10-04 16:46:08', 1, 'Active', NULL, NULL, '2026-10-04 10:46:08', '2026-10-04 10:46:08'),
(11, 12, 3, 4, 5.0000, 25000.00, 'Amount', 0.00, 0.00, 125000.00, 125000.00, 'No', NULL, '2026-10-09 14:19:28', 1, 'Active', NULL, NULL, '2026-10-09 08:19:28', '2026-10-09 08:19:28'),
(12, 13, 2, NULL, 10.0000, 15000.00, 'Amount', 0.00, 0.00, 150000.00, 150000.00, 'No', NULL, '2026-10-09 17:17:16', 1, 'Active', NULL, NULL, '2026-10-09 11:17:16', '2026-10-09 11:17:16'),
(13, 14, 2, NULL, 10.0000, 15000.00, 'Amount', 0.00, 0.00, 150000.00, 150000.00, 'No', NULL, '2026-10-09 17:19:51', 1, 'Active', NULL, NULL, '2026-10-09 11:19:51', '2026-10-09 11:19:51');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_returns`
--

CREATE TABLE `purchase_returns` (
  `id` bigint(20) NOT NULL,
  `return_no` varchar(100) DEFAULT NULL,
  `purchase_id` bigint(20) NOT NULL,
  `supplier_id` bigint(20) NOT NULL,
  `datetime` datetime NOT NULL,
  `subtotal` decimal(15,2) NOT NULL DEFAULT 0.00,
  `discount_type` enum('Percentage','Amount') NOT NULL DEFAULT 'Amount',
  `discount_value` decimal(15,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `grand_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `remarks` longtext DEFAULT NULL,
  `deleted` enum('Yes','No') NOT NULL DEFAULT 'No',
  `deleted_by` bigint(20) DEFAULT NULL,
  `created_datetime` datetime DEFAULT NULL,
  `created_by` bigint(20) DEFAULT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `updated_by` bigint(20) DEFAULT NULL,
  `updated_datetime` datetime DEFAULT NULL,
  `deleted_datetime` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `purchase_returns`
--

INSERT INTO `purchase_returns` (`id`, `return_no`, `purchase_id`, `supplier_id`, `datetime`, `subtotal`, `discount_type`, `discount_value`, `discount_amount`, `grand_total`, `remarks`, `deleted`, `deleted_by`, `created_datetime`, `created_by`, `status`, `updated_by`, `updated_datetime`, `deleted_datetime`, `created_at`, `updated_at`) VALUES
(8, 'PRE-000001', 8, 1, '2026-09-28 00:00:00', 30000.00, 'Amount', 0.00, 0.00, 30000.00, NULL, 'Yes', NULL, '2026-09-28 20:23:34', 1, 'Active', NULL, NULL, '2026-09-28 20:54:40', '2026-09-28 14:23:34', '2026-09-28 14:54:40'),
(9, 'PRE-000001', 8, 1, '2026-09-29 00:00:00', 30000.00, 'Amount', 0.00, 0.00, 30000.00, NULL, 'No', NULL, '2026-09-29 15:29:39', 1, 'Active', NULL, NULL, NULL, '2026-09-29 09:29:39', '2026-09-29 09:29:39'),
(10, 'PRE-000002', 7, 1, '2026-09-30 00:00:00', 60000.00, 'Amount', 0.00, 0.00, 60000.00, NULL, 'No', NULL, '2026-09-30 16:45:58', 1, 'Active', NULL, NULL, NULL, '2026-09-30 10:45:58', '2026-09-30 10:45:58');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_return_items`
--

CREATE TABLE `purchase_return_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `purchase_return_id` bigint(20) NOT NULL,
  `purchase_id` bigint(20) NOT NULL,
  `purchase_item_id` bigint(20) NOT NULL,
  `product_id` bigint(20) NOT NULL,
  `product_variant_id` bigint(20) DEFAULT NULL,
  `qty` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `purchase_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `subtotal` decimal(15,2) NOT NULL DEFAULT 0.00,
  `discount_type` decimal(15,2) DEFAULT NULL,
  `discount_value` decimal(15,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `deleted` enum('Yes','No') NOT NULL DEFAULT 'No',
  `deleted_by` bigint(20) DEFAULT NULL,
  `created_datetime` datetime DEFAULT NULL,
  `created_by` bigint(20) DEFAULT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `updated_by` bigint(20) DEFAULT NULL,
  `updated_datetime` datetime DEFAULT NULL,
  `deleted_datetime` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `purchase_return_items`
--

INSERT INTO `purchase_return_items` (`id`, `purchase_return_id`, `purchase_id`, `purchase_item_id`, `product_id`, `product_variant_id`, `qty`, `purchase_price`, `subtotal`, `discount_type`, `discount_value`, `discount_amount`, `total`, `deleted`, `deleted_by`, `created_datetime`, `created_by`, `status`, `updated_by`, `updated_datetime`, `deleted_datetime`, `created_at`, `updated_at`) VALUES
(3, 8, 8, 4, 2, NULL, 2.0000, 15000.00, 30000.00, NULL, 0.00, 0.00, 30000.00, 'Yes', NULL, '2026-09-28 20:23:34', 1, 'Active', NULL, NULL, '2026-09-28 20:54:40', '2026-09-28 14:23:34', '2026-09-28 14:54:40'),
(4, 9, 8, 4, 2, NULL, 2.0000, 15000.00, 30000.00, NULL, 0.00, 0.00, 30000.00, 'No', NULL, '2026-09-29 15:29:39', 1, 'Active', NULL, NULL, NULL, '2026-09-29 09:29:39', '2026-09-29 09:29:39'),
(5, 10, 7, 3, 1, 1, 3.0000, 20000.00, 60000.00, NULL, 0.00, 0.00, 60000.00, 'No', NULL, '2026-09-30 16:45:58', 1, 'Active', NULL, NULL, NULL, '2026-09-30 10:45:58', '2026-09-30 10:45:58');

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
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `invoice_no` varchar(100) NOT NULL,
  `sale_type` enum('Wholesale','Walk-In') NOT NULL,
  `customer_id` bigint(20) UNSIGNED DEFAULT NULL,
  `sale_date` date NOT NULL,
  `subtotal` decimal(15,2) NOT NULL DEFAULT 0.00,
  `discount_type` varchar(30) DEFAULT NULL,
  `discount_value` decimal(15,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `grand_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `paid_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `due_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `payment_method` varchar(50) DEFAULT NULL,
  `remarks` longtext DEFAULT NULL,
  `deleted` enum('Yes','No') NOT NULL DEFAULT 'No',
  `deleted_by` bigint(20) UNSIGNED DEFAULT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_date` datetime DEFAULT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_date` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sales`
--

INSERT INTO `sales` (`id`, `invoice_no`, `sale_type`, `customer_id`, `sale_date`, `subtotal`, `discount_type`, `discount_value`, `discount_amount`, `grand_total`, `paid_amount`, `due_amount`, `payment_method`, `remarks`, `deleted`, `deleted_by`, `status`, `created_by`, `created_date`, `updated_by`, `updated_date`, `created_at`, `updated_at`) VALUES
(6, 'INV-000001', 'Walk-In', 4, '2026-09-30', 28000.00, 'Percentage', 5.00, 1400.00, 26600.00, 25000.00, 1600.00, 'Cash', NULL, 'No', NULL, 'Active', 1, '2026-09-30 18:51:18', NULL, NULL, '2026-09-30 12:51:18', '2026-09-30 12:51:18'),
(7, 'INV-000002', 'Walk-In', 4, '2026-10-04', 28000.00, 'Amount', 0.00, 0.00, 28000.00, 25000.00, 3000.00, 'Cash', NULL, 'No', NULL, 'Active', 1, '2026-10-04 16:21:02', NULL, NULL, '2026-10-04 10:21:02', '2026-10-04 10:21:02'),
(8, 'INV-000003', 'Wholesale', 2, '2026-10-04', 22000.00, 'Amount', 500.00, 500.00, 21500.00, 0.00, 21500.00, 'Cash', NULL, 'No', NULL, 'Active', 1, '2026-10-04 16:47:48', NULL, NULL, '2026-10-04 10:47:48', '2026-10-04 10:47:48'),
(9, 'INV-000004', 'Wholesale', 2, '2026-10-05', 28000.00, 'Amount', 500.00, 500.00, 27500.00, 25000.00, 2500.00, 'Cash', 'Delivered from Sale Order SO-000003', 'No', NULL, 'Active', 1, '2026-10-05 18:30:33', NULL, NULL, '2026-10-05 12:30:33', '2026-10-05 12:30:33'),
(10, 'INV-000005', 'Walk-In', 4, '2026-10-09', 22000.00, 'Amount', 0.00, 0.00, 22000.00, 10000.00, 12000.00, 'Cash', NULL, 'No', NULL, 'Active', 1, '2026-10-09 17:26:32', NULL, NULL, '2026-10-09 11:26:32', '2026-10-09 11:26:32'),
(11, 'INV-000006', 'Wholesale', 3, '2026-10-09', 28000.00, 'Amount', 0.00, 0.00, 28000.00, 11100.00, 16900.00, 'Cash', NULL, 'No', NULL, 'Active', 1, '2026-10-09 17:56:08', NULL, NULL, '2026-10-09 11:56:08', '2026-10-09 11:56:08');

-- --------------------------------------------------------

--
-- Table structure for table `sale_items`
--

CREATE TABLE `sale_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `sale_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `product_variant_id` bigint(20) UNSIGNED DEFAULT NULL,
  `qty` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `sale_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `purchase_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `discount_type` varchar(30) DEFAULT NULL,
  `discount_value` decimal(15,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `subtotal` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `deleted` enum('Yes','No') NOT NULL DEFAULT 'No',
  `deleted_by` bigint(20) UNSIGNED DEFAULT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_date` datetime DEFAULT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_date` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sale_items`
--

INSERT INTO `sale_items` (`id`, `sale_id`, `product_id`, `product_variant_id`, `qty`, `sale_price`, `purchase_price`, `discount_type`, `discount_value`, `discount_amount`, `subtotal`, `total`, `deleted`, `deleted_by`, `status`, `created_by`, `created_date`, `updated_by`, `updated_date`, `created_at`, `updated_at`) VALUES
(6, 6, 1, 1, 1.0000, 28000.00, 20000.00, 'Amount', 0.00, 0.00, 28000.00, 28000.00, 'No', NULL, 'Active', 1, '2026-09-30 18:51:18', NULL, NULL, '2026-09-30 12:51:18', '2026-09-30 12:51:18'),
(7, 7, 1, 1, 1.0000, 28000.00, 20000.00, 'Amount', 0.00, 0.00, 28000.00, 28000.00, 'No', NULL, 'Active', 1, '2026-10-04 16:21:02', NULL, NULL, '2026-10-04 10:21:02', '2026-10-04 10:21:02'),
(8, 8, 2, NULL, 1.0000, 22000.00, 15000.00, 'Amount', 0.00, 0.00, 22000.00, 22000.00, 'No', NULL, 'Active', 1, '2026-10-04 16:47:48', NULL, NULL, '2026-10-04 10:47:48', '2026-10-04 10:47:48'),
(9, 9, 1, 1, 1.0000, 28000.00, 20000.00, 'Amount', 0.00, 0.00, 28000.00, 28000.00, 'No', NULL, 'Active', 1, '2026-10-05 18:30:33', NULL, NULL, '2026-10-05 12:30:33', '2026-10-05 12:30:33'),
(10, 10, 2, NULL, 1.0000, 22000.00, 15000.00, 'Amount', 0.00, 0.00, 22000.00, 22000.00, 'No', NULL, 'Active', 1, '2026-10-09 17:26:32', NULL, NULL, '2026-10-09 11:26:32', '2026-10-09 11:26:32'),
(11, 11, 1, 1, 1.0000, 28000.00, 20000.00, 'Amount', 0.00, 0.00, 28000.00, 28000.00, 'No', NULL, 'Active', 1, '2026-10-09 17:56:08', NULL, NULL, '2026-10-09 11:56:08', '2026-10-09 11:56:08');

-- --------------------------------------------------------

--
-- Table structure for table `sale_orders`
--

CREATE TABLE `sale_orders` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_no` varchar(100) NOT NULL,
  `customer_id` bigint(20) UNSIGNED DEFAULT NULL,
  `order_date` date NOT NULL,
  `delivery_date` date DEFAULT NULL,
  `subtotal` decimal(15,2) NOT NULL DEFAULT 0.00,
  `discount_type` varchar(30) DEFAULT NULL,
  `discount_value` decimal(15,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `grand_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `advance_paid` decimal(15,2) NOT NULL DEFAULT 0.00,
  `due_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `order_status` enum('Pending','Confirmed','Delivered','Cancelled','Partial') NOT NULL DEFAULT 'Pending',
  `delivery_datetime` datetime DEFAULT NULL,
  `cancel_datetime` datetime DEFAULT NULL,
  `remarks` longtext DEFAULT NULL,
  `deleted` enum('Yes','No') NOT NULL DEFAULT 'No',
  `deleted_by` bigint(20) UNSIGNED DEFAULT NULL,
  `deleted_date` date DEFAULT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_date` datetime DEFAULT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_date` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sale_orders`
--

INSERT INTO `sale_orders` (`id`, `order_no`, `customer_id`, `order_date`, `delivery_date`, `subtotal`, `discount_type`, `discount_value`, `discount_amount`, `grand_total`, `advance_paid`, `due_amount`, `order_status`, `delivery_datetime`, `cancel_datetime`, `remarks`, `deleted`, `deleted_by`, `deleted_date`, `status`, `created_by`, `created_date`, `updated_by`, `updated_date`, `created_at`, `updated_at`) VALUES
(1, 'SO-000001', 4, '2026-10-05', '2026-10-09', 532000.00, 'Amount', 0.00, 0.00, 532000.00, 0.00, 532000.00, 'Delivered', '2026-10-07 19:06:05', NULL, NULL, 'No', NULL, NULL, 'Active', 1, '2026-10-05 17:39:28', 1, '2026-10-07 19:06:05', '2026-10-05 11:39:28', '2026-10-07 13:06:05'),
(2, 'SO-000002', 4, '2026-10-05', NULL, 56599.00, 'Amount', 98.98, 98.98, 56500.02, 50000.00, 6500.02, 'Cancelled', NULL, NULL, NULL, 'No', NULL, NULL, 'Active', 1, '2026-10-05 17:58:11', 1, '2026-10-05 19:08:15', '2026-10-05 11:58:11', '2026-10-05 13:08:15'),
(3, 'SO-000003', 2, '2026-10-05', NULL, 28000.00, 'Amount', 500.00, 500.00, 27500.00, 25000.00, 2500.00, 'Delivered', '2026-10-07 18:55:20', NULL, NULL, 'No', NULL, NULL, 'Active', 1, '2026-10-05 18:27:48', 1, '2026-10-07 18:55:20', '2026-10-05 12:27:48', '2026-10-07 12:55:20'),
(4, 'SO-000004', 2, '2026-10-07', NULL, 420000.00, 'Amount', 0.00, 0.00, 420000.00, 400000.00, 20000.00, 'Cancelled', NULL, NULL, 'Cancelled: Customer Not Interested', 'No', NULL, NULL, 'Active', 1, '2026-10-07 19:07:51', 1, '2026-10-07 19:08:32', '2026-10-07 13:07:51', '2026-10-07 13:08:32'),
(5, 'SO-000005', 3, '2026-10-07', NULL, 285990.00, 'Amount', 0.00, 0.00, 285990.00, 0.00, 285990.00, 'Delivered', '2026-10-07 19:12:44', NULL, NULL, 'No', NULL, NULL, 'Active', 1, '2026-10-07 19:10:29', 1, '2026-10-07 19:12:44', '2026-10-07 13:10:29', '2026-10-07 13:12:44'),
(7, 'SO-000006', 2, '2026-10-07', NULL, 28000.00, 'Amount', 0.00, 0.00, 28000.00, 20000.00, 8000.00, 'Delivered', '2026-10-07 19:49:12', NULL, NULL, 'No', NULL, NULL, 'Active', 1, '2026-10-07 19:42:43', 1, '2026-10-07 19:49:12', '2026-10-07 13:42:43', '2026-10-07 13:49:12'),
(8, 'SO-000007', 3, '2026-10-07', NULL, 28000.00, 'Amount', 0.00, 0.00, 28000.00, 0.00, 28000.00, 'Pending', NULL, NULL, NULL, 'No', NULL, NULL, 'Active', 1, '2026-10-07 19:54:46', NULL, NULL, '2026-10-07 13:54:46', '2026-10-07 13:54:46'),
(9, 'SO-000008', 2, '2026-10-07', NULL, 85797.00, 'Amount', 0.00, 0.00, 85797.00, 80000.00, 5797.00, 'Partial', '2026-10-07 19:59:23', NULL, NULL, 'Yes', 1, NULL, 'Active', 1, '2026-10-07 19:57:25', 1, '2026-10-07 19:59:23', '2026-10-07 13:57:25', '2026-10-07 14:00:32'),
(11, 'SO-000009', 2, '2026-10-07', NULL, 114396.00, 'Amount', 0.00, 0.00, 114396.00, 100000.00, 14396.00, 'Partial', '2026-10-07 20:15:09', NULL, NULL, 'Yes', 1, '2026-10-07', 'Inactive', 1, '2026-10-07 20:14:44', 1, '2026-10-07 20:15:09', '2026-10-07 14:14:44', '2026-10-07 14:16:22');

-- --------------------------------------------------------

--
-- Table structure for table `sale_order_items`
--

CREATE TABLE `sale_order_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `sale_order_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `product_variant_id` bigint(20) UNSIGNED DEFAULT NULL,
  `qty` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `delivery_qty` bigint(20) DEFAULT NULL,
  `sale_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `discount_type` varchar(30) DEFAULT NULL,
  `discount_value` decimal(15,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `subtotal` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `remarks` longtext DEFAULT NULL,
  `deleted` enum('Yes','No') NOT NULL DEFAULT 'No',
  `deleted_by` bigint(20) UNSIGNED DEFAULT NULL,
  `deleted_date` date DEFAULT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_date` datetime DEFAULT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_date` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sale_order_items`
--

INSERT INTO `sale_order_items` (`id`, `sale_order_id`, `product_id`, `product_variant_id`, `qty`, `delivery_qty`, `sale_price`, `discount_type`, `discount_value`, `discount_amount`, `subtotal`, `total`, `remarks`, `deleted`, `deleted_by`, `deleted_date`, `status`, `created_by`, `created_date`, `updated_by`, `updated_date`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 2, 19.0000, 19, 28000.00, 'Amount', 0.00, 0.00, 532000.00, 532000.00, NULL, 'No', NULL, NULL, 'Active', 1, '2026-10-05 17:39:28', NULL, NULL, '2026-10-05 11:39:28', '2026-10-07 13:06:05'),
(2, 2, 1, 2, 1.0000, NULL, 28000.00, 'Amount', 0.00, 0.00, 28000.00, 28000.00, NULL, 'No', NULL, NULL, 'Active', 1, '2026-10-05 17:58:11', NULL, NULL, '2026-10-05 11:58:11', '2026-10-05 11:58:11'),
(3, 2, 3, 4, 1.0000, NULL, 28599.00, 'Amount', 0.00, 0.00, 28599.00, 28599.00, NULL, 'No', NULL, NULL, 'Active', 1, '2026-10-05 17:58:11', NULL, NULL, '2026-10-05 11:58:11', '2026-10-05 11:58:11'),
(4, 3, 1, 1, 1.0000, 1, 28000.00, 'Amount', 0.00, 0.00, 28000.00, 28000.00, NULL, 'No', NULL, NULL, 'Active', 1, '2026-10-05 18:27:48', NULL, NULL, '2026-10-05 12:27:48', '2026-10-07 12:55:20'),
(5, 4, 1, 1, 15.0000, NULL, 28000.00, 'Amount', 0.00, 0.00, 420000.00, 420000.00, NULL, 'No', NULL, NULL, 'Active', 1, '2026-10-07 19:07:51', NULL, NULL, '2026-10-07 13:07:51', '2026-10-07 13:07:51'),
(6, 5, 3, 4, 10.0000, 10, 28599.00, 'Amount', 0.00, 0.00, 285990.00, 285990.00, NULL, 'No', NULL, NULL, 'Active', 1, '2026-10-07 19:10:29', NULL, NULL, '2026-10-07 13:10:29', '2026-10-07 13:12:44'),
(8, 7, 1, 1, 1.0000, 1, 28000.00, 'Amount', 0.00, 0.00, 28000.00, 28000.00, NULL, 'No', NULL, NULL, 'Active', 1, '2026-10-07 19:42:43', NULL, NULL, '2026-10-07 13:42:43', '2026-10-07 13:49:12'),
(9, 8, 1, 1, 1.0000, NULL, 28000.00, 'Amount', 0.00, 0.00, 28000.00, 28000.00, NULL, 'No', NULL, NULL, 'Active', 1, '2026-10-07 19:54:46', NULL, NULL, '2026-10-07 13:54:46', '2026-10-07 13:54:46'),
(10, 9, 3, 4, 3.0000, 2, 28599.00, 'Amount', 0.00, 0.00, 85797.00, 85797.00, NULL, 'Yes', 1, NULL, 'Active', 1, '2026-10-07 19:57:25', NULL, NULL, '2026-10-07 13:57:25', '2026-10-07 14:00:32'),
(11, 11, 3, 4, 4.0000, 2, 28599.00, 'Amount', 0.00, 0.00, 114396.00, 114396.00, NULL, 'Yes', 1, '2026-10-07', 'Inactive', 1, '2026-10-07 20:14:44', NULL, NULL, '2026-10-07 14:14:44', '2026-10-07 14:16:22');

-- --------------------------------------------------------

--
-- Table structure for table `sale_returns`
--

CREATE TABLE `sale_returns` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `return_no` varchar(100) NOT NULL,
  `sale_id` bigint(20) UNSIGNED NOT NULL,
  `customer_id` bigint(20) UNSIGNED DEFAULT NULL,
  `sale_type` enum('Wholesale','Walk-In') NOT NULL,
  `datetime` datetime NOT NULL,
  `subtotal` decimal(15,2) NOT NULL DEFAULT 0.00,
  `grand_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `remarks` longtext DEFAULT NULL,
  `deleted` enum('Yes','No') NOT NULL DEFAULT 'No',
  `deleted_by` bigint(20) UNSIGNED DEFAULT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_date` datetime DEFAULT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_date` datetime DEFAULT NULL,
  `deleted_date` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sale_returns`
--

INSERT INTO `sale_returns` (`id`, `return_no`, `sale_id`, `customer_id`, `sale_type`, `datetime`, `subtotal`, `grand_total`, `remarks`, `deleted`, `deleted_by`, `status`, `created_by`, `created_date`, `updated_by`, `updated_date`, `deleted_date`, `created_at`, `updated_at`) VALUES
(2, 'SR-000001', 6, 4, 'Walk-In', '2026-09-30 00:00:00', 28000.00, 28000.00, NULL, 'No', NULL, 'Active', 1, '2026-09-30 20:26:38', NULL, NULL, NULL, '2026-09-30 14:26:38', '2026-09-30 14:26:38'),
(6, 'SR-000002', 9, 2, 'Wholesale', '2026-10-05 00:00:00', 28000.00, 28000.00, NULL, 'No', NULL, 'Active', 1, '2026-10-05 18:32:39', NULL, NULL, NULL, '2026-10-05 12:32:39', '2026-10-05 12:32:39');

-- --------------------------------------------------------

--
-- Table structure for table `sale_return_items`
--

CREATE TABLE `sale_return_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `sale_return_id` bigint(20) UNSIGNED NOT NULL,
  `sale_id` bigint(20) UNSIGNED NOT NULL,
  `sale_item_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `product_variant_id` bigint(20) UNSIGNED DEFAULT NULL,
  `qty` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `sale_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `subtotal` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `deleted` enum('Yes','No') NOT NULL DEFAULT 'No',
  `deleted_by` bigint(20) UNSIGNED DEFAULT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_date` datetime DEFAULT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_date` datetime DEFAULT NULL,
  `deleted_date` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sale_return_items`
--

INSERT INTO `sale_return_items` (`id`, `sale_return_id`, `sale_id`, `sale_item_id`, `product_id`, `product_variant_id`, `qty`, `sale_price`, `subtotal`, `total`, `deleted`, `deleted_by`, `status`, `created_by`, `created_date`, `updated_by`, `updated_date`, `deleted_date`, `created_at`, `updated_at`) VALUES
(1, 2, 6, 6, 1, 1, 1.0000, 28000.00, 28000.00, 28000.00, 'No', NULL, 'Active', 1, '2026-09-30 20:26:38', NULL, NULL, NULL, '2026-09-30 14:26:38', '2026-09-30 14:26:38'),
(2, 3, 7, 7, 1, 1, 1.0000, 28000.00, 28000.00, 28000.00, 'Yes', 1, 'Active', 1, '2026-10-04 16:21:28', NULL, NULL, '2026-10-04 16:42:58', '2026-10-04 10:21:28', '2026-10-04 10:42:58'),
(3, 6, 9, 9, 1, 1, 1.0000, 28000.00, 28000.00, 28000.00, 'No', NULL, 'Active', 1, '2026-10-05 18:32:39', NULL, NULL, NULL, '2026-10-05 12:32:39', '2026-10-05 12:32:39');

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
  `product_variant_id` int(20) DEFAULT NULL,
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
  `order_id` bigint(20) DEFAULT NULL,
  `order_return_id` bigint(20) DEFAULT NULL,
  `date` datetime NOT NULL,
  `remarks` varchar(500) DEFAULT NULL,
  `deleted` enum('Yes','No') NOT NULL DEFAULT 'No',
  `deleted_date` date DEFAULT NULL,
  `deleted_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_date` datetime DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_date` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `stocks`
--

INSERT INTO `stocks` (`id`, `product_id`, `product_variant_id`, `in_qty`, `out_qty`, `purchase_id`, `sale_id`, `damage_id`, `purchase_return_id`, `sale_return_id`, `adjustment_id`, `reference_type`, `reference_id`, `order_id`, `order_return_id`, `date`, `remarks`, `deleted`, `deleted_date`, `deleted_by`, `created_date`, `created_by`, `status`, `updated_by`, `updated_date`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 5.000, 0.000, NULL, NULL, NULL, NULL, NULL, NULL, 'Opening Stock', NULL, NULL, NULL, '2026-09-22 17:08:18', NULL, 'No', NULL, NULL, '2026-09-22 17:08:18', 1, 'Active', 1, '2026-09-22 17:08:18', NULL, NULL),
(2, 1, 2, 18.000, 0.000, NULL, NULL, NULL, NULL, NULL, NULL, 'Opening Stock', NULL, NULL, NULL, '2026-09-22 17:08:18', NULL, 'No', NULL, NULL, '2026-09-22 17:08:18', 1, 'Active', 1, '2026-09-22 17:08:18', NULL, NULL),
(3, 1, 3, 2.000, 0.000, NULL, NULL, NULL, NULL, NULL, NULL, 'Opening Stock', NULL, NULL, NULL, '2026-09-22 17:08:18', NULL, 'No', NULL, NULL, '2026-09-22 17:08:18', 1, 'Active', 1, '2026-09-22 17:08:18', NULL, NULL),
(4, 2, NULL, 50.000, 0.000, NULL, NULL, NULL, NULL, NULL, NULL, 'Opening Stock', NULL, NULL, NULL, '2026-09-22 17:41:33', NULL, 'No', NULL, NULL, '2026-09-22 17:41:33', 1, 'Active', 1, '2026-09-22 17:41:33', NULL, NULL),
(5, 3, 4, 10.000, 0.000, NULL, NULL, NULL, NULL, NULL, NULL, 'Opening Stock', NULL, NULL, NULL, '2026-09-22 17:58:29', NULL, 'No', NULL, NULL, '2026-09-22 17:58:29', 1, 'Active', 1, '2026-09-22 17:58:29', NULL, NULL),
(6, 3, 5, 15.000, 0.000, NULL, NULL, NULL, NULL, NULL, NULL, 'Opening Stock', NULL, NULL, NULL, '2026-09-22 17:58:29', NULL, 'No', NULL, NULL, '2026-09-22 17:58:29', 1, 'Active', 1, '2026-09-22 17:58:29', NULL, NULL),
(7, 3, 6, 13.000, 0.000, NULL, NULL, NULL, NULL, NULL, NULL, 'Opening Stock', NULL, NULL, NULL, '2026-09-22 17:58:29', NULL, 'No', NULL, NULL, '2026-09-22 17:58:29', 1, 'Active', 1, '2026-09-22 17:58:29', NULL, NULL),
(13, 1, 1, 10.000, 0.000, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-28 00:00:00', NULL, 'Yes', NULL, 1, NULL, NULL, 'Active', 1, '2026-09-28 19:41:13', '2026-09-28 11:05:33', '2026-09-28 13:41:13'),
(14, 3, 4, 10.000, 0.000, 6, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-28 00:00:00', NULL, 'Yes', NULL, 1, NULL, NULL, 'Inactive', 1, '2026-09-28 19:49:01', '2026-09-28 13:48:40', '2026-09-28 13:49:01'),
(15, 1, 1, 13.000, 0.000, 7, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-28 00:00:00', NULL, 'No', NULL, NULL, NULL, NULL, 'Active', NULL, NULL, '2026-09-28 13:49:40', '2026-09-28 13:49:40'),
(16, 2, NULL, 5.000, 0.000, 8, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-28 00:00:00', NULL, 'Yes', NULL, 1, NULL, NULL, 'Inactive', 1, '2026-09-29 16:33:20', '2026-09-28 13:50:15', '2026-09-29 10:33:20'),
(19, 2, NULL, 0.000, 2.000, 8, NULL, NULL, 8, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-28 00:00:00', NULL, 'Yes', NULL, 1, NULL, 1, 'Inactive', 1, '2026-09-29 16:33:20', '2026-09-28 14:23:34', '2026-09-29 10:33:20'),
(20, 2, NULL, 0.000, 2.000, 8, NULL, NULL, 9, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-29 00:00:00', NULL, 'Yes', NULL, 1, NULL, 1, 'Inactive', 1, '2026-09-29 16:33:20', '2026-09-29 09:29:40', '2026-09-29 10:33:20'),
(21, 1, 2, 20.000, 0.000, 9, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-29 00:00:00', NULL, 'Yes', NULL, 1, NULL, NULL, 'Inactive', 1, '2026-09-29 16:31:12', '2026-09-29 10:23:33', '2026-09-29 10:31:12'),
(22, 1, 1, 20.500, 0.000, 10, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-29 00:00:00', NULL, 'Yes', NULL, 1, NULL, NULL, 'Inactive', 1, '2026-09-29 16:31:02', '2026-09-29 10:28:07', '2026-09-29 10:31:02'),
(23, 1, 1, 0.000, 3.000, 7, NULL, NULL, 10, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-30 00:00:00', NULL, 'No', NULL, NULL, NULL, 1, 'Active', NULL, NULL, '2026-09-30 10:45:58', '2026-09-30 10:45:58'),
(29, 1, 1, 0.000, 1.000, NULL, 6, NULL, NULL, NULL, NULL, 'Sale', 6, NULL, NULL, '2026-09-30 00:00:00', 'Sale: INV-000001', 'No', NULL, NULL, '2026-09-30 18:51:18', 1, 'Active', NULL, NULL, NULL, NULL),
(30, 1, 1, 1.000, 0.000, NULL, NULL, NULL, NULL, 2, NULL, 'Sale Return', 2, NULL, NULL, '2026-09-30 00:00:00', 'Return: SR-000001', 'No', NULL, NULL, '2026-09-30 20:26:38', 1, 'Active', NULL, NULL, NULL, NULL),
(31, 1, 1, 0.000, 1.000, NULL, 7, NULL, NULL, NULL, NULL, 'Sale', 7, NULL, NULL, '2026-10-04 00:00:00', 'Sale: INV-000002', 'No', NULL, NULL, '2026-10-04 16:21:02', 1, 'Active', NULL, NULL, NULL, NULL),
(32, 1, 1, 1.000, 0.000, NULL, NULL, NULL, NULL, 3, NULL, 'Sale Return', 3, NULL, NULL, '2026-10-04 00:00:00', 'Return: SR-000002', 'Yes', NULL, 1, '2026-10-04 16:21:28', 1, 'Active', NULL, NULL, NULL, NULL),
(33, 1, 1, 101.000, 0.000, 11, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-10-04 00:00:00', NULL, 'No', NULL, NULL, NULL, NULL, 'Active', NULL, NULL, '2026-10-04 10:46:08', '2026-10-04 10:46:08'),
(34, 2, NULL, 100.000, 0.000, 11, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-10-04 00:00:00', NULL, 'No', NULL, NULL, NULL, NULL, 'Active', NULL, NULL, '2026-10-04 10:46:08', '2026-10-04 10:46:08'),
(35, 3, 5, 100.000, 0.000, 11, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-10-04 00:00:00', NULL, 'No', NULL, NULL, NULL, NULL, 'Active', NULL, NULL, '2026-10-04 10:46:08', '2026-10-04 10:46:08'),
(36, 3, 4, 100.000, 0.000, 11, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-10-04 00:00:00', NULL, 'No', NULL, NULL, NULL, NULL, 'Active', NULL, NULL, '2026-10-04 10:46:08', '2026-10-04 10:46:08'),
(37, 2, NULL, 0.000, 1.000, NULL, 8, NULL, NULL, NULL, NULL, 'Sale', 8, NULL, NULL, '2026-10-04 00:00:00', 'Sale: INV-000003', 'No', NULL, NULL, '2026-10-04 16:47:48', 1, 'Active', NULL, NULL, NULL, NULL),
(38, 1, 1, 0.000, 1.000, NULL, 9, NULL, NULL, NULL, NULL, 'Sale', 9, NULL, NULL, '2026-10-05 00:00:00', 'Sale: INV-000004 (Order SO-000003)', 'No', NULL, NULL, '2026-10-05 18:30:33', 1, 'Active', NULL, NULL, NULL, NULL),
(39, 1, 1, 1.000, 0.000, NULL, NULL, NULL, NULL, 6, NULL, 'Sale Return', 6, NULL, NULL, '2026-10-05 00:00:00', 'Return: SR-000002', 'No', NULL, NULL, '2026-10-05 18:32:39', 1, 'Active', NULL, NULL, NULL, NULL),
(40, 1, 1, 0.000, 1.000, NULL, NULL, NULL, NULL, NULL, NULL, 'Order', 7, 7, NULL, '2026-10-07 19:49:12', 'Order: SO-000006', 'No', NULL, NULL, '2026-10-07 19:49:12', 1, 'Active', NULL, NULL, NULL, NULL),
(41, 3, 4, 0.000, 2.000, NULL, NULL, NULL, NULL, NULL, NULL, 'Order', 9, 9, NULL, '2026-10-07 19:59:23', 'Order: SO-000008', 'No', NULL, NULL, '2026-10-07 19:59:23', 1, 'Active', NULL, NULL, NULL, NULL),
(42, 3, 4, 0.000, 2.000, NULL, NULL, NULL, NULL, NULL, NULL, 'Order', 11, 11, NULL, '2026-10-07 20:15:09', 'Order: SO-000009', 'Yes', '2026-10-07', 1, '2026-10-07 20:15:09', 1, 'Inactive', NULL, NULL, NULL, NULL),
(43, 3, 4, 5.000, 0.000, 12, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-10-09 00:00:00', NULL, 'No', NULL, NULL, NULL, NULL, 'Active', NULL, NULL, '2026-10-09 08:19:28', '2026-10-09 08:19:28'),
(44, 2, NULL, 10.000, 0.000, 13, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-10-09 00:00:00', NULL, 'No', NULL, NULL, NULL, NULL, 'Active', NULL, NULL, '2026-10-09 11:17:16', '2026-10-09 11:17:16'),
(45, 2, NULL, 10.000, 0.000, 14, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-10-09 00:00:00', NULL, 'No', NULL, NULL, NULL, NULL, 'Active', NULL, NULL, '2026-10-09 11:19:51', '2026-10-09 11:19:51'),
(46, 2, NULL, 0.000, 1.000, NULL, 10, NULL, NULL, NULL, NULL, 'Sale', 10, NULL, NULL, '2026-10-09 00:00:00', 'Sale: INV-000005', 'No', NULL, NULL, '2026-10-09 17:26:32', 1, 'Active', NULL, NULL, NULL, NULL),
(47, 1, 1, 0.000, 1.000, NULL, 11, NULL, NULL, NULL, NULL, 'Sale', 11, NULL, NULL, '2026-10-09 00:00:00', 'Sale: INV-000006', 'No', NULL, NULL, '2026-10-09 17:56:08', 1, 'Active', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `company_name` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `contact_person` varchar(255) DEFAULT NULL,
  `opening_balance` decimal(18,2) NOT NULL DEFAULT 0.00,
  `opening_balance_type` enum('Due','Advance') NOT NULL DEFAULT 'Due',
  `remarks` text DEFAULT NULL,
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

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`id`, `name`, `company_name`, `phone`, `email`, `address`, `city`, `country`, `contact_person`, `opening_balance`, `opening_balance_type`, `remarks`, `deleted`, `deleted_by`, `created_date`, `created_by`, `status`, `updated_by`, `updated_date`, `created_at`, `updated_at`) VALUES
(1, 'Selim', 'Selim Wholesale', '01774588569', 'selim@gmail.com', NULL, NULL, NULL, NULL, 0.00, 'Due', NULL, 'No', NULL, NULL, NULL, 'Active', NULL, NULL, NULL, NULL);

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
(2, 'Kg', 'kg', 'No', NULL, '2026-09-16 00:10:07', 1, 'Active', 1, '2026-09-16 00:10:20', NULL, '2026-09-15 12:10:20', '2026-09-15 12:10:07'),
(3, 'Piece', 'piece', 'No', NULL, '2026-09-18 21:49:07', 1, 'Active', NULL, NULL, NULL, '2026-09-18 09:49:07', '2026-09-18 09:49:07');

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
(1, 'uploads/users/1789401688_6aa81a589ad00.jpg', 'Farhan', 'Superadmin', 1, 'Active', 'No', 'admin@gmail.com', NULL, '$2y$12$mcZoP9WCe7p2YBzmE901MOg.ysj87VTyLwxCXbq7zWQfotNEm405m', 'ticTlzzBP5OFpzXqpexHxOBojZmEvMVfPMer6rfPH05Oe6ni1xjKVTbp1bIq', '2026-09-13 10:48:50', '2026-09-14 10:01:28', NULL, 1, NULL, NULL, '2026-09-14 16:01:28', NULL),
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
-- Indexes for table `chart_of_accounts`
--
ALTER TABLE `chart_of_accounts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `employees_employee_code_unique` (`employee_code`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `expenses_expense_no_unique` (`expense_no`);

--
-- Indexes for table `expense_details`
--
ALTER TABLE `expense_details`
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
-- Indexes for table `parties`
--
ALTER TABLE `parties`
  ADD PRIMARY KEY (`id`),
  ADD KEY `parties_type_index` (`type`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`email`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`);

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
-- Indexes for table `product_variants`
--
ALTER TABLE `product_variants`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `attribute_type_id` (`attribute_type_id`),
  ADD KEY `attribute_id` (`attribute_id`),
  ADD KEY `barcode` (`barcode`);

--
-- Indexes for table `purchases`
--
ALTER TABLE `purchases`
  ADD PRIMARY KEY (`id`),
  ADD KEY `purchases_supplier_id_index` (`supplier_id`),
  ADD KEY `purchases_deleted_index` (`deleted`),
  ADD KEY `purchases_status_index` (`status`);

--
-- Indexes for table `purchase_items`
--
ALTER TABLE `purchase_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `purchase_items_purchase_id_index` (`purchase_id`),
  ADD KEY `purchase_items_product_id_index` (`product_id`),
  ADD KEY `purchase_items_variant_id_index` (`product_variant_id`);

--
-- Indexes for table `purchase_returns`
--
ALTER TABLE `purchase_returns`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `purchase_return_items`
--
ALTER TABLE `purchase_return_items`
  ADD PRIMARY KEY (`id`);

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
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sales_invoice_no_unique` (`invoice_no`);

--
-- Indexes for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `sale_orders`
--
ALTER TABLE `sale_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sale_orders_order_no_unique` (`order_no`);

--
-- Indexes for table `sale_order_items`
--
ALTER TABLE `sale_order_items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `sale_returns`
--
ALTER TABLE `sale_returns`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sale_returns_return_no_unique` (`return_no`);

--
-- Indexes for table `sale_return_items`
--
ALTER TABLE `sale_return_items`
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
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `suppliers_name_index` (`name`),
  ADD KEY `suppliers_phone_index` (`phone`),
  ADD KEY `suppliers_deleted_index` (`deleted`),
  ADD KEY `suppliers_status_index` (`status`);

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
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `attribute_types`
--
ALTER TABLE `attribute_types`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `brands`
--
ALTER TABLE `brands`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `chart_of_accounts`
--
ALTER TABLE `chart_of_accounts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `expense_details`
--
ALTER TABLE `expense_details`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `parties`
--
ALTER TABLE `parties`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

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
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `product_attributes`
--
ALTER TABLE `product_attributes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `product_specs`
--
ALTER TABLE `product_specs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `product_variants`
--
ALTER TABLE `product_variants`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `purchases`
--
ALTER TABLE `purchases`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `purchase_items`
--
ALTER TABLE `purchase_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `purchase_returns`
--
ALTER TABLE `purchase_returns`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `purchase_return_items`
--
ALTER TABLE `purchase_return_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

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
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `sale_items`
--
ALTER TABLE `sale_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `sale_orders`
--
ALTER TABLE `sale_orders`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `sale_order_items`
--
ALTER TABLE `sale_order_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `sale_returns`
--
ALTER TABLE `sale_returns`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `sale_return_items`
--
ALTER TABLE `sale_return_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `stocks`
--
ALTER TABLE `stocks`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `units`
--
ALTER TABLE `units`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
