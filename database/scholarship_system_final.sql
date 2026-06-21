-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Máy chủ: 127.0.0.1
-- Thời gian đã tạo: Th6 21, 2026 lúc 08:21 AM
-- Phiên bản máy phục vụ: 10.4.32-MariaDB
-- Phiên bản PHP: 8.1.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Cơ sở dữ liệu: `scholarship_system`
--
CREATE DATABASE scholarship_system;
USE scholarship_system;
-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `applications`
--

CREATE TABLE `applications` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `program_id` int(11) NOT NULL,
  `status` enum('draft','submitted','reviewing','eligible','ineligible','approved','rejected','disbursed') DEFAULT 'draft',
  `eligible` tinyint(1) DEFAULT NULL COMMENT '1=đủ điều kiện, 0=không đủ',
  `submitted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `applications`
--

INSERT INTO `applications` (`id`, `student_id`, `program_id`, `status`, `eligible`, `submitted_at`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'submitted', 1, '2026-06-18 07:13:46', '2026-06-18 07:13:46', '2026-06-18 07:13:46'),
(31, 1, 4, 'eligible', 1, '2026-06-20 01:58:48', '2026-06-20 01:58:48', '2026-06-20 01:58:48');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `application_evidence`
--

CREATE TABLE `application_evidence` (
  `id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `original_name` varchar(255) NOT NULL COMMENT 'Tên file gốc của sinh viên',
  `stored_name` varchar(255) NOT NULL COMMENT 'Tên file đã lưu trên server (unique)',
  `file_path` varchar(500) NOT NULL COMMENT 'Đường dẫn tương đối: uploads/evidence/...',
  `file_size` int(11) DEFAULT 0 COMMENT 'Kích thước file (bytes)',
  `file_type` varchar(100) DEFAULT NULL COMMENT 'MIME type (image/jpeg, application/pdf...)',
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `reviewer_comment` text DEFAULT NULL COMMENT 'Nhận xét của reviewer về file minh chứng',
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `application_evidence`
--

INSERT INTO `application_evidence` (`id`, `application_id`, `student_id`, `original_name`, `stored_name`, `file_path`, `file_size`, `file_type`, `status`, `reviewer_comment`, `uploaded_at`) VALUES
(1, 31, 1, 'Ielts Certificate.png', 'ev_31_6a35f3d8b3bd9.png', 'uploads/evidence/ev_31_6a35f3d8b3bd9.png', 159570, 'image/png', 'pending', NULL, '2026-06-20 01:58:48');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `award_certificates`
--

CREATE TABLE `award_certificates` (
  `id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `certificate_code` varchar(100) NOT NULL,
  `issued_at` datetime DEFAULT current_timestamp(),
  `issued_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `comm_templates`
--

CREATE TABLE `comm_templates` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `template_type` enum('scholarship_awarded','scholarship_rejected','certificate_available','program_updated','eligibility_rules_updated','additional_documents_required','custom') NOT NULL DEFAULT 'custom',
  `subject` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `comm_templates`
--

INSERT INTO `comm_templates` (`id`, `name`, `template_type`, `subject`, `body`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Scholarship Awarded', 'scholarship_awarded', '🎉 Scholarship Awarded — {{program_name}}', 'Dear {{student_name}},\n\nYour application for the {{program_name}} scholarship has been APPROVED.\n\nRank: #{{ranking}} · Score: {{score}}\n\nPlease log in to view your award certificate.\n\n— Scholarship Management Office', 1, NULL, '2026-06-18 04:31:18', '2026-06-18 04:31:18'),
(2, 'Scholarship Rejected', 'scholarship_rejected', 'Scholarship Result — {{program_name}}', 'Dear {{student_name}},\n\nThank you for applying to {{program_name}}. After careful review, your application was not selected this round.\n\nRank: #{{ranking}} · Score: {{score}}\n\nWe encourage you to apply again in the next cycle.\n\n— Scholarship Management Office', 1, NULL, '2026-06-18 04:31:18', '2026-06-18 04:31:18'),
(3, 'Certificate Available', 'certificate_available', 'Your Certificate is Ready — {{program_name}}', 'Dear {{student_name}},\n\nYour award certificate for {{program_name}} is now available.\n\nLog in to Student Portal → My Results to download it.\n\nCongratulations!\n\n— Scholarship Management Office', 1, NULL, '2026-06-18 04:31:18', '2026-06-18 04:31:18'),
(4, 'Program Updated', 'program_updated', 'Scholarship Program Updated — {{program_name}}', 'Dear {{student_name}},\n\nThe scholarship program {{program_name}} has been updated. Please review the latest details in the portal.\n\n— Scholarship Management Office', 1, NULL, '2026-06-18 04:31:18', '2026-06-18 04:31:18'),
(5, 'Eligibility Rules Updated', 'eligibility_rules_updated', 'Eligibility Rules Updated — {{program_name}}', 'Dear Reviewer,\n\nEligibility rules for {{program_name}} have been updated. Please review them before proceeding with evaluations.\n\n— Scholarship Management Office', 1, NULL, '2026-06-18 04:31:18', '2026-06-18 04:31:18'),
(6, 'Additional Documents Required', 'additional_documents_required', 'Action Required: Documents — {{program_name}}', 'Dear {{student_name}},\n\nAdditional documents are needed for your {{program_name}} application. Please upload them within 7 business days.\n\n— Scholarship Management Office', 1, NULL, '2026-06-18 04:31:18', '2026-06-18 04:31:18');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `disbursements`
--

CREATE TABLE `disbursements` (
  `id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `status` enum('pending','approved','paid','failed') DEFAULT 'pending',
  `disbursed_at` datetime DEFAULT NULL,
  `note` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `eligibility_results`
--

CREATE TABLE `eligibility_results` (
  `id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `is_passed` tinyint(1) NOT NULL,
  `reason` text DEFAULT NULL,
  `rule_trace` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`rule_trace`)),
  `checked_by` int(11) DEFAULT NULL,
  `reviewer_verification_status` enum('pending','verified','rejected') NOT NULL DEFAULT 'pending',
  `checked_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `eligibility_results`
--

INSERT INTO `eligibility_results` (`id`, `application_id`, `is_passed`, `reason`, `rule_trace`, `checked_by`, `reviewer_verification_status`, `checked_at`) VALUES
(31, 31, 1, 'Meets all eligibility criteria.', '[{\"rule_id\":9,\"rule_type\":\"financial_status\",\"label\":\"Financial Status\",\"operator\":\"=\",\"expected\":\"difficult\",\"actual\":\"N\\/A\",\"passed\":null,\"fail_reason\":\"Rule type not mapped to a known profile field — skipped.\"},{\"rule_id\":10,\"rule_type\":\"gpa\",\"label\":\"GPA Requirement\",\"operator\":\">=\",\"expected\":2.8,\"actual\":3.5,\"passed\":true,\"fail_reason\":null}]', NULL, 'pending', '2026-06-20 01:58:48');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `eligibility_rules`
--

CREATE TABLE `eligibility_rules` (
  `id` int(11) NOT NULL,
  `program_id` int(11) NOT NULL,
  `rule_type` varchar(100) NOT NULL,
  `operator` varchar(10) NOT NULL,
  `value` varchar(100) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `eligibility_rules`
--

INSERT INTO `eligibility_rules` (`id`, `program_id`, `rule_type`, `operator`, `value`, `is_active`, `updated_by`, `updated_at`) VALUES
(1, 1, 'gpa', '>=', '3.5', 1, NULL, '2026-06-17 21:54:01'),
(2, 1, 'activities', '>=', '5', 1, NULL, '2026-06-17 21:54:01'),
(3, 1, 'failed_subjects', '=', '0', 1, NULL, '2026-06-17 21:54:01'),
(4, 2, 'gpa', '>=', '3.2', 1, NULL, '2026-06-17 21:54:01'),
(5, 2, 'research_projects', '>=', '1', 1, NULL, '2026-06-17 21:54:01'),
(6, 2, 'failed_subjects', '=', '0', 1, NULL, '2026-06-17 21:54:01'),
(7, 3, 'activities', '>=', '10', 1, 51, '2026-06-17 21:54:23'),
(8, 3, 'discipline', '=', 'good', 1, NULL, '2026-06-17 21:54:01'),
(9, 4, 'financial_status', '=', 'difficult', 1, NULL, '2026-06-17 21:54:01'),
(10, 4, 'gpa', '>=', '2.8', 1, NULL, '2026-06-17 21:54:01'),
(11, 5, 'english_score', '>=', '850', 1, NULL, '2026-06-17 21:54:01'),
(12, 5, 'gpa', '>=', '3.7', 1, NULL, '2026-06-17 21:54:01'),
(13, 5, 'research_projects', '>=', '2', 1, NULL, '2026-06-17 21:54:01');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `eligibility_rule_requests`
--

CREATE TABLE `eligibility_rule_requests` (
  `id` int(11) NOT NULL,
  `reviewer_id` int(11) NOT NULL,
  `rule_id` int(11) DEFAULT NULL,
  `program_id` int(11) NOT NULL,
  `current_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`current_data`)),
  `proposed_rule_type` varchar(100) DEFAULT NULL,
  `proposed_operator` varchar(10) DEFAULT NULL,
  `proposed_value` varchar(100) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `admin_id` int(11) DEFAULT NULL,
  `admin_note` text DEFAULT NULL,
  `responded_at` datetime DEFAULT NULL,
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `engine_run_history`
--

