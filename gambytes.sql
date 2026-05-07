-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 06, 2026 at 01:15 PM
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
-- Database: `gambytes`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_submissions`
--

CREATE TABLE `activity_submissions` (
  `id` int(11) NOT NULL,
  `activity_id` int(11) NOT NULL,
  `gambler_id` int(11) NOT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `submitted_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `booking_record`
--

CREATE TABLE `booking_record` (
  `id` int(11) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `start_time` datetime DEFAULT NULL,
  `end_time` datetime DEFAULT NULL,
  `status` enum('booked','approved','cancelled','no_show','completed') DEFAULT 'booked',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `notes` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `booking_record`
--

INSERT INTO `booking_record` (`id`, `email`, `name`, `start_time`, `end_time`, `status`, `created_at`, `updated_at`, `notes`) VALUES
(31, 'davedelacerna09@gmail.com', 'Dave Dela Cerna', '2026-05-07 08:00:00', '2026-05-07 09:00:00', '', '2026-05-06 17:56:52', '2026-05-06 17:57:21', NULL),
(32, 'davedelacerna09@gmail.com', 'Dave Dela Cerna', '2026-05-07 08:00:00', '2026-05-07 09:00:00', '', '2026-05-06 18:17:09', '2026-05-06 18:17:30', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `contract_documents`
--

CREATE TABLE `contract_documents` (
  `id` int(11) NOT NULL,
  `gambler_id` int(11) NOT NULL,
  `family_id` int(11) DEFAULT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `supervisor_id` int(11) NOT NULL,
  `contract_content` longtext DEFAULT NULL COMMENT 'Pre-generated MOA content',
  `status` enum('pending','sent','viewed_by_gambler','signed_by_gambler','sent_to_family','signed_by_family','completed') NOT NULL DEFAULT 'pending',
  `sent_to_gambler_at` datetime DEFAULT NULL,
  `sent_to_family_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Tracks contract workflow between supervisor, gambler, and family';

-- --------------------------------------------------------

--
-- Table structure for table `contract_form_templates`
--

CREATE TABLE `contract_form_templates` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `filename` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `uploaded_by` int(11) NOT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `contract_submissions`
--

CREATE TABLE `contract_submissions` (
  `id` int(11) NOT NULL,
  `gambler_id` int(11) NOT NULL,
  `family_member_id` int(11) DEFAULT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `gambler_data` longtext DEFAULT NULL,
  `family_data` longtext DEFAULT NULL,
  `template_id` int(11) NOT NULL DEFAULT 0,
  `gambler_sig` longtext DEFAULT NULL,
  `family_sig` longtext DEFAULT NULL,
  `status` enum('draft','submitted','reviewed','sent_to_parties','completed') NOT NULL DEFAULT 'draft',
  `ea_verification_status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `ea_verified_at` datetime DEFAULT NULL,
  `ea_verified_by` int(11) DEFAULT NULL,
  `supervisor_notes` text DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `ea_notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `contract_submissions`
--

INSERT INTO `contract_submissions` (`id`, `gambler_id`, `family_member_id`, `booking_id`, `gambler_data`, `family_data`, `template_id`, `gambler_sig`, `family_sig`, `status`, `ea_verification_status`, `ea_verified_at`, `ea_verified_by`, `supervisor_notes`, `sent_at`, `submitted_at`, `created_at`, `updated_at`, `ea_notes`) VALUES
(20, 16, 18, 32, NULL, NULL, 0, NULL, 'data:image/png;base64,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', 'sent_to_parties', 'pending', NULL, NULL, NULL, NULL, '2026-05-06 18:48:27', '2026-05-06 18:48:13', '2026-05-06 19:06:37', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `contract_verifications`
--

CREATE TABLE `contract_verifications` (
  `id` int(11) NOT NULL,
  `contract_submission_id` int(11) NOT NULL,
  `executive_assistant_id` int(11) NOT NULL,
  `verification_status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `verified_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `verification_notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `discharges`
--

CREATE TABLE `discharges` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `discharge_date` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `evaluations`
--

CREATE TABLE `evaluations` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `result` text DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `family_signed_documents`
--

CREATE TABLE `family_signed_documents` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `gambler_id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `document_type` varchar(50) NOT NULL DEFAULT 'family_rehab_agreement',
  `signature_data` longtext DEFAULT NULL,
  `signature_hash` varchar(64) DEFAULT NULL,
  `hash_algorithm` varchar(20) DEFAULT 'sha256',
  `signed_date` date DEFAULT NULL,
  `verification_status` enum('pending','verified','rejected') NOT NULL DEFAULT 'pending',
  `verified_by` int(11) DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `initial_interview_record`
--

CREATE TABLE `initial_interview_record` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `interviewer_id` int(11) NOT NULL,
  `q1` tinyint(1) DEFAULT NULL,
  `q2` tinyint(1) DEFAULT NULL,
  `q3` tinyint(1) DEFAULT NULL,
  `q4` tinyint(1) DEFAULT NULL,
  `q5` tinyint(1) DEFAULT NULL,
  `q6` tinyint(1) DEFAULT NULL,
  `q7` tinyint(1) DEFAULT NULL,
  `q8` tinyint(1) DEFAULT NULL,
  `q9` tinyint(1) DEFAULT NULL,
  `score` int(11) DEFAULT 0,
  `diagnosis` varchar(100) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `medications`
--

CREATE TABLE `medications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `medication_name` varchar(100) DEFAULT NULL,
  `dosage` varchar(50) DEFAULT NULL,
  `administered_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL DEFAULT 'general',
  `title` varchar(255) NOT NULL,
  `message` text DEFAULT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `message`, `link`, `is_read`, `created_at`) VALUES
(26, 16, 'contract_rejected', 'Contract Review Required', 'Your rehabilitation contract requires review. Please contact the administrator for more information.', '/GAMBYTES_Final/app/views/Users/Gamblers/my-interview.php', 1, '2026-05-02 20:47:14'),
(27, 16, 'contract_signed', 'Family Member Signed Agreement', 'Your family member has signed the rehabilitation support agreement.', '/GAMBYTES_Final/app/views/Users/Gamblers/my-interview.php', 1, '2026-05-02 21:16:26'),
(28, 8, 'new_booking', 'New Booking: Dave Dela Cerna', 'Dave Dela Cerna booked a rehabilitation session on May 2, 2026 8:00 AM.', '/GAMBYTES_Final/app/views/Users/Supervisor/booking-management.php', 1, '2026-05-02 21:25:35'),
(29, 16, 'booking_approved', 'Booking Approved!', 'Your rehabilitation session on May 2, 2026 at 8:00 AM has been approved by the supervisor.', '/GAMBYTES_Final/app/views/Users/Gamblers/booking-confirmation.php', 1, '2026-05-02 21:26:04'),
(30, 16, 'interview_done', 'Initial Interview Completed', 'Your initial interview has been recorded. Score: 9/9 — Gambling Disorder – Severe (8–9 criteria met).', '/GAMBYTES_Final/app/views/Users/Gamblers/my-interview.php', 1, '2026-05-02 21:26:45'),
(31, 16, 'contract_signed', 'Family Member Signed Agreement', 'Your family member has signed the rehabilitation support agreement.', '/GAMBYTES_Final/app/views/Users/Gamblers/my-interview.php', 1, '2026-05-02 21:38:14'),
(32, 16, 'contract_approved', 'Contract Approved! ✅', 'Your rehabilitation contract has been approved by the Executive Assistant. You can now proceed with the treatment program. Feedback: gooDS', '/GAMBYTES_Final/app/views/Users/Gamblers/my-interview.php', 1, '2026-05-02 21:44:14'),
(33, 8, 'new_booking', 'New Booking: Dave Dela Cerna', 'Dave Dela Cerna booked a rehabilitation session on May 4, 2026 8:00 AM.', '/GAMBYTES_Final/app/views/Users/Supervisor/booking-management.php', 1, '2026-05-02 21:48:23'),
(34, 16, 'booking_approved', 'Booking Approved!', 'Your rehabilitation session on May 4, 2026 at 8:00 AM has been approved by the supervisor.', '/GAMBYTES_Final/app/views/Users/Gamblers/booking-confirmation.php', 1, '2026-05-02 21:48:35'),
(35, 16, 'interview_done', 'Initial Interview Completed', 'Your initial interview has been recorded. Score: 9/9 — Gambling Disorder – Severe (8–9 criteria met).', '/GAMBYTES_Final/app/views/Users/Gamblers/my-interview.php', 1, '2026-05-02 21:49:04'),
(36, 16, 'parental_control', 'Parental Control Request', 'Arlene Vismanos is requesting parental access to monitor your rehabilitation activity. Please review and respond.', '/GAMBYTES_Final/app/views/Users/Gamblers/parental-control-requests.php', 1, '2026-05-02 21:49:30'),
(37, 18, 'parental_control', 'Parental Access Granted', 'Dave Dela Cerna has accepted your parental control request. You can now monitor their rehabilitation activity.', '/GAMBYTES_Final/app/views/Users/Family member/parental-control.php', 1, '2026-05-02 21:50:02'),
(38, 16, 'contract_signed', 'Family Member Signed Agreement', 'Your family member has signed the rehabilitation support agreement.', '/GAMBYTES_Final/app/views/Users/Gamblers/my-interview.php', 1, '2026-05-02 21:51:33'),
(39, 16, 'contract_approved', 'Contract Approved! ✅', 'Your rehabilitation contract has been approved by the Executive Assistant. You can now proceed with the treatment program. Feedback: All goods', '/GAMBYTES_Final/app/views/Users/Gamblers/my-interview.php', 1, '2026-05-02 21:55:44'),
(40, 8, 'new_booking', 'New Booking: Dave Dela Cerna', 'Dave Dela Cerna booked a rehabilitation session on May 5, 2026 8:00 AM.', '/GAMBYTES_Final/app/views/Users/Supervisor/booking-management.php', 1, '2026-05-04 18:37:35'),
(41, 16, 'booking_approved', 'Booking Approved!', 'Your rehabilitation session on May 5, 2026 at 8:00 AM has been approved by the supervisor.', '/GAMBYTES_Final/app/views/Users/Gamblers/booking-confirmation.php', 1, '2026-05-04 18:37:46'),
(42, 16, 'interview_done', 'Initial Interview Completed', 'Your initial interview has been recorded. Score: 9/9 — Gambling Disorder – Severe (8–9 criteria met).', '/GAMBYTES_Final/app/views/Users/Gamblers/my-interview.php', 1, '2026-05-04 18:38:36'),
(43, 8, 'new_booking', 'New Booking: Dave Dela Cerna', 'Dave Dela Cerna booked a rehabilitation session on May 5, 2026 8:00 AM.', '/GAMBYTES_Final/app/views/Users/Supervisor/booking-management.php', 1, '2026-05-04 19:12:47'),
(44, 16, 'booking_approved', 'Booking Approved!', 'Your rehabilitation session on May 5, 2026 at 8:00 AM has been approved by the supervisor.', '/GAMBYTES_Final/app/views/Users/Gamblers/booking-confirmation.php', 1, '2026-05-04 19:13:12'),
(45, 16, 'interview_done', 'Initial Interview Completed', 'Your initial interview has been recorded. Score: 9/9 — Gambling Disorder – Severe (8–9 criteria met).', '/GAMBYTES_Final/app/views/Users/Gamblers/my-interview.php', 1, '2026-05-04 19:14:11'),
(46, 8, 'new_booking', 'New Booking: Dave Dela Cerna', 'Dave Dela Cerna booked a rehabilitation session on May 5, 2026 8:00 AM.', '/GAMBYTES_Final/app/views/Users/Supervisor/booking-management.php', 1, '2026-05-04 19:19:20'),
(47, 16, 'booking_approved', 'Booking Approved!', 'Your rehabilitation session on May 5, 2026 at 8:00 AM has been approved by the supervisor.', '/GAMBYTES_Final/app/views/Users/Gamblers/booking-confirmation.php', 1, '2026-05-04 19:20:12'),
(48, 16, 'interview_done', 'Initial Interview Completed', 'Your initial interview has been recorded. Score: 9/9 — Gambling Disorder – Severe (8–9 criteria met).', '/GAMBYTES_Final/app/views/Users/Gamblers/my-interview.php', 1, '2026-05-04 19:20:51'),
(49, 8, 'new_booking', 'New Booking: Dave Dela Cerna', 'Dave Dela Cerna booked a rehabilitation session on May 5, 2026 8:00 AM.', '/GAMBYTES_Final/app/views/Users/Supervisor/booking-management.php', 1, '2026-05-04 19:22:55'),
(50, 16, 'booking_approved', 'Booking Approved!', 'Your rehabilitation session on May 5, 2026 at 8:00 AM has been approved by the supervisor.', '/GAMBYTES_Final/app/views/Users/Gamblers/booking-confirmation.php', 1, '2026-05-04 19:23:01'),
(51, 16, 'interview_done', 'Initial Interview Completed', 'Your initial interview has been recorded. Score: 9/9 — Gambling Disorder – Severe (8–9 criteria met).', '/GAMBYTES_Final/app/views/Users/Gamblers/my-interview.php', 1, '2026-05-04 19:23:24'),
(52, 16, 'contract_signed', 'Family Member Signed Agreement', 'Your family member has signed the rehabilitation support agreement.', '/GAMBYTES_Final/app/views/Users/Gamblers/my-interview.php', 1, '2026-05-04 19:27:39'),
(53, 16, 'parental_control', 'Parental Control Request', 'Arlene Vismanos is requesting parental access to monitor your rehabilitation activity. Please review and respond.', '/GAMBYTES_Final/app/views/Users/Gamblers/parental-control-requests.php', 1, '2026-05-04 19:58:59'),
(54, 18, 'parental_control', 'Parental Access Granted', 'Dave Dela Cerna has accepted your parental control request. You can now monitor their rehabilitation activity.', '/GAMBYTES_Final/app/views/Users/Family member/parental-control.php', 1, '2026-05-04 19:59:10'),
(55, 8, 'new_booking', 'New Booking: Dave Dela Cerna', 'Dave Dela Cerna booked a rehabilitation session on May 5, 2026 8:00 AM.', '/GAMBYTES_Final/app/views/Users/Supervisor/booking-management.php', 1, '2026-05-04 20:05:09'),
(56, 16, 'booking_approved', 'Booking Approved!', 'Your rehabilitation session on May 5, 2026 at 8:00 AM has been approved by the supervisor.', '/GAMBYTES_Final/app/views/Users/Gamblers/booking-confirmation.php', 1, '2026-05-04 20:05:26'),
(57, 16, 'interview_done', 'Initial Interview Completed', 'Your initial interview has been recorded. Score: 9/9 — Gambling Disorder – Severe (8–9 criteria met).', '/GAMBYTES_Final/app/views/Users/Gamblers/my-interview.php', 1, '2026-05-04 20:05:50'),
(58, 16, 'contract_signed', 'Family Member Signed Agreement', 'Your family member has signed the rehabilitation support agreement.', '/GAMBYTES_Final/app/views/Users/Gamblers/my-interview.php', 1, '2026-05-04 20:06:36'),
(59, 16, 'contract_approved', 'Contract Approved! ✅', 'Your rehabilitation contract has been approved by the Executive Assistant. You can now proceed with the treatment program. Feedback: Welcome to program', '/GAMBYTES_Final/app/views/Users/Gamblers/my-interview.php', 1, '2026-05-04 20:59:05'),
(60, 8, 'payment', 'Payment Received', 'Dave Dela Cerna has completed payment of ₱50,000 for the rehabilitation program.', '/GAMBYTES_Final/app/views/auth/dashboard.php', 0, '2026-05-05 21:33:13'),
(61, 25, 'payment', 'Payment Received', 'Dave Dela Cerna has completed payment of ₱50,000 for the rehabilitation program.', '/GAMBYTES_Final/app/views/auth/dashboard.php', 1, '2026-05-05 21:33:13'),
(62, 8, 'payment', 'Payment Received', 'Arlene Vismanos has completed payment of ₱50,000 for the rehabilitation program.', '/GAMBYTES_Final/app/views/auth/dashboard.php', 0, '2026-05-05 21:33:41'),
(63, 25, 'payment', 'Payment Received', 'Arlene Vismanos has completed payment of ₱50,000 for the rehabilitation program.', '/GAMBYTES_Final/app/views/auth/dashboard.php', 1, '2026-05-05 21:33:41'),
(64, 18, 'payment_verified', 'Payment Verified – Official Receipt Issued', 'Your payment of ₱50,000.00 has been verified. Receipt No: RCP-20260505-D71BA. Click to view your official receipt.', '/GAMBYTES_Final/app/views/Users/admin%20department/payment/view-receipt.php?receipt_id=1', 1, '2026-05-05 21:45:17'),
(65, 16, 'payment_verified', 'Payment Verified – Official Receipt Issued', 'Your payment of ₱50,000.00 has been verified. Receipt No: RCP-20260505-863FD. Click to view your official receipt.', '/GAMBYTES_Final/app/views/Users/admin%20department/payment/view-receipt.php?receipt_id=2', 1, '2026-05-05 21:45:36'),
(66, 18, 'payment_verified', 'Payment Verified – Official Receipt Issued', 'The payment of ₱50,000.00 for your family member\'s rehabilitation has been verified. Receipt No: RCP-20260505-863FD.', '/GAMBYTES_Final/app/views/Users/admin%20department/payment/view-receipt.php?receipt_id=2', 1, '2026-05-05 21:45:36'),
(67, 25, 'payment', 'New Payment – Verification Required', 'Arlene Vismanos has paid ₱50,000 for the rehabilitation program. Please verify and issue a receipt.', '/GAMBYTES_Final/app/views/Users/admin%20department/payment/verify-payments.php', 0, '2026-05-06 14:39:54'),
(68, 18, 'payment_verified', 'Payment Verified – Official Receipt Issued', 'Your payment of ₱50,000.00 has been verified. Receipt No: RCP-20260506-C528B. Click to view your official receipt.', '/GAMBYTES_Final/app/views/Users/admin%20department/payment/view-receipt.php?receipt_id=3', 1, '2026-05-06 14:40:28'),
(69, 25, 'payment', 'New Payment – Verification Required', 'Arlene Vismanos has paid ₱50,000 for the rehabilitation program. Please verify and issue a receipt.', '/GAMBYTES_Final/app/views/Users/admin%20department/payment/verify-payments.php', 0, '2026-05-06 14:53:20'),
(70, 18, 'payment_verified', 'Payment Verified – Official Receipt Issued', 'Your payment of ₱50,000.00 has been verified. Receipt No: RCP-20260506-38609. Click to view your official receipt.', '/GAMBYTES_Final/app/views/Users/admin department/payment/view-receipt.php?receipt_id=4', 1, '2026-05-06 15:01:57'),
(71, 16, 'payment_verified', 'Payment Verified – Official Receipt Issued', 'The payment of ₱50,000.00 for your rehabilitation program has been verified by your family member. Receipt No: RCP-20260506-38609. Click to view the official receipt.', '/GAMBYTES_Final/app/views/Users/admin department/payment/view-receipt.php?receipt_id=4', 1, '2026-05-06 15:01:57'),
(72, 8, 'new_booking', 'New Booking: Dave Dela Cerna', 'Dave Dela Cerna booked a rehabilitation session on May 7, 2026 8:00 AM.', '/GAMBYTES_Final/app/views/Users/Supervisor/booking-management.php', 0, '2026-05-06 16:26:19'),
(73, 25, 'new_booking', 'New Booking: Dave Dela Cerna', 'Dave Dela Cerna booked a rehabilitation session on May 7, 2026 8:00 AM.', '/GAMBYTES_Final/app/views/Users/Supervisor/booking-management.php', 0, '2026-05-06 16:26:19'),
(74, 16, 'booking_approved', 'Booking Approved!', 'Your rehabilitation session on May 7, 2026 at 8:00 AM has been approved by the supervisor.', '/GAMBYTES_Final/app/views/Users/Gamblers/booking-confirmation.php', 1, '2026-05-06 16:26:46'),
(75, 16, 'interview_done', 'Initial Interview Completed', 'Your initial interview has been recorded. Score: 9/9 — Gambling Disorder – Severe (8–9 criteria met).', '/GAMBYTES_Final/app/views/Users/Gamblers/my-interview.php', 1, '2026-05-06 16:27:09'),
(76, 8, 'new_booking', 'New Booking: Dave Dela Cerna', 'Dave Dela Cerna booked a rehabilitation session on May 7, 2026 8:00 AM.', '/GAMBYTES_Final/app/views/Users/Supervisor/booking-management.php', 0, '2026-05-06 16:35:42'),
(77, 25, 'new_booking', 'New Booking: Dave Dela Cerna', 'Dave Dela Cerna booked a rehabilitation session on May 7, 2026 8:00 AM.', '/GAMBYTES_Final/app/views/Users/Supervisor/booking-management.php', 0, '2026-05-06 16:35:42'),
(78, 16, 'booking_approved', 'Booking Approved!', 'Your rehabilitation session on May 7, 2026 at 8:00 AM has been approved by the supervisor.', '/GAMBYTES_Final/app/views/Users/Gamblers/booking-confirmation.php', 1, '2026-05-06 16:37:01'),
(79, 16, 'interview_done', 'Initial Interview Completed', 'Your initial interview has been recorded. Score: 9/9 — Gambling Disorder – Severe (8–9 criteria met).', '/GAMBYTES_Final/app/views/Users/Gamblers/my-interview.php', 1, '2026-05-06 16:37:29'),
(80, 8, 'new_booking', 'New Booking: Dave Dela Cerna', 'Dave Dela Cerna booked a rehabilitation session on May 7, 2026 9:00 AM.', '/GAMBYTES_Final/app/views/Users/Supervisor/booking-management.php', 0, '2026-05-06 16:40:39'),
(81, 25, 'new_booking', 'New Booking: Dave Dela Cerna', 'Dave Dela Cerna booked a rehabilitation session on May 7, 2026 9:00 AM.', '/GAMBYTES_Final/app/views/Users/Supervisor/booking-management.php', 0, '2026-05-06 16:40:39'),
(82, 16, 'booking_approved', 'Booking Approved!', 'Your rehabilitation session on May 7, 2026 at 9:00 AM has been approved by the supervisor.', '/GAMBYTES_Final/app/views/Users/Gamblers/booking-confirmation.php', 1, '2026-05-06 16:40:50'),
(83, 16, 'interview_done', 'Initial Interview Completed', 'Your initial interview has been recorded. Score: 9/9 — Gambling Disorder – Severe (8–9 criteria met).', '/GAMBYTES_Final/app/views/Users/Gamblers/my-interview.php', 1, '2026-05-06 16:41:13'),
(84, 8, 'new_booking', 'New Booking: Dave Dela Cerna', 'Dave Dela Cerna booked a rehabilitation session on May 7, 2026 10:00 AM.', '/GAMBYTES_Final/app/views/Users/Supervisor/booking-management.php', 0, '2026-05-06 16:52:05'),
(85, 25, 'new_booking', 'New Booking: Dave Dela Cerna', 'Dave Dela Cerna booked a rehabilitation session on May 7, 2026 10:00 AM.', '/GAMBYTES_Final/app/views/Users/Supervisor/booking-management.php', 0, '2026-05-06 16:52:05'),
(86, 16, 'booking_approved', 'Booking Approved!', 'Your rehabilitation session on May 7, 2026 at 10:00 AM has been approved by the supervisor.', '/GAMBYTES_Final/app/views/Users/Gamblers/booking-confirmation.php', 1, '2026-05-06 16:52:15'),
(87, 16, 'interview_done', 'Initial Interview Completed', 'Your initial interview has been recorded. Score: 9/9 — Gambling Disorder – Severe (8–9 criteria met).', '/GAMBYTES_Final/app/views/Users/Gamblers/my-interview.php', 1, '2026-05-06 16:52:34'),
(88, 8, 'new_booking', 'New Booking: Dave Dela Cerna', 'Dave Dela Cerna booked a rehabilitation session on May 7, 2026 8:00 AM.', '/GAMBYTES_Final/app/views/Users/Supervisor/booking-management.php', 0, '2026-05-06 17:22:31'),
(89, 25, 'new_booking', 'New Booking: Dave Dela Cerna', 'Dave Dela Cerna booked a rehabilitation session on May 7, 2026 8:00 AM.', '/GAMBYTES_Final/app/views/Users/Supervisor/booking-management.php', 0, '2026-05-06 17:22:31'),
(90, 16, 'booking_approved', 'Booking Approved!', 'Your rehabilitation session on May 7, 2026 at 8:00 AM has been approved by the supervisor.', '/GAMBYTES_Final/app/views/Users/Gamblers/booking-confirmation.php', 1, '2026-05-06 17:22:58'),
(91, 16, 'interview_done', 'Initial Interview Completed', 'Your initial interview has been recorded. Score: 9/9 — Gambling Disorder – Severe (8–9 criteria met).', '/GAMBYTES_Final/app/views/Users/Gamblers/my-interview.php', 1, '2026-05-06 17:23:28'),
(92, 8, 'new_booking', 'New Booking: Dave Dela Cerna', 'Dave Dela Cerna booked a rehabilitation session on May 7, 2026 8:00 AM.', '/GAMBYTES_Final/app/views/Users/Supervisor/booking-management.php', 0, '2026-05-06 17:35:41'),
(93, 25, 'new_booking', 'New Booking: Dave Dela Cerna', 'Dave Dela Cerna booked a rehabilitation session on May 7, 2026 8:00 AM.', '/GAMBYTES_Final/app/views/Users/Supervisor/booking-management.php', 0, '2026-05-06 17:35:41'),
(94, 16, 'booking_approved', 'Booking Approved!', 'Your rehabilitation session on May 7, 2026 at 8:00 AM has been approved by the supervisor.', '/GAMBYTES_Final/app/views/Users/Gamblers/booking-confirmation.php', 1, '2026-05-06 17:35:50'),
(95, 16, 'interview_done', 'Initial Interview Completed', 'Your initial interview has been recorded. Score: 9/9 — Gambling Disorder – Severe (8–9 criteria met).', '/GAMBYTES_Final/app/views/Users/Gamblers/my-interview.php', 1, '2026-05-06 17:36:07'),
(96, 8, 'new_booking', 'New Booking: Dave Dela Cerna', 'Dave Dela Cerna booked a rehabilitation session on May 7, 2026 8:00 AM.', '/GAMBYTES_Final/app/views/Users/Supervisor/booking-management.php', 0, '2026-05-06 17:50:50'),
(97, 25, 'new_booking', 'New Booking: Dave Dela Cerna', 'Dave Dela Cerna booked a rehabilitation session on May 7, 2026 8:00 AM.', '/GAMBYTES_Final/app/views/Users/Supervisor/booking-management.php', 0, '2026-05-06 17:50:50'),
(98, 16, 'booking_approved', 'Booking Approved!', 'Your rehabilitation session on May 7, 2026 at 8:00 AM has been approved by the supervisor.', '/GAMBYTES_Final/app/views/Users/Gamblers/booking-confirmation.php', 1, '2026-05-06 17:51:04'),
(99, 16, 'interview_done', 'Initial Interview Completed', 'Your initial interview has been recorded. Score: 9/9 — Gambling Disorder – Severe (8–9 criteria met).', '/GAMBYTES_Final/app/views/Users/Gamblers/my-interview.php', 1, '2026-05-06 17:51:26'),
(100, 8, 'new_booking', 'New Booking: Dave Dela Cerna', 'Dave Dela Cerna booked a rehabilitation session on May 7, 2026 8:00 AM.', '/GAMBYTES_Final/app/views/Users/Supervisor/booking-management.php', 0, '2026-05-06 17:54:02'),
(101, 25, 'new_booking', 'New Booking: Dave Dela Cerna', 'Dave Dela Cerna booked a rehabilitation session on May 7, 2026 8:00 AM.', '/GAMBYTES_Final/app/views/Users/Supervisor/booking-management.php', 0, '2026-05-06 17:54:02'),
(102, 16, 'booking_approved', 'Booking Approved!', 'Your rehabilitation session on May 7, 2026 at 8:00 AM has been approved by the supervisor.', '/GAMBYTES_Final/app/views/Users/Gamblers/booking-confirmation.php', 1, '2026-05-06 17:54:17'),
(103, 16, 'interview_done', 'Initial Interview Completed', 'Your initial interview has been recorded. Score: 9/9 — Gambling Disorder – Severe (8–9 criteria met).', '/GAMBYTES_Final/app/views/Users/Gamblers/my-interview.php', 1, '2026-05-06 17:54:40'),
(104, 8, 'new_booking', 'New Booking: Dave Dela Cerna', 'Dave Dela Cerna booked a rehabilitation session on May 7, 2026 8:00 AM.', '/GAMBYTES_Final/app/views/Users/Supervisor/booking-management.php', 0, '2026-05-06 17:56:52'),
(105, 25, 'new_booking', 'New Booking: Dave Dela Cerna', 'Dave Dela Cerna booked a rehabilitation session on May 7, 2026 8:00 AM.', '/GAMBYTES_Final/app/views/Users/Supervisor/booking-management.php', 0, '2026-05-06 17:56:52'),
(106, 16, 'booking_approved', 'Booking Approved!', 'Your rehabilitation session on May 7, 2026 at 8:00 AM has been approved by the supervisor.', '/GAMBYTES_Final/app/views/Users/Gamblers/booking-confirmation.php', 1, '2026-05-06 17:57:02'),
(107, 16, 'interview_done', 'Initial Interview Completed', 'Your initial interview has been recorded. Score: 9/9 — Gambling Disorder – Severe (8–9 criteria met).', '/GAMBYTES_Final/app/views/Users/Gamblers/my-interview.php', 1, '2026-05-06 17:57:21'),
(108, 8, 'new_booking', 'New Booking: Dave Dela Cerna', 'Dave Dela Cerna booked a rehabilitation session on May 7, 2026 8:00 AM.', '/GAMBYTES_Final/app/views/Users/Supervisor/booking-management.php', 0, '2026-05-06 18:17:09'),
(109, 25, 'new_booking', 'New Booking: Dave Dela Cerna', 'Dave Dela Cerna booked a rehabilitation session on May 7, 2026 8:00 AM.', '/GAMBYTES_Final/app/views/Users/Supervisor/booking-management.php', 0, '2026-05-06 18:17:09'),
(110, 16, 'booking_approved', 'Booking Approved!', 'Your rehabilitation session on May 7, 2026 at 8:00 AM has been approved by the supervisor.', '/GAMBYTES_Final/app/views/Users/Gamblers/booking-confirmation.php', 1, '2026-05-06 18:17:16'),
(111, 16, 'interview_done', 'Initial Interview Completed', 'Your initial interview has been recorded. Score: 9/9 — Gambling Disorder – Severe (8–9 criteria met).', '/GAMBYTES_Final/app/views/Users/Gamblers/my-interview.php', 1, '2026-05-06 18:17:30');

-- --------------------------------------------------------

--
-- Table structure for table `parental_control_requests`
--

CREATE TABLE `parental_control_requests` (
  `id` int(11) NOT NULL,
  `family_id` int(11) NOT NULL COMMENT 'family member user id',
  `gambler_id` int(11) NOT NULL COMMENT 'gambler user id',
  `status` enum('pending','accepted','declined') NOT NULL DEFAULT 'pending',
  `requested_at` datetime NOT NULL DEFAULT current_timestamp(),
  `responded_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `parental_control_requests`
--

INSERT INTO `parental_control_requests` (`id`, `family_id`, `gambler_id`, `status`, `requested_at`, `responded_at`) VALUES
(3, 18, 16, 'accepted', '2026-05-04 19:58:59', '2026-05-04 19:59:10');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `currency` varchar(10) NOT NULL DEFAULT 'PHP',
  `payment_status` varchar(50) NOT NULL DEFAULT 'pending',
  `paymongo_session_id` varchar(255) DEFAULT NULL,
  `paymongo_payment_id` varchar(255) DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `booking_id` int(11) DEFAULT NULL,
  `verified_by` int(11) DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `policy_files`
--

CREATE TABLE `policy_files` (
  `id` int(11) NOT NULL,
  `doc_title` varchar(255) NOT NULL,
  `doc_type` varchar(100) NOT NULL DEFAULT 'Other',
  `doc_category` varchar(100) NOT NULL DEFAULT 'General',
  `description` text DEFAULT NULL,
  `filename` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `uploaded_by` int(11) NOT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `policy_files`
--

INSERT INTO `policy_files` (`id`, `doc_title`, `doc_type`, `doc_category`, `description`, `filename`, `original_name`, `uploaded_by`, `uploaded_at`) VALUES
(1, 'Policy and Guidelines', 'Policy', 'Policies & Guidelines', 'Understand the policy', 'doc_1777543359_9a093764.pdf', '1.pdf', 8, '2026-04-30 18:02:39');

-- --------------------------------------------------------

--
-- Table structure for table `receipts`
--

CREATE TABLE `receipts` (
  `id` int(11) NOT NULL,
  `payment_id` int(11) DEFAULT NULL,
  `receipt_number` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `verified_by` int(11) DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `schedules`
--

CREATE TABLE `schedules` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `schedule_date` datetime DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `signed_contract_documents`
--

CREATE TABLE `signed_contract_documents` (
  `id` int(11) NOT NULL,
  `contract_document_id` int(11) NOT NULL COMMENT 'FK to contract_documents.id',
  `signer_id` int(11) NOT NULL COMMENT 'User ID of the person who signed',
  `signer_role` enum('gambler','family') NOT NULL COMMENT 'Role of the signer',
  `signature_data` longtext NOT NULL COMMENT 'Base64 encoded signature image',
  `signature_hash` varchar(64) DEFAULT NULL COMMENT 'SHA256 hash of signature for verification',
  `signed_at` datetime NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'IP address when signed',
  `user_agent` varchar(255) DEFAULT NULL COMMENT 'Browser user agent'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `signed_documents`
--

CREATE TABLE `signed_documents` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `document_type` varchar(50) NOT NULL DEFAULT 'rehab_agreement',
  `signature_data` longtext DEFAULT NULL,
  `signature_hash` varchar(64) DEFAULT NULL,
  `hash_algorithm` varchar(20) DEFAULT 'sha256',
  `original_stored` tinyint(1) DEFAULT 0,
  `signed_date` date DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `treatments`
--

CREATE TABLE `treatments` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `phase` int(11) DEFAULT NULL,
  `progress` text DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `treatment_activities`
--

CREATE TABLE `treatment_activities` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `gambler_id` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `document_path` varchar(500) DEFAULT NULL,
  `document_name` varchar(255) DEFAULT NULL,
  `open_date` date NOT NULL,
  `close_date` date NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `first_name` varchar(255) NOT NULL,
  `middle_name` varchar(255) DEFAULT NULL,
  `last_name` varchar(255) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role` enum('gambler','family','admin','case_manager','nurse','supervisor','executive_assistant') DEFAULT NULL,
  `verification_token` varchar(255) DEFAULT NULL,
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_expiry` datetime DEFAULT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `notif_last_seen` datetime DEFAULT '1970-01-01 00:00:00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `first_name`, `middle_name`, `last_name`, `email`, `password`, `role`, `verification_token`, `reset_token`, `reset_expiry`, `is_verified`, `created_at`, `notif_last_seen`) VALUES
(8, 'Jennyvieve', 'Nioda', 'Mahinay', 'jennyvievemahinay@gmail.com', '$2y$10$kL3KAhg7ze8lZppzF1bQX.bDj6Pr70uLqnd263bfV8LyCjnq7L71u', 'supervisor', NULL, NULL, NULL, 1, '2026-04-06 15:23:11', '2026-04-27 11:00:16'),
(16, 'Dave', 'Vismanos', 'Dela Cerna', 'davedelacerna09@gmail.com', '$2y$10$VTDaIliDHCc6XTf0mr7jPuKZiRF.YKrndOVrjaA4Iv6EXpxIf82Yu', 'gambler', NULL, NULL, NULL, 1, '2026-04-21 14:59:39', '1970-01-01 00:00:00'),
(18, 'Arlene', 'Dela Cerna', 'Vismanos', 'xzytrion09@gmail.com', '$2y$10$gw8QE2z4nVfAqijaqwGhv..UmipOmUnSaVG0O7pT/CuQKDA8kBtCy', 'family', NULL, NULL, NULL, 1, '2026-04-30 10:55:36', '1970-01-01 00:00:00'),
(22, 'lydia', 'Wawo', 'mahinay', 'mahinaylydia82@gmail.com', '$2y$10$BHHdwEl25cN.3KVbGPe3xeAL3a/uRh7NXRbRCJxmVhGRvQkF96sJy', 'executive_assistant', NULL, NULL, NULL, 1, '2026-05-02 12:26:15', '1970-01-01 00:00:00'),
(25, 'Marian', 'Mamis', 'Dapot', 'schoolprps2004@gmail.com', '$2y$10$YDttSsVXEx0qlb1E1vxFNuYqpudZZ9LsN3iPiqo4SySCesUah6gz6', 'admin', NULL, NULL, NULL, 1, '2026-05-05 13:00:33', '1970-01-01 00:00:00'),
(27, 'Jasmine', 'Deporos', 'Duran', 'jasminedeporosduran@gmail.com', '$2y$10$K.FmnubZVN3kn.UomF895el/oL9oRckCMlavPK3zICYB.vJ/Ky/D6', 'case_manager', NULL, NULL, NULL, 1, '2026-05-06 06:43:43', '1970-01-01 00:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `workflow_state_log`
--

CREATE TABLE `workflow_state_log` (
  `id` int(11) NOT NULL,
  `contract_document_id` int(11) NOT NULL,
  `previous_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) NOT NULL,
  `changed_by_user_id` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Audit trail for contract workflow state changes';

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_submissions`
--
ALTER TABLE `activity_submissions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `booking_record`
--
ALTER TABLE `booking_record`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `contract_documents`
--
ALTER TABLE `contract_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_gambler` (`gambler_id`),
  ADD KEY `idx_family` (`family_id`),
  ADD KEY `idx_booking` (`booking_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `supervisor_id` (`supervisor_id`);

--
-- Indexes for table `contract_form_templates`
--
ALTER TABLE `contract_form_templates`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `contract_submissions`
--
ALTER TABLE `contract_submissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_gambler` (`gambler_id`),
  ADD KEY `idx_ea_verification` (`ea_verification_status`),
  ADD KEY `idx_booking` (`booking_id`);

--
-- Indexes for table `contract_verifications`
--
ALTER TABLE `contract_verifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_contract_submission` (`contract_submission_id`),
  ADD KEY `idx_executive_assistant` (`executive_assistant_id`);

--
-- Indexes for table `discharges`
--
ALTER TABLE `discharges`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `evaluations`
--
ALTER TABLE `evaluations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `family_signed_documents`
--
ALTER TABLE `family_signed_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_family_user` (`user_id`),
  ADD KEY `idx_gambler` (`gambler_id`),
  ADD KEY `idx_booking` (`booking_id`),
  ADD KEY `idx_signature_hash` (`signature_hash`);

--
-- Indexes for table `initial_interview_record`
--
ALTER TABLE `initial_interview_record`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_booking` (`booking_id`);

--
-- Indexes for table `medications`
--
ALTER TABLE `medications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_read` (`user_id`,`is_read`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `parental_control_requests`
--
ALTER TABLE `parental_control_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_pair` (`family_id`,`gambler_id`),
  ADD KEY `idx_family` (`family_id`),
  ADD KEY `idx_gambler` (`gambler_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `policy_files`
--
ALTER TABLE `policy_files`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `receipts`
--
ALTER TABLE `receipts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `receipts_ibfk_1` (`payment_id`);

--
-- Indexes for table `schedules`
--
ALTER TABLE `schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `signed_contract_documents`
--
ALTER TABLE `signed_contract_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_contract_document` (`contract_document_id`),
  ADD KEY `idx_signer` (`signer_id`,`signer_role`),
  ADD KEY `idx_signed_at` (`signed_at`),
  ADD KEY `idx_signer_role` (`signer_role`);

--
-- Indexes for table `signed_documents`
--
ALTER TABLE `signed_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_document` (`user_id`,`document_type`),
  ADD KEY `idx_signature_hash` (`signature_hash`);

--
-- Indexes for table `treatments`
--
ALTER TABLE `treatments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `treatment_activities`
--
ALTER TABLE `treatment_activities`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `workflow_state_log`
--
ALTER TABLE `workflow_state_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_contract` (`contract_document_id`),
  ADD KEY `changed_by_user_id` (`changed_by_user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_submissions`
--
ALTER TABLE `activity_submissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `booking_record`
--
ALTER TABLE `booking_record`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `contract_documents`
--
ALTER TABLE `contract_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `contract_form_templates`
--
ALTER TABLE `contract_form_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `contract_submissions`
--
ALTER TABLE `contract_submissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `contract_verifications`
--
ALTER TABLE `contract_verifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `discharges`
--
ALTER TABLE `discharges`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `evaluations`
--
ALTER TABLE `evaluations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `family_signed_documents`
--
ALTER TABLE `family_signed_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `initial_interview_record`
--
ALTER TABLE `initial_interview_record`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `medications`
--
ALTER TABLE `medications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=112;

--
-- AUTO_INCREMENT for table `parental_control_requests`
--
ALTER TABLE `parental_control_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `policy_files`
--
ALTER TABLE `policy_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `receipts`
--
ALTER TABLE `receipts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `schedules`
--
ALTER TABLE `schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `signed_contract_documents`
--
ALTER TABLE `signed_contract_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `signed_documents`
--
ALTER TABLE `signed_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `treatments`
--
ALTER TABLE `treatments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `treatment_activities`
--
ALTER TABLE `treatment_activities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `workflow_state_log`
--
ALTER TABLE `workflow_state_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `contract_documents`
--
ALTER TABLE `contract_documents`
  ADD CONSTRAINT `contract_documents_ibfk_1` FOREIGN KEY (`gambler_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `contract_documents_ibfk_2` FOREIGN KEY (`family_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `contract_documents_ibfk_3` FOREIGN KEY (`booking_id`) REFERENCES `booking_record` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `contract_documents_ibfk_4` FOREIGN KEY (`supervisor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `contract_verifications`
--
ALTER TABLE `contract_verifications`
  ADD CONSTRAINT `contract_verifications_ibfk_1` FOREIGN KEY (`contract_submission_id`) REFERENCES `contract_submissions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `contract_verifications_ibfk_2` FOREIGN KEY (`executive_assistant_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `family_signed_documents`
--
ALTER TABLE `family_signed_documents`
  ADD CONSTRAINT `fk_family_signed_gambler` FOREIGN KEY (`gambler_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_family_signed_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `receipts`
--
ALTER TABLE `receipts`
  ADD CONSTRAINT `receipts_ibfk_1` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `schedules`
--
ALTER TABLE `schedules`
  ADD CONSTRAINT `schedules_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `signed_contract_documents`
--
ALTER TABLE `signed_contract_documents`
  ADD CONSTRAINT `fk_signed_contract_document` FOREIGN KEY (`contract_document_id`) REFERENCES `contract_documents` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `signed_documents`
--
ALTER TABLE `signed_documents`
  ADD CONSTRAINT `fk_signed_documents_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `treatments`
--
ALTER TABLE `treatments`
  ADD CONSTRAINT `treatments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `workflow_state_log`
--
ALTER TABLE `workflow_state_log`
  ADD CONSTRAINT `workflow_state_log_ibfk_1` FOREIGN KEY (`contract_document_id`) REFERENCES `contract_documents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `workflow_state_log_ibfk_2` FOREIGN KEY (`changed_by_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
