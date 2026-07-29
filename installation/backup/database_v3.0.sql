-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:8889
-- Generation Time: Feb 01, 2026 at 11:49 AM
-- Server version: 8.0.40
-- PHP Version: 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `drivemond_install`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` bigint UNSIGNED NOT NULL,
  `logable_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `logable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `edited_by` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `before` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `after` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `user_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_notifications`
--

CREATE TABLE `admin_notifications` (
  `id` bigint UNSIGNED NOT NULL,
  `model` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_seen` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ai_settings`
--

CREATE TABLE `ai_settings` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ai_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `base_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `api_key` text COLLATE utf8mb4_unicode_ci,
  `organization_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `applied_coupons`
--

CREATE TABLE `applied_coupons` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `coupon_setup_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `app_notifications`
--

CREATE TABLE `app_notifications` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ride_request_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notification_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `action` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `areas`
--

CREATE TABLE `areas` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `latitude` double NOT NULL,
  `longitude` double NOT NULL,
  `radius` double NOT NULL,
  `zone_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `area_bonus_setup`
--

CREATE TABLE `area_bonus_setup` (
  `id` bigint UNSIGNED NOT NULL,
  `area_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `bonus_setup_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `area_coupon_setup`
--

CREATE TABLE `area_coupon_setup` (
  `id` bigint UNSIGNED NOT NULL,
  `area_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `coupon_setup_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `area_discount_setup`
--

CREATE TABLE `area_discount_setup` (
  `id` bigint UNSIGNED NOT NULL,
  `area_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `discount_setup_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `area_pick_hour`
--

CREATE TABLE `area_pick_hour` (
  `id` bigint UNSIGNED NOT NULL,
  `area_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `pick_hour_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `banner_setups`
--

CREATE TABLE `banner_setups` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `time_period` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `display_position` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `redirect_link` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `banner_group` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total_redirection` decimal(8,2) NOT NULL DEFAULT '0.00',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blogs`
--

CREATE TABLE `blogs` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `readable_id` int UNSIGNED NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `blog_category_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `writer` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `thumbnail` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` tinyint UNSIGNED NOT NULL DEFAULT '1',
  `click_count` int UNSIGNED NOT NULL DEFAULT '0',
  `meta_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_image` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_drafted` tinyint UNSIGNED NOT NULL DEFAULT '0',
  `is_published` tinyint UNSIGNED NOT NULL DEFAULT '0',
  `published_at` date NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blog_categories`
--

CREATE TABLE `blog_categories` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` tinyint UNSIGNED NOT NULL DEFAULT '1',
  `click_count` int UNSIGNED NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blog_drafts`
--

CREATE TABLE `blog_drafts` (
  `id` bigint UNSIGNED NOT NULL,
  `blog_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `blog_category_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `writer` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `thumbnail` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `published_at` date NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blog_settings`
--

