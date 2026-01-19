-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Host: sql105.infinityfree.com
-- Generation Time: Oct 30, 2025 at 07:35 AM
-- Server version: 11.4.7-MariaDB
-- PHP Version: 7.2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `if0_40237844_web`
--

-- --------------------------------------------------------

--
-- Table structure for table `cancelation`
--

CREATE TABLE `cancelation` (
  `diplo_id` int(11) NOT NULL,
  `diplo_assignment_date` datetime DEFAULT NULL,
  `gs_num` int(11) DEFAULT NULL,
  `gs_year` int(11) DEFAULT NULL,
  `cancelation_reason` varchar(255) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cancelation`
--

INSERT INTO `cancelation` (`diplo_id`, `diplo_assignment_date`, `gs_num`, `gs_year`, `cancelation_reason`) VALUES
(402, '2025-03-14 00:00:00', 25, 2025, '?????? ???????'),
(403, '2025-01-15 00:00:00', 26, 2025, '??? ??????????');

-- --------------------------------------------------------

--
-- Table structure for table `diplo`
--

CREATE TABLE `diplo` (
  `diplo_id` int(11) NOT NULL,
  `diplo_title` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `diplo_desc` varchar(500) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `diplo_pdf` varchar(500) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `diplo_status` enum('active','cancelled','under assignment','finished') CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `diplo_trimelis` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `diplo_student` int(11) DEFAULT NULL,
  `diplo_professor` int(11) DEFAULT NULL,
  `diplo_grade` decimal(4,2) DEFAULT NULL,
  `nimertis_link` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `diplo`
--

INSERT INTO `diplo` (`diplo_id`, `diplo_title`, `diplo_desc`, `diplo_pdf`, `diplo_status`, `diplo_trimelis`, `diplo_student`, `diplo_professor`, `diplo_grade`, `nimertis_link`) VALUES
(401, 'Analysi dedomenon pelatwn gia provlepsi polisewn me Python', 'dhjol', 'G:My Drive4? ????7? ???????WebProject_26uploadsdiplo_.pdf', 'finished', NULL, 1000001, 20001, '9.00', ''),
(402, ' Machine Learning', 'apln', 'G:My Drive4? ????7? ???????WebProject_26uploadsdiplo_1.pdf', 'finished', NULL, 1000002, 20001, '9.00', ''),
(403, ' AI', 'alpk', 'G:My Drive4? ????7? ???????WebProject_26uploadsdiplo_2.pdf', 'cancelled', NULL, 1000003, 20004, NULL, ''),
(404, ' trends', 'askslck', 'G:My Drive4? ????7? ???????WebProject_26uploadsdiplo_3.pdf', 'active', NULL, 1000008, 20002, NULL, ''),
(405, ' NLP', 'afwmsk', 'G:My Drive4? ????7? ???????WebProject_26uploadsdiplo_4.pdf', 'active', NULL, 1000007, 20004, NULL, ''),
(406, 'Domes dedomenon', 'flelpk', 'G:My Drive4? ????7? ???????WebProject_26uploadsdiplo_5.pdf', 'finished', NULL, 10000010, 20005, '10.00', ''),
(407, 'spam emails ?? Deep Learning', 'kjn', 'G:My Drive4? ????7? ???????WebProject_26uploadsdiplo_6.pdf', 'under assignment', NULL, 1000006, 20002, NULL, ''),
(408, 'churn  Random Forest', '9', 'G:My Drive4? ????7? ???????WebProject_26uploadsdiplo_7.pdf', 'cancelled', NULL, 1000016, 20002, NULL, ''),
(409, 'IoT', 'fkid', 'G:My Drive4? ????7? ???????WebProject_26uploadsdiplo_8.pdf', 'active', NULL, 1000013, 20003, NULL, ''),
(410, ' responsive web app ?? React', 'pen', 'G:My Drive4? ????7? ???????WebProject_26uploadsdiplo_9.pdf', 'finished', NULL, 1000011, 20007, '9.50', '');

-- --------------------------------------------------------

--
-- Table structure for table `diplo_date`
--

CREATE TABLE `diplo_date` (
  `diplo_id` int(11) DEFAULT NULL,
  `diplo_date` datetime DEFAULT NULL,
  `diplo_status` enum('ready','pending','cancel','under review') DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `diplo_date`
--

INSERT INTO `diplo_date` (`diplo_id`, `diplo_date`, `diplo_status`) VALUES
(402, '2025-01-14 00:00:00', 'pending'),
(402, '2025-03-14 00:00:00', 'cancel'),
(403, '2024-10-14 00:00:00', 'pending'),
(402, '2025-01-15 00:00:00', 'cancel'),
(407, '2024-11-01 00:00:00', 'pending'),
(407, '2025-09-01 00:00:00', 'ready'),
(407, '2025-10-17 00:00:00', 'under review');

-- --------------------------------------------------------

--
-- Table structure for table `draft`
--

CREATE TABLE `draft` (
  `diplo_id` int(11) DEFAULT NULL,
  `draft_diplo_pdf` varchar(255) DEFAULT NULL,
  `draft_links` varchar(255) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `grade_criteria`
--

CREATE TABLE `grade_criteria` (
  `diplo_id` int(11) DEFAULT NULL,
  `professor_user_id` int(11) DEFAULT NULL,
  `quality_goals` decimal(4,2) DEFAULT NULL,
  `time_interval` decimal(4,2) DEFAULT NULL,
  `text_quality` decimal(4,2) DEFAULT NULL,
  `Presentation` decimal(4,2) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `grade_criteria`
--

INSERT INTO `grade_criteria` (`diplo_id`, `professor_user_id`, `quality_goals`, `time_interval`, `text_quality`, `Presentation`) VALUES
(401, 20001, '9.00', '8.50', '9.50', '10.00'),
(401, 20011, '9.00', '9.50', '8.50', '9.00'),
(401, 20008, '9.50', '8.00', '9.00', '9.00'),
(402, 20001, '8.00', '8.50', '9.50', '9.00'),
(402, 20002, '9.00', '8.50', '6.50', '8.00'),
(402, 20010, '9.00', '9.50', '8.50', '9.00'),
(406, 20005, '7.00', '8.50', '9.50', '10.00'),
(406, 20008, '9.00', '8.50', '8.50', '10.00'),
(406, 20007, '9.00', '8.50', '7.50', '10.00'),
(410, 20007, '8.00', '9.50', '9.50', '10.00'),
(410, 20009, '10.00', '7.50', '8.50', '9.00'),
(410, 20001, '9.00', '8.50', '8.50', '9.00');

-- --------------------------------------------------------

--
-- Table structure for table `presentation`
--

CREATE TABLE `presentation` (
  `diplo_id` int(11) DEFAULT NULL,
  `presentation_date` date DEFAULT NULL,
  `presentation_time` time DEFAULT NULL,
  `presentation_way` enum('online','in person') DEFAULT NULL,
  `presentation_room` varchar(20) DEFAULT NULL,
  `presentation_link` varchar(255) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `presentation`
--

INSERT INTO `presentation` (`diplo_id`, `presentation_date`, `presentation_time`, `presentation_way`, `presentation_room`, `presentation_link`) VALUES
(407, '2025-10-17', '11:20:00', 'in person', '1', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `professor`
--

CREATE TABLE `professor` (
  `professor_email` varchar(255) NOT NULL,
  `professor_id` int(11) DEFAULT NULL,
  `professor_name` varchar(255) DEFAULT NULL,
  `professor_surname` varchar(255) DEFAULT NULL,
  `professor_tel` varchar(255) DEFAULT NULL,
  `professor_office` varchar(20) DEFAULT NULL,
  `professor_department` varchar(50) DEFAULT NULL,
  `professor_uni` varchar(50) DEFAULT NULL,
  `professor_user_id` int(11) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `professor`
--

INSERT INTO `professor` (`professor_email`, `professor_id`, `professor_name`, `professor_surname`, `professor_tel`, `professor_office`, `professor_department`, `professor_uni`, `professor_user_id`) VALUES
('pr2000001@upnet.gr', NULL, 'Alex', 'Ioannou', '6912345678', 'A01', 'CEID', 'UPatras', 20001),
('pr2000002@upnet.gr', NULL, 'Mike', 'Dionisiou', '6915621568', 'A02', 'CEID', 'UPatras', 20002),
('pr2000003@upnet.gr', NULL, 'Andreas', 'Dionisiou', '6915231568', 'A03', 'CEID', 'UPatras', 20003),
('pr2000004@upnet.gr', NULL, 'Andreas', 'Andreou', '6911121568', 'A04', 'CEID', 'UPatras', 20004),
('pr2000005@upnet.gr', NULL, 'Mike', 'Dimitriou', '6915627778', 'A05', 'CEID', 'UPatras', 20005),
('pr2000006@upnet.gr', NULL, 'Ioannis', 'Theodorou', '6915621118', 'A06', 'CEID', 'UPatras', 20006),
('pr2000007@upnet.gr', NULL, 'Giorgos', 'Pavlou', '6933331568', 'A07', 'CEID', 'UPatras', 20007),
('pr2000008@upnet.gr', NULL, 'Dimitris', 'Dion', '6915622222', 'A08', 'CEID', 'UPatras', 20008),
('pr2000009@upnet.gr', NULL, 'Kostas', 'Blaxou', '6900112233', 'A09', 'CEID', 'UPatras', 20009),
('pr2000010@upnet.gr', NULL, 'Saki', 'Rouvas', '6915621500', 'A10', 'CEID', 'UPatras', 20010),
('pr2000011@upnet.gr', NULL, 'Kyriaki', 'Athanasiou', '6976435941', 'B01', 'CEID', 'UPatras', 20011),
('pr2000012@upnet.gr', NULL, 'Iakovos', 'Alexiou', '6976437941', 'B02', 'CEID', 'UPatras', 20012),
('pr2000013@upnet.gr', NULL, 'Tasos', 'Juan', '6996435941', 'B03', 'CEID', 'UPatras', 20013),
('pr2000014@upnet.gr', NULL, 'Panayiota', 'Panayiotou', '6976435141', 'B04', 'CEID', 'UPatras', 20014),
('pr2000015@upnet.gr', NULL, 'Zoe', 'Pittaka', '6976431241', 'B05', 'CEID', 'UPatras', 20015),
('pr2000016@upnet.gr', NULL, 'Kostas', 'Evangelou', '6928435941', 'B06', 'CEID', 'UPatras', 20016),
('pr2000017@upnet.gr', NULL, 'Ioanna', 'Ioannou', '6976435601', 'B07', 'CEID', 'UPatras', 20017),
('pr2000018@upnet.gr', NULL, 'Koullis', 'Loutzas', '6976414741', 'B08', 'CEID', 'UPatras', 20018),
('pr2000019@upnet.gr', NULL, 'Andreas', 'Charalambous', '6976694341', 'B09', 'CEID', 'UPatras', 20019),
('pr2000020@upnet.gr', NULL, 'Katerina', 'Katerinaki', '69', 'B10', 'CEID', 'UPatras', 20020);

-- --------------------------------------------------------

--
-- Table structure for table `professor_notes`
--

CREATE TABLE `professor_notes` (
  `diplo_id` int(11) DEFAULT NULL,
  `professor_user_id` int(11) DEFAULT NULL,
  `notes` varchar(300) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `professor_notes`
--

INSERT INTO `professor_notes` (`diplo_id`, `professor_user_id`, `notes`) VALUES
(401, 20001, '???? ???????? ??? ???? ???????????.'),
(402, 20001, '???? ???????. ?????????? ??????????'),
(404, 20002, '???? ???????? ??? ???? ???????????.'),
(405, 20004, '???? ???????? ??? ???? ???????????.');

-- --------------------------------------------------------

--
-- Table structure for table `secretary`
--

CREATE TABLE `secretary` (
  `secretary_user_id` int(11) NOT NULL,
  `secretary_name` varchar(255) DEFAULT NULL,
  `secretary_surname` varchar(255) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `secretary`
--

INSERT INTO `secretary` (`secretary_user_id`, `secretary_name`, `secretary_surname`) VALUES
(30000, 'maria', 'kafk');

-- --------------------------------------------------------

--
-- Table structure for table `student`
--

CREATE TABLE `student` (
  `student_am` int(11) NOT NULL,
  `student_name` varchar(255) DEFAULT NULL,
  `student_surname` varchar(255) DEFAULT NULL,
  `student_middlename` varchar(255) DEFAULT NULL,
  `student_street` varchar(255) DEFAULT NULL,
  `student_streetnum` int(11) DEFAULT NULL,
  `student_city` varchar(255) DEFAULT NULL,
  `student_postcode` int(11) DEFAULT NULL,
  `student_email` varchar(255) DEFAULT NULL,
  `student_tel` int(11) DEFAULT NULL,
  `student_user_id` int(11) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `trimelis_grades`
--

--
-- Dumping data for table `secretary`
--
INSERT INTO 'student' (student_am, student_name, student_surname, student_middlename, student_street, student_streetnum, student_city, student_postcode,student_email, student_tel, student_user_id) VALUES
(1000001, 'Andreas', 'Savva', 'Kostas', 'Ermou', 56, 'Patra', '26221', 'up1000001@upnet.gr', '6912346987', 10001),
(1000002, 'Ioannis', 'Kafkalias', 'Michalis', 'Riga Feraiou', 34, 'Lemesos', '3114', 'up1000002@upnet.gr', '6912346988', 10002),
(1000003, 'Christos', 'Koulermou', 'Pampos', 'Korinthou', 104, 'Patra', '26222', 'up1000003@upnet.gr', '6912346989', 10003),
(1000004, 'Kyros', 'Ataya', 'Alex', 'Agiou Nikolaou', 40, 'Patra', '26221', 'up1000004@upnet.gr', '6912346981', 10004),
(1000005, 'Maria', 'Ioannou', 'Kostas', 'Anexartisias', 56, 'Lefkosia', '4001', 'up1000005@upnet.gr', '6912346982', 10005),
(1000006, 'Michaella', 'Alexandrou', 'Ioannis', 'Ermou', 10, 'Patra', '26221', 'up1000006@upnet.gr', '6912346983', 10006),
(1000007, 'Kiki', 'Kyriazi', 'Leonidas', 'Mezonos', 84, 'Patra', '26225', 'up1000007@upnet.gr', '6912346984', 10007),
(1000008, 'Nikoletta', 'Thanasi', 'Nikos', 'Mezonos', 56, 'Patra', '26225', 'up1000008@upnet.gr', '6912346985', 10008),
(1000009, 'Andreas', 'Ioannou', 'Mariou', 'Eleftherias', 6, 'Lemesos', '2621', 'up1000009@upnet.gr', '6912346986', 10009),
(1000010, 'Elena', 'Konstantinou', 'Kostas', 'Ermou', 5, 'Athens', '21146', 'up1000010@upnet.gr', '6912346917', 10010),
(1000011, 'Katerina', 'Savva', 'Koulis', 'Korinthou', 28, 'Patra', '26221', 'up1000011@upnet.gr', '6912346927', 10011),
(1000012, 'Thanasis', 'Sokrati', 'Sokrati', 'Kolokotroni', 9, 'Athens', '26234', 'up1000012@upnet.gr', '6912346937', 10012),
(1000013, 'Maria', 'Nikolaou', 'Thanasis', 'Ermou', 76, 'Patra', '26221', 'up1000013@upnet.gr', '6912346947', 10013),
(1000014, 'Andreas', 'Rafael', 'Nikos', 'Korinthou', 26, 'Thessaloniki', '23221', 'up1000014@upnet.gr', '6912346957', 10014),
(1000015, 'Soulla', 'Soullou', 'Soulli', 'Lemesou', 56, 'Patra', '26321', 'up1000015@upnet.gr', '6912346967', 10015),
(1000016, 'Andreas', 'Savva', 'Christos', 'Pantanassis', 31, 'Lemesos', '2623', 'up1000016@upnet.gr', '6912346977', 10016),
(1000017, 'Nikoletta', 'Pittaka', 'Christos', 'Ermou', 56, 'Patra', '26221', 'up1000017@upnet.gr', '6912346787', 10017),
(1000018, 'Nikos', 'Savva', 'Michalis', 'Korinthou', 56, 'Patra', '26221', 'up1000018@upnet.gr', '6922346987', 10018),
(1000019, 'Odysseas', 'Elitis', 'Kostas', 'Eirinis', 12, 'Zakynthos', '2981', 'up1000019@upnet.gr', '6912356987', 10019),
(1000020, 'Giannis', 'Ritsos', 'Emannouel', 'Nikis', 74, 'Lemesos', '3114', 'up1000020@upnet.gr', '6912363887', 10020)
;

CREATE TABLE `trimelis_grades` (
  `diplo_id` int(11) DEFAULT NULL,
  `trimelis_professor1_grade` decimal(4,2) DEFAULT NULL,
  `trimelis_professor2_grade` decimal(4,2) DEFAULT NULL,
  `trimelis_professor3_grade` decimal(4,2) DEFAULT NULL,
  `trimelis_final_grade` decimal(4,2) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
--
-- Dumping data for table `trimelis_grades`
--

INSERT INTO `trimelis_grades` (`diplo_id`, `trimelis_professor1_grade`, `trimelis_professor2_grade`, `trimelis_professor3_grade`, `trimelis_final_grade`) VALUES
(401, '9.30', '9.00', '9.00', '9.00'),
(402, '9.00', '9.00', '9.00', '9.00'),
(406, '10.00', '9.00', '10.00', '10.00'),
(410, '10.00', '9.00', '8.50', '9.50');

-- --------------------------------------------------------

--
-- Table structure for table `trimelous`
--

CREATE TABLE `trimelous` (
  `diplo_id` int(11) NOT NULL,
  `trimelous_professor1` int(11) DEFAULT NULL,
  `trimelous_professor2` int(11) DEFAULT NULL,
  `trimelous_professor3` int(11) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `trimelous`
--

INSERT INTO `trimelous` (`diplo_id`, `trimelous_professor1`, `trimelous_professor2`, `trimelous_professor3`) VALUES
(401, 20001, 20011, 20008),
(402, 20001, 20002, 20010),
(406, 20005, 20008, 20007),
(410, 20007, 20009, 20001);

-- --------------------------------------------------------

--
-- Table structure for table `trimelous_invite`
--

CREATE TABLE `trimelous_invite` (
  `diplo_id` int(11) NOT NULL,
  `diplo_student_am` int(11) DEFAULT NULL,
  `professor_user_id` int(11) DEFAULT NULL,
  `trimelous_date` datetime DEFAULT NULL,
  `invite_status` enum('pending','accept','deny','cancel') DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `trimelous_invite`
--

INSERT INTO `trimelous_invite` (`diplo_id`, `diplo_student_am`, `professor_user_id`, `trimelous_date`, `invite_status`) VALUES
(401, 1000001, 20001, '2025-04-25 00:00:00', 'accept'),
(402, 1000002, 20001, '2025-04-25 00:00:00', 'accept'),
(404, 1000003, 20002, '2025-03-10 00:00:00', 'pending'),
(405, 1000007, 20004, '2025-01-01 00:00:00', 'deny'),
(406, 10000010, 20005, '2025-12-20 00:00:00', 'pending'),
(407, 1000006, 20002, '2025-03-03 00:00:00', 'accept'),
(409, 1000013, 20003, '2022-03-09 00:00:00', 'accept'),
(410, 1000011, 20007, '2025-04-17 00:00:00', 'accept');

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `user_id` int(11) NOT NULL,
  `user_username` varchar(255) NOT NULL,
  `user_pass` varchar(255) NOT NULL,
  `user_category` enum('Student','Professor','Secretary') DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`user_id`, `user_username`, `user_pass`, `user_category`) VALUES
(30000, 'maria', 'kafk', 'Secretary'),
(10001, 'st1000001@upnet.gr', '1', 'Student'),
(10002, 'st1000002@upnet.gr', '1', 'Student'),
(10003, 'st1000003@upnet.gr', '1', 'Student'),
(10004, 'st1000004@upnet.gr', '1', 'Student'),
(10005, 'st1000005@upnet.gr', '1', 'Student'),
(10006, 'st1000006@upnet.gr', '1', 'Student'),
(10007, 'st1000007@upnet.gr', '1', 'Student'),
(10008, 'st1000008@upnet.gr', '1', 'Student'),
(10009, 'st1000009@upnet.gr', '1', 'Student'),
(10010, 'st1000010@upnet.gr', '1', 'Student'),
(10011, 'st1000011@upnet.gr', '1', 'Student'),
(10012, 'st1000012@upnet.gr', '1', 'Student'),
(10013, 'st1000013@upnet.gr', '1', 'Student'),
(10014, 'st1000014@upnet.gr', '1', 'Student'),
(10015, 'st1000015@upnet.gr', '1', 'Student'),
(10016, 'st1000016@upnet.gr', '1', 'Student'),
(10017, 'st1000017@upnet.gr', '1', 'Student'),
(10018, 'st1000018@upnet.gr', '1', 'Student'),
(10019, 'st1000019@upnet.gr', '1', 'Student'),
(10020, 'st1000020@upnet.gr', '1', 'Student'),
(20001, 'pr2000001@upnet.gr', 'admin', 'Professor'),
(20002, 'pr2000002@upnet.gr', 'admin', 'Professor'),
(20003, 'pr2000003@upnet.gr', 'admin', 'Professor'),
(20004, 'pr2000004@upnet.gr', 'admin', 'Professor'),
(20005, 'pr2000005@upnet.gr', 'admin', 'Professor'),
(20006, 'pr2000006@upnet.gr', 'admin', 'Professor'),
(20007, 'pr2000007@upnet.gr', 'admin', 'Professor'),
(20008, 'pr2000008@upnet.gr', 'admin', 'Professor'),
(20009, 'pr2000009@upnet.gr', 'admin', 'Professor'),
(20010, 'pr2000010@upnet.gr', 'admin', 'Professor'),
(20011, 'pr2000011@upnet.gr', 'admin', 'Professor'),
(20012, 'pr2000012@upnet.gr', 'admin', 'Professor'),
(20013, 'pr2000013@upnet.gr', 'admin', 'Professor'),
(20014, 'pr2000014@upnet.gr', 'admin', 'Professor'),
(20015, 'pr2000015@upnet.gr', 'admin', 'Professor'),
(20016, 'pr2000016@upnet.gr', 'admin', 'Professor'),
(20017, 'pr2000017@upnet.gr', 'admin', 'Professor'),
(20018, 'pr2000018@upnet.gr', 'admin', 'Professor'),
(20019, 'pr2000019@upnet.gr', 'admin', 'Professor'),
(20020, 'pr2000020@upnet.gr', 'admin', 'Professor');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `cancelation`
--
ALTER TABLE `cancelation`
  ADD KEY `fk_diplo_cancelation` (`diplo_id`);

--
-- Indexes for table `diplo`
--
ALTER TABLE `diplo`
  ADD PRIMARY KEY (`diplo_id`),
  ADD KEY `fk_diplo_professor` (`diplo_professor`);

--
-- Indexes for table `diplo_date`
--
ALTER TABLE `diplo_date`
  ADD KEY `fk_diplo_date` (`diplo_id`);

--
-- Indexes for table `draft`
--
ALTER TABLE `draft`
  ADD KEY `fk_diplo_draft` (`diplo_id`);

--
-- Indexes for table `grade_criteria`
--
ALTER TABLE `grade_criteria`
  ADD KEY `fk_diplo_grade_criteria` (`diplo_id`);

--
-- Indexes for table `presentation`
--
ALTER TABLE `presentation`
  ADD KEY `fk_diplo_presentation` (`diplo_id`);

--
-- Indexes for table `professor`
--
ALTER TABLE `professor`
  ADD PRIMARY KEY (`professor_email`),
  ADD UNIQUE KEY `professor_user_id` (`professor_user_id`);

--
-- Indexes for table `professor_notes`
--
ALTER TABLE `professor_notes`
  ADD KEY `fk_diplo_professor_notes` (`diplo_id`);

--
-- Indexes for table `secretary`
--
ALTER TABLE `secretary`
  ADD PRIMARY KEY (`secretary_user_id`);

--
-- Indexes for table `student`
--
ALTER TABLE `student`
  ADD PRIMARY KEY (`student_am`),
  ADD UNIQUE KEY `student_user_id` (`student_user_id`);

--
-- Indexes for table `trimelis_grades`
--
ALTER TABLE `trimelis_grades`
  ADD KEY `fk_diplo_trimelis_grades` (`diplo_id`);

--
-- Indexes for table `trimelous`
--
ALTER TABLE `trimelous`
  ADD PRIMARY KEY (`diplo_id`);

--
-- Indexes for table `trimelous_invite`
--
ALTER TABLE `trimelous_invite`
  ADD KEY `fk_diplo_trimelous_invite` (`diplo_id`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `user_username` (`user_username`);
COMMIT;
ALTER TABLE diplo
  ADD COLUMN grading_enabled TINYINT(1) NOT NULL DEFAULT 0;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;



