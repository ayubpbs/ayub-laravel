-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 11, 2026 at 05:12 AM
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
-- Database: `ayub_laravel`
--

-- --------------------------------------------------------

--
-- Table structure for table `accounts`
--

CREATE TABLE `accounts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `type` varchar(20) NOT NULL,
  `name` varchar(60) NOT NULL,
  `account_no` varchar(40) DEFAULT NULL,
  `opening_balance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `accounts`
--

INSERT INTO `accounts` (`id`, `type`, `name`, `account_no`, `opening_balance`, `created_at`, `updated_at`) VALUES
(1, 'cash', 'Cash', NULL, 15235.00, '2026-10-07 21:18:35', '2026-10-07 21:36:54'),
(2, 'bkash', 'bKash', NULL, 6252.00, '2026-10-07 21:18:35', '2026-10-07 21:22:59'),
(3, 'nagad', 'Nagad', NULL, 5795.00, '2026-10-07 21:18:35', '2026-10-07 21:24:11'),
(5, 'bank', 'Islami Bank - Ayub', '03302215', 1574.00, '2026-10-07 21:28:28', '2026-10-07 21:37:46'),
(6, 'bank', 'Agrani Bank -Ayub', '20433456', 36780.00, '2026-10-07 21:31:56', '2026-10-07 21:31:56'),
(7, 'bank', 'Agrani Bank - Jonaki', '2049120', 205692.00, '2026-10-07 21:33:56', '2026-10-07 21:33:56'),
(8, 'bank', 'Islami Bank - Jonaki', '06393610', 263562.00, '2026-10-07 21:34:53', '2026-10-07 21:34:53');

-- --------------------------------------------------------

--
-- Table structure for table `account_categories`
--

