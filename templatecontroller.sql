-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 21, 2026 at 02:26 PM
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
-- Database: `templatecontroller`
--

-- --------------------------------------------------------

--
-- Table structure for table `generated_templates`
--

CREATE TABLE `generated_templates` (
  `id` int(11) NOT NULL,
  `directory` varchar(255) NOT NULL,
  `template_name` varchar(255) NOT NULL,
  `company` varchar(255) NOT NULL,
  `code` varchar(100) NOT NULL,
  `instructionDate` date NOT NULL,
  `role` varchar(100) NOT NULL,
  `output_filename` varchar(255) DEFAULT 'filledFile.doc',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `templates`
--

CREATE TABLE `templates` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `category` varchar(100) NOT NULL,
  `subcategory` varchar(100) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `templates`
--

INSERT INTO `templates` (`id`, `name`, `category`, `subcategory`, `file_path`, `created_at`) VALUES
(1, 'template1', 'category1', 'subcategory1', 'path/to/template1.doc', '2026-02-21 13:21:09'),
(2, 'template2', 'category1', 'subcategory2', 'path/to/template2.doc', '2026-02-21 13:21:09'),
(3, 'template1', 'category1', 'subcategory1', 'path/to/template1.doc', '2026-02-21 13:23:04'),
(4, 'template2', 'category1', 'subcategory2', 'path/to/template2.doc', '2026-02-21 13:23:04');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `generated_templates`
--
ALTER TABLE `generated_templates`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `templates`
--
ALTER TABLE `templates`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `generated_templates`
--
ALTER TABLE `generated_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `templates`
--
ALTER TABLE `templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