CREATE TABLE `engine_run_history` (
  `id` int(11) NOT NULL,
  `program_id` int(11) DEFAULT NULL,
  `total_checked` int(11) NOT NULL DEFAULT 0,
  `total_passed` int(11) NOT NULL DEFAULT 0,
  `total_failed` int(11) NOT NULL DEFAULT 0,
  `executed_by` int(11) NOT NULL,
  `run_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `evaluation_scores`
--

CREATE TABLE `evaluation_scores` (
  `id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `criteria_id` int(11) NOT NULL,
  `council_id` int(11) NOT NULL COMMENT 'reviewer/admin chấm điểm',
  `score` decimal(6,2) NOT NULL,
  `note` text DEFAULT NULL,
  `verification_status` enum('verified','need_clarification','rejected_evidence') NOT NULL DEFAULT 'verified',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `scored_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `evaluation_scores`
--

INSERT INTO `evaluation_scores` (`id`, `application_id`, `criteria_id`, `council_id`, `score`, `note`, `verification_status`, `updated_at`, `scored_at`) VALUES
(8, 1, 1, 52, 90.00, 'GPA xuất sắc', 'verified', '2026-06-18 07:13:46', '2026-06-18 07:13:46'),
(9, 1, 2, 52, 100.00, 'Có IELTS 7.5', 'verified', '2026-06-18 07:13:46', '2026-06-18 07:13:46'),
(10, 1, 3, 52, 85.00, 'Tham gia tích cực > 2 hoạt động', 'verified', '2026-06-18 07:13:46', '2026-06-18 07:13:46'),
(11, 1, 4, 52, 80.00, 'Có 1 đề tài cấp khoa', 'verified', '2026-06-18 07:13:46', '2026-06-18 07:13:46');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `evaluation_score_history`
--

CREATE TABLE `evaluation_score_history` (
  `id` int(11) NOT NULL,
  `score_id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `criteria_id` int(11) NOT NULL,
  `reviewer_id` int(11) NOT NULL,
  `old_score` decimal(6,2) DEFAULT NULL,
  `new_score` decimal(6,2) NOT NULL,
  `old_note` text DEFAULT NULL,
  `new_note` text DEFAULT NULL,
  `old_verification_status` varchar(50) DEFAULT NULL,
  `new_verification_status` varchar(50) DEFAULT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `recipient_id` int(11) DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `message_type` enum('direct','broadcast','system_alert','reply') NOT NULL DEFAULT 'direct',
  `parent_id` int(11) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  `has_attachment` tinyint(1) NOT NULL DEFAULT 0,
  `attachment_note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `messages`
--

INSERT INTO `messages` (`id`, `sender_id`, `recipient_id`, `subject`, `body`, `message_type`, `parent_id`, `is_read`, `read_at`, `has_attachment`, `attachment_note`, `created_at`) VALUES
(1, 1, 51, 'Error of upload', 'I can\'t upload file....', 'direct', NULL, 1, '2026-06-19 16:24:18', 0, NULL, '2026-06-19 09:23:28'),
(2, 51, 1, 'Re: Error of upload', 'you can send you evidences to abc@gmail.com, and they will check instead', 'reply', 1, 0, NULL, 0, NULL, '2026-06-19 09:25:35'),
(3, 51, 1, 'Re: Error of upload', 'you can send you evidences to abc@gmail.com, and they will check instead', 'reply', 1, 0, NULL, 0, NULL, '2026-06-19 09:26:06'),
(4, 1, 51, 'Test', 'Hello Admin!!!\r\nCan u see?', 'direct', NULL, 1, '2026-06-19 16:49:11', 0, NULL, '2026-06-19 09:48:49'),
(5, 51, 1, 'Re: Test', 'Yassssssssss', 'reply', 4, 1, '2026-06-19 16:49:33', 0, NULL, '2026-06-19 09:49:23'),
(6, 1, 51, 'Re: Test', 'omgggggggg', 'reply', 4, 1, '2026-06-19 17:26:36', 0, NULL, '2026-06-19 09:49:43'),
(7, 51, 1, 'Re: Re: Test', 'hihihahaha', 'reply', 6, 0, NULL, 0, NULL, '2026-06-19 10:26:50'),
(8, 51, 1, 'Re: Re: Test', 'hihihahaha', 'reply', 6, 0, NULL, 0, NULL, '2026-06-19 10:27:36'),
(9, 51, 1, 'Re: Re: Test', 'noooooo', 'reply', 6, 0, NULL, 0, NULL, '2026-06-19 10:47:38');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `message_recipients`
--

CREATE TABLE `message_recipients` (
  `id` int(11) NOT NULL,
  `message_id` int(11) NOT NULL,
  `recipient_id` int(11) NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `read_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','success','warning','error') DEFAULT 'info',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `type`, `is_read`, `created_at`) VALUES
(1, 1, 'Application Received', 'Your application for the Academic Excellence Scholarship has been successfully submitted.', 'info', 1, '2026-06-13 23:17:03'),
(2, 2, 'Eligibility Passed', 'Congratulations! You have passed the initial eligibility check.', 'success', 0, '2026-06-13 23:17:03'),
(3, 3, 'Eligibility Failed', 'We regret to inform you that your GPA does not meet the minimum requirements.', 'error', 0, '2026-06-13 23:17:03'),
(4, 30, 'Eligibility Check Failed', 'Your application did not meet the eligibility criteria. Reason: Failed criteria: GPA (actual: 3.19) does not satisfy rule: >= 3.5', 'error', 0, '2026-06-13 23:59:37'),
(5, 30, 'Eligibility Check Failed', 'Your application did not meet the eligibility criteria. Reason: Failed criteria: GPA (actual: 3.19) does not satisfy rule: >= 3.5', 'error', 0, '2026-06-13 23:59:39'),
(6, 30, 'Eligibility Check Failed', 'Your application did not meet the eligibility criteria. Reason: Failed criteria: GPA (actual: 3.19) does not satisfy rule: >= 3.5', 'error', 0, '2026-06-13 23:59:42'),
(7, 30, 'Eligibility Check Failed', 'Your application did not meet the eligibility criteria. Reason: Failed criteria: GPA (actual: 3.19) does not satisfy rule: >= 3.5', 'error', 0, '2026-06-13 23:59:43'),
(8, 1, 'Eligibility Check Failed', 'Your application did not meet the eligibility criteria. Reason: Failed criteria: GPA (actual: 3.3) does not satisfy rule: >= 3.5', 'error', 1, '2026-06-14 02:08:20'),
(9, 2, 'Eligibility Check Failed', 'Your application did not meet the eligibility criteria. Reason: Failed criteria: Research Projects (actual: 0) does not satisfy rule: >= 1', 'error', 0, '2026-06-14 02:08:20'),
(10, 3, 'Eligibility Check Failed', 'Your application did not meet the eligibility criteria. Reason: Failed criteria: Disadvantaged Status (actual: 0) does not satisfy rule: = 1', 'error', 0, '2026-06-14 02:08:20'),
(11, 4, 'Eligibility Check Failed', 'Your application did not meet the eligibility criteria. Reason: Failed criteria: GPA (actual: 3.37) does not satisfy rule: >= 3.7; Research Projects (actual: 0) does not satisfy rule: >= 2', 'error', 0, '2026-06-14 02:08:20'),
(12, 5, 'Eligibility Check Failed', 'Your application did not meet the eligibility criteria. Reason: Failed criteria: GPA (actual: 3.1) does not satisfy rule: >= 3.5', 'error', 0, '2026-06-14 02:08:20'),
(13, 6, 'Eligibility Check Failed', 'Your application did not meet the eligibility criteria. Reason: Failed criteria: Research Projects (actual: 0) does not satisfy rule: >= 1', 'error', 0, '2026-06-14 02:08:20'),
(14, 7, 'Eligibility Check Passed', 'Your application has passed the automatic eligibility check.', 'success', 0, '2026-06-14 02:08:20'),
(15, 8, 'Eligibility Check Failed', 'Your application did not meet the eligibility criteria. Reason: Failed criteria: Disadvantaged Status (actual: 0) does not satisfy rule: = 1', 'error', 0, '2026-06-14 02:08:20'),
(16, 9, 'Eligibility Check Failed', 'Your application did not meet the eligibility criteria. Reason: Failed criteria: GPA (actual: 3.41) does not satisfy rule: >= 3.7; Research Projects (actual: 0) does not satisfy rule: >= 2', 'error', 0, '2026-06-14 02:08:20'),
(17, 10, 'Eligibility Check Failed', 'Your application did not meet the eligibility criteria. Reason: Failed criteria: GPA (actual: 3.41) does not satisfy rule: >= 3.5', 'error', 0, '2026-06-14 02:08:20'),
(18, 11, 'Eligibility Check Failed', 'Your application did not meet the eligibility criteria. Reason: Failed criteria: GPA (actual: 3) does not satisfy rule: >= 3.2; Research Projects (actual: 0) does not satisfy rule: >= 1', 'error', 0, '2026-06-14 02:08:20'),
(19, 12, 'Eligibility Check Passed', 'Your application has passed the automatic eligibility check.', 'success', 0, '2026-06-14 02:08:20'),
(20, 13, 'Eligibility Check Failed', 'Your application did not meet the eligibility criteria. Reason: Failed criteria: Disadvantaged Status (actual: 0) does not satisfy rule: = 1', 'error', 0, '2026-06-14 02:08:20'),
(21, 14, 'Eligibility Check Failed', 'Your application did not meet the eligibility criteria. Reason: Failed criteria: GPA (actual: 3.38) does not satisfy rule: >= 3.7; Research Projects (actual: 0) does not satisfy rule: >= 2', 'error', 0, '2026-06-14 02:08:20'),
(22, 15, 'Eligibility Check Failed', 'Your application did not meet the eligibility criteria. Reason: Failed criteria: GPA (actual: 3.38) does not satisfy rule: >= 3.5; Extracurricular Activities (actual: 3) does not satisfy rule: >= 5', 'error', 0, '2026-06-14 02:08:20'),
(23, 16, 'Eligibility Check Failed', 'Your application did not meet the eligibility criteria. Reason: Failed criteria: Research Projects (actual: 0) does not satisfy rule: >= 1', 'error', 0, '2026-06-14 02:08:20'),
(24, 17, 'Eligibility Check Passed', 'Your application has passed the automatic eligibility check.', 'success', 0, '2026-06-14 02:08:20'),
(25, 18, 'Eligibility Check Failed', 'Your application did not meet the eligibility criteria. Reason: Failed criteria: Disadvantaged Status (actual: 0) does not satisfy rule: = 1', 'error', 0, '2026-06-14 02:08:20'),
(26, 19, 'Eligibility Check Failed', 'Your application did not meet the eligibility criteria. Reason: Failed criteria: GPA (actual: 2.61) does not satisfy rule: >= 3.7; Research Projects (actual: 0) does not satisfy rule: >= 2', 'error', 0, '2026-06-14 02:08:20'),
(27, 20, 'Eligibility Check Failed', 'Your application did not meet the eligibility criteria. Reason: Failed criteria: GPA (actual: 2.61) does not satisfy rule: >= 3.5', 'error', 0, '2026-06-14 02:08:20'),
(28, 21, 'Eligibility Check Failed', 'Your application did not meet the eligibility criteria. Reason: Failed criteria: GPA (actual: 2.61) does not satisfy rule: >= 3.2; Research Projects (actual: 0) does not satisfy rule: >= 1', 'error', 0, '2026-06-14 02:08:20'),
(29, 22, 'Eligibility Check Passed', 'Your application has passed the automatic eligibility check.', 'success', 0, '2026-06-14 02:08:20'),
(30, 23, 'Eligibility Check Failed', 'Your application did not meet the eligibility criteria. Reason: Failed criteria: Disadvantaged Status (actual: 0) does not satisfy rule: = 1', 'error', 0, '2026-06-14 02:08:20'),
(31, 24, 'Eligibility Check Failed', 'Your application did not meet the eligibility criteria. Reason: Failed criteria: GPA (actual: 2.62) does not satisfy rule: >= 3.7; Research Projects (actual: 0) does not satisfy rule: >= 2', 'error', 0, '2026-06-14 02:08:20'),
(32, 25, 'Eligibility Check Failed', 'Your application did not meet the eligibility criteria. Reason: Failed criteria: Extracurricular Activities (actual: 3) does not satisfy rule: >= 5', 'error', 0, '2026-06-14 02:08:20'),
(33, 26, 'Eligibility Check Failed', 'Your application did not meet the eligibility criteria. Reason: Failed criteria: Research Projects (actual: 0) does not satisfy rule: >= 1', 'error', 0, '2026-06-14 02:08:20'),
(34, 27, 'Eligibility Check Passed', 'Your application has passed the automatic eligibility check.', 'success', 0, '2026-06-14 02:08:20'),
(35, 28, 'Eligibility Check Failed', 'Your application did not meet the eligibility criteria. Reason: Failed criteria: Disadvantaged Status (actual: 0) does not satisfy rule: = 1', 'error', 0, '2026-06-14 02:08:20'),
(36, 29, 'Eligibility Check Failed', 'Your application did not meet the eligibility criteria. Reason: Failed criteria: GPA (actual: 3.12) does not satisfy rule: >= 3.7; Research Projects (actual: 0) does not satisfy rule: >= 2', 'error', 0, '2026-06-14 02:08:20'),
(37, 30, 'Eligibility Check Failed', 'Your application did not meet the eligibility criteria. Reason: Failed criteria: GPA (actual: 3.19) does not satisfy rule: >= 3.5', 'error', 0, '2026-06-14 09:21:49'),
(38, 30, 'Eligibility Check Failed', 'Your application did not meet the eligibility criteria. Reason: Failed criteria: GPA (actual: 3.19) does not satisfy rule: >= 3.5', 'error', 0, '2026-06-14 09:21:51'),
(39, 30, 'Eligibility Check Failed', 'Your application did not meet the eligibility criteria. Reason: Failed criteria: GPA (actual: 3.19) does not satisfy rule: >= 3.5', 'error', 0, '2026-06-14 09:21:52'),
(40, 30, 'Eligibility Check Failed', 'Your application did not meet the eligibility criteria. Reason: Failed criteria: GPA (actual: 3.19) does not satisfy rule: >= 3.5', 'error', 0, '2026-06-14 09:21:52'),
(41, 30, 'Eligibility Check Failed', 'Your application did not meet the eligibility criteria. Reason: Failed criteria: GPA (actual: 3.19) does not satisfy rule: >= 3.5', 'error', 0, '2026-06-14 09:21:52'),
(42, 30, 'Eligibility Check Failed', 'Your application did not meet the eligibility criteria. Reason: Failed criteria: GPA (actual: 3.19) does not satisfy rule: >= 3.5', 'error', 0, '2026-06-14 09:21:53'),
(43, 30, 'Eligibility Check Failed', 'Your application did not meet the eligibility criteria. Reason: Failed criteria: GPA (actual: 3.19) does not satisfy rule: >= 3.5', 'error', 0, '2026-06-14 09:21:53'),
(44, 30, 'Eligibility Check Failed', 'Your application did not meet the eligibility criteria. Reason: Failed criteria: GPA (actual: 3.19) does not satisfy rule: >= 3.5', 'error', 0, '2026-06-14 09:21:54'),
(45, 30, 'Eligibility Check Failed', 'Your application did not meet the eligibility criteria. Reason: Failed criteria: GPA (actual: 3.19) does not satisfy rule: >= 3.5', 'error', 0, '2026-06-14 09:22:06'),
(46, 30, 'Eligibility Check Failed', 'Your application did not meet the eligibility criteria. Reason: Failed criteria: GPA (actual: 3.19) does not satisfy rule: >= 3.5', 'error', 0, '2026-06-14 09:59:43'),
(47, 29, 'Eligibility Check Failed', 'Your application did not meet the eligibility criteria. Reason: Failed criteria: GPA (actual: 3.12) does not satisfy rule: >= 3.7; Research Projects (actual: 0) does not satisfy rule: >= 2', 'error', 0, '2026-06-14 09:59:44'),
(48, 30, 'Eligibility Check Failed', 'Your application did not meet the eligibility criteria. Reason: Failed criteria: GPA (actual: 3.19) does not satisfy rule: >= 3.5', 'error', 0, '2026-06-17 14:56:02'),
(49, 1, 'Scholarship Result — International Talent Scholarship', 'Thank you for applying to International Talent Scholarship. Unfortunately, your application was not selected this round. Rank: #1 · Score: 85.50.', 'info', 1, '2026-06-18 04:32:45'),
(50, 51, 'Error of upload', 'I can\'t upload file....', 'info', 1, '2026-06-19 09:23:28'),
(51, 1, 'Re: Error of upload', 'you can send you evidences to abc@gmail.com, and they will check instead', 'info', 1, '2026-06-19 09:25:35'),
(52, 1, 'Re: Error of upload', 'you can send you evidences to abc@gmail.com, and they will check instead', 'info', 1, '2026-06-19 09:26:06'),
(53, 51, 'Test', 'Hello Admin!!!\r\nCan u see?', 'info', 0, '2026-06-19 09:48:49'),
(54, 1, 'Re: Test', 'Yassssssssss', 'info', 1, '2026-06-19 09:49:23'),
(55, 51, 'Re: Test', 'omgggggggg', 'info', 0, '2026-06-19 09:49:43'),
(56, 1, 'Re: Re: Test', 'hihihahaha', 'info', 0, '2026-06-19 10:26:50'),
(57, 1, 'Re: Re: Test', 'hihihahaha', 'info', 0, '2026-06-19 10:27:36'),
(58, 1, 'Re: Re: Test', 'noooooo', 'info', 1, '2026-06-19 10:47:38'),
(59, 51, 'New evidence uploaded', 'A student has uploaded new evidence for review.', 'info', 0, '2026-06-20 01:58:48'),
(60, 52, 'New evidence uploaded', 'A student has uploaded new evidence for review.', 'info', 0, '2026-06-20 01:58:48'),
(61, 53, 'New evidence uploaded', 'A student has uploaded new evidence for review.', 'info', 0, '2026-06-20 01:58:48'),
(62, 1, 'Eligibility Check Passed', 'Your application has passed the automatic eligibility check.', 'success', 0, '2026-06-20 01:58:48'),
(63, 1, 'Application Submitted', 'Your application has been submitted successfully. Eligibility check is being processed.', 'info', 0, '2026-06-20 01:58:48');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `program_requests`
--

CREATE TABLE `program_requests` (
  `id` int(11) NOT NULL,
  `reviewer_id` int(11) NOT NULL,
  `request_type` enum('add','update','suspend','delete') NOT NULL DEFAULT 'add',
  `program_id` int(11) DEFAULT NULL,
  `proposed_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`proposed_data`)),
  `reason` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `admin_id` int(11) DEFAULT NULL,
  `admin_note` text DEFAULT NULL,
  `responded_at` datetime DEFAULT NULL,
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `ranking_results`
--

CREATE TABLE `ranking_results` (
  `id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `total_score` decimal(5,2) NOT NULL,
  `rank` int(11) NOT NULL,
  `recommended` tinyint(1) DEFAULT 0,
  `awarded` tinyint(1) NOT NULL DEFAULT 0,
  `tie_break_reason` varchar(100) DEFAULT NULL,
  `published` tinyint(1) NOT NULL DEFAULT 0,
  `published_at` datetime DEFAULT NULL,
  `published_by` int(11) DEFAULT NULL,
  `generated_at` datetime DEFAULT NULL,
  `generated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `ranking_run_history`
--

CREATE TABLE `ranking_run_history` (
  `id` int(11) NOT NULL,
  `program_id` int(11) DEFAULT NULL,
  `total_ranked` int(11) NOT NULL DEFAULT 0,
  `awarded_count` int(11) NOT NULL DEFAULT 0,
  `slots_used` int(11) NOT NULL DEFAULT 0,
  `generated_by` int(11) NOT NULL,
  `generated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `reports`
--

CREATE TABLE `reports` (
  `id` int(11) NOT NULL,
  `report_type` enum('ranking','disbursement','certificate','summary') NOT NULL,
  `generated_by` int(11) DEFAULT NULL,
  `file_url` varchar(255) DEFAULT NULL,
  `program_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `reports`
--

INSERT INTO `reports` (`id`, `report_type`, `generated_by`, `file_url`, `program_id`, `created_at`) VALUES
(1, 'disbursement', 36, '/reports/student_question_disbursement_student_1.pdf', 1, '2026-06-13 23:38:38'),
(2, 'certificate', 42, '/reports/student_question_certificate_student_1.pdf', 1, '2026-06-13 23:38:38'),
(3, 'ranking', 51, NULL, 1, '2026-06-14 08:27:15'),
(4, 'ranking', 51, NULL, 1, '2026-06-14 09:22:28');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `reviewer_decisions`
--

CREATE TABLE `reviewer_decisions` (
  `id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `reviewer_id` int(11) NOT NULL,
  `decision` varchar(50) NOT NULL,
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `reviewer_recommendations`
--

CREATE TABLE `reviewer_recommendations` (
  `id` int(11) NOT NULL,
  `reviewer_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `category` enum('policy','eligibility','scoring','system_improvement','other') DEFAULT 'other',
  `message` text NOT NULL,
  `priority` enum('low','medium','high','critical') DEFAULT 'medium',
  `status` enum('sent','reviewed','implemented','rejected') DEFAULT 'sent',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `reviewer_recommendations`
--

INSERT INTO `reviewer_recommendations` (`id`, `reviewer_id`, `title`, `category`, `message`, `priority`, `status`, `created_at`) VALUES
(1, 53, 'Update new rule', '', 'I want to rewrite the rules of....', 'medium', 'sent', '2026-06-19 08:36:07'),
(2, 53, 'Create scholarship for students with strong extracurricular contribution', '', 'Should we create a scholarship for students with strong extracurricular contribution? This can encourage students to join social activities, competitions, volunteer programs, and leadership projects. Admin may consider adding a separate scholarship type for this group.', 'high', 'sent', '2026-06-19 08:40:33');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `reviewer_verifications`
--

CREATE TABLE `reviewer_verifications` (
  `id` int(11) NOT NULL,
  `eligibility_id` int(11) NOT NULL,
  `reviewer_id` int(11) NOT NULL,
  `status` enum('pending','verified','rejected') NOT NULL DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `scholarship_programs`
--

CREATE TABLE `scholarship_programs` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `budget` decimal(12,2) NOT NULL,
  `slots` int(11) NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('open','closed') DEFAULT 'open'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `scholarship_programs`
--

INSERT INTO `scholarship_programs` (`id`, `name`, `description`, `budget`, `slots`, `start_date`, `end_date`, `status`) VALUES
(1, 'International Talent Scholarship', 'Scholarship for internationally competitive students.', 20000000.00, 3, '2026-03-01', '2026-09-01', 'open'),
(2, 'Research Innovation Scholarship', 'Support scholarship for students participating in scientific research.', 15000000.00, 5, '2026-01-10', '2026-07-15', 'open'),
(3, 'Academic Excellence Scholarship', 'Scholarship for students with outstanding academic performance.', 12000000.00, 5, '2026-01-01', '2026-06-30', 'open'),
(4, 'Community Leadership Scholarship', 'Scholarship for active students contributing to community activities.', 8000000.00, 6, '2026-02-01', '2026-08-01', 'open'),
(5, 'Financial Support Scholarship', 'Scholarship for students with financial difficulties.', 2000000.00, 10, '2026-01-15', '2026-07-30', 'open');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `scoring_criteria`
--

CREATE TABLE `scoring_criteria` (
  `id` int(11) NOT NULL,
  `program_id` int(11) NOT NULL,
  `criterion_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `weight` decimal(5,2) NOT NULL,
  `max_score` decimal(5,2) DEFAULT 100.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `scoring_criteria`
--

INSERT INTO `scoring_criteria` (`id`, `program_id`, `criterion_name`, `description`, `weight`, `max_score`, `is_active`, `updated_by`, `updated_at`) VALUES
(1, 1, 'GPA', NULL, 40.00, 100.00, 1, NULL, '2026-06-18 07:13:46'),
(2, 1, 'Language Certificate', NULL, 20.00, 100.00, 1, NULL, '2026-06-18 07:13:46'),
(3, 1, 'Extracurricular Activities', NULL, 20.00, 100.00, 1, NULL, '2026-06-18 07:13:46'),
(4, 1, 'Research Projects', NULL, 20.00, 100.00, 1, NULL, '2026-06-18 07:13:46');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `scoring_criteria_requests`
--

CREATE TABLE `scoring_criteria_requests` (
  `id` int(11) NOT NULL,
  `reviewer_id` int(11) NOT NULL,
  `criterion_id` int(11) DEFAULT NULL,
  `program_id` int(11) NOT NULL,
  `current_criterion_name` varchar(100) DEFAULT NULL,
  `current_weight` decimal(5,2) DEFAULT NULL,
  `current_max_score` decimal(5,2) DEFAULT NULL,
  `proposed_criterion_name` varchar(100) DEFAULT NULL,
  `proposed_weight` decimal(5,2) DEFAULT NULL,
  `proposed_max_score` decimal(5,2) DEFAULT NULL,
  `proposed_description` text DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `admin_id` int(11) DEFAULT NULL,
  `admin_note` text DEFAULT NULL,
  `responded_at` datetime DEFAULT NULL,
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `student_profiles`
--

CREATE TABLE `student_profiles` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `faculty` varchar(100) DEFAULT NULL,
  `major` varchar(100) DEFAULT NULL,
  `gpa` decimal(3,2) NOT NULL DEFAULT 0.00,
  `activities_count` int(11) DEFAULT 0 COMMENT 'Số hoạt động ngoại khoá',
  `is_disadvantaged` tinyint(1) DEFAULT 0 COMMENT '1=hoàn cảnh khó khăn',
  `research_count` int(11) DEFAULT 0 COMMENT 'Số công trình nghiên cứu',
  `failed_subjects` int(11) DEFAULT 0 COMMENT 'Số môn thi lại / trượt',
  `has_language_cert` tinyint(1) DEFAULT 0 COMMENT '1=Có chứng chỉ, 0=Không',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `activities_list` text DEFAULT NULL,
  `research_list` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `student_profiles`
--

INSERT INTO `student_profiles` (`id`, `student_id`, `faculty`, `major`, `gpa`, `activities_count`, `is_disadvantaged`, `research_count`, `failed_subjects`, `has_language_cert`, `created_at`, `updated_at`, `activities_list`, `research_list`) VALUES
(51, 1, 'KHUD', 'MIS', 3.50, 4, 0, 2, 0, 1, '2026-06-20 01:54:09', '2026-06-20 01:55:44', NULL, NULL);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('student','reviewer','admin') NOT NULL,
  `student_code` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `users`
--

INSERT INTO `users` (`id`, `full_name`, `email`, `password_hash`, `role`, `student_code`, `created_at`, `updated_at`) VALUES
(1, 'Nguyen Van An', 'an01@student.edu.vn', '$2y$10$N5j0.......', 'student', 'SV001', '2026-06-13 23:13:31', '2026-06-14 03:38:17'),
(2, 'Tran Minh Hoang', 'hoang02@student.edu.vn', '$2y$10$3QKD7gdGNADeV6lAzYOUzuaR5Rhw6taj8Ve7CmE5lfv5MD253y6EG', 'student', 'SV002', '2026-06-13 23:13:31', '2026-06-13 23:13:31'),
(3, 'Le Thu Ha', 'ha03@student.edu.vn', '$2y$10$3QKD7gdGNADeV6lAzYOUzuaR5Rhw6taj8Ve7CmE5lfv5MD253y6EG', 'student', 'SV003', '2026-06-13 23:13:31', '2026-06-13 23:13:31'),
(4, 'Pham Gia Bao', 'bao04@student.edu.vn', '$2y$10$3QKD7gdGNADeV6lAzYOUzuaR5Rhw6taj8Ve7CmE5lfv5MD253y6EG', 'student', 'SV004', '2026-06-13 23:13:31', '2026-06-13 23:13:31'),
(5, 'Vo Thanh Dat', 'dat05@student.edu.vn', '$2y$10$3QKD7gdGNADeV6lAzYOUzuaR5Rhw6taj8Ve7CmE5lfv5MD253y6EG', 'student', 'SV005', '2026-06-13 23:13:31', '2026-06-13 23:13:31'),
(6, 'Bui Quoc Khanh', 'khanh06@student.edu.vn', '$2y$10$3QKD7gdGNADeV6lAzYOUzuaR5Rhw6taj8Ve7CmE5lfv5MD253y6EG', 'student', 'SV006', '2026-06-13 23:13:31', '2026-06-13 23:13:31'),
(7, 'Dang Ngoc Linh', 'linh07@student.edu.vn', '$2y$10$3QKD7gdGNADeV6lAzYOUzuaR5Rhw6taj8Ve7CmE5lfv5MD253y6EG', 'student', 'SV007', '2026-06-13 23:13:31', '2026-06-13 23:13:31'),
(8, 'Hoang Minh Quan', 'quan08@student.edu.vn', '$2y$10$3QKD7gdGNADeV6lAzYOUzuaR5Rhw6taj8Ve7CmE5lfv5MD253y6EG', 'student', 'SV008', '2026-06-13 23:13:31', '2026-06-13 23:13:31'),
(9, 'Do Thi Mai', 'mai09@student.edu.vn', '$2y$10$3QKD7gdGNADeV6lAzYOUzuaR5Rhw6taj8Ve7CmE5lfv5MD253y6EG', 'student', 'SV009', '2026-06-13 23:13:31', '2026-06-13 23:13:31'),
(10, 'Phan Duc Huy', 'huy10@student.edu.vn', '$2y$10$3QKD7gdGNADeV6lAzYOUzuaR5Rhw6taj8Ve7CmE5lfv5MD253y6EG', 'student', 'SV010', '2026-06-13 23:13:31', '2026-06-13 23:13:31'),
(11, 'Nguyen Tuan Kiet', 'kiet11@student.edu.vn', '$2y$10$3QKD7gdGNADeV6lAzYOUzuaR5Rhw6taj8Ve7CmE5lfv5MD253y6EG', 'student', 'SV011', '2026-06-13 23:13:31', '2026-06-13 23:13:31'),
(12, 'Tran Bao Chau', 'chau12@student.edu.vn', '$2y$10$3QKD7gdGNADeV6lAzYOUzuaR5Rhw6taj8Ve7CmE5lfv5MD253y6EG', 'student', 'SV012', '2026-06-13 23:13:31', '2026-06-13 23:13:31'),
(13, 'Le Minh Tri', 'tri13@student.edu.vn', '$2y$10$3QKD7gdGNADeV6lAzYOUzuaR5Rhw6taj8Ve7CmE5lfv5MD253y6EG', 'student', 'SV013', '2026-06-13 23:13:31', '2026-06-13 23:13:31'),
(14, 'Pham Thanh Tung', 'tung14@student.edu.vn', '$2y$10$3QKD7gdGNADeV6lAzYOUzuaR5Rhw6taj8Ve7CmE5lfv5MD253y6EG', 'student', 'SV014', '2026-06-13 23:13:31', '2026-06-13 23:13:31'),
(15, 'Vo Ngoc Anh', 'anh15@student.edu.vn', '$2y$10$3QKD7gdGNADeV6lAzYOUzuaR5Rhw6taj8Ve7CmE5lfv5MD253y6EG', 'student', 'SV015', '2026-06-13 23:13:31', '2026-06-13 23:13:31'),
(16, 'Bui Thanh Nhan', 'nhan16@student.edu.vn', '$2y$10$3QKD7gdGNADeV6lAzYOUzuaR5Rhw6taj8Ve7CmE5lfv5MD253y6EG', 'student', 'SV016', '2026-06-13 23:13:31', '2026-06-13 23:13:31'),
(17, 'Dang Hoang Long', 'long17@student.edu.vn', '$2y$10$3QKD7gdGNADeV6lAzYOUzuaR5Rhw6taj8Ve7CmE5lfv5MD253y6EG', 'student', 'SV017', '2026-06-13 23:13:31', '2026-06-13 23:13:31'),
(18, 'Hoang Quynh Nhu', 'nhu18@student.edu.vn', '$2y$10$3QKD7gdGNADeV6lAzYOUzuaR5Rhw6taj8Ve7CmE5lfv5MD253y6EG', 'student', 'SV018', '2026-06-13 23:13:31', '2026-06-13 23:13:31'),
(19, 'Do Minh Tam', 'tam19@student.edu.vn', '$2y$10$3QKD7gdGNADeV6lAzYOUzuaR5Rhw6taj8Ve7CmE5lfv5MD253y6EG', 'student', 'SV019', '2026-06-13 23:13:31', '2026-06-13 23:13:31'),
(20, 'Phan Gia Huy', 'huy20@student.edu.vn', '$2y$10$3QKD7gdGNADeV6lAzYOUzuaR5Rhw6taj8Ve7CmE5lfv5MD253y6EG', 'student', 'SV020', '2026-06-13 23:13:31', '2026-06-13 23:13:31'),
(21, 'Nguyen Duc Tai', 'tai21@student.edu.vn', '$2y$10$3QKD7gdGNADeV6lAzYOUzuaR5Rhw6taj8Ve7CmE5lfv5MD253y6EG', 'student', 'SV021', '2026-06-13 23:13:31', '2026-06-13 23:13:31'),
(22, 'Tran Ha My', 'my22@student.edu.vn', '$2y$10$3QKD7gdGNADeV6lAzYOUzuaR5Rhw6taj8Ve7CmE5lfv5MD253y6EG', 'student', 'SV022', '2026-06-13 23:13:31', '2026-06-13 23:13:31'),
(23, 'Le Bao Ngoc', 'ngoc23@student.edu.vn', '$2y$10$3QKD7gdGNADeV6lAzYOUzuaR5Rhw6taj8Ve7CmE5lfv5MD253y6EG', 'student', 'SV023', '2026-06-13 23:13:31', '2026-06-13 23:13:31'),
(24, 'Pham Thanh Ha', 'ha24@student.edu.vn', '$2y$10$3QKD7gdGNADeV6lAzYOUzuaR5Rhw6taj8Ve7CmE5lfv5MD253y6EG', 'student', 'SV024', '2026-06-13 23:13:31', '2026-06-13 23:13:31'),
(25, 'Vo Minh Duc', 'duc25@student.edu.vn', '$2y$10$3QKD7gdGNADeV6lAzYOUzuaR5Rhw6taj8Ve7CmE5lfv5MD253y6EG', 'student', 'SV025', '2026-06-13 23:13:31', '2026-06-13 23:13:31'),
(26, 'Bui Tuan Anh', 'anh26@student.edu.vn', '$2y$10$3QKD7gdGNADeV6lAzYOUzuaR5Rhw6taj8Ve7CmE5lfv5MD253y6EG', 'student', 'SV026', '2026-06-13 23:13:31', '2026-06-13 23:13:31'),
(27, 'Dang Gia Han', 'han27@student.edu.vn', '$2y$10$3QKD7gdGNADeV6lAzYOUzuaR5Rhw6taj8Ve7CmE5lfv5MD253y6EG', 'student', 'SV027', '2026-06-13 23:13:31', '2026-06-13 23:13:31'),
(28, 'Hoang Thanh Son', 'son28@student.edu.vn', '$2y$10$3QKD7gdGNADeV6lAzYOUzuaR5Rhw6taj8Ve7CmE5lfv5MD253y6EG', 'student', 'SV028', '2026-06-13 23:13:31', '2026-06-13 23:13:31'),
(29, 'Do Thi Lan', 'lan29@student.edu.vn', '$2y$10$3QKD7gdGNADeV6lAzYOUzuaR5Rhw6taj8Ve7CmE5lfv5MD253y6EG', 'student', 'SV029', '2026-06-13 23:13:31', '2026-06-13 23:13:31'),
(30, 'Phan Minh Khoa', 'khoa30@student.edu.vn', '$2y$10$3QKD7gdGNADeV6lAzYOUzuaR5Rhw6taj8Ve7CmE5lfv5MD253y6EG', 'student', 'SV030', '2026-06-13 23:13:31', '2026-06-13 23:13:31'),
(31, 'Nguyen Anh Tuan', 'tuan31@student.edu.vn', '$2y$10$3QKD7gdGNADeV6lAzYOUzuaR5Rhw6taj8Ve7CmE5lfv5MD253y6EG', 'student', 'SV031', '2026-06-13 23:13:31', '2026-06-13 23:13:31'),
(32, 'Tran Kim Ngan', 'ngan32@student.edu.vn', '$2y$10$3QKD7gdGNADeV6lAzYOUzuaR5Rhw6taj8Ve7CmE5lfv5MD253y6EG', 'student', 'SV032', '2026-06-13 23:13:31', '2026-06-13 23:13:31'),
(33, 'Le Thanh Binh', 'binh33@student.edu.vn', '$2y$10$3QKD7gdGNADeV6lAzYOUzuaR5Rhw6taj8Ve7CmE5lfv5MD253y6EG', 'student', 'SV033', '2026-06-13 23:13:31', '2026-06-13 23:13:31'),
(34, 'Pham Hoang Nam', 'nam34@student.edu.vn', '$2y$10$3QKD7gdGNADeV6lAzYOUzuaR5Rhw6taj8Ve7CmE5lfv5MD253y6EG', 'student', 'SV034', '2026-06-13 23:13:31', '2026-06-13 23:13:31'),
(35, 'Vo Bao Tram', 'tram35@student.edu.vn', '$2y$10$3QKD7gdGNADeV6lAzYOUzuaR5Rhw6taj8Ve7CmE5lfv5MD253y6EG', 'student', 'SV035', '2026-06-13 23:13:31', '2026-06-13 23:13:31'),
(36, 'Bui Minh Thu', 'thu36@student.edu.vn', '$2y$10$3QKD7gdGNADeV6lAzYOUzuaR5Rhw6taj8Ve7CmE5lfv5MD253y6EG', 'student', 'SV036', '2026-06-13 23:13:31', '2026-06-13 23:13:31'),
(37, 'Dang Quoc Viet', 'viet37@student.edu.vn', '$2y$10$3QKD7gdGNADeV6lAzYOUzuaR5Rhw6taj8Ve7CmE5lfv5MD253y6EG', 'student', 'SV037', '2026-06-13 23:13:31', '2026-06-13 23:13:31'),
(38, 'Hoang Gia Linh', 'linh38@student.edu.vn', '$2y$10$3QKD7gdGNADeV6lAzYOUzuaR5Rhw6taj8Ve7CmE5lfv5MD253y6EG', 'student', 'SV038', '2026-06-13 23:13:31', '2026-06-13 23:13:31'),
(39, 'Do Thanh Phuc', 'phuc39@student.edu.vn', '$2y$10$3QKD7gdGNADeV6lAzYOUzuaR5Rhw6taj8Ve7CmE5lfv5MD253y6EG', 'student', 'SV039', '2026-06-13 23:13:31', '2026-06-13 23:13:31'),
(40, 'Phan Duc Minh', 'minh40@student.edu.vn', '$2y$10$3QKD7gdGNADeV6lAzYOUzuaR5Rhw6taj8Ve7CmE5lfv5MD253y6EG', 'student', 'SV040', '2026-06-13 23:13:31', '2026-06-13 23:13:31'),
(41, 'Nguyen Thi Yen', 'yen41@student.edu.vn', '$2y$10$3QKD7gdGNADeV6lAzYOUzuaR5Rhw6taj8Ve7CmE5lfv5MD253y6EG', 'student', 'SV041', '2026-06-13 23:13:31', '2026-06-13 23:13:31'),
(42, 'Tran Minh Chau', 'chau42@student.edu.vn', '$2y$10$3QKD7gdGNADeV6lAzYOUzuaR5Rhw6taj8Ve7CmE5lfv5MD253y6EG', 'student', 'SV042', '2026-06-13 23:13:31', '2026-06-13 23:13:31'),
(43, 'Le Hoang Vu', 'vu43@student.edu.vn', '$2y$10$3QKD7gdGNADeV6lAzYOUzuaR5Rhw6taj8Ve7CmE5lfv5MD253y6EG', 'student', 'SV043', '2026-06-13 23:13:31', '2026-06-13 23:13:31'),
(44, 'Pham Ngoc Han', 'han44@student.edu.vn', '$2y$10$3QKD7gdGNADeV6lAzYOUzuaR5Rhw6taj8Ve7CmE5lfv5MD253y6EG', 'student', 'SV044', '2026-06-13 23:13:31', '2026-06-13 23:13:31'),
(45, 'Vo Quang Huy', 'huy45@student.edu.vn', '$2y$10$3QKD7gdGNADeV6lAzYOUzuaR5Rhw6taj8Ve7CmE5lfv5MD253y6EG', 'student', 'SV045', '2026-06-13 23:13:31', '2026-06-13 23:13:31'),
(46, 'Bui Thanh Tung', 'tung46@student.edu.vn', '$2y$10$3QKD7gdGNADeV6lAzYOUzuaR5Rhw6taj8Ve7CmE5lfv5MD253y6EG', 'student', 'SV046', '2026-06-13 23:13:31', '2026-06-13 23:13:31'),
(47, 'Dang Minh Anh', 'anh47@student.edu.vn', '$2y$10$3QKD7gdGNADeV6lAzYOUzuaR5Rhw6taj8Ve7CmE5lfv5MD253y6EG', 'student', 'SV047', '2026-06-13 23:13:31', '2026-06-13 23:13:31'),
(48, 'Hoang Bao Long', 'long48@student.edu.vn', '$2y$10$3QKD7gdGNADeV6lAzYOUzuaR5Rhw6taj8Ve7CmE5lfv5MD253y6EG', 'student', 'SV048', '2026-06-13 23:13:31', '2026-06-13 23:13:31'),
(49, 'Do Gia Huy', 'huy49@student.edu.vn', '$2y$10$3QKD7gdGNADeV6lAzYOUzuaR5Rhw6taj8Ve7CmE5lfv5MD253y6EG', 'student', 'SV049', '2026-06-13 23:13:31', '2026-06-13 23:13:31'),
(50, 'Phan Thanh Dat', 'dat50@student.edu.vn', '$2y$10$3QKD7gdGNADeV6lAzYOUzuaR5Rhw6taj8Ve7CmE5lfv5MD253y6EG', 'student', 'SV050', '2026-06-13 23:13:31', '2026-06-13 23:13:31'),
(51, 'System Admin', 'admin@scholarship.edu.vn', '$2y$10$e0NR9o8K3J7K4s0Qv9Jj0u6dM8m8V4N2z3KxvVq5J4Wn8g6xvJ8eS', 'admin', NULL, '2026-06-13 23:13:31', '2026-06-18 08:10:49'),
(52, 'Reviewer One', 'reviewer1@scholarship.edu.vn', '$2y$10$lQC46ZT3OBD6zi6wz3kYPOKbi2n0HshzQdS9xOmL1hqRvGPu.wAD2', 'reviewer', NULL, '2026-06-13 23:13:31', '2026-06-13 23:13:31'),
(53, 'Reviewer Two', 'reviewer2@scholarship.edu.vn', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'reviewer', NULL, '2026-06-13 23:13:31', '2026-06-13 23:57:03');

--
-- Chỉ mục cho các bảng đã đổ
--

--
-- Chỉ mục cho bảng `applications`
--
ALTER TABLE `applications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_student_program` (`student_id`,`program_id`),
  ADD KEY `program_id` (`program_id`);

--
-- Chỉ mục cho bảng `application_evidence`
--
ALTER TABLE `application_evidence`
  ADD PRIMARY KEY (`id`),
  ADD KEY `application_id` (`application_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Chỉ mục cho bảng `award_certificates`
--
ALTER TABLE `award_certificates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `certificate_code` (`certificate_code`),
  ADD KEY `application_id` (`application_id`),
  ADD KEY `issued_by` (`issued_by`);

--
-- Chỉ mục cho bảng `comm_templates`
--
ALTER TABLE `comm_templates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Chỉ mục cho bảng `disbursements`
--
ALTER TABLE `disbursements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `application_id` (`application_id`);

--
-- Chỉ mục cho bảng `eligibility_results`
--
ALTER TABLE `eligibility_results`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_eligibility_results_applications` (`application_id`);

--
-- Chỉ mục cho bảng `eligibility_rules`
--
ALTER TABLE `eligibility_rules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `program_id` (`program_id`);

--
-- Chỉ mục cho bảng `eligibility_rule_requests`
--
ALTER TABLE `eligibility_rule_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `reviewer_id` (`reviewer_id`),
  ADD KEY `rule_id` (`rule_id`),
  ADD KEY `program_id` (`program_id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Chỉ mục cho bảng `engine_run_history`
--
ALTER TABLE `engine_run_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `program_id` (`program_id`),
  ADD KEY `executed_by` (`executed_by`);

--
-- Chỉ mục cho bảng `evaluation_scores`
--
ALTER TABLE `evaluation_scores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_eval` (`application_id`,`criteria_id`,`council_id`),
  ADD KEY `criteria_id` (`criteria_id`),
  ADD KEY `council_id` (`council_id`);

--
-- Chỉ mục cho bảng `evaluation_score_history`
--
ALTER TABLE `evaluation_score_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `score_id` (`score_id`),
  ADD KEY `application_id` (`application_id`),
  ADD KEY `criteria_id` (`criteria_id`),
  ADD KEY `reviewer_id` (`reviewer_id`);

--
-- Chỉ mục cho bảng `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `recipient_id` (`recipient_id`);

--
-- Chỉ mục cho bảng `message_recipients`
--
ALTER TABLE `message_recipients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `message_id` (`message_id`),
  ADD KEY `recipient_id` (`recipient_id`);

--
-- Chỉ mục cho bảng `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Chỉ mục cho bảng `program_requests`
--
ALTER TABLE `program_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `reviewer_id` (`reviewer_id`),
  ADD KEY `program_id` (`program_id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Chỉ mục cho bảng `ranking_results`
--
ALTER TABLE `ranking_results`
  ADD PRIMARY KEY (`id`),
  ADD KEY `application_id` (`application_id`);

--
-- Chỉ mục cho bảng `ranking_run_history`
--
ALTER TABLE `ranking_run_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `program_id` (`program_id`),
  ADD KEY `generated_by` (`generated_by`);

--
-- Chỉ mục cho bảng `reports`
--
ALTER TABLE `reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `generated_by` (`generated_by`),
  ADD KEY `program_id` (`program_id`);

--
-- Chỉ mục cho bảng `reviewer_decisions`
--
ALTER TABLE `reviewer_decisions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_application_id` (`application_id`),
  ADD KEY `idx_reviewer_id` (`reviewer_id`);

--
-- Chỉ mục cho bảng `reviewer_recommendations`
--
ALTER TABLE `reviewer_recommendations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `reviewer_id` (`reviewer_id`);

--
-- Chỉ mục cho bảng `reviewer_verifications`
--
ALTER TABLE `reviewer_verifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `eligibility_id` (`eligibility_id`),
  ADD KEY `reviewer_id` (`reviewer_id`);

--
-- Chỉ mục cho bảng `scholarship_programs`
--
ALTER TABLE `scholarship_programs`
  ADD PRIMARY KEY (`id`);

--
-- Chỉ mục cho bảng `scoring_criteria`
--
ALTER TABLE `scoring_criteria`
  ADD PRIMARY KEY (`id`),
  ADD KEY `program_id` (`program_id`);

--
-- Chỉ mục cho bảng `scoring_criteria_requests`
--
ALTER TABLE `scoring_criteria_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `reviewer_id` (`reviewer_id`),
  ADD KEY `criterion_id` (`criterion_id`),
  ADD KEY `program_id` (`program_id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Chỉ mục cho bảng `student_profiles`
--
ALTER TABLE `student_profiles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_id` (`student_id`);

--
-- Chỉ mục cho bảng `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `student_code` (`student_code`);

--
-- AUTO_INCREMENT cho các bảng đã đổ
--

--
-- AUTO_INCREMENT cho bảng `applications`
--
ALTER TABLE `applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT cho bảng `application_evidence`
--
ALTER TABLE `application_evidence`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT cho bảng `award_certificates`
--
ALTER TABLE `award_certificates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT cho bảng `comm_templates`
--
ALTER TABLE `comm_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT cho bảng `disbursements`
--
ALTER TABLE `disbursements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT cho bảng `eligibility_results`
--
ALTER TABLE `eligibility_results`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT cho bảng `eligibility_rules`
--
ALTER TABLE `eligibility_rules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT cho bảng `eligibility_rule_requests`
--
ALTER TABLE `eligibility_rule_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `engine_run_history`
--
ALTER TABLE `engine_run_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `evaluation_scores`
--
ALTER TABLE `evaluation_scores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT cho bảng `evaluation_score_history`
--
ALTER TABLE `evaluation_score_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT cho bảng `message_recipients`
--
ALTER TABLE `message_recipients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=64;

--
-- AUTO_INCREMENT cho bảng `program_requests`
--
ALTER TABLE `program_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `ranking_results`
--
ALTER TABLE `ranking_results`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT cho bảng `ranking_run_history`
--
ALTER TABLE `ranking_run_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `reports`
--
ALTER TABLE `reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT cho bảng `reviewer_decisions`
--
ALTER TABLE `reviewer_decisions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `reviewer_recommendations`
--
ALTER TABLE `reviewer_recommendations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT cho bảng `reviewer_verifications`
--
ALTER TABLE `reviewer_verifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `scholarship_programs`
--
ALTER TABLE `scholarship_programs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT cho bảng `scoring_criteria`
--
ALTER TABLE `scoring_criteria`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT cho bảng `scoring_criteria_requests`
--
ALTER TABLE `scoring_criteria_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `student_profiles`
--
ALTER TABLE `student_profiles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT cho bảng `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;

--
-- Các ràng buộc cho các bảng đã đổ
--

--
-- Các ràng buộc cho bảng `applications`
--
ALTER TABLE `applications`
  ADD CONSTRAINT `applications_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `applications_ibfk_2` FOREIGN KEY (`program_id`) REFERENCES `scholarship_programs` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `application_evidence`
--
ALTER TABLE `application_evidence`
  ADD CONSTRAINT `application_evidence_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `application_evidence_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `award_certificates`
--
ALTER TABLE `award_certificates`
  ADD CONSTRAINT `award_certificates_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `award_certificates_ibfk_2` FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Các ràng buộc cho bảng `comm_templates`
--
ALTER TABLE `comm_templates`
  ADD CONSTRAINT `comm_templates_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Các ràng buộc cho bảng `disbursements`
--
ALTER TABLE `disbursements`
  ADD CONSTRAINT `disbursements_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `eligibility_results`
--
ALTER TABLE `eligibility_results`
  ADD CONSTRAINT `fk_eligibility_results_applications` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `eligibility_rules`
--
ALTER TABLE `eligibility_rules`
  ADD CONSTRAINT `eligibility_rules_ibfk_1` FOREIGN KEY (`program_id`) REFERENCES `scholarship_programs` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `eligibility_rule_requests`
--
ALTER TABLE `eligibility_rule_requests`
  ADD CONSTRAINT `eligibility_rule_requests_ibfk_1` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `eligibility_rule_requests_ibfk_2` FOREIGN KEY (`rule_id`) REFERENCES `eligibility_rules` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `eligibility_rule_requests_ibfk_3` FOREIGN KEY (`program_id`) REFERENCES `scholarship_programs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `eligibility_rule_requests_ibfk_4` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Các ràng buộc cho bảng `engine_run_history`
--
ALTER TABLE `engine_run_history`
  ADD CONSTRAINT `engine_run_history_ibfk_1` FOREIGN KEY (`program_id`) REFERENCES `scholarship_programs` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `engine_run_history_ibfk_2` FOREIGN KEY (`executed_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `evaluation_scores`
--
ALTER TABLE `evaluation_scores`
  ADD CONSTRAINT `evaluation_scores_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `evaluation_scores_ibfk_2` FOREIGN KEY (`criteria_id`) REFERENCES `scoring_criteria` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `evaluation_scores_ibfk_3` FOREIGN KEY (`council_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `evaluation_score_history`
--
ALTER TABLE `evaluation_score_history`
  ADD CONSTRAINT `evaluation_score_history_ibfk_1` FOREIGN KEY (`score_id`) REFERENCES `evaluation_scores` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `evaluation_score_history_ibfk_2` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `evaluation_score_history_ibfk_3` FOREIGN KEY (`criteria_id`) REFERENCES `scoring_criteria` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `evaluation_score_history_ibfk_4` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`recipient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `message_recipients`
--
ALTER TABLE `message_recipients`
  ADD CONSTRAINT `message_recipients_ibfk_1` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `message_recipients_ibfk_2` FOREIGN KEY (`recipient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `program_requests`
--
ALTER TABLE `program_requests`
  ADD CONSTRAINT `program_requests_ibfk_1` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `program_requests_ibfk_2` FOREIGN KEY (`program_id`) REFERENCES `scholarship_programs` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `program_requests_ibfk_3` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Các ràng buộc cho bảng `ranking_results`
--
ALTER TABLE `ranking_results`
  ADD CONSTRAINT `ranking_results_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `ranking_run_history`
--
ALTER TABLE `ranking_run_history`
  ADD CONSTRAINT `ranking_run_history_ibfk_1` FOREIGN KEY (`program_id`) REFERENCES `scholarship_programs` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `ranking_run_history_ibfk_2` FOREIGN KEY (`generated_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `reports`
--
ALTER TABLE `reports`
  ADD CONSTRAINT `reports_ibfk_1` FOREIGN KEY (`generated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `reports_ibfk_2` FOREIGN KEY (`program_id`) REFERENCES `scholarship_programs` (`id`) ON DELETE SET NULL;

--
-- Các ràng buộc cho bảng `reviewer_decisions`
--
ALTER TABLE `reviewer_decisions`
  ADD CONSTRAINT `fk_reviewer_decisions_application` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_reviewer_decisions_reviewer` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `reviewer_recommendations`
--
ALTER TABLE `reviewer_recommendations`
  ADD CONSTRAINT `reviewer_recommendations_ibfk_1` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `reviewer_verifications`
--
ALTER TABLE `reviewer_verifications`
  ADD CONSTRAINT `reviewer_verifications_ibfk_1` FOREIGN KEY (`eligibility_id`) REFERENCES `eligibility_results` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reviewer_verifications_ibfk_2` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `scoring_criteria`
--
ALTER TABLE `scoring_criteria`
  ADD CONSTRAINT `scoring_criteria_ibfk_1` FOREIGN KEY (`program_id`) REFERENCES `scholarship_programs` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `scoring_criteria_requests`
--
ALTER TABLE `scoring_criteria_requests`
  ADD CONSTRAINT `scoring_criteria_requests_ibfk_1` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `scoring_criteria_requests_ibfk_2` FOREIGN KEY (`criterion_id`) REFERENCES `scoring_criteria` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `scoring_criteria_requests_ibfk_3` FOREIGN KEY (`program_id`) REFERENCES `scholarship_programs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `scoring_criteria_requests_ibfk_4` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Các ràng buộc cho bảng `student_profiles`
--
ALTER TABLE `student_profiles`
  ADD CONSTRAINT `student_profiles_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/* thêm cho evidence_verification line 43*/

ALTER TABLE `application_evidence`
ADD COLUMN `reviewed_by` int(11) DEFAULT NULL COMMENT 'ID của Reviewer đã duyệt minh chứng',
ADD COLUMN `reviewed_at` datetime DEFAULT NULL COMMENT 'Thời gian duyệt minh chứng',
ADD CONSTRAINT `fk_evidence_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL;

/*sửa lỗi status scholarship_programs*/
ALTER TABLE `scholarship_programs` 
MODIFY COLUMN `status` ENUM('draft', 'open', 'suspended', 'closed') DEFAULT 'draft';