CREATE TABLE `account_categories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `type` varchar(10) NOT NULL,
  `name` varchar(60) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `account_categories`
--

INSERT INTO `account_categories` (`id`, `type`, `name`, `created_at`, `updated_at`) VALUES
(1, 'income', 'SteadFast', '2026-10-09 03:00:15', '2026-10-09 03:00:15'),
(2, 'expense', 'House Rent', '2026-10-09 03:00:31', '2026-10-09 03:00:31'),
(3, 'expense', 'Salary', '2026-10-09 03:00:43', '2026-10-09 03:00:43'),
(4, 'income', 'Gift-h', '2026-10-09 03:18:39', '2026-10-09 03:18:39'),
(5, 'expense', 'Supplier purchase', '2026-10-09 03:36:10', '2026-10-09 03:36:10'),
(6, 'income', 'Bkash Advance', '2026-10-09 04:01:15', '2026-10-09 04:01:15'),
(7, 'income', 'Nagad advance', '2026-10-09 04:01:25', '2026-10-09 04:01:25'),
(8, 'expense', 'বাসার বাাজার', '2026-10-09 04:07:17', '2026-10-09 04:07:17');

-- --------------------------------------------------------

--
-- Table structure for table `account_transactions`
--

CREATE TABLE `account_transactions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `account_id` bigint(20) UNSIGNED NOT NULL,
  `direction` varchar(3) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `txn_date` date NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `category_id` bigint(20) UNSIGNED DEFAULT NULL,
  `source` varchar(30) NOT NULL DEFAULT 'manual',
  `transfer_ref` varchar(36) DEFAULT NULL,
  `payment_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `account_transactions`
--

INSERT INTO `account_transactions` (`id`, `account_id`, `direction`, `amount`, `txn_date`, `note`, `category_id`, `source`, `transfer_ref`, `payment_id`, `created_at`, `updated_at`) VALUES
(1, 1, 'in', 5000.00, '2026-10-08', 'hamim', 4, 'income', NULL, NULL, '2026-10-09 03:20:03', '2026-10-09 03:20:03'),
(2, 8, 'out', 50010.00, '2026-10-09', 'Paid to Parcel International for TOMZN', 5, 'expense', NULL, NULL, '2026-10-09 03:38:15', '2026-10-09 03:57:42'),
(3, 7, 'out', 50010.00, '2026-10-09', 'Transfer to Islami Bank - Ayub — celfin limit', NULL, 'transfer', '38b0686c-6fd2-4d6a-a633-c259da7bebd2', NULL, '2026-10-09 03:48:00', '2026-10-09 04:17:05'),
(4, 5, 'in', 50010.00, '2026-10-09', 'Transfer from Agrani Bank - Jonaki — celfin limit', NULL, 'transfer', '38b0686c-6fd2-4d6a-a633-c259da7bebd2', NULL, '2026-10-09 03:48:00', '2026-10-09 04:17:05'),
(5, 5, 'out', 47669.00, '2026-10-09', 'Paid to Parcel International for TOMZN', 5, 'expense', NULL, NULL, '2026-10-09 03:49:11', '2026-10-09 03:56:50'),
(6, 2, 'in', 100.00, '2026-10-09', '01846728368', 6, 'income', NULL, NULL, '2026-10-09 04:02:22', '2026-10-09 04:02:22'),
(7, 2, 'in', 80.00, '2026-10-08', '01993880743', 6, 'income', NULL, NULL, '2026-10-09 04:03:02', '2026-10-09 04:03:02'),
(8, 1, 'out', 390.00, '2026-10-09', 'cash থেকে খরচ', 8, 'expense', NULL, NULL, '2026-10-09 04:08:53', '2026-10-09 04:08:53'),
(9, 2, 'in', 100.00, '2026-10-09', '01714', 6, 'income', NULL, NULL, '2026-10-09 06:27:47', '2026-10-09 06:27:47'),
(10, 8, 'out', 25010.00, '2026-10-10', 'CC Camera Purchase', 5, 'expense', NULL, NULL, '2026-10-10 02:06:58', '2026-10-10 02:06:58'),
(11, 2, 'in', 500.00, '2026-10-10', 'customer advance', 6, 'income', NULL, NULL, '2026-10-10 09:36:29', '2026-10-10 09:36:29');

-- --------------------------------------------------------

--
-- Table structure for table `admin_notifications`
--

CREATE TABLE `admin_notifications` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `type` varchar(30) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` varchar(500) DEFAULT NULL,
  `order_id` bigint(20) UNSIGNED DEFAULT NULL,
  `product_id` bigint(20) UNSIGNED DEFAULT NULL,
  `url` varchar(255) DEFAULT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_notifications`
--

INSERT INTO `admin_notifications` (`id`, `type`, `title`, `message`, `order_id`, `product_id`, `url`, `read_at`, `created_at`, `updated_at`) VALUES
(1, 'new_order', 'New order #1', 'Jonaki (01755293333) — ৳ 2,260', 1, NULL, '/admin/orders/1', NULL, '2026-10-07 21:15:58', '2026-10-07 21:15:58'),
(2, 'new_order', 'New order #2', 'Jonaki (01755293333) — ৳ 6,660', 2, NULL, '/admin/orders/2', NULL, '2026-10-10 03:03:22', '2026-10-10 03:03:22'),
(3, 'new_order', 'New order #3', 'Jonaki (01724604020) — ৳ 1,280', 3, NULL, '/admin/orders/3', NULL, '2026-10-10 09:48:59', '2026-10-10 09:48:59'),
(4, 'payment', 'Payment received: ৳ 2,260 — order #1', 'Cash on delivery (courier) · Jonaki', 1, NULL, '/admin/orders/1', NULL, '2026-10-10 09:51:15', '2026-10-10 09:51:15'),
(5, 'payment', 'Payment received: ৳ 6,660 — order #2', 'Cash on delivery (courier) · Jonaki', 2, NULL, '/admin/orders/2', NULL, '2026-10-10 09:51:15', '2026-10-10 09:51:15'),
(6, 'payment', 'Payment received: ৳ 1,280 — order #3', 'Cash on delivery (courier) · Jonaki', 3, NULL, '/admin/orders/3', NULL, '2026-10-10 09:51:15', '2026-10-10 09:51:15'),
(7, 'new_order', 'New order #4', 'MD AYUB HOSSAIN (01711302759) — ৳ 4,210', 4, NULL, '/admin/orders/4', NULL, '2026-10-10 20:44:53', '2026-10-10 20:44:53');

-- --------------------------------------------------------

--
-- Table structure for table `brands`
--

CREATE TABLE `brands` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `sort_order` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `brands`
--

INSERT INTO `brands` (`id`, `name`, `logo`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 'Ayub Tech', NULL, 1, '2026-10-07 13:13:17', '2026-10-07 13:13:17'),
(2, 'Premium', NULL, 2, '2026-10-07 13:13:17', '2026-10-07 13:13:17'),
(3, 'JIUJI', NULL, 0, '2026-10-07 13:17:13', '2026-10-07 13:17:13'),
(4, 'China', NULL, 0, '2026-10-10 04:12:10', '2026-10-10 04:12:10');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `parent_id` bigint(20) UNSIGNED DEFAULT NULL,
  `label` varchar(255) NOT NULL,
  `icon` varchar(255) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `sort_order` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `parent_id`, `label`, `icon`, `image`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, NULL, 'Lamps & Lights', '💡', NULL, 1, '2026-10-07 12:52:17', '2026-10-07 12:52:17'),
(2, NULL, 'Fans', '🌀', NULL, 2, '2026-10-07 12:52:17', '2026-10-07 12:52:17'),
(3, NULL, 'Switches & Sockets', '🔌', NULL, 3, '2026-10-07 12:52:17', '2026-10-07 12:52:17'),
(4, NULL, 'Wires & Cables', '🧵', NULL, 4, '2026-10-07 12:52:17', '2026-10-07 12:52:17'),
(5, NULL, 'Smart Devices', '📡', NULL, 5, '2026-10-07 12:52:17', '2026-10-07 12:52:17'),
(6, NULL, 'Tools', '🔧', NULL, 6, '2026-10-07 12:52:17', '2026-10-07 12:52:17'),
(7, NULL, 'Smart Home', NULL, NULL, 0, '2026-10-07 13:16:49', '2026-10-07 13:16:49'),
(8, 7, 'Smart WiFi Circuit Breaker', NULL, NULL, 1, '2026-10-09 22:25:45', '2026-10-09 22:25:45'),
(9, 7, 'Smart Switch', NULL, NULL, 2, '2026-10-09 22:25:45', '2026-10-09 22:25:45'),
(10, 7, 'Smart Plug', NULL, NULL, 3, '2026-10-09 22:25:45', '2026-10-09 22:25:45'),
(11, 7, 'Smart Door Lock', NULL, NULL, 4, '2026-10-09 22:25:45', '2026-10-09 22:25:45'),
(12, 7, 'Smart Sensor', NULL, NULL, 5, '2026-10-09 22:25:45', '2026-10-09 22:25:45'),
(13, 7, 'Smart Home Accessories', NULL, NULL, 6, '2026-10-09 22:25:45', '2026-10-09 22:25:45'),
(14, NULL, 'Electrical', NULL, NULL, 7, '2026-10-09 22:25:45', '2026-10-09 22:25:45'),
(15, 14, 'MCB', NULL, NULL, 1, '2026-10-09 22:25:45', '2026-10-09 22:25:45'),
(16, 14, 'RCCB', NULL, NULL, 2, '2026-10-09 22:25:45', '2026-10-09 22:25:45'),
(17, 14, 'RCBO', NULL, NULL, 3, '2026-10-09 22:25:45', '2026-10-09 22:25:45'),
(18, 14, 'MCCB', NULL, NULL, 4, '2026-10-09 22:25:45', '2026-10-09 22:25:45'),
(19, 14, 'AFDD', NULL, NULL, 5, '2026-10-09 22:25:45', '2026-10-09 22:25:45'),
(20, 14, 'SPD / Surge Protector', NULL, NULL, 6, '2026-10-09 22:25:45', '2026-10-09 22:25:45'),
(21, 14, 'Voltage Protector', NULL, NULL, 7, '2026-10-09 22:25:45', '2026-10-09 22:25:45'),
(22, 14, 'Changeover Switch', NULL, NULL, 8, '2026-10-09 22:25:45', '2026-10-09 22:25:45'),
(23, 14, 'ATS', NULL, NULL, 9, '2026-10-09 22:25:45', '2026-10-09 22:25:45'),
(24, 14, 'Electrical Accessories', NULL, NULL, 10, '2026-10-09 22:25:45', '2026-10-09 22:25:45'),
(25, NULL, 'Solar', NULL, NULL, 8, '2026-10-09 22:25:45', '2026-10-09 22:25:45'),
(26, 25, 'Solar Panel', NULL, NULL, 1, '2026-10-09 22:25:45', '2026-10-09 22:25:45'),
(27, 25, 'Solar Inverter', NULL, NULL, 2, '2026-10-09 22:25:45', '2026-10-09 22:25:45'),
(28, 25, 'Hybrid Inverter', NULL, NULL, 3, '2026-10-09 22:25:45', '2026-10-09 22:25:45'),
(29, 25, 'Solar Charge Controller', NULL, NULL, 4, '2026-10-09 22:25:45', '2026-10-09 22:25:45'),
(30, 25, 'Solar Battery', NULL, NULL, 5, '2026-10-09 22:25:45', '2026-10-09 22:25:45'),
(31, 25, 'Solar Accessories', NULL, NULL, 6, '2026-10-09 22:25:45', '2026-10-09 22:25:45'),
(32, NULL, 'Gadgets', NULL, NULL, 9, '2026-10-09 22:25:45', '2026-10-09 22:25:45'),
(33, 32, 'Mobile Accessories', NULL, NULL, 1, '2026-10-09 22:25:45', '2026-10-09 22:25:45'),
(34, 32, 'Memory Card', NULL, NULL, 2, '2026-10-09 22:25:45', '2026-10-09 22:25:45'),
(35, 32, 'USB Gadgets', NULL, NULL, 3, '2026-10-09 22:25:45', '2026-10-09 22:25:45'),
(36, 32, 'Computer Accessories', NULL, NULL, 4, '2026-10-09 22:25:45', '2026-10-09 22:25:45'),
(37, 32, 'Electronic Gadgets', NULL, NULL, 5, '2026-10-09 22:25:45', '2026-10-09 22:25:45'),
(38, 32, 'Other Accessories', NULL, NULL, 6, '2026-10-09 22:25:45', '2026-10-09 22:25:45'),
(39, NULL, 'CC Camera', NULL, NULL, 10, '2026-10-09 22:25:45', '2026-10-09 22:25:45'),
(40, 39, 'WiFi Camera', NULL, NULL, 1, '2026-10-09 22:25:45', '2026-10-09 22:25:45'),
(41, 39, 'IP Camera', NULL, NULL, 2, '2026-10-09 22:25:45', '2026-10-09 22:25:45'),
(42, 39, 'Bullet Camera', NULL, NULL, 3, '2026-10-09 22:25:45', '2026-10-09 22:25:45'),
(43, 39, 'Dome Camera', NULL, NULL, 4, '2026-10-09 22:25:45', '2026-10-09 22:25:45'),
(44, 39, 'Solar Camera', NULL, NULL, 5, '2026-10-09 22:25:45', '2026-10-09 22:25:45'),
(45, 39, 'CCTV Accessories', NULL, NULL, 6, '2026-10-09 22:25:45', '2026-10-09 22:25:45'),
(46, 39, 'Memory Card for Camera', NULL, NULL, 7, '2026-10-09 22:25:45', '2026-10-09 22:25:45');

-- --------------------------------------------------------

--
-- Table structure for table `coupons`
--

CREATE TABLE `coupons` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `code` varchar(30) NOT NULL,
  `type` varchar(10) NOT NULL DEFAULT 'percent',
  `value` int(10) UNSIGNED NOT NULL,
  `min_order` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `max_discount` int(10) UNSIGNED DEFAULT NULL,
  `usage_limit` int(10) UNSIGNED DEFAULT NULL,
  `used_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `starts_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `note` varchar(150) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `coupons`
--

INSERT INTO `coupons` (`id`, `code`, `type`, `value`, `min_order`, `max_discount`, `usage_limit`, `used_count`, `starts_at`, `expires_at`, `is_active`, `note`, `created_at`, `updated_at`) VALUES
(1, 'SAVE40', 'fixed', 40, 0, NULL, NULL, 1, NULL, NULL, 1, NULL, '2026-10-10 20:38:22', '2026-10-10 20:44:53');

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
-- Table structure for table `invoices`
--

CREATE TABLE `invoices` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `issued_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `invoices`
--

