-- phpMyAdmin SQL Dump
-- version 4.9.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Feb 11, 2020 at 10:31 PM
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
-- Table structure for table `systemdata`
--

CREATE TABLE `systemdata` (
  `id` int(11) NOT NULL,
  `timestamp` datetime NOT NULL,
  `StarSystem` varchar(255) NOT NULL,
  `SystemAddress` varchar(15) NOT NULL,
  `Population` int(11) NOT NULL,
  `SystemAllegiance` varchar(255) NOT NULL,
  `SystemGovernment` varchar(255) NOT NULL,
  `SystemSecurity` varchar(255) NOT NULL,
  `SystemEconomy` varchar(255) NOT NULL,
  `SystemSecondEconomy` varchar(255) NOT NULL,
  `ControllingFaction` varchar(255) NOT NULL,
  `FactionState` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `systemdata`
--
ALTER TABLE `systemdata`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `systemdata`
--
ALTER TABLE `systemdata`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