CREATE TABLE `blog_settings` (
  `id` bigint UNSIGNED NOT NULL,
  `key_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` json NOT NULL,
  `settings_type` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bonus_setups`
--

CREATE TABLE `bonus_setups` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `user_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_level_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `min_trip_amount` decimal(8,2) NOT NULL DEFAULT '0.00',
  `max_bonus` decimal(8,2) NOT NULL DEFAULT '0.00',
  `bonus` decimal(8,2) NOT NULL DEFAULT '0.00',
  `amount_type` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'percentage',
  `bonus_type` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'default',
  `limit` int DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `rules` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total_used` decimal(8,2) NOT NULL DEFAULT '0.00',
  `total_amount` decimal(8,2) NOT NULL DEFAULT '0.00',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bonus_setup_vehicle_category`
--

CREATE TABLE `bonus_setup_vehicle_category` (
  `id` bigint UNSIGNED NOT NULL,
  `bonus_setup_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `vehicle_category_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `business_settings`
--

CREATE TABLE `business_settings` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `key_name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `settings_type` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `business_settings`
--

INSERT INTO `business_settings` (`id`, `key_name`, `value`, `settings_type`, `created_at`, `updated_at`) VALUES
('0a21956c-46f7-4bc3-be9f-5c5bee4aded9', 'parcel_refund_validity', '2', 'parcel_settings', '2024-11-04 07:12:48', '2024-11-04 07:12:48'),
('3d8e3eea-556a-4707-bd0b-208c02b5e048', 'return_time_for_driver', '24', 'parcel_settings', '2024-11-04 07:12:48', '2024-11-04 07:12:48'),
('647146d1-ee1a-4ba5-9588-a0225fbcb64b', 'return_fee_for_driver_time_exceed', '0', 'parcel_settings', '2024-11-04 07:12:48', '2024-11-04 07:12:48'),
('9c0991cc-6b7f-454a-8e0a-72ffb04f1ea6', 'return_time_type_for_driver', '\"hour\"', 'parcel_settings', '2024-11-04 07:12:48', '2024-11-04 07:12:48'),
('b6308a75-5017-44bf-847c-0c09004a459f', 'parcel_refund_validity_type', '\"day\"', 'parcel_settings', '2024-11-04 07:12:48', '2024-11-04 07:12:48'),
('d9002a88-0046-4530-9ebc-ba753462af5c', 'parcel_tracking_message', '\"Dear {CustomerName}\\nParcel ID is {ParcelId} You can track this parcel from this link {TrackingLink}\"', 'parcel_settings', '2024-11-04 07:12:48', '2024-11-04 07:12:48');

-- --------------------------------------------------------

--
-- Table structure for table `cancellation_reasons`
--

CREATE TABLE `cancellation_reasons` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `cancellation_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `channel_conversations`
--

CREATE TABLE `channel_conversations` (
  `id` bigint UNSIGNED NOT NULL,
  `channel_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `convable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `convable_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '(DC2Type:guid)',
  `is_read` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `channel_lists`
--

CREATE TABLE `channel_lists` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `channelable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `channelable_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '(DC2Type:guid)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `channel_users`
--

CREATE TABLE `channel_users` (
  `id` bigint UNSIGNED NOT NULL,
  `channel_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `conversation_files`
--

CREATE TABLE `conversation_files` (
  `id` bigint UNSIGNED NOT NULL,
  `conversation_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `coupon_setups`
--

CREATE TABLE `coupon_setups` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zone_coupon_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'custom',
  `customer_level_coupon_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'custom',
  `customer_coupon_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'custom',
  `category_coupon_type` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `min_trip_amount` decimal(8,2) NOT NULL DEFAULT '0.00',
  `max_coupon_amount` decimal(8,2) NOT NULL DEFAULT '0.00',
  `coupon` decimal(8,2) NOT NULL DEFAULT '0.00',
  `amount_type` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'percentage',
  `coupon_type` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'default',
  `coupon_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `limit` int DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `total_used` decimal(8,2) NOT NULL DEFAULT '0.00',
  `total_amount` decimal(8,2) NOT NULL DEFAULT '0.00',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `coupon_setup_vehicle_category`
--

CREATE TABLE `coupon_setup_vehicle_category` (
  `id` bigint UNSIGNED NOT NULL,
  `coupon_setup_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `vehicle_category_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customer_coupon_setups`
--

CREATE TABLE `customer_coupon_setups` (
  `user_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `coupon_setup_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `limit_per_user` int NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customer_discount_setups`
--

CREATE TABLE `customer_discount_setups` (
  `user_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `discount_setup_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `limit_per_user` int NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customer_level_coupon_setups`
--

CREATE TABLE `customer_level_coupon_setups` (
  `user_level_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `coupon_setup_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customer_level_discount_setups`
--

CREATE TABLE `customer_level_discount_setups` (
  `user_level_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `discount_setup_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `discount_setups`
--

CREATE TABLE `discount_setups` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `short_description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `terms_conditions` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `image` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `zone_discount_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'custom',
  `customer_level_discount_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'custom',
  `customer_discount_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'custom',
  `module_discount_type` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `discount_amount_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `limit_per_user` int NOT NULL DEFAULT '0',
  `discount_amount` double NOT NULL,
  `max_discount_amount` double NOT NULL DEFAULT '0',
  `min_trip_amount` double NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `total_used` int NOT NULL DEFAULT '0',
  `total_amount` double NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `discount_setup_vehicle_category`
--

CREATE TABLE `discount_setup_vehicle_category` (
  `id` bigint UNSIGNED NOT NULL,
  `discount_setup_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `vehicle_category_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `driver_details`
--

CREATE TABLE `driver_details` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_online` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `availability_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unavailable',
  `online` time DEFAULT NULL,
  `offline` time DEFAULT NULL,
  `online_time` double(23,2) NOT NULL DEFAULT '0.00',
  `accepted` time DEFAULT NULL,
  `completed` time DEFAULT NULL,
  `start_driving` time DEFAULT NULL,
  `on_driving_time` double(23,2) NOT NULL DEFAULT '0.00',
  `idle_time` double(23,2) NOT NULL DEFAULT '0.00',
  `service` json DEFAULT NULL,
  `ride_count` int NOT NULL DEFAULT '0',
  `parcel_count` int NOT NULL DEFAULT '0',
  `is_verified` tinyint UNSIGNED NOT NULL DEFAULT '0',
  `base_image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `verified_image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_suspended` tinyint UNSIGNED NOT NULL DEFAULT '0',
  `suspend_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trigger_verification_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `driver_identity_verifications`
--

CREATE TABLE `driver_identity_verifications` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `driver_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempt_details` json DEFAULT NULL,
  `current_status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `driver_time_logs`
--

CREATE TABLE `driver_time_logs` (
  `id` bigint UNSIGNED NOT NULL,
  `driver_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` date NOT NULL,
  `online` time DEFAULT NULL,
  `offline` time DEFAULT NULL,
  `online_time` double(23,2) NOT NULL DEFAULT '0.00',
  `accepted` time DEFAULT NULL,
  `completed` time DEFAULT NULL,
  `start_driving` time DEFAULT NULL,
  `on_driving_time` double(23,2) NOT NULL DEFAULT '0.00',
  `idle_time` double(23,2) NOT NULL DEFAULT '0.00',
  `on_time_completed` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `late_completed` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `late_pickup` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `external_configurations`
--

CREATE TABLE `external_configurations` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `key` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` json NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint UNSIGNED NOT NULL,
  `uuid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fare_biddings`
--

CREATE TABLE `fare_biddings` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `trip_request_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `driver_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `bid_fare` decimal(8,2) NOT NULL,
  `is_ignored` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fare_bidding_logs`
--

CREATE TABLE `fare_bidding_logs` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `trip_request_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `driver_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bid_fare` decimal(8,2) DEFAULT NULL,
  `is_ignored` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `firebase_push_notifications`
--

CREATE TABLE `firebase_push_notifications` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dynamic_values` json DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '0',
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `group` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `firebase_push_notifications`
--

INSERT INTO `firebase_push_notifications` (`id`, `name`, `value`, `dynamic_values`, `status`, `type`, `group`, `action`, `created_at`, `updated_at`) VALUES
(1, 'trip_started', 'Your trip is started.', '[\"{tripId}\", \"{dropOffLocation}\"]', 1, 'regular_trip', 'customer', 'trip_started', '2025-04-12 22:55:15', '2025-04-12 22:55:15'),
(2, 'trip_completed', 'Your trip is completed.', '[\"{sentTime}\", \"{tripId}\"]', 1, 'regular_trip', 'customer', 'trip_completed', '2025-04-12 22:55:15', '2025-04-12 22:55:15'),
(3, 'trip_canceled', 'Your trip is cancelled.', '[\"{sentTime}\", \"{tripId}\"]', 1, 'regular_trip', 'customer', 'trip_canceled', '2025-04-12 22:55:15', '2025-04-12 22:55:15'),
(4, 'trip_paused', 'Trip request is paused.', '[\"{tripId}\"]', 1, 'regular_trip', 'customer', 'trip_paused', '2025-04-12 22:55:15', '2025-04-12 22:55:15'),
(5, 'trip_resumed', 'Trip request is resumed.', '[\"{tripId}\"]', 1, 'regular_trip', 'customer', 'trip_resumed', '2025-04-12 22:55:15', '2025-04-12 22:55:15'),
(6, 'another_driver_assigned', 'Another driver already accepted the trip request.', '[\"{tripId}\"]', 1, 'regular_trip', 'customer', 'another_driver_assigned', '2025-04-12 22:55:15', '2025-04-12 22:55:15'),
(7, 'driver_on_the_way', 'Driver accepted your trip request.', '[\"{vehicleCategory}\", \"{pickUpLocation}\", \"{tripId}\"]', 1, 'regular_trip', 'customer', 'driver_on_the_way', '2025-04-12 22:55:15', '2025-04-12 22:55:15'),
(8, 'bid_request_from_driver', 'Driver sent a bid request', '[\"{userName}\"]', 1, 'regular_trip', 'customer', 'bid_request_from_driver', '2025-04-12 22:55:15', '2025-04-12 22:55:15'),
(9, 'driver_canceled_ride_request', 'Driver has canceled your ride.', '[\"{sentTime}\"]', 1, 'regular_trip', 'customer', 'driver_canceled_ride_request', '2025-04-12 22:55:15', '2025-04-12 22:55:15'),
(10, 'payment_successful', '{paidAmount} payment successful on this trip by {methodName}.', '[\"{paidAmount}\", \"{methodName}\", \"{tripId}\"]', 1, 'regular_trip', 'customer', 'payment_successful', '2025-04-12 22:55:15', '2025-04-12 22:55:15'),
(11, 'new_ride_request', 'You have a new ride request.', '[\"{tripId}\", \"{approximateAmount}\", \"{dropOffLocation}\", \"{pickUpLocation}\"]', 1, 'regular_trip', 'driver', 'new_ride_request', '2025-04-12 22:55:15', '2025-04-12 22:55:15'),
(12, 'bid_accepted', 'Customer confirmed your bid.', '[\"{tripId}\", \"{approximateAmount}\", \"{pickUpLocation}\"]', 1, 'regular_trip', 'driver', 'bid_accepted', '2025-04-12 22:55:15', '2025-04-12 22:55:15'),
(13, 'trip_request_canceled', 'A trip request is cancelled.', '[\"{tripId}\"]', 1, 'regular_trip', 'driver', 'trip_request_canceled', '2025-04-12 22:55:15', '2025-04-12 22:55:15'),
(14, 'customer_canceled_trip', 'Customer just declined a request.', '[\"{tripId}\", \"{sentTime}\"]', 1, 'regular_trip', 'driver', 'customer_canceled_trip', '2025-04-12 22:55:15', '2025-04-12 22:55:15'),
(15, 'bid_request_canceled_by_customer', 'Customer has canceled your bid request.', NULL, 1, 'regular_trip', 'driver', 'bid_request_canceled_by_customer', '2025-04-12 22:55:15', '2025-04-12 22:55:15'),
(16, 'tips_from_customer', 'Customer has given the tips {tipsAmount} with payment.', '[\"{tripId}\", \"{tipsAmount}\", \"{customerName}\"]', 1, 'regular_trip', 'driver', 'tips_from_customer', '2025-04-12 22:55:15', '2025-04-12 22:55:15'),
(17, 'received_new_bid', 'Received a new bid request.', '[\"{tripId}\", \"{approximateAmount}\"]', 1, 'regular_trip', 'driver', 'received_new_bid', '2025-04-12 22:55:15', '2025-04-12 22:55:15'),
(18, 'customer_rejected_bid', 'We regret to inform you that your bid request for trip ID {tripId} has been rejected by the customer.', '[\"{tripId}\", \"{approximateAmount}\"]', 1, 'regular_trip', 'driver', 'customer_rejected_bid', '2025-04-12 22:55:15', '2025-04-12 22:55:15'),
(19, 'new_parcel', 'You have a new parcel request.', '[\"{parcelId}\"]', 1, 'parcel', 'customer', 'new_parcel', '2025-04-12 22:55:15', '2025-04-12 22:55:15'),
(20, 'parcel_picked_up', 'Parcel Picked-up.', '[\"{parcelId}\"]', 1, 'parcel', 'customer', 'parcel_picked_up', '2025-04-12 22:55:15', '2025-04-12 22:55:15'),
(21, 'parcel_on_the_way', 'Parcel on the way.', '[\"{parcelId}\"]', 1, 'parcel', 'customer', 'parcel_on_the_way', '2025-04-12 22:55:15', '2025-04-12 22:55:15'),
(22, 'parcel_delivery_completed', 'Parcel delivered successfully.', '[\"{parcelId}\"]', 1, 'parcel', 'customer', 'parcel_delivery_completed', '2025-04-12 22:55:15', '2025-04-12 22:55:15'),
(23, 'parcel_canceled', 'Parcel Cancel.', '[\"{parcelId}\", \"{sentTime}\"]', 1, 'parcel', 'customer', 'parcel_canceled', '2025-04-12 22:55:15', '2025-04-12 22:55:15'),
(24, 'parcel_returned', 'Parcel returned successfully.', '[\"{parcelId}\", \"{customerName}\"]', 1, 'parcel', 'customer', 'parcel_returned', '2025-04-12 22:55:15', '2025-04-12 22:55:15'),
(25, 'parcel_returning_otp', 'Your parcel returning OTP is {otp}.', '[\"{parcelId}\", \"{otp}\"]', 1, 'parcel', 'customer', 'parcel_returning_otp', '2025-04-12 22:55:15', '2025-04-12 22:55:15'),
(26, 'refund_accepted', 'For parcel ID #{parcelId} your refund request has been approved by admin. You will be refunded soon.', '[\"{parcelId}\"]', 1, 'parcel', 'customer', 'refund_accepted', '2025-04-12 22:55:15', '2025-04-12 22:55:15'),
(27, 'refund_denied', 'For parcel ID #{parcelId} your refund request has been denied by admin. You can check the denied reason from parcel details.', '[\"{parcelId}\"]', 1, 'parcel', 'customer', 'refund_denied', '2025-04-12 22:55:15', '2025-04-12 22:55:15'),
(28, 'refunded_to_wallet', 'For parcel ID # {parcelId}, your refund request has been approved by admin and {approximateAmount} refunded to your Wallet.', '[\"{parcelId}\", \"{approximateAmount}\"]', 1, 'parcel', 'customer', 'refunded_to_wallet', '2025-04-12 22:55:15', '2025-04-12 22:55:15'),
(29, 'refunded_as_coupon', 'For parcel ID # {parcelId}, your refund request has been approved by admin and {approximateAmount} has been issued as a coupon. You can use this coupon for your trip whenever you like.', '[\"{parcelId}\", \"{approximateAmount}\"]', 1, 'parcel', 'customer', 'refunded_as_coupon', '2025-04-12 22:55:15', '2025-04-12 22:55:15'),
(30, 'new_parcel_request', 'New Parcel Request.', '[\"{parcelId}\", \"{approximateAmount}\", \"{dropOffLocation}\", \"{pickUpLocation}\"]', 1, 'parcel', 'driver', 'new_parcel_request', '2025-04-12 22:55:15', '2025-04-12 22:55:15'),
(31, 'parcel_amount_deducted', 'Due to a damage parcel ID #{parcelId} claimed by customer, {approximateAmount} will be deducted from your wallet. If you want to avoid the fine contact with admin.', '[\"{parcelId}\", \"{approximateAmount}\"]', 1, 'parcel', 'driver', 'parcel_amount_deducted', '2025-04-12 22:55:15', '2025-04-12 22:55:15'),
(32, 'refund_accepted', 'Refund request of parcel ID #{parcelId} has been approved by Admin. If you have any quarries please contact with admin.', '[\"{parcelId}\"]', 1, 'parcel', 'driver', 'refund_accepted', '2025-04-12 22:55:15', '2025-04-12 22:55:15'),
(33, 'refund_denied', 'Refund request of parcel ID #{parcelId} has been denied by Admin. You don’t need to worry.', '[\"{parcelId}\"]', 1, 'parcel', 'driver', 'refund_denied', '2025-04-12 22:55:15', '2025-04-12 22:55:15'),
(34, 'parcel_amount_debited', 'Due to a damaged parcel, {approximateAmount} has been deducted from your wallet. Please settle the amount as soon as possible and check the parcel details.', '[\"{approximateAmount}\"]', 1, 'parcel', 'driver', 'parcel_amount_debited', '2025-04-12 22:55:15', '2025-04-12 22:55:15'),
(35, 'registration_approved', 'Admin approved your registration. You can login now.', '[\"{userName}\", \"{sentTime}\", \"{vehicleCategory}\"]', 1, 'driver_registration', 'driver', 'registration_approved', '2025-04-12 22:55:15', '2025-04-12 22:55:15'),
(36, 'vehicle_request_approved', 'Your vehicle is approved by admin.', '[\"{userName}\", \"{sentTime}\", \"{vehicleCategory}\"]', 1, 'driver_registration', 'driver', 'vehicle_request_approved', '2025-04-12 22:55:15', '2025-04-12 22:55:15'),
(37, 'vehicle_request_denied', 'Your vehicle request is denied.', '[\"{userName}\", \"{sentTime}\", \"{vehicleCategory}\"]', 1, 'driver_registration', 'driver', 'vehicle_request_denied', '2025-04-12 22:55:15', '2025-04-12 22:55:15'),
(38, 'identity_image_rejected', 'Your identity image update request is rejected.', '[\"{userName}\", \"{sentTime}\"]', 1, 'driver_registration', 'driver', 'identity_image_rejected', '2025-04-12 22:55:15', '2025-04-12 22:55:15'),
(39, 'identity_image_approved', 'Your identity image update request is approved.', '[\"{userName}\", \"{sentTime}\"]', 1, 'driver_registration', 'driver', 'identity_image_approved', '2025-04-12 22:55:15', '2025-04-12 22:55:15'),
(40, 'vehicle_active', 'Your vehicle status has been activated by admin.', '[\"{userName}\", \"{sentTime}\", \"{vehicleCategory}\"]', 1, 'driver_registration', 'driver', 'vehicle_active', '2025-04-12 22:55:15', '2025-04-12 22:55:15'),
(41, 'coupon_applied', 'Customer got discount of.', '[\"{approximateAmount}\"]', 1, 'others', 'coupon', 'coupon_applied', '2025-04-12 22:55:15', '2025-04-12 22:55:15'),
(42, 'coupon_removed', 'Customer removed previously applied coupon.', NULL, 1, 'others', 'coupon', 'coupon_removed', '2025-04-12 22:55:15', '2025-04-12 22:55:15'),
(43, 'review_from_customer', 'New review from a customer! See what they had to say about your service.', '[\"{customerName}\"]', 1, 'others', 'review', 'review_from_customer', '2025-04-12 22:55:15', '2025-04-12 22:55:15'),
(44, 'review_from_driver', 'New review from a driver! See what he had to say about your trip.', '[\"{driverName}\"]', 1, 'others', 'review', 'review_from_driver', '2025-04-12 22:55:15', '2025-04-12 22:55:15'),
(45, 'someone_used_your_code', 'Your code was successfully used by a friend. You\'ll receive your reward after their first ride is completed.', NULL, 1, 'others', 'referral', 'someone_used_your_code', '2025-04-12 22:55:15', '2025-04-12 22:55:15'),
(46, 'referral_reward_received', 'You\'ve successfully received {referralRewardAmount} reward. You can use this amount on your next ride.', '[\"{referralRewardAmount}\"]', 1, 'others', 'referral', 'referral_reward_received', '2025-04-12 22:55:15', '2025-04-12 22:55:15'),
(47, 'safety_alert_sent', 'Safety Alert Sent.', NULL, 1, 'others', 'safety_alert', 'safety_alert_sent', '2025-04-12 22:55:15', '2025-04-12 22:55:15'),
(48, 'safety_problem_resolved', 'Safety Problem Resolved.', NULL, 1, 'others', 'safety_alert', 'safety_problem_resolved', '2025-04-12 22:55:15', '2025-04-12 22:55:15'),
(49, 'terms_and_conditions_updated', 'Admin just updated system terms and conditions.', NULL, 1, 'others', 'business_page', 'terms_and_conditions_updated', '2025-04-12 22:55:15', '2025-04-12 22:55:15'),
(50, 'privacy_policy_updated', 'Admin just updated our privacy policy.', NULL, 1, 'others', 'business_page', 'privacy_policy_updated', '2025-04-12 22:55:15', '2025-04-12 22:55:15'),
(51, 'legal_updated', 'We have updated our legal.', NULL, 1, 'others', 'business_page', 'legal_updated', '2025-04-12 22:55:15', '2025-04-12 22:55:15'),
(52, 'new_message', 'You got a new message from {userName}.', '[\"{userName}\", \"{sentTime}\", \"{tripId}\"]', 1, 'others', 'chatting', 'new_message', '2025-04-12 22:55:15', '2025-04-12 22:55:15'),
(53, 'admin_message', 'You got a new message from admin.', '[\"{sentTime}\", \"{driverName}\"]', 1, 'others', 'chatting', 'admin_message', '2025-04-12 22:55:15', '2025-04-12 22:55:15'),
(54, 'level_up', 'You have completed your challenges and reached level {levelName}.', '[\"{levelName}\"]', 1, 'others', 'level', 'level_up', '2025-04-12 22:55:15', '2025-04-12 22:55:15'),
(55, 'fund_added_by_admin', 'Admin has added {walletAmount} to your wallet.', '[\"{walletAmount}\"]', 1, 'others', 'fund', 'fund_added_by_admin', '2025-04-12 22:55:15', '2025-04-12 22:55:15'),
(56, 'admin_collected_cash', 'Admin collected cash.', '[\"{paidAmount}\"]', 1, 'others', 'fund', 'admin_collected_cash', '2025-04-12 22:55:15', '2025-04-12 22:55:15'),
(57, 'withdraw_request_rejected', 'Unfortunately, your withdrawal request has been rejected. {withdrawNote}.', '[\"{withdrawNote}\", \"{userName}\"]', 1, 'others', 'withdraw_request', 'withdraw_request_rejected', '2025-04-12 22:55:15', '2025-04-12 22:55:15'),
(58, 'withdraw_request_approved', 'We are pleased to inform you that your withdrawal request has been approved. The funds will be transferred to your account shortly.', '[\"{userName}\"]', 1, 'others', 'withdraw_request', 'withdraw_request_approved', '2025-04-12 22:55:15', '2025-04-12 22:55:15'),
(59, 'withdraw_request_settled', 'Your withdrawal request has been successfully settled. The funds have been transferred to your account.', '[\"{userName}\"]', 1, 'others', 'withdraw_request', 'withdraw_request_settled', '2025-04-12 22:55:15', '2025-04-12 22:55:15'),
(60, 'withdraw_request_reversed', 'Your withdrawal request has been successfully settled. The funds have been transferred to your account.', '[\"{userName}\"]', 1, 'others', 'withdraw_request', 'withdraw_request_reversed', '2025-04-12 22:55:15', '2025-04-12 22:55:15'),
(61, 'schedule_trip_booked', 'Schedule trip booked.', '[\"{tripId}\"]', 1, 'schedule_trip', 'customer', 'trip_booked', '2025-07-19 05:19:11', '2025-07-19 05:19:11'),
(62, 'schedule_trip_edited', 'Schedule trip edited.', '[\"{tripId}\"]', 1, 'schedule_trip', 'customer', 'trip_edited', '2025-07-19 05:19:11', '2025-07-19 05:19:11'),
(63, 'schedule_trip_accepted_by_driver', 'Schedule trip accepted by driver.', '[\"{tripId}\", \"{vehicleCategory}\", \"{pickUpLocation}\"]', 1, 'schedule_trip', 'customer', 'trip_accepted', '2025-07-19 05:19:11', '2025-07-19 05:19:11'),
(64, 'driver_on_the_way_to_pickup_location', 'Driver on the way to pickup location.', '[\"{tripId}\", \"{vehicleCategory}\", \"{pickUpLocation}\"]', 1, 'schedule_trip', 'customer', 'driver_on_the_way', '2025-07-19 05:19:11', '2025-07-19 05:19:11'),
(65, 'schedule_ride_started', 'Schedule ride started.', '[\"{tripId}\", \"{dropOffLocation}\"]', 1, 'schedule_trip', 'customer', 'trip_started', '2025-07-19 05:19:11', '2025-07-19 05:19:11'),
(66, 'schedule_ride_completed', 'Schedule ride completed.', '[\"{tripId}\", \"{sentTime}\"]', 1, 'schedule_trip', 'customer', 'trip_completed', '2025-07-19 05:19:11', '2025-07-19 05:19:11'),
(67, 'schedule_ride_canceled', 'Schedule ride canceled.', '[\"{tripId}\", \"{sentTime}\"]', 1, 'schedule_trip', 'customer', 'trip_canceled', '2025-07-19 05:19:11', '2025-07-19 05:19:11'),
(68, 'schedule_ride_paused', 'Schedule ride paused.', '[\"{tripId}\"]', 1, 'schedule_trip', 'customer', 'trip_paused', '2025-07-19 05:19:11', '2025-07-19 05:19:11'),
(69, 'schedule_ride_resumed', 'Schedule ride resumed.', '[\"{tripId}\"]', 1, 'schedule_trip', 'customer', 'trip_resumed', '2025-07-19 05:19:11', '2025-07-19 05:19:11'),
(70, 'driver_canceled_schedule_trip_request', 'Driver canceled the schedule trip request.', '[\"{tripId}\"]', 1, 'schedule_trip', 'customer', 'driver_canceled_ride_request', '2025-07-19 05:19:11', '2025-07-19 05:19:11'),
(71, 'payment_successful', '{paidAmount} payment successful on this trip by {methodName}.', '[\"{paidAmount}\", \"{methodName}\", \"{tripId}\"]', 1, 'schedule_trip', 'customer', 'payment_successful', '2025-07-19 05:19:11', '2025-07-19 05:19:11'),
(72, 'new_schedule_trip_request', 'New schedule trip request.', '[\"{tripId}\", \"{approximateAmount}\", \"{dropOffLocation}\", \"{pickUpLocation}\"]', 1, 'schedule_trip', 'driver', 'new_ride_request', '2025-07-19 05:19:11', '2025-07-19 05:19:11'),
(73, 'pickup_time_started', 'Pickup time started.', '[\"{tripId}\"]', 1, 'schedule_trip', 'driver', 'pickup_time_started', '2025-07-19 05:19:11', '2025-07-19 05:19:11'),
(74, 'tips_from_customer', 'Customer has given the tips {tipsAmount} with payment.', '[\"{tripId}\", \"{tipsAmount}\", \"{customerName}\"]', 1, 'schedule_trip', 'driver', 'tips_from_customer', '2025-07-19 05:19:11', '2025-07-19 05:19:11'),
(75, 'customer_canceled_the_trip', 'Customer canceled the trip.', '[\"{tripId}\", \"{sentTime}\"]', 1, 'schedule_trip', 'driver', 'customer_canceled_trip', '2025-07-19 05:19:11', '2025-07-19 05:19:11'),
(76, 'cash_in_hand_limit_exceeds', 'Your limit to hold cash in hand has been exceeded. Please pay the due to admin from your wallet page.', '[\"{driverName}\"]', 1, 'others', 'cash_in_hand', 'cash_in_hand_limit_exceeds', NULL, NULL),
(77, 'digital_payment_successful', '{paidAmount} Payment to admin is successful.', '[\"{paidAmount}\"]', 1, 'others', 'fund', 'digital_payment_successful', NULL, NULL),
(78, 'parcel_return_penalty', 'You have been penalized {approximateAmount} for not returning the parcel ID: {parcelId} in due time.', '[\"{approximateAmount}\", \"{parcelId}\"]', 1, 'parcel', 'driver', 'parcel_return_penalty', NULL, NULL),
(79, 'parcel_canceled', 'Parcel canceled. Please return the parcel within {dueTime}.', '[\"{parcelId}\", \"{sentTime}\", \"{dueTime}\"]', 1, 'parcel', 'driver', 'parcel_canceled', NULL, NULL),
(80, 'parcel_canceled_after_trip_started', 'Parcel canceled. Your paid amount is returned to your wallet', '[\"{parcelId}\", \"{approximateAmount}\"]', 1, 'parcel', 'customer', 'parcel_canceled_after_trip_started', NULL, NULL),
(81, 'fund_added_digitally', '{totalAmount} is added to your wallet.', '[\"{paidAmount}, {bonusAmount}, {totalAmount}\"]', 1, 'others', 'fund', 'fund_added_digitally', NULL, NULL),
(82, 'face_verification_completed_successfully', 'You are now a verified driver - start earning more with {businessName}', '[\"{businessName}\"]', 1, 'others', 'face_verification', 'face_verification_completed_successfully', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `id` bigint UNSIGNED NOT NULL,
  `queue` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint UNSIGNED NOT NULL,
  `reserved_at` int UNSIGNED DEFAULT NULL,
  `available_at` int UNSIGNED NOT NULL,
  `created_at` int UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `landing_page_sections`
--

CREATE TABLE `landing_page_sections` (
  `id` bigint UNSIGNED NOT NULL,
  `key_name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` json NOT NULL,
  `settings_type` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `landing_page_sections`
--

INSERT INTO `landing_page_sections` (`id`, `key_name`, `value`, `settings_type`, `created_at`, `updated_at`) VALUES
(1, 'intro_contents', '{\"title\": \"Navigate Life with Ease: Welcome to DriveMond, Your Premier Ride-Sharing Experience!\", \"sub_title\": \"Unlock a World of Convenience: Welcome to DriveMond, Your Ultimate Ride-Sharing Destination! Seamlessly Connect with Reliable Drivers, Enjoy Comfortable Journeys- One Ride at a Time.\", \"background_image\": \"\"}', 'intro_section', '2026-01-29 16:43:31', '2026-01-29 16:43:31'),
(2, 'total_download', '{\"image\": \"\", \"title\": \"40K+\", \"status\": 1, \"content\": \"Downloads\"}', 'business_statistics', '2026-01-29 16:43:31', '2026-01-29 17:00:43'),
(3, 'complete_ride', '{\"image\": \"\", \"title\": \"20M+\", \"status\": 1, \"content\": \"Complete Ride\"}', 'business_statistics', '2026-01-29 16:43:31', '2026-01-29 17:02:40'),
(4, 'happy_customer', '{\"image\": \"\", \"title\": \"1M+\", \"status\": 1, \"content\": \"Happy Customer\"}', 'business_statistics', '2026-01-29 16:43:31', '2026-01-29 17:03:25'),
(5, 'support', '{\"image\": \"\", \"title\": \"24/7hr\", \"status\": 1, \"content\": \"Support\"}', 'business_statistics', '2026-01-29 16:43:31', '2026-01-29 17:03:38'),
(6, 'intro_contents', '{\"title\": \"Our **Solutions**\", \"sub_title\": \"Explore our dynamic day-to-day solution for everyday life\"}', 'our_solutions', '2026-01-29 16:43:31', '2026-02-01 09:09:14'),
(7, 'solutions', '{\"image\": \"\", \"title\": \"Parcel Delivery\", \"status\": 1, \"description\": \"Send important parcels to the right place with custom fare setup option\"}', 'our_solutions', '2026-01-29 16:43:31', '2026-01-29 17:06:52'),
(8, 'solutions', '{\"image\": \"\", \"title\": \"Ride Sharing\", \"status\": 1, \"description\": \"Book a ride to your desired destination and set a custom fare from the app\"}', 'our_solutions', '2026-01-29 16:43:31', '2026-01-29 17:07:00'),
(9, 'service_1', '{\"image\": \"\", \"title\": \"Hit the road instantly and start **earning** on your own terms\", \"status\": 1, \"tab_name\": \"Regular Trip\", \"description\": \"<p>Join the DriveMond community of drivers and turn every mile into a milestone with our seamless, real-time trip booking system.</p><ul><li>Accept trip requests that fit your current location and availability with just a single tap.</li><li>Whether you prefer the comfort of a car or the agility of a motorbike, we support your choice of ride.</li><li>Track your income in real-time with instant payouts and performance-based rewards after every ride.</li></ul>\"}', 'our_services', '2026-01-29 16:43:31', '2026-02-01 10:33:10'),
(10, 'service_2', '{\"image\": \"\", \"title\": \"Plan your next adventure with DriveMond\'s trip **scheduling** features.\", \"status\": 1, \"tab_name\": \"Schedule Trip\", \"description\": \"<p>Discover endless opportunities to schedule trips that align with your skills and interests, transforming your time into a profitable venture.</p><ul><li>Discover endless opportunities to schedule trips that align with your skills and interests, transforming your time into a profitable venture.</li><li>Enjoy the freedom of scheduling trips that suit your personal timetable.</li><li>Enjoy the freedom of scheduling trips that suit your personal timetable.                                                \\r\\n                                            </li></ul>\"}', 'our_services', '2026-01-29 16:43:31', '2026-02-01 09:31:47'),
(11, 'service_3', '{\"image\": \"\", \"title\": \"Become a delivery **hero** and keep your city moving.\", \"status\": 1, \"tab_name\": \"Parcel Delivery\", \"description\": \"<p>Unlock a steady stream of income by delivering packages and essentials. It’s a flexible way to earn while exploring every corner of your neighborhood.</p><ul><li>Get optimized routes for multi-stop deliveries, ensuring you save time and fuel while maximizing earnings.</li><li>Use your bicycle, scooter, or car to handle everything from small envelopes to larger parcels.</li><li>Benefit from a consistent flow of delivery tasks, competitive base rates, and tips from satisfied customers.</li></ul><p></p><p>                                                \\r\\n                                            </p>\"}', 'our_services', '2026-01-29 16:43:31', '2026-02-01 10:33:22'),
(12, 'card_1', '{\"image\": \"\", \"title\": \"Ride Completed **Hassle-Free**\", \"subtitle\": \"Experience comfort, safety, and satisfaction with every trip. End your journey with a smile — every time with DriveMond.\"}', 'gallery', '2026-01-29 16:43:31', '2026-01-29 17:16:46'),
(13, 'card_2', '{\"image\": \"\", \"title\": \"Easily **Share** Your Ride\", \"subtitle\": \"With every turn of the wheel, discover something new — because each ride opens the door to infinite possibilities.\"}', 'gallery', '2026-01-29 16:43:31', '2026-01-29 17:17:39'),
(14, 'intro_contents', '{\"image\": \"\", \"title\": \"Earn Money with **DriveMond**\", \"subtitle\": \"Explore limitless possibilities with our platform — turning your skills, time, and passion into a rewarding source of income.\"}', 'earn_money', '2026-01-29 16:43:31', '2026-01-29 17:24:37'),
(15, 'reviews', '{\"rating\": \"5\", \"review\": \"\\\"DriveMond: Tactical Transport for Every Mission!\\\"\", \"status\": \"1\", \"designation\": \"Officer\", \"reviewer_name\": \"Lois Nila\", \"reviewer_image\": \"\"}', 'testimonial', '2026-01-29 16:43:31', '2026-01-29 16:43:31'),
(16, 'reviews', '{\"rating\": \"4.9\", \"review\": \"\\\"DriveMond: Effortless Journeys for Busy Lives!\\\"\", \"status\": \"1\", \"designation\": \"Engineer\", \"reviewer_name\": \"Mac Steven Moba\", \"reviewer_image\": \"\"}', 'testimonial', '2026-01-29 16:43:31', '2026-01-29 16:43:31'),
(17, 'reviews', '{\"rating\": \"4.5\", \"review\": \"\\\"DriveMond: Healing Journeys Every Day!\\\"\", \"status\": \"1\", \"designation\": \"Doctor\", \"reviewer_name\": \"Jenny Klath\", \"reviewer_image\": \"\"}', 'testimonial', '2026-01-29 16:43:31', '2026-01-29 16:43:31'),
(18, 'reviews', '{\"rating\": \"5\", \"review\": \"\\\"DriveMond: Elevate Your Business Moves!\\\"\", \"status\": \"1\", \"designation\": \"Businessman\", \"reviewer_name\": \"Sir Moba\", \"reviewer_image\": \"\"}', 'testimonial', '2026-01-29 16:43:31', '2026-01-29 16:43:31'),
(19, 'reviews', '{\"rating\": \"5\", \"review\": \"\\\"DriveMond: Student Rides Simplified!\\\"\", \"status\": \"1\", \"designation\": \"Student\", \"reviewer_name\": \"Jhon Doe\", \"reviewer_image\": \"\"}', 'testimonial', '2026-01-29 16:43:31', '2026-01-29 16:43:31'),
(20, 'is_business_statistics_enabled', '\"1\"', 'business_statistics', '2026-01-29 17:04:03', '2026-01-29 17:04:03'),
(21, 'is_our_solutions_enabled', '\"1\"', 'our_solutions', '2026-01-29 17:07:06', '2026-01-29 17:07:06'),
(22, 'intro_contents', '{\"title\": \"Our Services\", \"subtitle\": \"Discover our innovative solutions designed to enhance daily operations.\"}', 'our_services', '2026-01-29 17:07:47', '2026-01-29 17:07:47'),
(23, 'is_gallery_enabled', '\"1\"', 'gallery', '2026-01-29 17:17:45', '2026-01-29 17:17:45'),
(24, 'intro_contents', '{\"image\": \"\", \"title\": \"Your **Smooth Ride**, Just a Tap Away\", \"subtitle\": \"Experience hassle-free transportation with DriveMond. Reliable rides anytime, anywhere.\"}', 'customer_app_download', '2026-01-29 17:18:48', '2026-02-01 09:09:46'),
(25, 'button_contents', '{\"image\": \"\", \"title\": \"Download the User App\", \"subtitle\": \"Start your Journey here\"}', 'customer_app_download', '2026-01-29 17:19:13', '2026-01-29 17:19:13'),
(26, 'is_customer_app_download_enabled', '\"1\"', 'customer_app_download', '2026-01-29 17:19:21', '2026-01-29 17:19:21'),
(27, 'button_contents', '{\"image\": \"\", \"title\": \"Download the Delivery / Driver App\", \"subtitle\": \"Start your earning Journey here\"}', 'earn_money', '2026-01-29 17:24:58', '2026-01-29 17:24:58'),
(28, 'is_earn_money_enabled', '\"1\"', 'earn_money', '2026-01-29 17:25:03', '2026-01-29 17:25:03'),
(29, 'intro_contents', '{\"title\": \"**2000+** People Share Their Love\"}', 'testimonial', '2026-01-29 17:25:58', '2026-01-29 17:25:58'),
(30, 'is_testimonial_enabled', '\"1\"', 'testimonial', '2026-01-29 17:26:04', '2026-01-29 17:26:04'),
(31, 'intro_contents', '{\"title\": \"GET ALL UPDATES & EXCITING NEWS\", \"subtitle\": \"Subscribe to out newsletters to receive all the latest activity we provide for you\", \"background_image\": \"\"}', 'newsletter', '2026-01-29 17:27:08', '2026-01-29 17:27:08'),
(32, 'is_newsletter_enabled', '\"1\"', 'newsletter', '2026-01-29 17:27:12', '2026-01-29 17:27:12'),
(33, 'footer_contents', '{\"title\": \"Connect with our social media and other sites to keep up to date\"}', 'footer', '2026-01-29 17:27:42', '2026-01-29 17:27:42'),
(34, 'is_our_services_enabled', '\"1\"', 'our_services', '2026-02-01 09:10:13', '2026-02-01 09:10:13'),
(35, 'solutions', '{\"image\": \"\", \"title\": \"Schedule Trip\", \"status\": 1, \"description\": \"Plan your next adventure with DriveMond\'s trip scheduling features.\"}', 'our_solutions', '2026-02-01 09:26:17', '2026-02-01 09:26:17');

-- --------------------------------------------------------

--
-- Table structure for table `late_return_penalty_notifications`
--

CREATE TABLE `late_return_penalty_notifications` (
  `id` bigint UNSIGNED NOT NULL,
  `trip_request_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sending_notification_at` timestamp NOT NULL,
  `is_notification_sent` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `level_accesses`
--

CREATE TABLE `level_accesses` (
  `id` bigint UNSIGNED NOT NULL,
  `level_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `bid` tinyint(1) NOT NULL DEFAULT '0',
  `see_destination` tinyint(1) NOT NULL DEFAULT '0',
  `see_subtotal` tinyint(1) NOT NULL DEFAULT '0',
  `see_level` tinyint(1) NOT NULL DEFAULT '0',
  `create_hire_request` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `loyalty_points_histories`
--

CREATE TABLE `loyalty_points_histories` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `model` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `points` double NOT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` int UNSIGNED NOT NULL,
  `migration` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '2016_06_01_000001_create_oauth_auth_codes_table', 1),
(2, '2016_06_01_000002_create_oauth_access_tokens_table', 1),
(3, '2016_06_01_000003_create_oauth_refresh_tokens_table', 1),
(4, '2016_06_01_000004_create_oauth_clients_table', 1),
(5, '2016_06_01_000005_create_oauth_personal_access_clients_table', 1),
(6, '2019_08_19_000000_create_failed_jobs_table', 1),
(7, '2019_12_14_000001_create_personal_access_tokens_table', 1),
(8, '2022_11_21_045555_create_payments_table', 1),
(9, '2022_11_21_085924_create_payment_settings_table', 1),
(10, '2023_01_10_114636_create_users_table', 1),
(11, '2023_01_10_115750_create_vehicles_table', 1),
(12, '2023_01_11_073558_create_vehicle_brands_table', 1),
(13, '2023_01_11_113737_create_vehicle_models_table', 1),
(14, '2023_01_12_062420_create_vehicle_categories_table', 1),
(15, '2023_01_16_043100_create_zones_table', 1),
(16, '2023_01_16_052732_create_vehicle_category_zone_table', 1),
(17, '2023_01_16_121122_create_user_levels_table', 1),
(18, '2023_01_17_034948_create_areas_table', 1),
(19, '2023_01_22_121648_create_business_settings_table', 1),
(20, '2023_01_24_070220_create_pick_hours_table', 1),
(21, '2023_01_24_102512_create_area_pick_hour_table', 1),
(22, '2023_01_26_091327_create_banner_setups_table', 1),
(23, '2023_01_26_110443_create_notification_settings_table', 1),
(24, '2023_01_26_111922_create_firebase_push_notifications_table', 1),
(25, '2023_01_28_041320_create_discount_setups_table', 1),
(26, '2023_01_28_103231_create_level_accesses_table', 1),
(27, '2023_01_29_115233_create_social_links_table', 1),
(28, '2023_01_30_063201_create_area_discount_setup_table', 1),
(29, '2023_01_30_114525_create_discount_setup_vehicle_category_table', 1),
(30, '2023_02_01_035306_create_milestone_setups_table', 1),
(31, '2023_02_01_042116_create_bonus_setups_table', 1),
(32, '2023_02_01_060559_create_area_bonus_setup_table', 1),
(33, '2023_02_01_060650_create_bonus_setup_vehicle_category_table', 1),
(34, '2023_02_05_035750_create_coupon_setups_table', 1),
(35, '2023_02_05_051702_create_area_coupon_setup_table', 1),
(36, '2023_02_05_052020_create_coupon_setup_vehicle_category_table', 1),
(37, '2023_02_08_065339_create_roles_table', 1),
(38, '2023_02_09_065343_create_role_user_table', 1),
(39, '2023_02_12_054054_create_trip_fares_table', 1),
(40, '2023_02_12_070009_create_parcel_categories_table', 1),
(41, '2023_02_12_092239_create_parcel_weights_table', 1),
(42, '2023_02_13_091841_create_parcel_fares_table', 1),
(43, '2023_02_15_101259_create_module_accesses_table', 1),
(44, '2023_02_16_093144_create_user_address_table', 1),
(45, '2023_02_19_043220_create_trip_requests_table', 1),
(46, '2023_02_19_070337_create_trip_status_table', 1),
(47, '2023_02_19_071606_create_trip_routes_table', 1),
(48, '2023_02_19_102134_create_fare_biddings_table', 1),
(49, '2023_02_20_114458_create_parcel_fares_parcel_weights_table', 1),
(50, '2023_02_22_063650_create_parcels_table', 1),
(51, '2023_02_22_085634_create_channel_conversations_table', 1),
(52, '2023_02_22_085659_create_channel_lists_table', 1),
(53, '2023_02_22_085727_create_channel_users_table', 1),
(54, '2023_02_22_085752_create_conversation_files_table', 1),
(55, '2023_02_25_035752_create_reviews_table', 1),
(56, '2023_02_27_042506_create_user_last_locations_table', 1),
(57, '2023_03_02_032942_create_activity_logs_table', 1),
(58, '2023_03_06_052511_create_recent_addresses_table', 1),
(59, '2023_03_14_121257_create_fare_bidding_logs_table', 1),
(60, '2023_03_16_074055_add_payer_information_to_payment_requests_table', 1),
(61, '2023_03_18_042902_add_external_redirect_link_to_payment_requests_table', 1),
(62, '2023_03_19_113319_change_column_in_payment_settings_table', 1),
(63, '2023_03_21_072752_add_receiver_information_to_payment_requests_table', 1),
(64, '2023_03_22_040654_create_jobs_table', 1),
(65, '2023_03_22_053625_create_driver_details_table', 1),
(66, '2023_03_22_072803_create_driver_time_logs_table', 1),
(67, '2023_03_23_055542_create_user_level_histories_table', 1),
(68, '2023_03_28_041451_add_column_to_payment_requests', 1),
(69, '2023_03_28_061810_add_payment_platform_column_to_payment_requests', 1),
(70, '2023_03_28_064934_create_rejected_driver_requests_table', 1),
(71, '2023_04_03_075904_create_temp_trip_notifications_table', 1),
(72, '2023_04_10_064449_rename_payment_settings_to_settings_table', 1),
(73, '2023_04_12_071813_aad_additional_data_column_to_settings_table', 1),
(74, '2023_04_29_061951_create_trip_request_fees_table', 1),
(75, '2023_04_29_062028_create_trip_request_coordinates_table', 1),
(76, '2023_04_30_060033_create_trip_request_times_table', 1),
(77, '2023_04_30_094812_create_transactions_table', 1),
(78, '2023_04_30_110147_create_user_accounts_table', 1),
(79, '2023_05_02_112219_create_parcel_user_infomations_table', 1),
(80, '2023_05_02_112241_create_parcel_information_table', 1),
(81, '2023_05_13_102728_create_admin_notifications_table', 1),
(82, '2023_05_13_123323_create_app_notifications_table', 1),
(83, '2023_05_17_091349_create_loyalty_points_histories_table', 1),
(84, '2023_05_18_045035_create_withdraw_methods_table', 1),
(85, '2023_05_18_102011_create_withdraw_requests_table', 1),
(86, '2023_05_25_084737_create_otp_verifications_table', 1),
(87, '2023_05_29_100521_create_time_tracks_table', 1),
(88, '2023_05_29_100531_create_time_logs_table', 1),
(89, '2023_06_08_065011_add_failed_attempt_col_to_users_table', 1),
(90, '2023_06_08_101119_add_more_cols_to_otp_verifications_table', 1),
(91, '2023_07_05_055628_add_is_paused_to_trip_requests_table', 1),
(92, '2023_07_09_060537_add_screenshot_column_to_trip_requests_table', 1),
(93, '2023_07_12_062801_add_is_ignred_column_to_fare_biddings_table', 1),
(94, '2023_07_12_100856_add_is_ignred_column_to_fare_bidding_logs_table', 1),
(95, '2023_11_12_105624_add_base_fare_column_to_parcel_fares_parcel_weights_table', 2),
(96, '2023_11_13_040038_create_zone_wise_default_trip_fares_table', 2),
(97, '2023_11_13_041656_add_zone_wise_default_trip_fare_id_column_to_trip_fares_table', 2),
(98, '0000_00_00_000000_create_websockets_statistics_entries_table', 3),
(99, '2024_02_12_105135_add_column_channelable_to_channel_lists_table', 3),
(100, '2024_02_13_150109_add_column_conversationable_to_channel_conversations_table', 3),
(101, '2024_02_23_180314_change_vin_number_and_transmission_column_type_to_vehicles_table', 3),
(102, '2024_03_23_131340_add_old_identification_image_column_to_users_table', 4),
(103, '2024_03_25_094242_create_cancellation_reasons_table', 4),
(104, '2024_03_25_140744_add_trip_cancelletion_reason_column_to_trip_requests_table', 4),
(105, '2024_04_02_144248_add_full_name_column_to_users_table', 4),
(106, '2024_04_21_142556_add_is_read_column_to_channel_conversations_table', 5),
(107, '2024_04_23_180557_create_applied_coupons_table', 5),
(108, '2024_04_24_132919_add_current_language_key_column_to_users_table', 5),
(109, '2024_04_24_162240_create_discount_setups_table', 5),
(110, '2024_04_25_094825_create_zone_discount_setups_table', 5),
(111, '2024_04_25_094846_create_customer_level_discount_setups_table', 5),
(112, '2024_04_25_094855_create_customer_discount_setups_table', 5),
(113, '2024_04_25_111529_create_vehicle_category_discount_setups_table', 5),
(114, '2024_04_30_145010_add_discount_id_discount_amount_column_to_trip_requests_table', 5),
(115, '2024_05_07_095536_add_transaction_type_to_transactions_table', 5),
(116, '2024_05_26_102832_add_soft_deletes_to_withdraw_methods_table', 6),
(117, '2024_05_26_104421_create_user_withdraw_method_infos_table', 6),
(118, '2024_05_28_100241_add_status_column_to_withdraw_requests_table', 6),
(119, '2024_05_28_154644_add_driver_note_approval_note_denied_note_column_to_withdraw_requests_table', 6),
(120, '2024_05_30_170257_add_method_name_to_user_withdraw_method_infos_table', 6),
(121, '2024_06_25_101513_create_zone_coupon_setups_table', 7),
(122, '2024_06_25_101559_create_customer_coupon_setups_table', 7),
(123, '2024_06_25_101616_create_customer_level_coupon_setups_table', 7),
(124, '2024_06_25_122501_add_multiple_column_to_coupon_setups_table', 7),
(125, '2024_06_25_165330_create_vehicle_category_coupon_setups_table', 7),
(126, '2024_07_26_150807_add_service_to_driver_details_table', 8),
(127, '2024_07_26_162948_add_parcel_weight_capacity_to_vehicles_table', 8),
(128, '2024_07_27_162359_create_external_configurations_table', 9),
(129, '2024_08_25_095629_create_referral_earning_settings_table', 9),
(130, '2024_08_25_151348_add_ref_code_and_ref_by_column_to_users_table', 9),
(131, '2024_08_27_161454_create_referral_customers_table', 9),
(132, '2024_08_27_161506_create_referral_drivers_table', 9),
(133, '2024_08_28_102837_add_referral_earn_column_to_user_accounts_table', 9),
(134, '2024_08_28_150709_create_parcel_cancellation_reasons_table', 9),
(135, '2024_08_28_180146_add_return_fee_column_to_parcel_fares_table', 9),
(136, '2024_08_28_180211_add_return_fee_column_to_parcel_fares_parcel_weights_table', 9),
(137, '2024_09_07_121820_add_return_fee_to_trip_request_fees_table', 9),
(138, '2024_09_08_161850_add_return_fee_and_return_time_to_trip_requests_table', 9),
(139, '2024_09_09_130859_add_due_amount_to_trip_requests_table', 9),
(140, '2024_09_09_135219_add_returning_and_returned_to_trip_status_table', 9),
(141, '2024_09_18_171058_add_cancellation_fee_column_to_parcel_fares_table', 9),
(142, '2024_09_18_171122_add_cancellation_fee_column_to_parcel_fares_parcel_weights_table', 9),
(143, '2024_09_19_153330_add_cancellation_fee_column_to_trip_requests_table', 9),
(144, '2024_09_25_165143_add_extra_fare_status_fee_reason_column_to_zones_table', 10),
(145, '2024_10_06_102727_add_readable_id_to_zones_table', 10),
(146, '2024_10_06_114505_set_readable_id_for_existing_zones', 10),
(147, '2024_10_10_104044_create_parcel_refund_reasons_table', 10),
(148, '2024_10_10_172108_create_parcel_refunds_table', 10),
(149, '2024_10_10_172133_create_parcel_refund_proofs_table', 10),
(150, '2024_10_22_155426_add_extra_fare_fee_and_extra_fare_amount_to_trip_requests_table', 10),
(151, '2024_10_31_140822_add_customer_note_column_to_parcel_refunds_table', 10),
(152, '2024_11_10_124148_create_question_answers_table', 11),
(153, '2024_11_10_124929_create_support_saved_replies_table', 11),
(154, '2024_11_12_094856_change_morphable_columns_nullable_to_channel_conversations_table', 11),
(155, '2024_11_14_121108_change_morphable_columns_nullable_channel_lists_table', 11),
(156, '2024_11_27_092614_add_readable_id_to_roles_table', 12),
(157, '2024_11_27_092915_set_readable_id_for_existing_roles', 12),
(158, '2024_11_28_180147_add_ride_count_parcel_count_column_to_driver_details_table', 12),
(159, '2024_12_01_154702_add_draft_to_vehicles_table', 12),
(160, '2024_12_01_155122_add_is_approved_to_vehicles_table', 12),
(161, '2024_12_02_124425_add_deny_note_to_vehicles_table', 12),
(162, '2024_12_04_124303_add_reference_to_transactions_table', 12),
(163, '2024_12_17_105240_create_safety_precautions_table', 13),
(164, '2024_12_17_124938_create_safety_alert_reasons_table', 13),
(165, '2024_12_21_100757_create_safety_alerts_table', 13),
(166, '2025_02_26_102543_add_type_group_action_column_to_firebase_push_notifications_table', 13),
(167, '2025_03_03_092817_remove_unique_constraint_from_name_column_in_firebase_push_notifications_table', 13),
(168, '2025_03_03_142412_insert_data_to_firebase_push_notifications_table', 13),
(169, '2025_05_15_104430_insert_schedule_trip_data_to_firebase_push_notifications_table', 14),
(170, '2025_05_19_173812_add_is_read_and_notification_type_columns_to_app_notifications_table', 14),
(171, '2025_05_31_151021_add_ride_request_type_scheduled_at_is_notification_sent_sending_notification_at_to_trip_requests_table', 14),
(172, '2025_06_12_110454_add_readable_id_to_transactions_table', 14),
(173, '2025_06_12_122124_set_readable_id_for_existing_transactions', 14),
(174, '2025_07_14_183005_create_send_notifications_table', 14),
(175, '2025_07_17_121110_add_dynamic_values_to_firebase_push_notifications_table', 14),
(176, '2025_08_11_105202_create_surge_pricing_table', 15),
(177, '2025_08_11_105214_create_surge_pricing_zones_table', 15),
(178, '2025_08_11_105303_create_surge_pricing_time_slots_table', 15),
(179, '2025_08_11_110454_create_surge_pricing_service_categories_table', 15),
(180, '2025_08_23_170522_add_surge_percentage_to_trip_requests_table', 15),
(181, '2025_09_24_113827_create_wallet_bonuses_table', 16),
(182, '2025_09_29_152222_add_added_bonus_to_transactions_table', 16),
(183, '2025_10_07_150135_insert_cash_in_hand_limit_exceeds_data_to_firebase_push_notifications_table', 16),
(184, '2025_10_13_173152_insert_digital_payment_successful_data_to_firebase_push_notifications_table', 16),
(185, '2025_10_13_190748_change_dynamic_value_of_admin_collected_cash_to_firebase_push_notifications_table', 16),
(186, '2025_10_16_102042_create_late_return_penalty_notifications_table', 16),
(187, '2025_10_16_135726_insert_parcel_return_penalty_data_to_firebase_push_notifications_table', 16),
(188, '2025_10_18_155206_insert_parcel_canceled_data_for_driver_to_firebase_push_notifications_table', 16),
(189, '2025_10_19_125225_insert_parcel_canceled_after_trip_started_data_to_firebase_push_notifications_table', 16),
(190, '2025_10_21_140346_insert_fund_added_digitally_data_to_firebase_push_notifications_table', 16),
(191, '2025_10_21_161049_add_trx_type_to_transactions_table', 16),
(192, '2026_01_11_150028_create_landing_page_sections_table', 17),
(193, '2026_01_11_172655_move_landing_pages_data_to_landing_pages_table', 17),
(194, '2026_01_13_110421_create_blog_settings_table', 17),
(195, '2026_01_14_100911_create_blog_categories_table', 17),
(196, '2026_01_14_154749_create_blogs_table', 17),
(197, '2026_01_15_124701_create_blog_drafts_table', 17),
(198, '2026_01_18_163608_create_driver_identity_verifications_table', 17),
(199, '2026_01_19_000149_add_is_verified_base_image_verified_image_is_suspended_suspend_reason_trigger_verification_at_to_driver_details_table', 17),
(200, '2026_01_21_162254_create_ai_settings_table', 17),
(201, '2026_01_26_125509_create_newsletter_subscriptions_table', 17),
(202, '2026_01_29_120205_insert_face_verification_completed_successfully_data_to_firebase_push_notifications_table', 17);

-- --------------------------------------------------------

--
-- Table structure for table `milestone_setups`
--

CREATE TABLE `milestone_setups` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_level_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `driver_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `driver_level_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `thumbnail` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `banner` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `reward_type` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `reward_amount` decimal(30,2) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `challenge_type` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_count` decimal(5,2) NOT NULL,
  `referral_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `module_accesses`
--

CREATE TABLE `module_accesses` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `role_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `module_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `view` tinyint(1) NOT NULL DEFAULT '0',
  `add` tinyint(1) NOT NULL DEFAULT '0',
  `update` tinyint(1) NOT NULL DEFAULT '0',
  `delete` tinyint(1) NOT NULL DEFAULT '0',
  `log` tinyint(1) NOT NULL DEFAULT '0',
  `export` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `newsletter_subscriptions`
--

CREATE TABLE `newsletter_subscriptions` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` tinyint UNSIGNED NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notification_settings`
--

CREATE TABLE `notification_settings` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `push` tinyint(1) NOT NULL DEFAULT '0',
  `email` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notification_settings`
--

INSERT INTO `notification_settings` (`id`, `name`, `push`, `email`, `created_at`, `updated_at`) VALUES
(4, 'privacy_policy', 1, 0, '2023-11-09 17:09:10', '2023-11-09 17:09:16'),
(5, 'terms_and_conditions', 1, 0, '2023-11-09 17:09:20', '2023-11-12 11:16:39'),
(6, 'legal', 1, 0, '2024-07-14 02:57:46', '2024-07-14 02:57:46');

-- --------------------------------------------------------

--
-- Table structure for table `oauth_access_tokens`
--

CREATE TABLE `oauth_access_tokens` (
  `id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `client_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `scopes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `revoked` tinyint(1) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `oauth_auth_codes`
--

CREATE TABLE `oauth_auth_codes` (
  `id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `client_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `scopes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `revoked` tinyint(1) NOT NULL,
  `expires_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `oauth_clients`
--

CREATE TABLE `oauth_clients` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint UNSIGNED DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `secret` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provider` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `redirect` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `personal_access_client` tinyint(1) NOT NULL,
  `password_client` tinyint(1) NOT NULL,
  `revoked` tinyint(1) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `oauth_clients`
--

INSERT INTO `oauth_clients` (`id`, `user_id`, `name`, `secret`, `provider`, `redirect`, `personal_access_client`, `password_client`, `revoked`, `created_at`, `updated_at`) VALUES
('9a878b41-fc9e-4789-a835-0b3ebe060778', NULL, 'Laravel Personal Access Client', 'CId0ouRzX08kOyn8IWdbjegiUofmzJKLvOTGXzqU', NULL, 'http://localhost', 1, 0, 0, '2023-11-04 09:44:36', '2023-11-04 09:44:36'),
('9a878b42-03fd-4e56-923f-3f098de9188a', NULL, 'Laravel Password Grant Client', 'c8BTROa9IggqR1cG60e4ckfiBiXqQyJ3WU9AXxEo', 'users', 'http://localhost', 0, 1, 0, '2023-11-04 09:44:36', '2023-11-04 09:44:36');

-- --------------------------------------------------------

--
-- Table structure for table `oauth_personal_access_clients`
--

CREATE TABLE `oauth_personal_access_clients` (
  `id` bigint UNSIGNED NOT NULL,
  `client_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `oauth_personal_access_clients`
--

INSERT INTO `oauth_personal_access_clients` (`id`, `client_id`, `created_at`, `updated_at`) VALUES
(1, '9a878b41-fc9e-4789-a835-0b3ebe060778', '2023-11-04 09:44:36', '2023-11-04 09:44:36');

-- --------------------------------------------------------

--
-- Table structure for table `oauth_refresh_tokens`
--

CREATE TABLE `oauth_refresh_tokens` (
  `id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `access_token_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `revoked` tinyint(1) NOT NULL,
  `expires_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `otp_verifications`
--

CREATE TABLE `otp_verifications` (
  `id` bigint UNSIGNED NOT NULL,
  `phone_or_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `otp` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_temp_blocked` tinyint(1) NOT NULL DEFAULT '0',
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `failed_attempt` int NOT NULL DEFAULT '0',
  `blocked_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `parcels`
--

CREATE TABLE `parcels` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `trip_request_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sender_person_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sender_person_phone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sender_address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `receiver_person_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `receiver_person_phone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `receiver_address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `parcel_category_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `parcel_weight_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `parcel_cancellation_reasons`
--

CREATE TABLE `parcel_cancellation_reasons` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `cancellation_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `parcel_categories`
--

CREATE TABLE `parcel_categories` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `parcel_fares`
--

CREATE TABLE `parcel_fares` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `zone_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `base_fare` decimal(8,2) NOT NULL,
  `return_fee` double NOT NULL DEFAULT '0',
  `cancellation_fee` double NOT NULL DEFAULT '0',
  `base_fare_per_km` decimal(8,2) NOT NULL,
  `cancellation_fee_percent` decimal(8,2) NOT NULL,
  `min_cancellation_fee` decimal(8,2) NOT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `parcel_fares_parcel_weights`
--

CREATE TABLE `parcel_fares_parcel_weights` (
  `id` bigint UNSIGNED NOT NULL,
  `parcel_fare_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `parcel_weight_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `parcel_category_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `base_fare` double NOT NULL DEFAULT '0',
  `return_fee` double NOT NULL DEFAULT '0',
  `cancellation_fee` double NOT NULL DEFAULT '0',
  `fare_per_km` decimal(8,2) NOT NULL,
  `zone_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `parcel_information`
--

CREATE TABLE `parcel_information` (
  `id` bigint UNSIGNED NOT NULL,
  `parcel_category_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `trip_request_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payer` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `weight` double DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `parcel_refunds`
--

CREATE TABLE `parcel_refunds` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `readable_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trip_request_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `coupon_setup_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `parcel_approximate_price` decimal(23,6) NOT NULL DEFAULT '0.000000',
  `refund_amount_by_admin` decimal(23,6) NOT NULL DEFAULT '0.000000',
  `reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `approval_note` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deny_note` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `note` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_note` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `refund_method` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `parcel_refund_proofs`
--

CREATE TABLE `parcel_refund_proofs` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `parcel_refund_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `attachment` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `parcel_refund_reasons`
--

CREATE TABLE `parcel_refund_reasons` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `parcel_user_infomations`
--

CREATE TABLE `parcel_user_infomations` (
  `id` bigint UNSIGNED NOT NULL,
  `trip_request_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact_number` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `parcel_weights`
--

CREATE TABLE `parcel_weights` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `min_weight` decimal(10,2) NOT NULL DEFAULT '0.00',
  `max_weight` decimal(10,2) NOT NULL DEFAULT '0.00',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_requests`
--

CREATE TABLE `payment_requests` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payer_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `receiver_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_amount` decimal(24,2) NOT NULL DEFAULT '0.00',
  `gateway_callback_url` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hook` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transaction_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `currency_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'USD',
  `payment_method` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `additional_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_paid` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `payer_information` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `external_redirect_link` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `receiver_information` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `attribute_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attribute` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_platform` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `personal_access_tokens`
--

CREATE TABLE `personal_access_tokens` (
  `id` bigint UNSIGNED NOT NULL,
  `tokenable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pick_hours`
--

CREATE TABLE `pick_hours` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `duration_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `extra_charge` int DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `week_days` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `zone_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `question_answers`
--

CREATE TABLE `question_answers` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `question` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `answer` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `question_answer_for` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'driver',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `recent_addresses`
--

CREATE TABLE `recent_addresses` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zone_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pickup_coordinates` point DEFAULT NULL,
  `pickup_address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `destination_coordinates` point DEFAULT NULL,
  `destination_address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `referral_customers`
--

CREATE TABLE `referral_customers` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ref_by` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ref_by_earning_amount` double NOT NULL DEFAULT '0',
  `customer_discount_amount` double NOT NULL DEFAULT '0',
  `customer_discount_amount_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_discount_validity` int NOT NULL DEFAULT '0',
  `customer_discount_validity_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_used` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `referral_drivers`
--

CREATE TABLE `referral_drivers` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `driver_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ref_by` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ref_by_earning_amount` double NOT NULL DEFAULT '0',
  `driver_earning_amount` double NOT NULL DEFAULT '0',
  `is_used` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `referral_earning_settings`
--

CREATE TABLE `referral_earning_settings` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `key_name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` json NOT NULL,
  `settings_type` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rejected_driver_requests`
--

CREATE TABLE `rejected_driver_requests` (
  `id` bigint UNSIGNED NOT NULL,
  `trip_request_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `id` bigint UNSIGNED NOT NULL,
  `trip_request_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `given_by` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `received_by` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trip_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rating` int NOT NULL DEFAULT '1',
  `feedback` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `images` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_saved` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `readable_id` int DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `modules` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `role_user`
--

CREATE TABLE `role_user` (
  `id` bigint UNSIGNED NOT NULL,
  `role_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `safety_alerts`
--

CREATE TABLE `safety_alerts` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `trip_request_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sent_by` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` json DEFAULT NULL,
  `comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `alert_location` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `resolved_location` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `number_of_alert` int NOT NULL DEFAULT '1',
  `resolved_by` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trip_status_when_make_alert` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `safety_alert_reasons`
--

CREATE TABLE `safety_alert_reasons` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason_for_whom` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `safety_precautions`
--

CREATE TABLE `safety_precautions` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `for_whom` json NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `send_notifications`
--

CREATE TABLE `send_notifications` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `targeted_users` json NOT NULL,
  `image` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_active` smallint UNSIGNED NOT NULL DEFAULT '1' COMMENT '1=active, 0=inactive',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `key_name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `live_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `test_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `settings_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mode` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'live',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `additional_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `key_name`, `live_values`, `test_values`, `settings_type`, `mode`, `is_active`, `created_at`, `updated_at`, `additional_data`) VALUES
('070c6bbd-d777-11ed-96f4-0c7a158e4469', 'twilio', '{\"gateway\":\"twilio\",\"mode\":\"live\",\"status\":\"0\",\"sid\":null,\"messaging_service_sid\":null,\"token\":null,\"from\":null,\"otp_template\":null}', '{\"gateway\":\"twilio\",\"mode\":\"live\",\"status\":\"0\",\"sid\":null,\"messaging_service_sid\":null,\"token\":null,\"from\":null,\"otp_template\":null}', 'sms_config', 'live', 0, NULL, '2023-11-20 00:22:10', NULL),
('070c766c-d777-11ed-96f4-0c7a158e4469', '2factor', '{\"gateway\":\"2factor\",\"mode\":\"live\",\"status\":\"0\",\"api_key\":null}', '{\"gateway\":\"2factor\",\"mode\":\"live\",\"status\":\"0\",\"api_key\":null}', 'sms_config', 'live', 0, NULL, '2023-11-20 00:21:59', NULL),
('0d8a9308-d6a5-11ed-962c-0c7a158e4469', 'mercadopago', '{\"gateway\":\"mercadopago\",\"mode\":\"test\",\"status\":\"0\",\"access_token\":null,\"public_key\":null}', '{\"gateway\":\"mercadopago\",\"mode\":\"test\",\"status\":\"0\",\"access_token\":null,\"public_key\":null}', 'payment_config', 'test', 0, NULL, '2023-11-20 00:14:31', '{\"gateway_title\":null,\"gateway_image\":\"2023-11-20-655ae66916a34.png\"}'),
('0d8a9e49-d6a5-11ed-962c-0c7a158e4469', 'liqpay', '{\"gateway\":\"liqpay\",\"mode\":\"test\",\"status\":\"0\",\"private_key\":null,\"public_key\":null}', '{\"gateway\":\"liqpay\",\"mode\":\"test\",\"status\":\"0\",\"private_key\":null,\"public_key\":null}', 'payment_config', 'test', 0, NULL, '2023-11-20 00:14:42', '{\"gateway_title\":null,\"gateway_image\":\"2023-11-20-655ae64c304b9.png\"}'),
('101befdf-d44b-11ed-8564-0c7a158e4469', 'paypal', '{\"gateway\":\"paypal\",\"mode\":\"test\",\"status\":\"0\",\"client_id\":null,\"client_secret\":null}', '{\"gateway\":\"paypal\",\"mode\":\"test\",\"status\":\"0\",\"client_id\":null,\"client_secret\":null}', 'payment_config', 'test', 0, NULL, '2023-11-20 00:15:14', '{\"gateway_title\":null,\"gateway_image\":\"2023-11-20-655ae611d7c91.png\"}'),
('1821029f-d776-11ed-96f4-0c7a158e4469', 'msg91', '{\"gateway\":\"msg91\",\"mode\":\"live\",\"status\":\"0\",\"template_id\":null,\"auth_key\":null}', '{\"gateway\":\"msg91\",\"mode\":\"live\",\"status\":\"0\",\"template_id\":null,\"auth_key\":null}', 'sms_config', 'live', 0, NULL, '2023-11-20 00:22:17', NULL),
('18210f2b-d776-11ed-96f4-0c7a158e4469', 'nexmo', '{\"gateway\":\"nexmo\",\"mode\":\"live\",\"status\":\"0\",\"api_key\":\"\",\"api_secret\":\"\",\"token\":\"\",\"from\":\"\",\"otp_template\":\"\"}', '{\"gateway\":\"nexmo\",\"mode\":\"live\",\"status\":\"0\",\"api_key\":\"\",\"api_secret\":\"\",\"token\":\"\",\"from\":\"\",\"otp_template\":\"\"}', 'sms_config', 'live', 0, NULL, '2023-04-10 02:14:44', NULL),
('2767d142-d6a1-11ed-962c-0c7a158e4469', 'paytm', '{\"gateway\":\"paytm\",\"mode\":\"test\",\"status\":\"0\",\"merchant_key\":null,\"merchant_id\":null,\"merchant_website_link\":null}', '{\"gateway\":\"paytm\",\"mode\":\"test\",\"status\":\"0\",\"merchant_key\":null,\"merchant_id\":null,\"merchant_website_link\":null}', 'payment_config', 'test', 0, NULL, '2023-11-20 00:15:26', '{\"gateway_title\":null,\"gateway_image\":\"2023-11-20-655ae718cd837.png\"}'),
('4593b25c-d6a1-11ed-962c-0c7a158e4469', 'paytabs', '{\"gateway\":\"paytabs\",\"mode\":\"test\",\"status\":\"0\",\"profile_id\":null,\"server_key\":null,\"base_url\":null}', '{\"gateway\":\"paytabs\",\"mode\":\"test\",\"status\":\"0\",\"profile_id\":null,\"server_key\":null,\"base_url\":null}', 'payment_config', 'test', 0, NULL, '2023-11-20 00:15:41', '{\"gateway_title\":null,\"gateway_image\":\"2023-11-20-655ae7325e9b7.png\"}'),
('4e9b8dfb-e7d1-11ed-a559-0c7a158e4469', 'bkash', '{\"gateway\":\"bkash\",\"mode\":\"test\",\"status\":\"0\",\"app_key\":null,\"app_secret\":null,\"username\":null,\"password\":null}', '{\"gateway\":\"bkash\",\"mode\":\"test\",\"status\":\"0\",\"app_key\":null,\"app_secret\":null,\"username\":null,\"password\":null}', 'payment_config', 'test', 0, NULL, '2023-11-20 00:15:56', '{\"gateway_title\":null,\"gateway_image\":\"2023-11-20-655ae74591a98.png\"}'),
('998ccc62-d6a0-11ed-962c-0c7a158e4469', 'stripe', '{\"gateway\":\"stripe\",\"mode\":\"test\",\"status\":\"0\",\"api_key\":null,\"published_key\":null}', '{\"gateway\":\"stripe\",\"mode\":\"test\",\"status\":\"0\",\"api_key\":null,\"published_key\":null}', 'payment_config', 'test', 0, NULL, '2023-11-20 00:16:11', '{\"gateway_title\":null,\"gateway_image\":\"2023-11-20-655ae761a1905.png\"}'),
('ad5af1c1-d6a2-11ed-962c-0c7a158e4469', 'razor_pay', '{\"gateway\":\"razor_pay\",\"mode\":\"test\",\"status\":\"0\",\"api_key\":null,\"api_secret\":null}', '{\"gateway\":\"razor_pay\",\"mode\":\"test\",\"status\":\"0\",\"api_key\":null,\"api_secret\":null}', 'payment_config', 'test', 0, NULL, '2023-11-20 00:16:26', '{\"gateway_title\":null,\"gateway_image\":\"2023-11-20-655ae7733cc68.png\"}'),
('ad5b02a0-d6a2-11ed-962c-0c7a158e4469', 'senang_pay', '{\"gateway\":\"senang_pay\",\"mode\":\"test\",\"status\":\"0\",\"callback_url\":null,\"secret_key\":null,\"merchant_id\":null}', '{\"gateway\":\"senang_pay\",\"mode\":\"test\",\"status\":\"0\",\"callback_url\":null,\"secret_key\":null,\"merchant_id\":null}', 'payment_config', 'test', 0, NULL, '2023-11-20 00:17:04', '{\"gateway_title\":null,\"gateway_image\":\"2023-11-20-655ae78baeb8d.png\"}'),
('b8992bd4-d6a0-11ed-962c-0c7a158e4469', 'paymob_accept', '{\"gateway\":\"paymob_accept\",\"mode\":\"test\",\"status\":\"0\",\"callback_url\":null,\"api_key\":null,\"iframe_id\":null,\"integration_id\":null,\"hmac\":null}', '{\"gateway\":\"paymob_accept\",\"mode\":\"test\",\"status\":\"0\",\"callback_url\":null,\"api_key\":null,\"iframe_id\":null,\"integration_id\":null,\"hmac\":null}', 'payment_config', 'test', 0, NULL, '2023-11-20 00:16:49', '{\"gateway_title\":null,\"gateway_image\":\"2023-11-20-655ae7c0c7bd2.png\"}'),
('cb0081ce-d775-11ed-96f4-0c7a158e4469', 'releans', '{\"gateway\":\"releans\",\"mode\":\"live\",\"status\":0,\"api_key\":\"\",\"from\":\"\",\"otp_template\":\"\"}', '{\"gateway\":\"releans\",\"mode\":\"live\",\"status\":0,\"api_key\":\"\",\"from\":\"\",\"otp_template\":\"\"}', 'sms_config', 'live', 0, NULL, '2023-04-10 02:14:44', NULL),
('d4f3f5f1-d6a0-11ed-962c-0c7a158e4469', 'flutterwave', '{\"gateway\":\"flutterwave\",\"mode\":\"test\",\"status\":\"0\",\"secret_key\":null,\"public_key\":null,\"hash\":null}', '{\"gateway\":\"flutterwave\",\"mode\":\"test\",\"status\":\"0\",\"secret_key\":null,\"public_key\":null,\"hash\":null}', 'payment_config', 'test', 0, NULL, '2023-11-20 00:17:19', '{\"gateway_title\":null,\"gateway_image\":\"2023-11-20-655ae81c421b7.png\"}'),
('d822f1a5-c864-11ed-ac7a-0c7a158e4469', 'paystack', '{\"gateway\":\"paystack\",\"mode\":\"test\",\"status\":\"0\",\"public_key\":null,\"secret_key\":null,\"merchant_email\":null}', '{\"gateway\":\"paystack\",\"mode\":\"test\",\"status\":\"0\",\"public_key\":null,\"secret_key\":null,\"merchant_email\":null}', 'payment_config', 'test', 0, NULL, '2023-11-20 00:14:18', '{\"gateway_title\":null,\"gateway_image\":\"2023-11-20-655ae7d9bec0f.png\"}'),
('ea346efe-cdda-11ed-affe-0c7a158e4469', 'ssl_commerz', '{\"gateway\":\"ssl_commerz\",\"mode\":\"test\",\"status\":\"0\",\"store_id\":null,\"store_password\":null}', '{\"gateway\":\"ssl_commerz\",\"mode\":\"test\",\"status\":\"0\",\"store_id\":null,\"store_password\":null}', 'payment_config', 'test', 0, NULL, '2023-11-20 00:13:58', '{\"gateway_title\":null,\"gateway_image\":\"2023-11-20-655ae7e7231f5.png\"}');

-- --------------------------------------------------------

--
-- Table structure for table `social_links`
--

CREATE TABLE `social_links` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `link` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `support_saved_replies`
--

CREATE TABLE `support_saved_replies` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `topic` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `answer` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `surge_pricing`
--

CREATE TABLE `surge_pricing` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `readable_id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `surge_pricing_for` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `increase_for_all_vehicles` tinyint(1) NOT NULL DEFAULT '1',
  `all_vehicle_surge_percent` double DEFAULT NULL,
  `increase_for_all_parcels` tinyint(1) NOT NULL DEFAULT '1',
  `all_parcel_surge_percent` double DEFAULT NULL,
  `zone_setup_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `schedule` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `customer_note` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `surge_pricing_service_categories`
--

CREATE TABLE `surge_pricing_service_categories` (
  `id` bigint UNSIGNED NOT NULL,
  `surge_pricing_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `service_category_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `service_category_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `surge_multiplier` double NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `surge_pricing_time_slots`
--

CREATE TABLE `surge_pricing_time_slots` (
  `id` bigint UNSIGNED NOT NULL,
  `surge_pricing_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_date` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `end_date` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `selected_days` json DEFAULT NULL,
  `slots` json NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `surge_pricing_zones`
--

CREATE TABLE `surge_pricing_zones` (
  `surge_pricing_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `zone_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `temp_trip_notifications`
--

CREATE TABLE `temp_trip_notifications` (
  `id` bigint UNSIGNED NOT NULL,
  `trip_request_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `time_logs`
--

CREATE TABLE `time_logs` (
  `id` bigint UNSIGNED NOT NULL,
  `time_track_id` bigint UNSIGNED NOT NULL,
  `online_at` time NOT NULL,
  `offline_at` time DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `time_tracks`
--

CREATE TABLE `time_tracks` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` date NOT NULL,
  `total_online` int NOT NULL DEFAULT '0',
  `total_offline` int NOT NULL DEFAULT '0',
  `total_idle` int NOT NULL DEFAULT '0',
  `total_driving` int NOT NULL DEFAULT '0',
  `last_ride_started_at` time DEFAULT NULL,
  `last_ride_completed_at` time DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `readable_id` int DEFAULT NULL,
  `attribute_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attribute` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `debit` decimal(24,2) NOT NULL DEFAULT '0.00',
  `credit` decimal(24,2) NOT NULL DEFAULT '0.00',
  `balance` decimal(24,2) NOT NULL DEFAULT '0.00',
  `added_bonus` decimal(24,2) NOT NULL DEFAULT '0.00',
  `user_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `account` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transaction_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `trx_ref_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trx_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `trip_fares`
--

CREATE TABLE `trip_fares` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `zone_wise_default_trip_fare_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `zone_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `vehicle_category_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `base_fare` decimal(8,2) NOT NULL,
  `base_fare_per_km` decimal(8,2) NOT NULL,
  `waiting_fee_per_min` decimal(8,2) NOT NULL,
  `cancellation_fee_percent` decimal(8,2) NOT NULL,
  `min_cancellation_fee` decimal(8,2) NOT NULL,
  `idle_fee_per_min` decimal(8,2) NOT NULL,
  `trip_delay_fee_per_min` decimal(8,2) NOT NULL,
  `penalty_fee_for_cancel` decimal(8,2) NOT NULL,
  `fee_add_to_next` decimal(8,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `trip_requests`
--

CREATE TABLE `trip_requests` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ref_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `driver_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vehicle_category_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vehicle_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zone_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `area_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estimated_fare` decimal(23,3) NOT NULL,
  `actual_fare` decimal(23,3) NOT NULL DEFAULT '0.000',
  `estimated_distance` double(8,2) NOT NULL,
  `paid_fare` decimal(23,3) NOT NULL DEFAULT '0.000',
  `return_fee` decimal(23,3) NOT NULL DEFAULT '0.000',
  `cancellation_fee` decimal(23,3) NOT NULL DEFAULT '0.000',
  `extra_fare_fee` decimal(23,3) NOT NULL DEFAULT '0.000',
  `extra_fare_amount` decimal(23,3) NOT NULL DEFAULT '0.000',
  `surge_percentage` double DEFAULT '0',
  `return_time` datetime DEFAULT NULL,
  `due_amount` decimal(23,3) NOT NULL DEFAULT '0.000',
  `actual_distance` double(8,2) DEFAULT NULL,
  `encoded_polyline` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `accepted_by` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_method` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'unpaid',
  `coupon_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `coupon_amount` decimal(23,3) DEFAULT NULL,
  `discount_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `discount_amount` decimal(23,3) DEFAULT NULL,
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `entrance` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `otp` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rise_request_count` int NOT NULL DEFAULT '0',
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ride_request_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `scheduled_at` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `current_status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `is_notification_sent` tinyint(1) NOT NULL DEFAULT '1',
  `sending_notification_at` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `checked` tinyint(1) NOT NULL DEFAULT '0',
  `tips` double NOT NULL DEFAULT '0',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_paused` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'trip_pause_status',
  `map_screenshot` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trip_cancellation_reason` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `trip_request_coordinates`
--

CREATE TABLE `trip_request_coordinates` (
  `id` bigint UNSIGNED NOT NULL,
  `trip_request_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `pickup_coordinates` point DEFAULT NULL,
  `pickup_address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `destination_coordinates` point DEFAULT NULL,
  `is_reached_destination` tinyint(1) NOT NULL DEFAULT '0',
  `destination_address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `intermediate_coordinates` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `int_coordinate_1` point DEFAULT NULL,
  `is_reached_1` tinyint(1) NOT NULL DEFAULT '0',
  `int_coordinate_2` point DEFAULT NULL,
  `is_reached_2` tinyint(1) NOT NULL DEFAULT '0',
  `intermediate_addresses` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `start_coordinates` point DEFAULT NULL,
  `drop_coordinates` point DEFAULT NULL,
  `driver_accept_coordinates` point DEFAULT NULL,
  `customer_request_coordinates` point DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `trip_request_fees`
--

CREATE TABLE `trip_request_fees` (
  `id` bigint UNSIGNED NOT NULL,
  `trip_request_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `cancellation_fee` decimal(23,3) NOT NULL DEFAULT '0.000',
  `return_fee` decimal(23,3) NOT NULL DEFAULT '0.000',
  `cancelled_by` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `waiting_fee` decimal(23,3) NOT NULL DEFAULT '0.000',
  `waited_by` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `idle_fee` decimal(23,3) NOT NULL DEFAULT '0.000',
  `delay_fee` decimal(23,3) NOT NULL DEFAULT '0.000',
  `delayed_by` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vat_tax` decimal(23,3) NOT NULL DEFAULT '0.000',
  `tips` decimal(23,3) NOT NULL DEFAULT '0.000',
  `admin_commission` decimal(23,3) NOT NULL DEFAULT '0.000',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `trip_request_times`
--

CREATE TABLE `trip_request_times` (
  `id` bigint UNSIGNED NOT NULL,
  `trip_request_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `estimated_time` double(10,4) NOT NULL,
  `actual_time` double(8,2) DEFAULT NULL,
  `waiting_time` double(8,2) DEFAULT NULL,
  `delay_time` double(8,2) DEFAULT NULL,
  `idle_timestamp` timestamp NULL DEFAULT NULL,
  `idle_time` double(8,2) DEFAULT NULL,
  `driver_arrival_time` double(8,2) DEFAULT NULL,
  `driver_arrival_timestamp` timestamp NULL DEFAULT NULL,
  `driver_arrives_at` timestamp NULL DEFAULT NULL,
  `customer_arrives_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `trip_routes`
--

CREATE TABLE `trip_routes` (
  `id` bigint UNSIGNED NOT NULL,
  `trip_request_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `coordinates` point NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `trip_status`
--

CREATE TABLE `trip_status` (
  `id` bigint UNSIGNED NOT NULL,
  `trip_request_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `driver_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pending` timestamp NULL DEFAULT NULL,
  `accepted` timestamp NULL DEFAULT NULL,
  `out_for_pickup` timestamp NULL DEFAULT NULL,
  `picked_up` timestamp NULL DEFAULT NULL,
  `ongoing` timestamp NULL DEFAULT NULL,
  `completed` timestamp NULL DEFAULT NULL,
  `cancelled` timestamp NULL DEFAULT NULL,
  `failed` timestamp NULL DEFAULT NULL,
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `returning` timestamp NULL DEFAULT NULL,
  `returned` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `full_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_level_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `first_name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `identification_number` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `identification_type` varchar(25) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `identification_image` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `old_identification_image` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `other_documents` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `profile_image` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fcm_token` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone_verified_at` timestamp NULL DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `loyalty_points` double NOT NULL DEFAULT '0',
  `password` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ref_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_type` varchar(25) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'customer',
  `role_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remember_token` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '0',
  `current_language_key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `failed_attempt` int NOT NULL DEFAULT '0',
  `is_temp_blocked` tinyint(1) NOT NULL DEFAULT '0',
  `blocked_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_accounts`
--

CREATE TABLE `user_accounts` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payable_balance` decimal(24,2) NOT NULL DEFAULT '0.00',
  `receivable_balance` decimal(24,2) NOT NULL DEFAULT '0.00',
  `received_balance` decimal(24,2) NOT NULL DEFAULT '0.00',
  `pending_balance` decimal(24,2) NOT NULL DEFAULT '0.00',
  `wallet_balance` decimal(24,2) NOT NULL DEFAULT '0.00',
  `total_withdrawn` decimal(24,2) NOT NULL DEFAULT '0.00',
  `referral_earn` double NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_address`
--

CREATE TABLE `user_address` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zone_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `latitude` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `longitude` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `street` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `house` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zip_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_person_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_person_phone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `address_label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_last_locations`
--

CREATE TABLE `user_last_locations` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `latitude` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `longitude` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zone_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_levels`
--

CREATE TABLE `user_levels` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sequence` int NOT NULL,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `reward_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `reward_amount` decimal(8,2) DEFAULT NULL,
  `image` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `targeted_ride` int NOT NULL,
  `targeted_ride_point` int NOT NULL,
  `targeted_amount` double NOT NULL,
  `targeted_amount_point` int NOT NULL,
  `targeted_cancel` int NOT NULL,
  `targeted_cancel_point` int NOT NULL,
  `targeted_review` int NOT NULL,
  `targeted_review_point` int NOT NULL,
  `user_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_level_histories`
--

CREATE TABLE `user_level_histories` (
  `id` bigint UNSIGNED NOT NULL,
  `user_level_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `completed_ride` int NOT NULL DEFAULT '0',
  `ride_reward_status` tinyint(1) NOT NULL DEFAULT '0',
  `total_amount` decimal(8,2) NOT NULL DEFAULT '0.00',
  `amount_reward_status` tinyint(1) NOT NULL DEFAULT '0',
  `cancellation_rate` decimal(8,2) NOT NULL DEFAULT '0.00',
  `cancellation_reward_status` tinyint(1) NOT NULL DEFAULT '0',
  `reviews` int NOT NULL DEFAULT '0',
  `reviews_reward_status` tinyint(1) NOT NULL DEFAULT '0',
  `is_level_reward_granted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_withdraw_method_infos`
--

CREATE TABLE `user_withdraw_method_infos` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `method_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `withdraw_method_id` bigint UNSIGNED NOT NULL,
  `method_info` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vehicles`
--

CREATE TABLE `vehicles` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ref_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `brand_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `category_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `licence_plate_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `licence_expire_date` date NOT NULL,
  `vin_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transmission` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `parcel_weight_capacity` double DEFAULT NULL,
  `fuel_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ownership` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `driver_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `documents` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '0',
  `draft` json DEFAULT NULL,
  `vehicle_request_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'approved',
  `deny_note` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vehicle_brands`
--

CREATE TABLE `vehicle_brands` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vehicle_categories`
--

CREATE TABLE `vehicle_categories` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vehicle_category_coupon_setups`
--

CREATE TABLE `vehicle_category_coupon_setups` (
  `vehicle_category_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `coupon_setup_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vehicle_category_discount_setups`
--

CREATE TABLE `vehicle_category_discount_setups` (
  `vehicle_category_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `discount_setup_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vehicle_category_zone`
--

CREATE TABLE `vehicle_category_zone` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `zone_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vehicle_category_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `base_fare` decimal(8,2) NOT NULL,
  `base_fare_per_km` decimal(8,2) NOT NULL,
  `waiting_fee_per_min` decimal(8,2) NOT NULL,
  `cancellation_fee_percent` decimal(8,2) NOT NULL,
  `min_cancellation_fee` decimal(8,2) NOT NULL,
  `idle_fee_per_min` decimal(8,2) NOT NULL,
  `trip_delay_fee_per_min` decimal(8,2) NOT NULL,
  `penalty_fee_for_cancel` decimal(8,2) NOT NULL,
  `fee_add_to_next` decimal(8,2) NOT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vehicle_models`
--

CREATE TABLE `vehicle_models` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `brand_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `seat_capacity` int NOT NULL,
  `maximum_weight` decimal(8,2) NOT NULL,
  `hatch_bag_capacity` int NOT NULL,
  `engine` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wallet_bonuses`
--

CREATE TABLE `wallet_bonuses` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bonus_amount` decimal(8,2) NOT NULL DEFAULT '0.00',
  `amount_type` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'amount',
  `min_add_amount` decimal(8,2) NOT NULL DEFAULT '0.00',
  `max_bonus_amount` decimal(8,2) NOT NULL DEFAULT '0.00',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `user_type` json NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `websockets_statistics_entries`
--

CREATE TABLE `websockets_statistics_entries` (
  `id` int UNSIGNED NOT NULL,
  `app_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `peak_connection_count` int NOT NULL,
  `websocket_message_count` int NOT NULL,
  `api_message_count` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `withdraw_methods`
--

CREATE TABLE `withdraw_methods` (
  `id` bigint UNSIGNED NOT NULL,
  `method_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `method_fields` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `withdraw_requests`
--

CREATE TABLE `withdraw_requests` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` double NOT NULL DEFAULT '0',
  `method_id` bigint UNSIGNED NOT NULL,
  `method_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `driver_note` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `approval_note` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `denied_note` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `rejection_cause` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_approved` tinyint(1) DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `zones`
--

CREATE TABLE `zones` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `readable_id` int DEFAULT NULL,
  `coordinates` polygon DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `extra_fare_status` tinyint(1) NOT NULL DEFAULT '0',
  `extra_fare_fee` double NOT NULL DEFAULT '0',
  `extra_fare_reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `zone_coupon_setups`
--

CREATE TABLE `zone_coupon_setups` (
  `zone_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `coupon_setup_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `zone_discount_setups`
--

CREATE TABLE `zone_discount_setups` (
  `zone_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `discount_setup_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `zone_wise_default_trip_fares`
--

CREATE TABLE `zone_wise_default_trip_fares` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `zone_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `base_fare` double NOT NULL,
  `base_fare_per_km` double NOT NULL,
  `waiting_fee_per_min` double NOT NULL,
  `cancellation_fee_percent` double NOT NULL,
  `min_cancellation_fee` double NOT NULL,
  `idle_fee_per_min` double NOT NULL,
  `trip_delay_fee_per_min` double NOT NULL,
  `penalty_fee_for_cancel` double NOT NULL,
  `fee_add_to_next` double NOT NULL,
  `category_wise_different_fare` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `admin_notifications`
--
ALTER TABLE `admin_notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `ai_settings`
--
ALTER TABLE `ai_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `app_notifications`
--
ALTER TABLE `app_notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `areas`
--
ALTER TABLE `areas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `areas_name_unique` (`name`);

--
-- Indexes for table `area_bonus_setup`
--
ALTER TABLE `area_bonus_setup`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `area_coupon_setup`
--
ALTER TABLE `area_coupon_setup`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `area_discount_setup`
--
ALTER TABLE `area_discount_setup`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `area_pick_hour`
--
ALTER TABLE `area_pick_hour`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `banner_setups`
--
ALTER TABLE `banner_setups`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `blogs`
--
ALTER TABLE `blogs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `blogs_readable_id_unique` (`readable_id`),
  ADD UNIQUE KEY `blogs_slug_unique` (`slug`);

--
-- Indexes for table `blog_categories`
--
ALTER TABLE `blog_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `blog_categories_slug_unique` (`slug`);

--
-- Indexes for table `blog_drafts`
--
ALTER TABLE `blog_drafts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `blog_settings`
--
ALTER TABLE `blog_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `bonus_setups`
--
ALTER TABLE `bonus_setups`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `bonus_setup_vehicle_category`
--
ALTER TABLE `bonus_setup_vehicle_category`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `business_settings`
--
ALTER TABLE `business_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `cancellation_reasons`
--
ALTER TABLE `cancellation_reasons`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `channel_conversations`
--
ALTER TABLE `channel_conversations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `channel_conversations_convable_type_convable_id_index` (`convable_type`,`convable_id`);

--
-- Indexes for table `channel_lists`
--
ALTER TABLE `channel_lists`
  ADD PRIMARY KEY (`id`),
  ADD KEY `channel_lists_channelable_type_channelable_id_index` (`channelable_type`,`channelable_id`);

--
-- Indexes for table `channel_users`
--
ALTER TABLE `channel_users`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `conversation_files`
--
ALTER TABLE `conversation_files`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `coupon_setups`
--
ALTER TABLE `coupon_setups`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `coupon_setups_coupon_code_unique` (`coupon_code`);

--
-- Indexes for table `coupon_setup_vehicle_category`
--
ALTER TABLE `coupon_setup_vehicle_category`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `customer_coupon_setups`
--
ALTER TABLE `customer_coupon_setups`
  ADD PRIMARY KEY (`user_id`,`coupon_setup_id`);

--
-- Indexes for table `customer_discount_setups`
--
ALTER TABLE `customer_discount_setups`
  ADD PRIMARY KEY (`user_id`,`discount_setup_id`);

--
-- Indexes for table `customer_level_coupon_setups`
--
ALTER TABLE `customer_level_coupon_setups`
  ADD PRIMARY KEY (`user_level_id`,`coupon_setup_id`);

--
-- Indexes for table `customer_level_discount_setups`
--
ALTER TABLE `customer_level_discount_setups`
  ADD PRIMARY KEY (`user_level_id`,`discount_setup_id`);

--
-- Indexes for table `discount_setups`
--
ALTER TABLE `discount_setups`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `discount_setup_vehicle_category`
--
ALTER TABLE `discount_setup_vehicle_category`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `driver_details`
--
ALTER TABLE `driver_details`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `driver_identity_verifications`
--
ALTER TABLE `driver_identity_verifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `driver_time_logs`
--
ALTER TABLE `driver_time_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `external_configurations`
--
ALTER TABLE `external_configurations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`);

--
-- Indexes for table `fare_biddings`
--
ALTER TABLE `fare_biddings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `fare_bidding_logs`
--
ALTER TABLE `fare_bidding_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `firebase_push_notifications`
--
ALTER TABLE `firebase_push_notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jobs_queue_index` (`queue`);

--
-- Indexes for table `landing_page_sections`
--
ALTER TABLE `landing_page_sections`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `late_return_penalty_notifications`
--
ALTER TABLE `late_return_penalty_notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `level_accesses`
--
ALTER TABLE `level_accesses`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `loyalty_points_histories`
--
ALTER TABLE `loyalty_points_histories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `milestone_setups`
--
ALTER TABLE `milestone_setups`
  ADD UNIQUE KEY `milestone_setups_id_unique` (`id`);

--
-- Indexes for table `module_accesses`
--
ALTER TABLE `module_accesses`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `newsletter_subscriptions`
--
ALTER TABLE `newsletter_subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `newsletter_subscriptions_email_unique` (`email`);

--
-- Indexes for table `notification_settings`
--
ALTER TABLE `notification_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `oauth_access_tokens`
--
ALTER TABLE `oauth_access_tokens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `oauth_access_tokens_user_id_index` (`user_id`);

--
-- Indexes for table `oauth_auth_codes`
--
ALTER TABLE `oauth_auth_codes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `oauth_auth_codes_user_id_index` (`user_id`);

--
-- Indexes for table `oauth_clients`
--
ALTER TABLE `oauth_clients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `oauth_clients_user_id_index` (`user_id`);

--
-- Indexes for table `oauth_personal_access_clients`
--
ALTER TABLE `oauth_personal_access_clients`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `oauth_refresh_tokens`
--
ALTER TABLE `oauth_refresh_tokens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `oauth_refresh_tokens_access_token_id_index` (`access_token_id`);

--
-- Indexes for table `otp_verifications`
--
ALTER TABLE `otp_verifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `parcels`
--
ALTER TABLE `parcels`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `parcel_cancellation_reasons`
--
ALTER TABLE `parcel_cancellation_reasons`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `parcel_categories`
--
ALTER TABLE `parcel_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `parcel_categories_name_unique` (`name`);

--
-- Indexes for table `parcel_fares`
--
ALTER TABLE `parcel_fares`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `parcel_fares_parcel_weights`
--
ALTER TABLE `parcel_fares_parcel_weights`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `parcel_information`
--
ALTER TABLE `parcel_information`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `parcel_refunds`
--
ALTER TABLE `parcel_refunds`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `parcel_refund_proofs`
--
ALTER TABLE `parcel_refund_proofs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `parcel_refund_reasons`
--
ALTER TABLE `parcel_refund_reasons`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `parcel_user_infomations`
--
ALTER TABLE `parcel_user_infomations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `parcel_weights`
--
ALTER TABLE `parcel_weights`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `payment_requests`
--
ALTER TABLE `payment_requests`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  ADD KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`);

--
-- Indexes for table `pick_hours`
--
ALTER TABLE `pick_hours`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `question_answers`
--
ALTER TABLE `question_answers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `recent_addresses`
--
ALTER TABLE `recent_addresses`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `referral_customers`
--
ALTER TABLE `referral_customers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `referral_drivers`
--
ALTER TABLE `referral_drivers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `referral_earning_settings`
--
ALTER TABLE `referral_earning_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `rejected_driver_requests`
--
ALTER TABLE `rejected_driver_requests`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `role_user`
--
ALTER TABLE `role_user`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `safety_alerts`
--
ALTER TABLE `safety_alerts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `safety_alert_reasons`
--
ALTER TABLE `safety_alert_reasons`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `safety_precautions`
--
ALTER TABLE `safety_precautions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `send_notifications`
--
ALTER TABLE `send_notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `payment_settings_id_index` (`id`);

--
-- Indexes for table `social_links`
--
ALTER TABLE `social_links`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `support_saved_replies`
--
ALTER TABLE `support_saved_replies`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `surge_pricing`
--
ALTER TABLE `surge_pricing`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `surge_pricing_readable_id_unique` (`readable_id`);

--
-- Indexes for table `surge_pricing_service_categories`
--
ALTER TABLE `surge_pricing_service_categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sp_scat_scid_idx` (`service_category_type`,`service_category_id`);

--
-- Indexes for table `surge_pricing_time_slots`
--
ALTER TABLE `surge_pricing_time_slots`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `temp_trip_notifications`
--
ALTER TABLE `temp_trip_notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `time_logs`
--
ALTER TABLE `time_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `time_tracks`
--
ALTER TABLE `time_tracks`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `trip_fares`
--
ALTER TABLE `trip_fares`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `trip_requests`
--
ALTER TABLE `trip_requests`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `trip_request_coordinates`
--
ALTER TABLE `trip_request_coordinates`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `trip_request_fees`
--
ALTER TABLE `trip_request_fees`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `trip_request_times`
--
ALTER TABLE `trip_request_times`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `trip_routes`
--
ALTER TABLE `trip_routes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `trip_status`
--
ALTER TABLE `trip_status`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_email_unique` (`email`),
  ADD UNIQUE KEY `users_phone_unique` (`phone`),
  ADD UNIQUE KEY `users_ref_code_unique` (`ref_code`);

--
-- Indexes for table `user_accounts`
--
ALTER TABLE `user_accounts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `user_address`
--
ALTER TABLE `user_address`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `user_last_locations`
--
ALTER TABLE `user_last_locations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `user_levels`
--
ALTER TABLE `user_levels`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `user_level_histories`
--
ALTER TABLE `user_level_histories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `user_withdraw_method_infos`
--
ALTER TABLE `user_withdraw_method_infos`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `vehicles`
--
ALTER TABLE `vehicles`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `vehicle_brands`
--
ALTER TABLE `vehicle_brands`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `vehicle_brands_name_unique` (`name`);

--
-- Indexes for table `vehicle_categories`
--
ALTER TABLE `vehicle_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `vehicle_categories_name_unique` (`name`);

--
-- Indexes for table `vehicle_category_coupon_setups`
--
ALTER TABLE `vehicle_category_coupon_setups`
  ADD PRIMARY KEY (`vehicle_category_id`,`coupon_setup_id`);

--
-- Indexes for table `vehicle_category_discount_setups`
--
ALTER TABLE `vehicle_category_discount_setups`
  ADD PRIMARY KEY (`vehicle_category_id`,`discount_setup_id`);

--
-- Indexes for table `vehicle_category_zone`
--
ALTER TABLE `vehicle_category_zone`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `vehicle_models`
--
ALTER TABLE `vehicle_models`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `wallet_bonuses`
--
ALTER TABLE `wallet_bonuses`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `websockets_statistics_entries`
--
ALTER TABLE `websockets_statistics_entries`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `withdraw_methods`
--
ALTER TABLE `withdraw_methods`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `withdraw_requests`
--
ALTER TABLE `withdraw_requests`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `zones`
--
ALTER TABLE `zones`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `zones_name_unique` (`name`);

--
-- Indexes for table `zone_coupon_setups`
--
ALTER TABLE `zone_coupon_setups`
  ADD PRIMARY KEY (`zone_id`,`coupon_setup_id`);

--
-- Indexes for table `zone_discount_setups`
--
ALTER TABLE `zone_discount_setups`
  ADD PRIMARY KEY (`zone_id`,`discount_setup_id`);

--
-- Indexes for table `zone_wise_default_trip_fares`
--
ALTER TABLE `zone_wise_default_trip_fares`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admin_notifications`
--
ALTER TABLE `admin_notifications`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `app_notifications`
--
ALTER TABLE `app_notifications`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `area_bonus_setup`
--
ALTER TABLE `area_bonus_setup`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `area_coupon_setup`
--
ALTER TABLE `area_coupon_setup`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `area_discount_setup`
--
ALTER TABLE `area_discount_setup`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `area_pick_hour`
--
ALTER TABLE `area_pick_hour`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `blog_drafts`
--
ALTER TABLE `blog_drafts`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `blog_settings`
--
ALTER TABLE `blog_settings`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bonus_setup_vehicle_category`
--
ALTER TABLE `bonus_setup_vehicle_category`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `channel_conversations`
--
ALTER TABLE `channel_conversations`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `channel_users`
--
ALTER TABLE `channel_users`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `conversation_files`
--
ALTER TABLE `conversation_files`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `coupon_setup_vehicle_category`
--
ALTER TABLE `coupon_setup_vehicle_category`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `discount_setup_vehicle_category`
--
ALTER TABLE `discount_setup_vehicle_category`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `driver_details`
--
ALTER TABLE `driver_details`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `driver_time_logs`
--
ALTER TABLE `driver_time_logs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `firebase_push_notifications`
--
ALTER TABLE `firebase_push_notifications`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=83;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `landing_page_sections`
--
ALTER TABLE `landing_page_sections`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `late_return_penalty_notifications`
--
ALTER TABLE `late_return_penalty_notifications`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `level_accesses`
--
ALTER TABLE `level_accesses`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `loyalty_points_histories`
--
ALTER TABLE `loyalty_points_histories`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=203;

--
-- AUTO_INCREMENT for table `module_accesses`
--
ALTER TABLE `module_accesses`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notification_settings`
--
ALTER TABLE `notification_settings`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `oauth_personal_access_clients`
--
ALTER TABLE `oauth_personal_access_clients`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `otp_verifications`
--
ALTER TABLE `otp_verifications`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=71;

--
-- AUTO_INCREMENT for table `parcel_fares_parcel_weights`
--
ALTER TABLE `parcel_fares_parcel_weights`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `parcel_information`
--
ALTER TABLE `parcel_information`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `parcel_user_infomations`
--
ALTER TABLE `parcel_user_infomations`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `recent_addresses`
--
ALTER TABLE `recent_addresses`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rejected_driver_requests`
--
ALTER TABLE `rejected_driver_requests`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `role_user`
--
ALTER TABLE `role_user`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `surge_pricing_service_categories`
--
ALTER TABLE `surge_pricing_service_categories`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `surge_pricing_time_slots`
--
ALTER TABLE `surge_pricing_time_slots`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `temp_trip_notifications`
--
ALTER TABLE `temp_trip_notifications`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=326;

--
-- AUTO_INCREMENT for table `time_logs`
--
ALTER TABLE `time_logs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `time_tracks`
--
ALTER TABLE `time_tracks`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `trip_request_coordinates`
--
ALTER TABLE `trip_request_coordinates`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `trip_request_fees`
--
ALTER TABLE `trip_request_fees`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `trip_request_times`
--
ALTER TABLE `trip_request_times`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `trip_routes`
--
ALTER TABLE `trip_routes`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `trip_status`
--
ALTER TABLE `trip_status`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_address`
--
ALTER TABLE `user_address`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_last_locations`
--
ALTER TABLE `user_last_locations`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_level_histories`
--
ALTER TABLE `user_level_histories`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `websockets_statistics_entries`
--
ALTER TABLE `websockets_statistics_entries`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `withdraw_methods`
--
ALTER TABLE `withdraw_methods`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `withdraw_requests`
--
ALTER TABLE `withdraw_requests`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