INSERT INTO `invoices` (`id`, `order_id`, `issued_at`, `note`, `created_at`, `updated_at`) VALUES
(1, 2, '2026-10-10 09:46:40', NULL, '2026-10-10 09:46:40', '2026-10-10 09:46:40');

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
(2, '2014_10_12_100000_create_password_resets_table', 1),
(3, '2019_08_19_000000_create_failed_jobs_table', 1),
(4, '2019_12_14_000001_create_personal_access_tokens_table', 1),
(5, '2026_10_07_034800_create_categories_table', 1),
(6, '2026_10_07_034846_create_products_table', 1),
(7, '2026_10_07_040000_create_orders_table', 1),
(8, '2026_10_07_040100_create_order_items_table', 1),
(9, '2026_10_08_000001_add_is_admin_to_users_table', 1),
(10, '2026_10_08_000000_update_order_statuses', 2),
(11, '2026_10_08_010000_create_brands_table', 2),
(12, '2026_10_08_020000_add_courier_columns_to_orders', 2),
(13, '2026_10_08_030000_create_payments_table', 2),
(14, '2026_10_08_040000_create_invoices_table', 2),
(15, '2026_10_10_000000_create_admin_notifications_table', 2),
(16, '2026_10_11_000000_create_settings_table', 2),
(17, '2026_10_12_000000_create_accounts_tables', 3),
(18, '2026_10_12_010000_allow_many_bank_accounts', 4),
(19, '2026_10_13_000000_create_account_categories_table', 5),
(20, '2026_10_13_010000_add_category_id_to_account_transactions', 6),
(21, '2026_10_13_020000_add_transfer_ref_to_account_transactions', 7),
(22, '2026_10_14_000000_add_payment_id_to_account_transactions', 8),
(23, '2026_10_15_000000_add_parent_id_to_categories_table', 9),
(24, '2026_10_16_000000_create_product_images_table', 10),
(25, '2026_10_17_000000_create_product_reviews_table', 11),
(26, '2026_10_18_000000_add_video_urls_to_products_table', 12),
(27, '2026_10_19_000000_add_specifications_to_products_table', 13),
(28, '2026_10_20_000000_add_sku_to_products_table', 14),
(29, '2026_10_21_000000_add_basic_info_fields_to_products_table', 15),
(30, '2026_10_22_000000_create_coupons_table', 16),
(31, '2026_10_22_010000_add_checkout_fields_to_orders_table', 16);

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `district` varchar(60) DEFAULT NULL,
  `upazila` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `address` text NOT NULL,
  `note` text DEFAULT NULL,
  `subtotal` int(10) UNSIGNED NOT NULL,
  `coupon_code` varchar(30) DEFAULT NULL,
  `discount` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `delivery_charge` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `total` int(10) UNSIGNED NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'pending',
  `courier` varchar(255) DEFAULT NULL,
  `consignment_id` varchar(255) DEFAULT NULL,
  `tracking_code` varchar(255) DEFAULT NULL,
  `courier_status` varchar(255) DEFAULT NULL,
  `courier_sent_at` timestamp NULL DEFAULT NULL,
  `courier_checked_at` timestamp NULL DEFAULT NULL,
  `return_requested_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `name`, `phone`, `district`, `upazila`, `city`, `address`, `note`, `subtotal`, `coupon_code`, `discount`, `delivery_charge`, `total`, `status`, `courier`, `consignment_id`, `tracking_code`, `courier_status`, `courier_sent_at`, `courier_checked_at`, `return_requested_at`, `created_at`, `updated_at`) VALUES
(1, 'Jonaki', '01755293333', NULL, NULL, NULL, 'asdf', 'asfd', 2200, NULL, 0, 60, 2260, 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-10-07 21:15:58', '2026-10-07 21:15:58'),
(2, 'Jonaki', '01755293333', NULL, NULL, NULL, 'asdf', NULL, 6600, NULL, 0, 60, 6660, 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-10-10 03:03:22', '2026-10-10 03:03:22'),
(3, 'Jonaki', '01724604020', NULL, NULL, NULL, 'Mawna, Sreepur, Gazipur.', NULL, 1220, NULL, 0, 60, 1280, 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-10-10 09:48:59', '2026-10-10 09:48:59'),
(4, 'MD AYUB HOSSAIN', '01711302759', 'Gazipur', 'Kaliakair', 'Gazipur', 'Mawna Pear Ali College Gate, Mawna, Sreepur, Gazipur., Gazipur, Kaliakair, Gazipur', NULL, 4150, 'SAVE40', 40, 100, 4210, 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-10-10 20:44:53', '2026-10-10 20:44:53');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `price` int(10) UNSIGNED NOT NULL,
  `quantity` int(10) UNSIGNED NOT NULL,
  `line_total` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `title`, `price`, `quantity`, `line_total`, `created_at`, `updated_at`) VALUES
(1, 1, 19, 'Jiuji Multi-Function Protector-63 LWS WiFi (ওয়াই-ফাই ভোল্টেজ প্রটেক্টর)', 2200, 1, 2200, '2026-10-07 21:15:58', '2026-10-07 21:15:58'),
(2, 2, 19, 'Jiuji Multi-Function Protector-63 LWS WiFi (ওয়াই-ফাই ভোল্টেজ প্রটেক্টর)', 2200, 3, 6600, '2026-10-10 03:03:22', '2026-10-10 03:03:22'),
(3, 3, 21, 'Single Lens Bulb CC Camera Sulink App Support', 1220, 1, 1220, '2026-10-10 09:48:59', '2026-10-10 09:48:59'),
(4, 4, 20, 'V380 E9 Dual Lens Bulb PTZ IP Camera- White Color', 1950, 1, 1950, '2026-10-10 20:44:53', '2026-10-10 20:44:53'),
(5, 4, 19, 'Jiuji Multi-Function Protector-63 LWS WiFi (ওয়াই-ফাই ভোল্টেজ প্রটেক্টর)', 2200, 1, 2200, '2026-10-10 20:44:53', '2026-10-10 20:44:53');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
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
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `type` varchar(255) NOT NULL DEFAULT 'payment',
  `method` varchar(255) NOT NULL,
  `amount` int(10) UNSIGNED NOT NULL,
  `trx_id` varchar(255) DEFAULT NULL,
  `sender_number` varchar(20) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `paid_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `order_id`, `type`, `method`, `amount`, `trx_id`, `sender_number`, `note`, `paid_at`, `created_at`, `updated_at`) VALUES
