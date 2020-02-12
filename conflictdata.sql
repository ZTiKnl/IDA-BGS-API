-- phpMyAdmin SQL Dump
-- version 4.9.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Feb 11, 2020 at 10:30 PM
-- Server version: 10.3.22-MariaDB-0+deb10u1
-- PHP Version: 7.3.11-1~deb10u1

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ida_bgs`
--

-- --------------------------------------------------------

--
-- Table structure for table `conflictdata`
--

CREATE TABLE `conflictdata` (
  `id` int(11) NOT NULL,
  `timestamp` datetime NOT NULL,
  `StarSystem` varchar(255) NOT NULL,
  `SystemAddress` varchar(15) NOT NULL,
  `conflicttype` varchar(255) NOT NULL,
  `conflictstatus` varchar(255) NOT NULL,
  `conflictfaction1name` varchar(255) NOT NULL,
  `conflictfaction1stake` varchar(255) NOT NULL,
  `conflictfaction1windays` smallint(4) NOT NULL,
  `conflictfaction2name` varchar(255) NOT NULL,
  `conflictfaction2stake` varchar(255) NOT NULL,
  `conflictfaction2windays` smallint(4) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `conflictdata`
--
ALTER TABLE `conflictdata`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `conflictdata`
--
ALTER TABLE `conflictdata`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
