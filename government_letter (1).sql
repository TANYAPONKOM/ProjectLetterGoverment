-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 02, 2025 at 12:33 PM
-- Server version: 10.4.27-MariaDB
-- PHP Version: 8.0.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `government_letter`
--

-- --------------------------------------------------------

--
-- Table structure for table `approvals`
--

CREATE TABLE `approvals` (
  `approval_id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  `step_no` int(11) NOT NULL,
  `approver_id` int(11) NOT NULL,
  `action` enum('pending','approved','rejected') DEFAULT 'pending',
  `action_at` timestamp NULL DEFAULT NULL,
  `comment` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_thai_520_w2;

--
-- Dumping data for table `approvals`
--

INSERT INTO `approvals` (`approval_id`, `document_id`, `step_no`, `approver_id`, `action`, `action_at`, `comment`) VALUES
(1, 1, 1, 2, 'pending', NULL, NULL),
(2, 1, 2, 1, 'pending', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `log_id` bigint(20) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `document_id` int(11) DEFAULT NULL,
  `action` varchar(120) NOT NULL,
  `detail` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_thai_520_w2;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`log_id`, `user_id`, `document_id`, `action`, `detail`, `created_at`) VALUES
(1, 3, 1, 'SUBMITTED', 'ผู้ใช้ส่งคำขอฝึกอบรม TRN_REQ_2566', '2025-08-11 07:35:09');

-- --------------------------------------------------------

--
-- Table structure for table `budget_items`
--

CREATE TABLE `budget_items` (
  `item_id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  `item_type` enum('registration','transport','accommodation','per_diem','other') NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_thai_520_w2;

--
-- Dumping data for table `budget_items`
--

INSERT INTO `budget_items` (`item_id`, `document_id`, `item_type`, `description`, `amount`) VALUES
(1, 1, 'registration', 'ค่าลงทะเบียน', '8200.00'),
(2, 1, 'transport', 'ค่ายานพาหนะ', '1200.00'),
(3, 1, 'accommodation', 'ค่าที่พัก', '1124.00');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `category_id` int(11) NOT NULL,
  `main_category` varchar(100) NOT NULL,
  `sub_category` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_thai_520_w2;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`category_id`, `main_category`, `sub_category`) VALUES
(1, 'กิจกรรม', 'ฝึกอบรม'),
(2, 'กิจกรรม', 'ประชุมวิชาการ');

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `department_id` int(11) NOT NULL,
  `faculty_id` int(11) NOT NULL,
  `department_name` varchar(150) NOT NULL,
  `phone` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_thai_520_w2;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`department_id`, `faculty_id`, `department_name`, `phone`) VALUES
(1, 1, 'เทคโนโลยีสารสนเทศ', '7064');

-- --------------------------------------------------------

--
-- Table structure for table `documents`
--