(1, 1, 'payment', 'cod', 2260, NULL, NULL, 'Cash on delivery collected', '2026-10-10 09:51:15', '2026-10-10 09:51:15', '2026-10-10 09:51:15'),
(2, 2, 'payment', 'cod', 6660, NULL, NULL, 'Cash on delivery collected', '2026-10-10 09:51:15', '2026-10-10 09:51:15', '2026-10-10 09:51:15'),
(3, 3, 'payment', 'cod', 1280, NULL, NULL, 'Cash on delivery collected', '2026-10-10 09:51:15', '2026-10-10 09:51:15', '2026-10-10 09:51:15');

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
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `category_id` bigint(20) UNSIGNED DEFAULT NULL,
  `brand_id` bigint(20) UNSIGNED DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `brand` varchar(255) DEFAULT NULL,
  `description` mediumtext DEFAULT NULL,
  `icon` varchar(255) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `price` int(10) UNSIGNED NOT NULL,
  `old_price` int(10) UNSIGNED DEFAULT NULL,
  `badge` varchar(255) DEFAULT NULL,
  `rating` decimal(2,1) NOT NULL DEFAULT 0.0,
  `reviews` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `section` varchar(255) DEFAULT NULL,
  `stock_quantity` int(11) NOT NULL DEFAULT 0,
  `is_upcoming` tinyint(1) NOT NULL DEFAULT 0,
  `is_published` tinyint(1) NOT NULL DEFAULT 1,
  `published_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `video_urls` text DEFAULT NULL,
  `specifications` text DEFAULT NULL,
  `sku` varchar(60) DEFAULT NULL,
  `slug` varchar(190) DEFAULT NULL,
  `short_description` text DEFAULT NULL,
  `product_type` varchar(20) NOT NULL DEFAULT 'simple'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `category_id`, `brand_id`, `title`, `brand`, `description`, `icon`, `image`, `price`, `old_price`, `badge`, `rating`, `reviews`, `section`, `stock_quantity`, `is_upcoming`, `is_published`, `published_at`, `created_at`, `updated_at`, `video_urls`, `specifications`, `sku`, `slug`, `short_description`, `product_type`) VALUES
(1, 1, 1, 'LED Bulb 9W', 'Ayub Tech', 'This is a sample product. Real descriptions will appear here once you add them from the admin panel.', '💡', NULL, 120, 150, '-20%', 4.5, 38, 'top', 50, 0, 1, '2026-09-19 12:52:17', '2026-10-07 12:52:17', '2026-10-07 12:52:17', NULL, NULL, NULL, 'led-bulb-9w', NULL, 'simple'),
(2, 1, 1, 'LED Tube Light 18W', 'Ayub Tech', 'This is a sample product. Real descriptions will appear here once you add them from the admin panel.', '💡', NULL, 380, NULL, 'New', 4.0, 12, 'new', 30, 0, 1, '2026-09-20 12:52:17', '2026-10-07 12:52:17', '2026-10-07 12:52:17', NULL, NULL, NULL, 'led-tube-light-18w', NULL, 'simple'),
(3, 1, 2, 'Solar Garden Light', 'Premium', 'This is a sample product. Real descriptions will appear here once you add them from the admin panel.', '💡', NULL, 650, 800, '-19%', 4.2, 9, NULL, 15, 0, 1, '2026-09-21 12:52:17', '2026-10-07 12:52:17', '2026-10-07 12:52:17', NULL, NULL, NULL, 'solar-garden-light', NULL, 'simple'),
(4, 2, 2, 'Ceiling Fan 56\"', 'Premium', 'This is a sample product. Real descriptions will appear here once you add them from the admin panel.', '🌀', NULL, 3200, 3600, '-11%', 4.6, 74, 'top', 20, 0, 1, '2026-09-22 12:52:17', '2026-10-07 12:52:17', '2026-10-07 12:52:17', NULL, NULL, NULL, 'ceiling-fan-56', NULL, 'simple'),
(5, 2, 1, 'Exhaust Fan 8\"', 'Ayub Tech', 'This is a sample product. Real descriptions will appear here once you add them from the admin panel.', '🌀', NULL, 1150, NULL, NULL, 4.1, 21, NULL, 12, 0, 1, '2026-09-23 12:52:17', '2026-10-07 12:52:17', '2026-10-07 12:52:17', NULL, NULL, NULL, 'exhaust-fan-8', NULL, 'simple'),
(6, 2, 2, 'Table Fan 12\"', 'Premium', 'This is a sample product. Real descriptions will appear here once you add them from the admin panel.', '🌀', NULL, 2450, NULL, 'New', 4.3, 17, 'new', 0, 0, 1, '2026-09-24 12:52:17', '2026-10-07 12:52:17', '2026-10-07 12:52:17', NULL, NULL, NULL, 'table-fan-12', NULL, 'simple'),
(7, 3, 1, 'Multi Plug 5 Socket', 'Ayub Tech', 'This is a sample product. Real descriptions will appear here once you add them from the admin panel.', '🔌', NULL, 450, 520, '-13%', 4.4, 55, 'top', 80, 0, 1, '2026-09-25 12:52:17', '2026-10-07 12:52:17', '2026-10-07 12:52:17', NULL, NULL, NULL, 'multi-plug-5-socket', NULL, 'simple'),
(8, 3, 2, 'Touch Switch 2 Gang', 'Premium', 'This is a sample product. Real descriptions will appear here once you add them from the admin panel.', '🔌', NULL, 890, NULL, 'New', 4.0, 6, 'new', 25, 0, 1, '2026-09-26 12:52:17', '2026-10-07 12:52:17', '2026-10-07 12:52:17', NULL, NULL, NULL, 'touch-switch-2-gang', NULL, 'simple'),
(9, 3, 1, 'Switch Board 6 inch', 'Ayub Tech', 'This is a sample product. Real descriptions will appear here once you add them from the admin panel.', '🔌', NULL, 180, NULL, NULL, 4.2, 30, NULL, 60, 0, 1, '2026-09-27 12:52:17', '2026-10-07 12:52:17', '2026-10-07 12:52:17', NULL, NULL, NULL, 'switch-board-6-inch', NULL, 'simple'),
(10, 4, 1, 'Cable 3/22 (1 Coil)', 'Ayub Tech', 'This is a sample product. Real descriptions will appear here once you add them from the admin panel.', '🧵', NULL, 1650, 1800, '-8%', 4.5, 45, 'top', 40, 0, 1, '2026-09-28 12:52:17', '2026-10-07 12:52:17', '2026-10-07 12:52:17', NULL, NULL, NULL, 'cable-3-22-1-coil', NULL, 'simple'),
(11, 4, 2, 'Extension Cord 5m', 'Premium', 'This is a sample product. Real descriptions will appear here once you add them from the admin panel.', '🧵', NULL, 520, NULL, NULL, 4.1, 19, NULL, 35, 0, 1, '2026-09-29 12:52:17', '2026-10-07 12:52:17', '2026-10-07 12:52:17', NULL, NULL, NULL, 'extension-cord-5m', NULL, 'simple'),
(12, 4, 1, 'HDMI Cable 2m', 'Ayub Tech', 'This is a sample product. Real descriptions will appear here once you add them from the admin panel.', '🧵', NULL, 350, NULL, 'New', 4.0, 8, 'new', 45, 0, 1, '2026-09-30 12:52:17', '2026-10-07 12:52:17', '2026-10-07 12:52:17', NULL, NULL, NULL, 'hdmi-cable-2m', NULL, 'simple'),
(13, 5, 2, 'Smart WiFi Plug', 'Premium', 'This is a sample product. Real descriptions will appear here once you add them from the admin panel.', '📡', NULL, 990, 1200, '-18%', 4.3, 27, 'top', 22, 0, 1, '2026-10-01 12:52:17', '2026-10-07 12:52:17', '2026-10-07 12:52:17', NULL, NULL, NULL, 'smart-wifi-plug', NULL, 'simple'),
(14, 5, 2, 'Smart LED Strip 5m', 'Premium', 'This is a sample product. Real descriptions will appear here once you add them from the admin panel.', '📡', NULL, 1450, NULL, 'New', 4.2, 11, 'new', 18, 0, 1, '2026-10-02 12:52:17', '2026-10-07 12:52:17', '2026-10-07 12:52:17', NULL, NULL, NULL, 'smart-led-strip-5m', NULL, 'simple'),
(15, 5, 2, 'Smart Door Sensor', 'Premium', 'This is a sample product. Real descriptions will appear here once you add them from the admin panel.', '📡', NULL, 1250, NULL, NULL, 0.0, 0, NULL, 0, 1, 1, '2026-10-03 12:52:17', '2026-10-07 12:52:17', '2026-10-07 12:52:17', NULL, NULL, NULL, 'smart-door-sensor', NULL, 'simple'),
(16, 6, 1, 'Digital Multimeter', 'Ayub Tech', 'This is a sample product. Real descriptions will appear here once you add them from the admin panel.', '🔧', NULL, 1350, 1500, '-10%', 4.7, 92, 'top', 28, 0, 1, '2026-10-04 12:52:17', '2026-10-07 12:52:17', '2026-10-07 12:52:17', NULL, NULL, NULL, 'digital-multimeter', NULL, 'simple'),
(17, 6, 1, 'Soldering Iron 60W', 'Ayub Tech', 'This is a sample product. Real descriptions will appear here once you add them from the admin panel.', '🔧', NULL, 480, NULL, NULL, 4.3, 33, NULL, 40, 0, 1, '2026-10-05 12:52:17', '2026-10-07 12:52:17', '2026-10-07 12:52:17', NULL, NULL, NULL, 'soldering-iron-60w', NULL, 'simple'),
(18, 6, 2, 'Wire Stripper', 'Premium', 'This is a sample product. Real descriptions will appear here once you add them from the admin panel.', '🔧', NULL, 420, NULL, 'New', 4.1, 10, 'new', 30, 0, 1, '2026-10-06 12:52:17', '2026-10-07 12:52:17', '2026-10-07 12:52:17', NULL, NULL, NULL, 'wire-stripper', NULL, 'simple'),
(19, 7, 3, 'Jiuji Multi-Function Protector-63 LWS WiFi (ওয়াই-ফাই ভোল্টেজ প্রটেক্টর)', 'JIUJI', '✅ওভার ভোল্টেজ-আন্ডার ভোল্টেজ সুরক্ষা।\r\n\r\n✅ওভার কারেন্ট সুরক্ষা। \r\n\r\n✅ লিকেজ হতে বা সক হতে সুরক্ষা\r\n\r\n✅টাইম সেট করার সুবিধা\r\n\r\n✅ WiFi 2.4 Hz\r\n\r\n✅ ফেজ: সিঙ্গেল ফেজ\r\n\r\n✅ কারেন্ট টাইপ: এসি \r\n\r\n✅ ব্র্যান্ডের নাম জিউজি\r\n\r\n✅ মডেল নম্বর JJMVPD-63LWS\r\n\r\n✅ কারেন্ট 1-63A\r\n\r\n✅ ডিসপ্লে LED ডিসপ্লে\r\n\r\n ✅ দক্ষতা উচ্চ দক্ষতা\r\n\r\n✅ ফ্রিকোয়েন্সি 50 Hz \r\n\r\n✅ ১ বছরের রিপ্লেসমেন্ট গ্যারান্টি', NULL, '5jABlzkk65FvNlTMZmcrkDiE.jpg', 2200, 2450, NULL, 0.0, 0, 'top', 20, 0, 1, '2026-10-07 13:18:56', '2026-10-07 13:18:56', '2026-10-10 20:44:53', NULL, NULL, NULL, 'jiuji-multi-function-protector-63-lws-wifi-ওয়াই-ফাই-ভোল্টেজ-প্রটেক্টর', NULL, 'simple'),
(20, 40, 2, 'V380 E9 Dual Lens Bulb PTZ IP Camera- White Color', 'Premium', 'মডেল: V380 E9\r\n\r\n✅ ক্যামেরার ধরন: Dual Lens Bulb PTZ IP Camera\r\n\r\n✅ লেন্স: ২টি লেন্স\r\n\r\n✅ প্যানোরামা ভিউ: 360° Panorama\r\n\r\n✅ রেজোলিউশন: Full HD 1080P', NULL, '2gvEgn0uNCrmEnQoDB0V5kUL.jpg', 1950, 2450, 'WiFi', 5.0, 1, 'top', 9, 0, 1, '2026-10-09 22:34:06', '2026-10-09 22:34:06', '2026-10-10 20:44:53', 'https://www.youtube.com/watch?v=KlzSxYabwKM\nhttps://www.youtube.com/watch?v=NRXcxqDh-CE', NULL, NULL, 'v380-e9-dual-lens-bulb-ptz-ip-camera-white-color', NULL, 'simple'),
(21, 40, NULL, 'Single Lens Bulb CC Camera Sulink App Support', NULL, '<p style=\"font-family: SolaimanLipi, sans-serif; margin: 0px 0px 12px; color: rgb(51, 51, 51); font-size: 15px; font-style: normal; font-weight: 400; letter-spacing: normal; text-align: start; text-indent: 0px; background-color: rgb(255, 255, 255)\"><strong>📋 ক্যামেরার বৈশিষ্ট্য ও স্পেসিফিকেশন</strong></p>\r\n<p style=\"font-family: SolaimanLipi, sans-serif; margin: 0px 0px 12px; color: rgb(51, 51, 51); font-size: 15px; font-style: normal; font-weight: 400; letter-spacing: normal; text-align: start; text-indent: 0px; background-color: rgb(255, 255, 255)\">✅ রেজোলিউশন: 1080P Full HD</p>\r\n<p style=\"font-family: SolaimanLipi, sans-serif; margin: 0px 0px 12px; color: rgb(51, 51, 51); font-size: 15px; font-style: normal; font-weight: 400; letter-spacing: normal; text-align: start; text-indent: 0px; background-color: rgb(255, 255, 255)\">✅ ভিউয়িং অ্যাঙ্গেল: 360°</p>\r\n<p style=\"font-family: SolaimanLipi, sans-serif; margin: 0px 0px 12px; color: rgb(51, 51, 51); font-size: 15px; font-style: normal; font-weight: 400; letter-spacing: normal; text-align: start; text-indent: 0px; background-color: rgb(255, 255, 255)\">✅ নাইট ভিশন: আছে</p>\r\n<p style=\"font-family: SolaimanLipi, sans-serif; margin: 0px 0px 12px; color: rgb(51, 51, 51); font-size: 15px; font-style: normal; font-weight: 400; letter-spacing: normal; text-align: start; text-indent: 0px; background-color: rgb(255, 255, 255)\">✅ টু-ওয়ে অডিও: আছে</p>\r\n<p style=\"font-family: SolaimanLipi, sans-serif; margin: 0px 0px 12px; color: rgb(51, 51, 51); font-size: 15px; font-style: normal; font-weight: 400; letter-spacing: normal; text-align: start; text-indent: 0px; background-color: rgb(255, 255, 255)\">✅ ওয়ান-ওয়ে অডিও: আছে</p>\r\n<p style=\"font-family: SolaimanLipi, sans-serif; margin: 0px 0px 12px; color: rgb(51, 51, 51); font-size: 15px; font-style: normal; font-weight: 400; letter-spacing: normal; text-align: start; text-indent: 0px; background-color: rgb(255, 255, 255)\">✅ সাউন্ড ডিটেকশন: আছে</p>\r\n<p style=\"font-family: SolaimanLipi, sans-serif; margin: 0px 0px 12px; color: rgb(51, 51, 51); font-size: 15px; font-style: normal; font-weight: 400; letter-spacing: normal; text-align: start; text-indent: 0px; background-color: rgb(255, 255, 255)\">✅ ফুল কালার: সাপোর্টেড</p>\r\n<p style=\"font-family: SolaimanLipi, sans-serif; margin: 0px 0px 12px; color: rgb(51, 51, 51); font-size: 15px; font-style: normal; font-weight: 400; letter-spacing: normal; text-align: start; text-indent: 0px; background-color: rgb(255, 255, 255)\">✅ বিল্ট-ইন মাইক্রোফোন: আছে</p>\r\n<p style=\"font-family: SolaimanLipi, sans-serif; margin: 0px 0px 12px; color: rgb(51, 51, 51); font-size: 15px; font-style: normal; font-weight: 400; letter-spacing: normal; text-align: start; text-indent: 0px; background-color: rgb(255, 255, 255)\"> ✅ স্টোরেজ: TF Card সাপোর্টেড 128GB</p>\r\n<p style=\"font-family: SolaimanLipi, sans-serif; margin: 0px 0px 12px; color: rgb(51, 51, 51); font-size: 15px; font-style: normal; font-weight: 400; letter-spacing: normal; text-align: start; text-indent: 0px; background-color: rgb(255, 255, 255)\">✅ ওয়াই-ফাই: 2.4GHz</p>\r\n<p style=\"font-family: SolaimanLipi, sans-serif; margin: 0px 0px 12px; color: rgb(51, 51, 51); font-size: 15px; font-style: normal; font-weight: 400; letter-spacing: normal; text-align: start; text-indent: 0px; background-color: rgb(255, 255, 255)\">✅ অ্যাপ: Sunlink</p>\r\n<p style=\"font-family: SolaimanLipi, sans-serif; margin: 0px 0px 12px; color: rgb(51, 51, 51); font-size: 15px; font-style: normal; font-weight: 400; letter-spacing: normal; text-align: start; text-indent: 0px; background-color: rgb(255, 255, 255)\">✅ কন্ট্রোল: রিমোট কন্ট্রোল</p>\r\n<p style=\"font-family: SolaimanLipi, sans-serif; margin: 0px 0px 12px; color: rgb(51, 51, 51); font-size: 15px; font-style: normal; font-weight: 400; letter-spacing: normal; text-align: start; text-indent: 0px; background-color: rgb(255, 255, 255)\"> ✅ মডেল: E27 Single</p>\r\n<p style=\"font-family: SolaimanLipi, sans-serif; margin: 0px 0px 12px; color: rgb(51, 51, 51); font-size: 15px; font-style: normal; font-weight: 400; letter-spacing: normal; text-align: start; text-indent: 0px; background-color: rgb(255, 255, 255)\">✅ ইনস্টলেশন: সহজে ইনস্টল করা যায়</p>\r\n<p style=\"font-family: SolaimanLipi, sans-serif; margin: 0px 0px 12px; color: rgb(51, 51, 51); font-size: 15px; font-style: normal; font-weight: 400; letter-spacing: normal; text-align: start; text-indent: 0px; background-color: rgb(255, 255, 255)\">✅ বডি ম্যাটেরিয়াল: ABS</p>\r\n<p style=\"font-family: SolaimanLipi, sans-serif; margin: 0px 0px 12px; color: rgb(51, 51, 51); font-size: 15px; font-style: normal; font-weight: 400; letter-spacing: normal; text-align: start; text-indent: 0px; background-color: rgb(255, 255, 255)\">✅ পণ্যের ধরন: Security IP Camera</p>\r\n<p style=\"font-family: SolaimanLipi, sans-serif; margin: 0px 0px 12px; color: rgb(51, 51, 51); font-size: 15px; font-style: normal; font-weight: 400; letter-spacing: normal; text-align: start; text-indent: 0px; background-color: rgb(255, 255, 255)\">✅ অ্যালার্ম ফাংশন: Alarm I/O</p>', NULL, 'nxjHE9dq6lj4FDykucstBqnP.jpg', 1220, 1490, NULL, 5.0, 1, 'top', 9, 0, 1, '2026-10-10 04:14:21', '2026-10-10 04:14:21', '2026-10-10 09:52:00', 'https://www.youtube.com/watch?v=r-zuMczDYJ4', NULL, NULL, 'single-lens-bulb-cc-camera-sulink', '<p style=\"font-family: SolaimanLipi, sans-serif; margin: 0px 0px 12px; color: rgb(51, 51, 51); font-size: 15px; font-style: normal; font-weight: 400; letter-spacing: normal; text-align: start; text-indent: 0px; background-color: rgb(255, 255, 255)\"><strong>📋 ক্যামেরার বৈশিষ্ট্য ও স্পেসিফিকেশন</strong></p>\r\n<p style=\"font-family: SolaimanLipi, sans-serif; margin: 0px 0px 12px; color: rgb(51, 51, 51); font-size: 15px; font-style: normal; font-weight: 400; letter-spacing: normal; text-align: start; text-indent: 0px; background-color: rgb(255, 255, 255)\">✅ রেজোলিউশন: 1080P Full HD</p>\r\n<p style=\"font-family: SolaimanLipi, sans-serif; margin: 0px 0px 12px; color: rgb(51, 51, 51); font-size: 15px; font-style: normal; font-weight: 400; letter-spacing: normal; text-align: start; text-indent: 0px; background-color: rgb(255, 255, 255)\">✅ ভিউয়িং অ্যাঙ্গেল: 360°</p>\r\n<p style=\"font-family: SolaimanLipi, sans-serif; margin: 0px 0px 12px; color: rgb(51, 51, 51); font-size: 15px; font-style: normal; font-weight: 400; letter-spacing: normal; text-align: start; text-indent: 0px; background-color: rgb(255, 255, 255)\">✅ নাইট ভিশন: আছে</p>\r\n<p style=\"font-family: SolaimanLipi, sans-serif; margin: 0px 0px 12px; color: rgb(51, 51, 51); font-size: 15px; font-style: normal; font-weight: 400; letter-spacing: normal; text-align: start; text-indent: 0px; background-color: rgb(255, 255, 255)\">✅ টু-ওয়ে অডিও: আছে</p>\r\n<p style=\"font-family: SolaimanLipi, sans-serif; margin: 0px 0px 12px; color: rgb(51, 51, 51); font-size: 15px; font-style: normal; font-weight: 400; letter-spacing: normal; text-align: start; text-indent: 0px; background-color: rgb(255, 255, 255)\">✅ ওয়ান-ওয়ে অডিও: আছে</p>', 'simple');

-- --------------------------------------------------------

--
-- Table structure for table `product_images`
--

CREATE TABLE `product_images` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `image` varchar(255) NOT NULL,
  `sort_order` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_images`
