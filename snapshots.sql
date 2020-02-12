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
-- Table structure for table `snapshots`
--

CREATE TABLE `snapshots` (
  `id` int(11) NOT NULL,
  `tickid` int(11) NOT NULL,
  `timestamp` datetime NOT NULL,
  `issystem` tinyint(1) NOT NULL DEFAULT 0,
  `isfaction` tinyint(1) NOT NULL DEFAULT 0,
  `isconflict` tinyint(1) NOT NULL DEFAULT 0,
  `StarSystem` varchar(255) DEFAULT NULL,
  `SystemAddress` varchar(15) DEFAULT NULL,
  `Population` int(11) DEFAULT NULL,
  `SystemAllegiance` varchar(255) DEFAULT NULL,
  `SystemGovernment` varchar(255) DEFAULT NULL,
  `SystemSecurity` varchar(255) DEFAULT NULL,
  `SystemEconomy` varchar(255) DEFAULT NULL,
  `SystemSecondEconomy` varchar(255) DEFAULT NULL,
  `ControllingFaction` varchar(255) DEFAULT NULL,
  `FactionState` varchar(255) DEFAULT NULL,
  `Name` varchar(255) DEFAULT NULL,
  `factionsystem` varchar(255) DEFAULT NULL,
  `factionaddress` varchar(15) DEFAULT NULL,
  `Government` varchar(255) DEFAULT NULL,
  `Influence` decimal(10,10) DEFAULT NULL,
  `Allegiance` varchar(255) DEFAULT NULL,
  `Happiness` varchar(255) NOT NULL DEFAULT '0',
  `stateBlight` tinyint(1) NOT NULL DEFAULT 0,
  `stateBoom` tinyint(1) NOT NULL DEFAULT 0,
  `stateBust` tinyint(1) NOT NULL DEFAULT 0,
  `stateCivilLiberty` tinyint(1) NOT NULL DEFAULT 0,
  `stateCivilUnrest` tinyint(1) NOT NULL DEFAULT 0,
  `stateCivilWar` tinyint(1) NOT NULL DEFAULT 0,
  `stateColdWar` tinyint(1) NOT NULL DEFAULT 0,
  `stateColonisation` tinyint(1) NOT NULL DEFAULT 0,
  `stateDamaged` tinyint(1) NOT NULL DEFAULT 0,
  `stateDrought` tinyint(1) NOT NULL DEFAULT 0,
  `stateElection` tinyint(1) NOT NULL DEFAULT 0,
  `stateExpansion` tinyint(1) NOT NULL DEFAULT 0,
  `stateFamine` tinyint(1) NOT NULL DEFAULT 0,
  `stateHistoricEvent` tinyint(1) NOT NULL DEFAULT 0,
  `stateInfrastructureFailure` tinyint(1) NOT NULL DEFAULT 0,
  `stateInvestment` tinyint(1) NOT NULL DEFAULT 0,
  `stateLockdown` tinyint(1) NOT NULL DEFAULT 0,
  `stateNaturalDisaster` tinyint(1) NOT NULL DEFAULT 0,
  `stateOutbreak` tinyint(1) NOT NULL DEFAULT 0,
  `statePirateAttack` tinyint(1) NOT NULL DEFAULT 0,
  `statePublicHoliday` tinyint(1) NOT NULL DEFAULT 0,
  `stateRetreat` tinyint(1) NOT NULL DEFAULT 0,
  `stateRevolution` tinyint(1) NOT NULL DEFAULT 0,
  `stateTechnologicalLeap` tinyint(1) NOT NULL DEFAULT 0,
  `stateTerroristAttack` tinyint(1) NOT NULL DEFAULT 0,
  `stateTradeWar` tinyint(1) NOT NULL DEFAULT 0,
  `stateUnderRepairs` tinyint(1) NOT NULL DEFAULT 0,
  `stateWar` tinyint(1) NOT NULL DEFAULT 0,
  `recBlight` tinyint(1) NOT NULL DEFAULT 0,
  `recBlighttrend` tinyint(1) DEFAULT NULL,
  `recBoom` tinyint(1) NOT NULL DEFAULT 0,
  `recBoomtrend` tinyint(1) DEFAULT NULL,
  `recBust` tinyint(1) NOT NULL DEFAULT 0,
  `recBusttrend` tinyint(1) DEFAULT NULL,
  `recCivilLiberty` tinyint(1) NOT NULL DEFAULT 0,
  `recCivilLibertytrend` tinyint(1) DEFAULT NULL,
  `recCivilUnrest` tinyint(1) NOT NULL DEFAULT 0,
  `recCivilUnresttrend` tinyint(1) DEFAULT NULL,
  `recCivilWar` tinyint(1) NOT NULL DEFAULT 0,
  `recCivilWartrend` tinyint(1) DEFAULT NULL,
  `recColdWar` tinyint(1) NOT NULL DEFAULT 0,
  `recColdWartrend` tinyint(1) DEFAULT NULL,
  `recColonisation` tinyint(1) NOT NULL DEFAULT 0,
  `recColonisationtrend` tinyint(1) DEFAULT NULL,
  `recDamaged` tinyint(1) NOT NULL DEFAULT 0,
  `recDamagedtrend` tinyint(1) DEFAULT NULL,
  `recDrought` tinyint(1) NOT NULL DEFAULT 0,
  `recDroughttrend` tinyint(1) DEFAULT NULL,
  `recElection` tinyint(1) NOT NULL DEFAULT 0,
  `recElectiontrend` tinyint(1) DEFAULT NULL,
  `recExpansion` tinyint(1) NOT NULL DEFAULT 0,
  `recExpansiontrend` tinyint(1) DEFAULT NULL,
  `recFamine` tinyint(1) NOT NULL DEFAULT 0,
  `recFaminetrend` tinyint(1) DEFAULT NULL,
  `recHistoricEvent` tinyint(1) NOT NULL DEFAULT 0,
  `recHistoricEventtrend` tinyint(1) DEFAULT NULL,
  `recInfrastructureFailure` tinyint(1) NOT NULL DEFAULT 0,
  `recInfrastructureFailuretrend` tinyint(1) DEFAULT NULL,
  `recInvestment` tinyint(1) NOT NULL DEFAULT 0,
  `recInvestmenttrend` tinyint(1) DEFAULT NULL,
  `recLockdown` tinyint(1) NOT NULL DEFAULT 0,
  `recLockdowntrend` tinyint(1) DEFAULT NULL,
  `recNaturalDisaster` tinyint(1) NOT NULL DEFAULT 0,
  `recNaturalDisastertrend` tinyint(1) DEFAULT NULL,
  `recOutbreak` tinyint(1) NOT NULL DEFAULT 0,
  `recOutbreaktrend` tinyint(1) DEFAULT NULL,
  `recPirateAttack` tinyint(1) NOT NULL DEFAULT 0,
  `recPirateAttacktrend` tinyint(1) DEFAULT NULL,
  `recPublicHoliday` tinyint(1) NOT NULL DEFAULT 0,
  `recPublicHolidaytrend` tinyint(1) DEFAULT NULL,
  `recRetreat` tinyint(1) NOT NULL DEFAULT 0,
  `recRetreattrend` tinyint(1) DEFAULT NULL,
  `recRevolution` tinyint(1) NOT NULL DEFAULT 0,
  `recRevolutiontrend` tinyint(1) DEFAULT NULL,
  `recTechnologicalLeap` tinyint(1) NOT NULL DEFAULT 0,
  `recTechnologicalLeaptrend` tinyint(1) DEFAULT NULL,
  `recTerroristAttack` tinyint(1) NOT NULL DEFAULT 0,
  `recTerroristAttacktrend` tinyint(1) DEFAULT NULL,
  `recTradeWar` tinyint(1) NOT NULL DEFAULT 0,
  `recTradeWartrend` tinyint(1) DEFAULT NULL,
  `recUnderRepairs` tinyint(1) NOT NULL DEFAULT 0,
  `recUnderRepairstrend` tinyint(1) DEFAULT NULL,
  `recWar` tinyint(1) NOT NULL DEFAULT 0,
  `recWartrend` tinyint(1) DEFAULT NULL,
  `pendingBlight` tinyint(1) NOT NULL DEFAULT 0,
  `pendingBlighttrend` tinyint(1) DEFAULT NULL,
  `pendingBoom` tinyint(1) NOT NULL DEFAULT 0,
  `pendingBoomtrend` tinyint(1) DEFAULT NULL,
  `pendingBust` tinyint(1) NOT NULL DEFAULT 0,
  `pendingBusttrend` tinyint(1) DEFAULT NULL,
  `pendingCivilLiberty` tinyint(1) NOT NULL DEFAULT 0,
  `pendingCivilLibertytrend` tinyint(1) DEFAULT NULL,
  `pendingCivilUnrest` tinyint(1) NOT NULL DEFAULT 0,
  `pendingCivilUnresttrend` tinyint(1) DEFAULT NULL,
  `pendingCivilWar` tinyint(1) NOT NULL DEFAULT 0,
  `pendingCivilWartrend` tinyint(1) DEFAULT NULL,
  `pendingColdWar` tinyint(1) NOT NULL DEFAULT 0,
  `pendingColdWartrend` tinyint(1) DEFAULT NULL,
  `pendingColonisation` tinyint(1) NOT NULL DEFAULT 0,
  `pendingColonisationtrend` tinyint(1) DEFAULT NULL,
  `pendingDamaged` tinyint(1) NOT NULL DEFAULT 0,
  `pendingDamagedtrend` tinyint(1) DEFAULT NULL,
  `pendingDrought` tinyint(1) NOT NULL DEFAULT 0,
  `pendingDroughttrend` tinyint(1) DEFAULT NULL,
  `pendingElection` tinyint(1) NOT NULL DEFAULT 0,
  `pendingElectiontrend` tinyint(1) DEFAULT NULL,
  `pendingExpansion` tinyint(1) NOT NULL DEFAULT 0,
  `pendingExpansiontrend` tinyint(1) DEFAULT NULL,
  `pendingFamine` tinyint(1) NOT NULL DEFAULT 0,
  `pendingFaminetrend` tinyint(1) DEFAULT NULL,
  `pendingHistoricEvent` tinyint(1) NOT NULL DEFAULT 0,
  `pendingHistoricEventtrend` tinyint(1) DEFAULT NULL,
  `pendingInfrastructureFailure` tinyint(1) NOT NULL DEFAULT 0,
  `pendingInfrastructureFailuretrend` tinyint(1) DEFAULT NULL,
  `pendingInvestment` tinyint(1) NOT NULL DEFAULT 0,
  `pendingInvestmenttrend` tinyint(1) DEFAULT NULL,
  `pendingLockdown` tinyint(1) NOT NULL DEFAULT 0,
  `pendingLockdowntrend` tinyint(1) DEFAULT NULL,
  `pendingNaturalDisaster` tinyint(1) NOT NULL DEFAULT 0,
  `pendingNaturalDisastertrend` tinyint(1) DEFAULT NULL,
  `pendingOutbreak` tinyint(1) NOT NULL DEFAULT 0,
  `pendingOutbreaktrend` tinyint(1) DEFAULT NULL,
  `pendingPirateAttack` tinyint(1) NOT NULL DEFAULT 0,
  `pendingPirateAttacktrend` tinyint(1) DEFAULT NULL,
  `pendingPublicHoliday` tinyint(1) NOT NULL DEFAULT 0,
  `pendingPublicHolidaytrend` tinyint(1) DEFAULT NULL,
  `pendingRetreat` tinyint(1) NOT NULL DEFAULT 0,
  `pendingRetreattrend` tinyint(1) DEFAULT NULL,
  `pendingRevolution` tinyint(1) NOT NULL DEFAULT 0,
  `pendingRevolutiontrend` tinyint(1) DEFAULT NULL,
  `pendingTechnologicalLeap` tinyint(1) NOT NULL DEFAULT 0,
  `pendingTechnologicalLeaptrend` tinyint(1) DEFAULT NULL,
  `pendingTerroristAttack` tinyint(1) NOT NULL DEFAULT 0,
  `pendingTerroristAttacktrend` tinyint(1) DEFAULT NULL,
  `pendingTradeWar` tinyint(1) NOT NULL DEFAULT 0,
  `pendingTradeWartrend` tinyint(1) DEFAULT NULL,
  `pendingUnderRepairs` tinyint(1) NOT NULL DEFAULT 0,
  `pendingUnderRepairstrend` tinyint(1) DEFAULT NULL,
  `pendingWar` tinyint(1) NOT NULL DEFAULT 0,
  `pendingWartrend` tinyint(1) DEFAULT NULL,
  `conflicttype` varchar(255) DEFAULT NULL,
  `conflictstatus` varchar(255) DEFAULT NULL,
  `conflictfaction1name` varchar(255) DEFAULT NULL,
  `conflictfaction1stake` varchar(255) DEFAULT NULL,
  `conflictfaction1windays` smallint(4) DEFAULT NULL,
  `conflictfaction2name` varchar(255) DEFAULT NULL,
  `conflictfaction2stake` varchar(255) DEFAULT NULL,
  `conflictfaction2windays` smallint(4) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `snapshots`
--
ALTER TABLE `snapshots`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `snapshots`
--
ALTER TABLE `snapshots`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