CREATE TABLE `documents` (
  `document_id` int(11) NOT NULL,
  `template_id` int(11) NOT NULL,
  `owner_id` int(11) NOT NULL,
  `department_id` int(11) NOT NULL,
  `doc_no` varchar(60) DEFAULT NULL,
  `doc_date` date NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `status` enum('draft','submitted','reviewing','approved','rejected','closed') DEFAULT 'submitted',
  `remark` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_thai_520_w2;

--
-- Dumping data for table `documents`
--

INSERT INTO `documents` (`document_id`, `template_id`, `owner_id`, `department_id`, `doc_no`, `doc_date`, `subject`, `status`, `remark`, `created_at`, `updated_at`) VALUES
(1, 1, 3, 1, NULL, '2025-07-06', NULL, 'submitted', NULL, '2025-08-11 07:35:09', NULL),
(4, 1, 1, 1, NULL, '2025-09-01', NULL, 'submitted', NULL, '2025-09-01 16:16:43', NULL),
(5, 1, 1, 1, NULL, '2025-09-01', NULL, 'submitted', NULL, '2025-09-02 08:15:34', NULL),
(6, 1, 1, 1, NULL, '2025-09-02', NULL, 'submitted', NULL, '2025-09-02 14:11:08', NULL),
(7, 1, 1, 1, NULL, '2025-09-04', NULL, 'submitted', NULL, '2025-09-02 14:15:38', NULL),
(8, 1, 1, 1, NULL, '2025-09-04', NULL, 'submitted', NULL, '2025-09-02 14:16:34', NULL),
(9, 1, 1, 1, NULL, '2025-09-10', NULL, 'submitted', NULL, '2025-09-02 14:18:55', NULL),
(10, 1, 1, 1, NULL, '2025-09-03', NULL, 'submitted', NULL, '2025-09-02 15:02:50', NULL),
(11, 1, 1, 1, NULL, '2025-09-07', NULL, 'submitted', NULL, '2025-09-07 12:45:34', NULL),
(12, 1, 1, 1, NULL, '2025-09-21', NULL, 'submitted', NULL, '2025-09-21 15:31:15', NULL),
(13, 1, 1, 1, NULL, '2025-09-20', NULL, 'submitted', NULL, '2025-09-21 16:46:23', NULL),
(14, 1, 1, 1, NULL, '2025-09-19', NULL, 'submitted', NULL, '2025-09-22 03:02:48', NULL),
(15, 1, 1, 1, NULL, '2025-09-22', NULL, 'submitted', NULL, '2025-09-22 04:19:44', NULL),
(16, 1, 1, 1, NULL, '2025-09-27', NULL, 'submitted', NULL, '2025-09-27 08:48:49', NULL),
(17, 1, 1, 1, NULL, '2025-09-27', NULL, 'submitted', NULL, '2025-09-27 10:42:31', NULL),
(18, 1, 1, 1, NULL, '2025-09-27', NULL, 'submitted', NULL, '2025-09-27 12:11:50', NULL),
(19, 1, 1, 1, NULL, '2025-10-10', NULL, 'submitted', NULL, '2025-09-27 13:50:31', NULL),
(20, 1, 1, 1, NULL, '2025-09-28', NULL, 'submitted', NULL, '2025-09-27 23:17:23', NULL),
(21, 1, 1, 1, NULL, '2025-09-28', NULL, 'submitted', NULL, '2025-09-28 14:53:12', NULL),
(22, 1, 1, 1, NULL, '2025-09-29', 'เข้ารับการฝึกอบรมหลักสูตร อบรมทัศนคติในการขับเคลื่อนอนาคตของโลก', 'submitted', NULL, '2025-09-28 18:19:53', NULL),
(23, 1, 1, 1, NULL, '2025-09-29', 'เข้ารับการฝึกอบรมหลักสูตรอบรมทัศนคติในการขับเคลื่อนอนาคตของโลก', 'submitted', NULL, '2025-09-28 18:28:04', NULL),
(24, 1, 1, 1, NULL, '2025-09-29', 'เข้ารับการฝึกอบรมหลักสูตรอบรมทัศนคติในการขับเคลื่อนอนาคต', 'submitted', NULL, '2025-09-28 18:28:30', NULL),
(25, 1, 1, 1, NULL, '2025-10-01', 'เข้ารับการฝึกอบรมหลักสูตรอบรมทัศนคติในการขับเคลื่อนอนาคตของโลก', 'submitted', NULL, '2025-10-01 03:34:55', NULL),
(26, 1, 1, 1, NULL, '2025-10-01', 'เข้ารับการฝึกอบรมหลักสูตรเพื่อการศึกษาอบรมอนาคต', 'submitted', NULL, '2025-10-01 04:19:23', '2025-10-01 09:35:08'),
(27, 1, 1, 1, NULL, '2025-10-01', 'นำเสนอผลงานทางวิชาการDFndgn', 'submitted', NULL, '2025-10-01 04:34:48', NULL),
(28, 1, 1, 1, NULL, '2025-10-01', 'เข้าร่วมประชุมวิชาการในงานการศึกษาเพื่อพัฒนากระบวนการ', 'submitted', NULL, '2025-10-01 16:01:29', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `document_files`
--

CREATE TABLE `document_files` (
  `file_id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  `file_type` enum('word','pdf','attachment') NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_thai_520_w2;

--
-- Dumping data for table `document_files`
--

INSERT INTO `document_files` (`file_id`, `document_id`, `file_type`, `file_path`, `created_at`) VALUES
(1, 1, 'word', '/outputs/doc1.docx', '2025-08-11 07:35:09'),
(2, 1, 'pdf', '/outputs/doc1.pdf', '2025-08-11 07:35:09');

-- --------------------------------------------------------

--
-- Table structure for table `document_values`
--

CREATE TABLE `document_values` (
  `value_id` bigint(20) NOT NULL,
  `document_id` int(11) NOT NULL,
  `field_id` int(11) NOT NULL,
  `value_text` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_thai_520_w2;

--
-- Dumping data for table `document_values`
--

INSERT INTO `document_values` (`value_id`, `document_id`, `field_id`, `value_text`) VALUES
(1, 1, 1, '2025-07-06'),
(2, 1, 2, 'อาจารย์ ดร.พิทย์พิน สุรอด'),
(3, 1, 3, 'อาจารย์ประจำภาควิชาเทคโนโลยีสารสนเทศ'),
(4, 1, 4, 'เข้ารับการฝึกอบรมหลักสูตร'),
(5, 1, 5, '“คณาจารย์นิเทศ CWIE สำหรับผู้ที่ ไม่เคยอบรม (ปรับปรุง พ.ศ. 2566)” รุ่นที่ 1'),
(6, 1, 6, '10 - 11 กรกฎาคม 2568'),
(7, 1, 7, 'โรงแรม Best Western PLUS ถนนแจ้งวัฒนะ จังหวัดนนทบุรี'),
(8, 1, 8, '10524.00'),
(9, 1, 9, 'กร 1906 พัทลุง'),
(20, 4, 1, '2025-09-01'),
(21, 4, 2, 'อาจารย์ ดร.พิทย์พิมล ชูรัตน์'),
(22, 4, 3, 'อาจารย์ประจำภาควิชาเทคโนโลยีสารสนเทศ'),
(23, 4, 4, 'เข้ารับการฝึกอบรมหลักสูตร'),
(24, 4, 5, 'การพัฒนาเด็กเล็ก'),
(25, 4, 6, '10 - 11 กรกฎาคม 2568'),
(26, 4, 7, 'โรงแรม Best Western PLUS ถนนแจ้งวัฒนะ จังหวัดนนทบุรี'),
(27, 4, 8, '10524.00'),
(28, 4, 9, 'กร 1906 พัทลุง'),
(29, 4, 10, 'คณะเทคโนโลยีและการจัดการอุตสาหกรรม'),
(30, 4, 11, 'เทคโนโลยีสารสนเทศ'),
(31, 5, 1, '2025-09-01'),
(32, 5, 2, 'อาจารย์ ดร.พิทย์พิมล ชูรัตน์'),
(33, 5, 3, 'อาจารย์ประจำภาควิชาเทคโนโลยีสารสนเทศ'),
(34, 5, 4, 'เข้าร่วมประชุมวิชาการในงาน'),
(35, 5, 5, 'ประชุมการสมนาระหว่างประเทศ'),
(36, 5, 6, '20 กันยายน 2568'),
(37, 5, 7, 'โรงแรม Best Western PLUS ถนนแจ้งวัฒนะ จังหวัดนนทบุรี'),
(38, 5, 8, '0.00'),
(39, 5, 9, ''),
(40, 5, 10, 'คณะเทคโนโลยีและการจัดการอุตสาหกรรม'),
(41, 5, 11, 'เทคโนโลยีสารสนเทศ'),
(42, 6, 1, '2025-09-02'),
(43, 6, 2, 'อาจารย์ ดร.พิทย์พิมล ชูรัตน์'),
(44, 6, 3, 'อาจารย์ประจำภาควิชาเทคโนโลยีสารสนเทศ'),
(45, 6, 4, 'เข้ารับการฝึกอบรมหลักสูตร'),
(46, 6, 5, 'อบรมระบบเครือข่ายเพื่อการศึกษา'),
(47, 6, 6, '10 - 11 กรกฎาคม 2568'),
(48, 6, 7, 'โรงแรม Best Western PLUS ถนนแจ้งวัฒนะ จังหวัดนนทบุรี'),
(49, 6, 8, '10524.00'),
(50, 6, 9, 'กร 1906 พัทลุง'),
(51, 6, 10, 'คณะเทคโนโลยีและการจัดการอุตสาหกรรม'),
(52, 6, 11, 'เทคโนโลยีสารสนเทศ'),
(53, 7, 1, '2025-09-04'),
(54, 7, 2, 'อาจารย์ ดร.พิทย์พิมล ชูรัตน์'),
(55, 7, 3, 'อาจารย์ประจำภาควิชาเทคโนโลยีสารสนเทศ'),
(56, 7, 4, 'นำเสนอผลงานทางวิชาการ'),
(57, 7, 5, 'ผลงานการเข้าร่วมทุนการศึกษา'),
(58, 7, 6, '10 - 11 กรกฎาคม 2568'),
(59, 7, 7, 'โรงแรม Best Western PLUS ถนนแจ้งวัฒนะ จังหวัดนนทบุรี'),
(60, 7, 8, '10524.00'),
(61, 7, 9, 'กร 1021 สระบุรี'),
(62, 7, 10, 'คณะเทคโนโลยีและการจัดการอุตสาหกรรม'),
(63, 7, 11, 'เทคโนโลยีสารสนเทศ'),
(64, 8, 1, '2025-09-04'),
(65, 8, 2, 'อาจารย์ ดร.พิทย์พิมล ชูรัตน์'),
(66, 8, 3, 'อาจารย์ประจำภาควิชาเทคโนโลยีสารสนเทศ'),
(67, 8, 4, 'นำเสนอผลงานทางวิชาการ'),
(68, 8, 5, 'ผลงานการเข้าร่วมทุนการศึกษา'),
(69, 8, 6, '10 - 11 กรกฎาคม 2568'),
(70, 8, 7, 'โรงแรม Best Western PLUS ถนนแจ้งวัฒนะ จังหวัดนนทบุรี'),
(71, 8, 8, '10524.00'),
(72, 8, 9, 'กร 1021 สระบุรี'),
(73, 8, 10, 'คณะเทคโนโลยีและการจัดการอุตสาหกรรม'),
(74, 8, 11, 'เทคโนโลยีสารสนเทศ'),
(75, 9, 1, '2025-09-10'),
(76, 9, 2, 'อาจารย์ ดร.พิทย์พิมล ชูรัตน์'),
(77, 9, 3, 'อาจารย์ประจำภาควิชาเทคโนโลยีสารสนเทศ'),
(78, 9, 4, 'เข้ารับการฝึกอบรมหลักสูตร'),
(79, 9, 5, 'การดยาเหดนบหกดหด'),
(80, 9, 6, '10 - 11 กรกฎาคม 2568'),
(81, 9, 7, 'โรงแรม Best Western PLUS ถนนแจ้งวัฒนะ จังหวัดนนทบุรี'),
(82, 9, 8, '10524.00'),
(83, 9, 9, 'กร 1021 สระบุรี'),
(84, 9, 10, 'คณะเทคโนโลยีและการจัดการอุตสาหกรรม'),
(85, 9, 11, 'เทคโนโลยีสารสนเทศ'),
(86, 10, 1, '2025-09-03'),
(87, 10, 2, 'อาจารย์ ดร.พิทย์พิมล ชูรัตน์'),
(88, 10, 3, 'อาจารย์ประจำภาควิชาเทคโนโลยีสารสนเทศ'),
(89, 10, 4, 'เข้ารับการฝึกอบรมหลักสูตร'),
(90, 10, 5, 'เพื่อเข้ารับการอบรมพัฒนาสมองส่วนหน้าและข้างซ้าย'),
(91, 10, 6, '10 - 11 กรกฎาคม 2568'),
(92, 10, 7, 'โรงแรม Best Western PLUS ถนนแจ้งวัฒนะ จังหวัดนนทบุรี'),
(93, 10, 8, '10524.00'),
(94, 10, 9, 'กร 1021 สระบุรี'),
(95, 10, 10, 'คณะเทคโนโลยีและการจัดการอุตสาหกรรม'),
(96, 10, 11, 'เทคโนโลยีสารสนเทศ'),
(97, 11, 1, '2025-09-07'),
(98, 11, 2, 'อาจารย์ ดร.พิทย์พิมล ชูรัตน์'),
(99, 11, 3, 'อาจารย์ประจำภาควิชาเทคโนโลยีสารสนเทศ'),
(100, 11, 4, 'เข้ารับการฝึกอบรมหลักสูตร'),
(101, 11, 5, 'หกเะนรว้ดเกหดอด'),
(102, 11, 6, '10 - 11 กรกฎาคม 2568'),
(103, 11, 7, 'โรงแรม Best Western PLUS ถนนแจ้งวัฒนะ จังหวัดนนทบุรี'),
(104, 11, 8, '10524.00'),
(105, 11, 9, ''),
(106, 11, 10, 'คณะเทคโนโลยีและการจัดการอุตสาหกรรม'),
(107, 11, 11, 'เทคโนโลยีสารสนเทศ'),
(108, 12, 1, '2025-09-21'),
(109, 12, 2, 'อาจารย์ ดร.พิทย์พิมล ชูรอด'),
(110, 12, 3, 'อาจารย์ประจำภาควิชาเทคโนโลยีสารสนเทศ'),
(111, 12, 4, 'นำเสนอผลงานทางวิชาการ'),
(112, 12, 5, 'เพื่อการอบรมเพิ่มเติมในส่วนการบริหารจัดการเวลา'),
(113, 12, 6, '10 - 11 กรกฎาคม 2568'),
(114, 12, 7, 'เข้าร่วมรูปแบบออนไลน์'),
(115, 12, 8, '1000.00'),
(116, 12, 9, 'กร 1021 สระบุรี'),
(117, 12, 10, 'คณะเทคโนโลยีและการจัดการอุตสาหกรรม'),
(118, 12, 11, 'เทคโนโลยีสารสนเทศ'),
(119, 13, 1, '2025-09-20'),
(120, 13, 2, 'อาจารย์ ดร.พิทย์พิมล ชูรอด'),
(121, 13, 3, 'อาจารย์ประจำภาควิชาเทคโนโลยีสารสนเทศ'),
(122, 13, 4, 'เข้ารับการฝึกอบรมหลักสูตร'),
(123, 13, 5, 'อบรมทัศนคติในการขับเคลื่อนอนาคตของโลก'),
(124, 13, 6, '10 - 11 กรกฎาคม 2568'),
(125, 13, 7, 'เข้าร่วมรูปแบบออนไลน์'),
(126, 13, 8, '1200.00'),
(127, 13, 9, 'กร 1906 พัทลุง'),
(128, 13, 10, 'คณะเทคโนโลยีและการจัดการอุตสาหกรรม'),
(129, 13, 11, 'เทคโนโลยีสารสนเทศ'),
(130, 14, 1, '2025-09-19'),
(131, 14, 2, 'อาจารย์ ดร.พิทย์พิมล ชูรอด'),
(132, 14, 3, 'อาจารย์ประจำภาควิชาเทคโนโลยีสารสนเทศ'),
(133, 14, 4, 'เข้ารับการฝึกอบรมหลักสูตร'),
(134, 14, 5, 'การจัดการและพัฒนาเพื่ออนาคตของการศึกษา'),
(135, 14, 6, '20 - 21 กันยายน 2568'),
(136, 14, 7, 'โรงแรม Best Western PLUS ถนนแจ้งวัฒนะ จังหวัดนนทบุรี'),
(137, 14, 8, '2050.00'),
(138, 14, 9, 'กร 1906 พัทลุง'),
(139, 14, 10, 'คณะเทคโนโลยีและการจัดการอุตสาหกรรม'),
(140, 14, 11, 'เทคโนโลยีสารสนเทศ'),
(141, 15, 1, '2025-09-22'),
(142, 15, 2, 'อาจารย์ ดร.พิทย์พิมล ชูรอด'),
(143, 15, 3, 'อาจารย์ประจำภาควิชาเทคโนโลยีสารสนเทศ'),
(144, 15, 4, 'เข้ารับการฝึกอบรมหลักสูตร'),
(145, 15, 5, 'เพื่อจัดการอบรมการพัฒนาเทคโนโลยี'),
(146, 15, 6, '20 - 21 กันยายน 2568'),
(147, 15, 7, 'โรงแรม Best Western PLUS ถนนแจ้งวัฒนะ จังหวัดนนทบุรี'),
(148, 15, 8, '10524.00'),
(149, 15, 9, 'กร 1906 พัทลุง'),
(150, 15, 10, 'คณะเทคโนโลยีและการจัดการอุตสาหกรรม'),
(151, 15, 11, 'เทคโนโลยีสารสนเทศ'),
(152, 16, 1, '2025-09-27'),
(153, 16, 2, 'อาจารย์ ดร.พิทย์พิมล ชูรอด'),
(154, 16, 3, 'อาจารย์ประจำภาควิชาเทคโนโลยีสารสนเทศ'),
(155, 16, 4, 'เข้ารับการฝึกอบรมหลักสูตร'),
(156, 16, 5, 'จริยธรรมการปฏิบัติทางโลก'),
(157, 16, 6, '10 - 11 กรกฎาคม 2568'),
(158, 16, 7, 'โรงแรม Best Western PLUS ถนนแจ้งวัฒนะ จังหวัดนนทบุรี'),
(159, 16, 8, '10524.00'),
(160, 16, 9, 'กร 1906 พัทลุง'),
(161, 16, 10, 'คณะเทคโนโลยีและการจัดการอุตสาหกรรม'),
(162, 16, 11, 'เทคโนโลยีสารสนเทศ'),
(163, 17, 1, '2025-09-27'),
(164, 17, 2, 'อาจารย์ ดร.พิทย์พิมล ชูรอด'),
(165, 17, 3, 'อาจารย์ประจำภาควิชาเทคโนโลยีสารสนเทศ'),
(166, 17, 4, 'เข้าร่วมประชุมวิชาการในงาน'),
(167, 17, 5, 'ในการจัดการเรื่องการบริหารเรื่องส่วนบุคคลการจัดการความเครียดสะสม'),
(168, 17, 6, '10 - 11 กรกฎาคม 2568'),
(169, 17, 7, 'โรงแรม Best Western PLUS ถนนแจ้งวัฒนะ จังหวัดนนทบุรี'),
(170, 17, 8, '10524.00'),
(171, 17, 9, 'กร 1021 สระบุรี'),
(172, 17, 10, 'คณะเทคโนโลยีและการจัดการอุตสาหกรรม'),
(173, 17, 11, 'เทคโนโลยีสารสนเทศ'),
(174, 18, 1, '2025-09-27'),
(175, 18, 2, 'อาจารย์ ดร.พิทย์พิมล ชูรอด'),
(176, 18, 3, 'อาจารย์ประจำภาควิชาเทคโนโลยีสารสนเทศ'),
(177, 18, 4, 'เข้าร่วมประชุมวิชาการในงาน'),
(178, 18, 5, 'พัฒนาวิวัตถนาการกกดแหกอ'),
(179, 18, 6, '10 - 11 กรกฎาคม 2568'),
(180, 18, 7, 'โรงแรม Best Western PLUS ถนนแจ้งวัฒนะ จังหวัดนนทบุรี'),
(181, 18, 8, '10524.00'),
(182, 18, 9, ''),
(183, 18, 10, 'คณะเทคโนโลยีและการจัดการอุตสาหกรรม'),
(184, 18, 11, 'เทคโนโลยีสารสนเทศ'),
(185, 19, 1, '2025-10-10'),
(186, 19, 2, 'อาจารย์ ดร.พิทย์พิมล ชูรอด'),
(187, 19, 3, 'อาจารย์ประจำภาควิชาเทคโนโลยีสารสนเทศ'),
(188, 19, 4, 'เข้าร่วมประชุมวิชาการในงาน'),
(189, 19, 5, 'ฝงงงงงงงงงงงงงงงงงงงฝงฝงฝยส'),
(190, 19, 6, '10 - 11 กรกฎาคม 2568'),
(191, 19, 7, 'โรงแรม Best Western PLUS ถนนแจ้งวัฒนะ จังหวัดนนทบุรี'),
(192, 19, 8, '10524.00'),
(193, 19, 9, ''),
(194, 19, 10, 'คณะเทคโนโลยีและการจัดการอุตสาหกรรม'),
(195, 19, 11, 'เทคโนโลยีสารสนเทศ'),
(196, 20, 1, '2025-09-28'),
(197, 20, 2, 'อาจารย์ ดร.พิทย์พิมล ชูรอด'),
(198, 20, 3, 'อาจารย์ประจำภาควิชาเทคโนโลยีสารสนเทศ'),
(199, 20, 4, 'เข้ารับการฝึกอบรมหลักสูตร'),
(200, 20, 5, 'ฟพัารัสีนสม้่ทกเ้ืหเดเเพ้'),
(201, 20, 6, '10 - 11 กรกฎาคม 2568'),
(202, 20, 7, 'โรงแรม Best Western PLUS ถนนแจ้งวัฒนะ จังหวัดนนทบุรี'),
(203, 20, 8, '10524.00'),
(204, 20, 9, ''),
(205, 20, 10, 'คณะเทคโนโลยีและการจัดการอุตสาหกรรม'),
(206, 20, 11, 'เทคโนโลยีสารสนเทศ'),
(207, 21, 1, '2025-09-28'),
(208, 21, 2, 'อาจารย์ ดร.พิทย์พิมล ชูรอด'),
(209, 21, 3, 'อาจารย์ประจำภาควิชาเทคโนโลยีสารสนเทศ'),
(210, 21, 4, 'เข้าร่วมประชุมวิชาการในงาน'),
(211, 21, 5, 'หะัาีพัรสีนวะรดาทดเื'),
(212, 21, 6, '10 - 11 กรกฎาคม 2568'),
(213, 21, 7, 'โรงแรม Best Western PLUS ถนนแจ้งวัฒนะ จังหวัดนนทบุรี'),
(214, 21, 8, '10524.00'),
(215, 21, 9, ''),
(216, 21, 10, 'คณะเทคโนโลยีและการจัดการอุตสาหกรรม'),
(217, 21, 11, 'เทคโนโลยีสารสนเทศ'),
(218, 22, 1, '2025-09-29'),
(219, 22, 2, 'อาจารย์ ดร.พิทย์พิมล ชูรอด'),
(220, 22, 3, 'อาจารย์ประจำภาควิชาเทคโนโลยีสารสนเทศ'),
(221, 22, 4, 'เข้ารับการฝึกอบรมหลักสูตร'),
(222, 22, 5, 'อบรมทัศนคติในการขับเคลื่อนอนาคตของโลก'),
(223, 22, 6, '10 - 11 กรกฎาคม 2568'),
(224, 22, 7, 'โรงแรม Best Western PLUS ถนนแจ้งวัฒนะ จังหวัดนนทบุรี'),
(225, 22, 8, '10524.00'),
(226, 22, 9, ''),
(227, 22, 10, 'คณะเทคโนโลยีและการจัดการอุตสาหกรรม'),
(228, 22, 11, 'เทคโนโลยีสารสนเทศ'),
(229, 23, 1, '2025-09-29'),
(230, 23, 2, 'อาจารย์ ดร.พิทย์พิมล ชูรอด'),
(231, 23, 3, 'อาจารย์ประจำภาควิชาเทคโนโลยีสารสนเทศ'),
(232, 23, 4, 'เข้ารับการฝึกอบรมหลักสูตร'),
(233, 23, 5, 'อบรมทัศนคติในการขับเคลื่อนอนาคตของโลก'),
(234, 23, 6, '10 - 11 กรกฎาคม 2568'),
(235, 23, 7, 'โรงแรม Best Western PLUS ถนนแจ้งวัฒนะ จังหวัดนนทบุรี'),
(236, 23, 8, '10524.00'),
(237, 23, 9, ''),
(238, 23, 10, 'คณะเทคโนโลยีและการจัดการอุตสาหกรรม'),
(239, 23, 11, 'เทคโนโลยีสารสนเทศ'),
(240, 24, 1, '2025-09-29'),
(241, 24, 2, 'อาจารย์ ดร.พิทย์พิมล ชูรอด'),
(242, 24, 3, 'อาจารย์ประจำภาควิชาเทคโนโลยีสารสนเทศ'),
(243, 24, 4, 'เข้ารับการฝึกอบรมหลักสูตร'),
(244, 24, 5, 'อบรมทัศนคติในการขับเคลื่อนอนาคต'),
(245, 24, 6, '10 - 11 กรกฎาคม 2568'),
(246, 24, 7, 'โรงแรม Best Western PLUS ถนนแจ้งวัฒนะ จังหวัดนนทบุรี'),
(247, 24, 8, '10524.00'),
(248, 24, 9, ''),
(249, 24, 10, 'คณะเทคโนโลยีและการจัดการอุตสาหกรรม'),
(250, 24, 11, 'เทคโนโลยีสารสนเทศ'),
(251, 25, 1, '2025-10-01'),
(252, 25, 2, 'อาจารย์ ดร.พิทย์พิมล ชูรอด'),
(253, 25, 3, 'อาจารย์ประจำภาควิชาเทคโนโลยีสารสนเทศ'),
(254, 25, 4, 'เข้ารับการฝึกอบรมหลักสูตร'),
(255, 25, 5, 'อบรมทัศนคติในการขับเคลื่อนอนาคตของโลก'),
(256, 25, 6, '10 - 11 กรกฎาคม 2568'),
(257, 25, 7, 'โรงแรม Best Western PLUS ถนนแจ้งวัฒนะ จังหวัดนนทบุรี'),
(258, 25, 8, '10524.00'),
(259, 25, 9, ''),
(260, 25, 10, 'คณะเทคโนโลยีและการจัดการอุตสาหกรรม'),
(261, 25, 11, 'เทคโนโลยีสารสนเทศ'),
(262, 26, 1, '2025-10-01'),
(263, 26, 2, 'อาจารย์ ดร.พิทย์พิมล ชูรอดด'),
(264, 26, 3, 'อาจารย์ประจำภาควิชาเทคโนโลยีสารสนเทศน'),
(265, 26, 4, 'เข้ารับการฝึกอบรมหลักสูตร'),
(266, 26, 5, 'เพื่อการศึกษาอบรมอนาคต'),
(267, 26, 6, '10 - 12 กรกฎาคม 2568'),
(268, 26, 7, 'โรงแรม Best Western PLUSS ถนนแจ้งวัฒนะ จังหวัดนนทบุรี'),
(269, 26, 8, '10514.00'),
(270, 26, 9, ''),
(271, 26, 10, 'คณะเทคโนโลยีและการจัดการอุตสาหกรรมน'),
(272, 26, 11, 'เทคโนโลยีสารสนเทศน'),
(273, 27, 1, '2025-10-01'),
(274, 27, 2, 'อาจารย์ ดร.พิทย์พิมล ชูรอด'),
(275, 27, 3, 'อาจารย์ประจำภาควิชาเทคโนโลยีสารสนเทศ'),
(276, 27, 4, 'นำเสนอผลงานทางวิชาการ'),
(277, 27, 5, 'DFndgn'),
(278, 27, 6, '10 - 11 กรกฎาคม 2568'),
(279, 27, 7, 'โรงแรม Best Western PLUS ถนนแจ้งวัฒนะ จังหวัดนนทบุรี'),
(280, 27, 8, '10524.00'),
(281, 27, 9, ''),
(282, 27, 10, 'คณะเทคโนโลยีและการจัดการอุตสาหกรรม'),
(283, 27, 11, 'เทคโนโลยีสารสนเทศ'),
(317, 28, 1, '2025-10-01'),
(318, 28, 2, 'อาจารย์ ดร.พิทย์พิมล ชูรอด'),
(319, 28, 3, 'อาจารย์ประจำภาควิชาเทคโนโลยีสารสนเทศ'),
(320, 28, 4, 'เข้าร่วมประชุมวิชาการในงาน'),
(321, 28, 5, 'การศึกษาเพื่อพัฒนากระบวนการ'),
(322, 28, 6, '10 - 11 กรกฎาคม 2568'),
(323, 28, 7, 'โรงแรม Best Western PLUS ถนนแจ้งวัฒนะ จังหวัดนนทบุรี'),
(324, 28, 8, '10524.00'),
(325, 28, 9, ''),
(326, 28, 10, 'คณะเทคโนโลยีและการจัดการอุตสาหกรรม'),
(327, 28, 11, 'เทคโนโลยีสารสนเทศ');

-- --------------------------------------------------------

--
-- Table structure for table `faculties`
--

CREATE TABLE `faculties` (
  `faculty_id` int(11) NOT NULL,
  `faculty_name` varchar(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_thai_520_w2;

--
-- Dumping data for table `faculties`
--

INSERT INTO `faculties` (`faculty_id`, `faculty_name`) VALUES
(1, 'คณะเทคโนโลยีและการจัดการอุตสาหกรรม');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notif_id` bigint(20) NOT NULL,
  `user_id` int(11) NOT NULL,
  `document_id` int(11) DEFAULT NULL,
  `channel` enum('inapp','email') DEFAULT 'inapp',
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_thai_520_w2;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notif_id`, `user_id`, `document_id`, `channel`, `title`, `message`, `is_read`, `created_at`) VALUES
(1, 2, 1, 'inapp', 'มีเอกสารรอพิจารณา', 'เอกสาร #1 รออนุมัติ ขั้นตอนที่ 1', 0, '2025-08-11 07:35:09');

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `perm_id` int(11) NOT NULL,
  `perm_code` varchar(80) NOT NULL,
  `perm_name` varchar(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_thai_520_w2;

--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`perm_id`, `perm_code`, `perm_name`) VALUES
(1, 'template.manage', 'จัดการเทมเพลตเอกสาร'),
(2, 'user.manage', 'จัดการผู้ใช้และสิทธิ์'),
(3, 'document.review', 'ตรวจ/อนุมัติเอกสาร'),
(4, 'document.create', 'สร้างและแก้ไขเอกสารของตน'),
(5, 'document.export', 'ส่งออก Word/PDF');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `role_id` int(11) NOT NULL,
  `role_code` varchar(30) NOT NULL,
  `role_name` varchar(80) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_thai_520_w2;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`role_id`, `role_code`, `role_name`) VALUES
(1, 'Admin', 'ผู้ดูแลระบบ'),
(2, 'Officer', 'เจ้าหน้าที่'),
(3, 'User', 'ผู้ใช้งาน');

-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `role_id` int(11) NOT NULL,
  `perm_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_thai_520_w2;

--
-- Dumping data for table `role_permissions`
--

INSERT INTO `role_permissions` (`role_id`, `perm_id`) VALUES
(1, 1),
(1, 2),
(1, 3),
(1, 4),
(1, 5),
(2, 3),
(2, 5),
(3, 4),
(3, 5);

-- --------------------------------------------------------

--
-- Table structure for table `templates`
--

CREATE TABLE `templates` (
  `template_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `template_code` varchar(60) NOT NULL,
  `template_name` varchar(200) NOT NULL,
  `word_path` varchar(255) DEFAULT NULL,
  `pdf_path` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_thai_520_w2;

--
-- Dumping data for table `templates`
--

INSERT INTO `templates` (`template_id`, `category_id`, `template_code`, `template_name`, `word_path`, `pdf_path`, `is_active`, `created_by`, `created_at`) VALUES
(1, 1, 'TRN_REQ_2566', 'ขออนุมัติไปเข้ารับการฝึกอบรมหลักสูตร', '/templates/training.docx', NULL, 1, 1, '2025-08-11 07:35:09');

-- --------------------------------------------------------

--
-- Table structure for table `template_fields`
--

CREATE TABLE `template_fields` (
  `field_id` int(11) NOT NULL,
  `template_id` int(11) NOT NULL,
  `field_key` varchar(80) NOT NULL,
  `field_label` varchar(150) NOT NULL,
  `field_type` enum('text','textarea','date','number','select','checkbox') NOT NULL,
  `is_required` tinyint(1) DEFAULT 0,
  `sort_order` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_thai_520_w2;

--
-- Dumping data for table `template_fields`
--

INSERT INTO `template_fields` (`field_id`, `template_id`, `field_key`, `field_label`, `field_type`, `is_required`, `sort_order`) VALUES
(1, 1, 'doc_date', 'วัน เดือน ปี', 'date', 1, 10),
(2, 1, 'owner_name', 'ชื่อ - นามสกุล', 'select', 1, 20),
(3, 1, 'position', 'ตำแหน่ง', 'text', 1, 30),
(4, 1, 'join_type', 'ขออนุมัติไปเข้าร่วม', 'select', 1, 40),
(5, 1, 'course_name', 'ชื่อหลักสูตร/ชื่อประชุม', 'text', 1, 50),
(6, 1, 'join_date_range', 'ในระหว่างวันที่', 'text', 1, 60),
(7, 1, 'location', 'สถานที่จัด', 'text', 1, 70),
(8, 1, 'total_cost', 'รวมงบประมาณค่าใช้จ่าย', 'number', 0, 80),
(9, 1, 'vehicle', 'รถยนต์ส่วนบุคคล/ทะเบียน', 'text', 0, 90),
(10, 1, 'faculty', 'คณะ', 'text', 0, 95),
(11, 1, 'department', 'ภาควิชา', 'text', 0, 96);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `fullname` varchar(120) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `role_id` int(11) NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `position` varchar(120) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_thai_520_w2;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password`, `fullname`, `email`, `role_id`, `department_id`, `position`, `is_active`, `created_at`) VALUES
(1, 'admin', '123', 'สมชาย แอดมิน', 'admin@example.com', 1, 1, 'ผู้ดูแลระบบ', 1, '2025-08-11 07:35:09'),
(2, 'officer1', '123456', 'นฤมล เจ้าหน้าที่', 'officer@example.com', 2, 1, 'เจ้าหน้าที่งานเอกสาร', 1, '2025-08-11 07:35:09'),
(3, 'teacher1', '123456', 'ดร.พิทย์พิน ชูรอด', 'teacher@example.com', 3, 1, 'อาจารย์ประจำภาควิชา', 1, '2025-08-11 07:35:09');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `approvals`
--
ALTER TABLE `approvals`
  ADD PRIMARY KEY (`approval_id`),
  ADD UNIQUE KEY `uq_doc_step` (`document_id`,`step_no`),
  ADD KEY `approver_id` (`approver_id`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_log_doc` (`document_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `budget_items`
--
ALTER TABLE `budget_items`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `idx_budget_doc` (`document_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`category_id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`department_id`),
  ADD KEY `faculty_id` (`faculty_id`);

--
-- Indexes for table `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`document_id`),
  ADD KEY `template_id` (`template_id`),
  ADD KEY `department_id` (`department_id`),
  ADD KEY `idx_docs_owner` (`owner_id`),
  ADD KEY `idx_docs_status` (`status`);

--
-- Indexes for table `document_files`
--
ALTER TABLE `document_files`
  ADD PRIMARY KEY (`file_id`),
  ADD KEY `document_id` (`document_id`);

--
-- Indexes for table `document_values`
--
ALTER TABLE `document_values`
  ADD PRIMARY KEY (`value_id`),
  ADD UNIQUE KEY `uq_doc_field` (`document_id`,`field_id`),
  ADD KEY `field_id` (`field_id`);

--
-- Indexes for table `faculties`
--
ALTER TABLE `faculties`
  ADD PRIMARY KEY (`faculty_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notif_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `document_id` (`document_id`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`perm_id`),
  ADD UNIQUE KEY `perm_code` (`perm_code`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`role_id`),
  ADD UNIQUE KEY `role_code` (`role_code`);

--
-- Indexes for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`role_id`,`perm_id`),
  ADD KEY `perm_id` (`perm_id`);

--
-- Indexes for table `templates`
--
ALTER TABLE `templates`
  ADD PRIMARY KEY (`template_id`),
  ADD UNIQUE KEY `template_code` (`template_code`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `template_fields`
--
ALTER TABLE `template_fields`
  ADD PRIMARY KEY (`field_id`),
  ADD KEY `template_id` (`template_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `role_id` (`role_id`),
  ADD KEY `department_id` (`department_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `approvals`
--
ALTER TABLE `approvals`
  MODIFY `approval_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `log_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `budget_items`
--
ALTER TABLE `budget_items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `department_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `documents`
--
ALTER TABLE `documents`
  MODIFY `document_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `document_files`
--
ALTER TABLE `document_files`
  MODIFY `file_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `document_values`
--
ALTER TABLE `document_values`
  MODIFY `value_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=328;

--
-- AUTO_INCREMENT for table `faculties`
--
ALTER TABLE `faculties`
  MODIFY `faculty_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notif_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `perm_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `role_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `templates`
--
ALTER TABLE `templates`
  MODIFY `template_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `template_fields`
--
ALTER TABLE `template_fields`
  MODIFY `field_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `approvals`
--
ALTER TABLE `approvals`
  ADD CONSTRAINT `approvals_ibfk_1` FOREIGN KEY (`document_id`) REFERENCES `documents` (`document_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `approvals_ibfk_2` FOREIGN KEY (`approver_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `audit_logs_ibfk_2` FOREIGN KEY (`document_id`) REFERENCES `documents` (`document_id`) ON DELETE SET NULL;

--
-- Constraints for table `budget_items`
--
ALTER TABLE `budget_items`
  ADD CONSTRAINT `budget_items_ibfk_1` FOREIGN KEY (`document_id`) REFERENCES `documents` (`document_id`) ON DELETE CASCADE;

--
-- Constraints for table `departments`
--
ALTER TABLE `departments`
  ADD CONSTRAINT `departments_ibfk_1` FOREIGN KEY (`faculty_id`) REFERENCES `faculties` (`faculty_id`);

--
-- Constraints for table `documents`
--
ALTER TABLE `documents`
  ADD CONSTRAINT `documents_ibfk_1` FOREIGN KEY (`template_id`) REFERENCES `templates` (`template_id`),
  ADD CONSTRAINT `documents_ibfk_2` FOREIGN KEY (`owner_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `documents_ibfk_3` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`);

--
-- Constraints for table `document_files`
--
ALTER TABLE `document_files`
  ADD CONSTRAINT `document_files_ibfk_1` FOREIGN KEY (`document_id`) REFERENCES `documents` (`document_id`) ON DELETE CASCADE;

--
-- Constraints for table `document_values`
--
ALTER TABLE `document_values`
  ADD CONSTRAINT `document_values_ibfk_1` FOREIGN KEY (`document_id`) REFERENCES `documents` (`document_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `document_values_ibfk_2` FOREIGN KEY (`field_id`) REFERENCES `template_fields` (`field_id`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`document_id`) REFERENCES `documents` (`document_id`) ON DELETE SET NULL;

--
-- Constraints for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD CONSTRAINT `role_permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`),
  ADD CONSTRAINT `role_permissions_ibfk_2` FOREIGN KEY (`perm_id`) REFERENCES `permissions` (`perm_id`);

--
-- Constraints for table `templates`
--
ALTER TABLE `templates`
  ADD CONSTRAINT `templates_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`),
  ADD CONSTRAINT `templates_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `template_fields`
--
ALTER TABLE `template_fields`
  ADD CONSTRAINT `template_fields_ibfk_1` FOREIGN KEY (`template_id`) REFERENCES `templates` (`template_id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`),
  ADD CONSTRAINT `users_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