--

INSERT INTO `product_images` (`id`, `product_id`, `image`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 20, 'M8bvdjEhVpfxHQwtI35rQVrE.jpg', 1, '2026-10-10 03:10:30', '2026-10-10 03:10:30'),
(3, 20, 'w8PTWpU2nxUyMF26SG0FSt2O.jpg', 3, '2026-10-10 03:10:30', '2026-10-10 03:10:30'),
(4, 20, 'DiG1LpWjuSZWgmwXb4ECUJDB.jpg', 4, '2026-10-10 03:10:30', '2026-10-10 03:10:30'),
(6, 20, '0tICxUSkL9uNkuyeiMYTGNRX.jpg', 6, '2026-10-10 03:10:30', '2026-10-10 03:10:30');

-- --------------------------------------------------------

--
-- Table structure for table `product_reviews`
--

CREATE TABLE `product_reviews` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(80) NOT NULL,
  `rating` tinyint(3) UNSIGNED NOT NULL,
  `comment` text DEFAULT NULL,
  `status` varchar(10) NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_reviews`
--

INSERT INTO `product_reviews` (`id`, `product_id`, `name`, `rating`, `comment`, `status`, `created_at`, `updated_at`) VALUES
(1, 20, 'MD AYUB HOSSAIN', 5, 'excellent camera', 'approved', '2026-10-10 03:15:55', '2026-10-10 03:35:02'),
(2, 21, 'MD AYUB HOSSAIN', 5, 'excellent camera', 'approved', '2026-10-10 04:15:02', '2026-10-10 09:52:00');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `key` varchar(60) NOT NULL,
  `value` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `key`, `value`, `created_at`, `updated_at`) VALUES
(1, 'acct_link_start', '2026-10-09 09:28:55', '2026-10-09 03:28:55', '2026-10-09 03:28:55'),
(2, 'logo', '', '2026-10-09 03:43:10', '2026-10-09 03:43:10'),
(3, 'shop_name', 'Ayub Tech Shop', '2026-10-09 03:43:10', '2026-10-09 03:43:10'),
(4, 'tagline', 'Electrical Parts', '2026-10-09 03:43:10', '2026-10-09 03:43:10'),
(5, 'phone', '09638604020', '2026-10-09 03:43:10', '2026-10-09 03:43:10'),
(6, 'whatsapp', '01711-302759', '2026-10-09 03:43:10', '2026-10-09 03:43:10'),
(7, 'delivery_charge', '60', '2026-10-09 03:43:10', '2026-10-09 03:43:10'),
(8, 'advance_amount', '0', '2026-10-09 03:43:10', '2026-10-09 03:43:10'),
(9, 'warranty_days', '0', '2026-10-09 03:43:10', '2026-10-09 03:43:10'),
(10, 'warranty_terms', '', '2026-10-09 03:43:10', '2026-10-09 03:43:10'),
(11, 'invoice_note', 'Thank you for shopping with us!', '2026-10-09 03:43:10', '2026-10-09 03:43:10'),
(12, 'invoice_terms', '', '2026-10-09 03:43:10', '2026-10-09 03:43:10'),
(13, 'address', '', '2026-10-09 03:43:10', '2026-10-09 03:43:10'),
(14, 'email', 'ayub.pbs@gmail.com', '2026-10-09 03:43:10', '2026-10-09 03:43:10'),
(15, 'business_hours', '', '2026-10-09 03:43:10', '2026-10-09 03:43:10'),
(16, 'trade_license', '', '2026-10-09 03:43:10', '2026-10-09 03:43:10'),
(17, 'bin_tin', '', '2026-10-09 03:43:10', '2026-10-09 03:43:10'),
(18, 'about', '', '2026-10-09 03:43:10', '2026-10-09 03:43:10'),
(19, 'facebook', 'https://www.facebook.com/ayubpbs', '2026-10-09 03:43:10', '2026-10-09 03:43:10'),
(20, 'messenger', 'https://www.facebook.com/messages/t/61585361660591', '2026-10-09 03:43:10', '2026-10-09 03:43:10'),
(21, 'instagram', 'https://www.instagram.com/ayub.pbs/?hl=en', '2026-10-09 03:43:10', '2026-10-09 03:43:10'),
(22, 'youtube', 'https://www.youtube.com/@ayubtechshop', '2026-10-09 03:43:10', '2026-10-09 03:43:10'),
(23, 'tiktok', 'https://www.tiktok.com/@ayub365.t', '2026-10-09 03:43:10', '2026-10-09 03:43:10'),
(24, 'x', '', '2026-10-09 03:43:10', '2026-10-09 03:43:10'),
(25, 'warranty_enabled', '0', '2026-10-09 03:43:10', '2026-10-09 03:43:10'),
(26, 'invoice_show_logo', '1', '2026-10-09 03:43:10', '2026-10-09 03:43:10'),
(27, 'invoice_show_phone', '1', '2026-10-09 03:43:10', '2026-10-09 03:43:10'),
(28, 'invoice_show_warranty', '1', '2026-10-09 03:43:10', '2026-10-09 03:43:10');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `is_admin` tinyint(1) NOT NULL DEFAULT 0,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `accounts`
--
ALTER TABLE `accounts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `accounts_type_index` (`type`);

--
-- Indexes for table `account_categories`
--
ALTER TABLE `account_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `account_categories_type_name_unique` (`type`,`name`);

--
-- Indexes for table `account_transactions`
--
ALTER TABLE `account_transactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `account_transactions_payment_id_unique` (`payment_id`),
  ADD KEY `account_transactions_account_id_txn_date_index` (`account_id`,`txn_date`),
  ADD KEY `account_transactions_category_id_index` (`category_id`),
  ADD KEY `account_transactions_transfer_ref_index` (`transfer_ref`);

--
-- Indexes for table `admin_notifications`
--
ALTER TABLE `admin_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_notifications_read_at_created_at_index` (`read_at`,`created_at`),
  ADD KEY `admin_notifications_type_created_at_index` (`type`,`created_at`),
  ADD KEY `admin_notifications_type_product_id_index` (`type`,`product_id`);

--
-- Indexes for table `brands`
--
ALTER TABLE `brands`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `brands_name_unique` (`name`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `categories_parent_id_index` (`parent_id`);

--
-- Indexes for table `coupons`
--
ALTER TABLE `coupons`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `coupons_code_unique` (`code`);

--
-- Indexes for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`);

--
-- Indexes for table `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `invoices_order_id_unique` (`order_id`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `orders_status_index` (`status`),
  ADD KEY `orders_consignment_id_index` (`consignment_id`),
  ADD KEY `orders_courier_status_index` (`courier_status`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_items_order_id_foreign` (`order_id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD KEY `password_resets_email_index` (`email`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `payments_trx_id_unique` (`trx_id`),
  ADD KEY `payments_order_id_foreign` (`order_id`),
  ADD KEY `payments_type_paid_at_index` (`type`,`paid_at`);

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
  ADD UNIQUE KEY `products_sku_unique` (`sku`),
  ADD UNIQUE KEY `products_slug_unique` (`slug`),
  ADD KEY `products_category_id_index` (`category_id`),
  ADD KEY `products_section_index` (`section`),
  ADD KEY `products_brand_id_index` (`brand_id`);

--
-- Indexes for table `product_images`
--
ALTER TABLE `product_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_images_product_id_index` (`product_id`);

--
-- Indexes for table `product_reviews`
--
ALTER TABLE `product_reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_reviews_product_id_index` (`product_id`),
  ADD KEY `product_reviews_status_index` (`status`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `settings_key_unique` (`key`);

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
-- AUTO_INCREMENT for table `accounts`
--
ALTER TABLE `accounts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `account_categories`
--
ALTER TABLE `account_categories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `account_transactions`
--
ALTER TABLE `account_transactions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `admin_notifications`
--
ALTER TABLE `admin_notifications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `brands`
--
ALTER TABLE `brands`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `coupons`
--
ALTER TABLE `coupons`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `product_images`
--
ALTER TABLE `product_images`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `product_reviews`
--
ALTER TABLE `product_reviews`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `account_transactions`
--
ALTER TABLE `account_transactions`
  ADD CONSTRAINT `account_transactions_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `invoices`
--
ALTER TABLE `invoices`
  ADD CONSTRAINT `invoices_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
