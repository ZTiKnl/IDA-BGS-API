<?PHP
// include config variables
include('config.inc.php');
$dryrun = false;

$logfile = $loglocation.$logtickprocessor;

// connect to db
include($securedbcreds);
$con = mysqli_connect($servername,$username,$password,$database) or die("SQL connection error");

/*
$oldtick = '2020-02-04 13:29:43';
$oldtickid = 1548;
$newtick = '2020-02-05 13:38:50';
$newtickid = 1549;
*/

$oldtick = '';
$oldtickid = 0;
$newtick = '';
$newtickid = 0;
$log = '';

$tickquery = "SELECT id, timestamp FROM dailyticks ORDER BY id DESC LIMIT 2";
if ($tickresult = mysqli_query($con, $tickquery)){
  if (mysqli_num_rows($tickresult) === 2) {
    $i = 0;
    $rows = array();
    while($row = mysqli_fetch_array($tickresult, MYSQLI_ASSOC)) {
      $rows[$i]['id'] = $row['id'];
      $rows[$i]['timestamp'] = $row['timestamp'];
      $i++;
    }
    $newtick = $rows[0]['timestamp'];
    $newtickid = $rows[0]['id'];
    $oldtick = $rows[1]['timestamp'];
    $oldtickid = $rows[1]['id'];
  } elseif (mysqli_num_rows($tickresult) === 1) {
    $row = mysqli_fetch_array($tickresult, MYSQLI_ASSOC);
    $newtick = $row['timestamp'];
    $newtickid = $row['id'];
  }
} else {
  $log .= "Couldn't fetch tick data";
}

$tickprocessedyet = false;
$tickdataquery = "SELECT id FROM snapshot_systems WHERE tickid = '$oldtickid'";
if ($tickdataresult = mysqli_query($con, $tickdataquery)){
  if (mysqli_num_rows($tickdataresult) > 1) {
    if (!$dryrun) {
      $tickprocessedyet = true;
    }
    $log .= "Tick #".$oldtickid." has already been processed.\n";
  } else {
    $log .= "Processing tick #".$oldtickid."\n";
  }
}

$servertime = gmdate("Y-m-d H:i:s");
$log .= "ED Server time (no precision): ".$servertime."\n";
$log .= "Last server tick: ";
if (!$newtick) {
  $log .= "Unknown, not enough data\n";
} else {
  $log .= "(#".$newtickid.") ".$newtick."\n";
}
$log .= "One before last server tick: ";
if (!$oldtick) {
  $log .= "Unknown, not enough data\n";
} else {
  $log .= "(#".$oldtickid.") ".$oldtick."\n\n";
}

if (!$tickprocessedyet) {
  if(!$dryrun) {
    // REMOVE OLD DATA FROM ACTIVESNAPSHOT
    $deleteactivesnapshotsquery = "DELETE FROM act_snapshot_conflicts WHERE timestamp < '$newtick'";
    if ($deleteactivesnapshotsresult = mysqli_query($con, $deleteactivesnapshotsquery)){
      $log .= "Deleted old conflict records from active snapshot where timestamp < ".$newtick."\n";  
    }
    $deleteactivesnapshotsquery = "DELETE FROM act_snapshot_factions WHERE timestamp < '$newtick'";
    if ($deleteactivesnapshotsresult = mysqli_query($con, $deleteactivesnapshotsquery)){
      $log .= "Deleted old faction records from active snapshot where timestamp < ".$newtick."\n";  
    }
    $deleteactivesnapshotsquery = "DELETE FROM act_snapshot_systems WHERE timestamp < '$newtick'";
    if ($deleteactivesnapshotsresult = mysqli_query($con, $deleteactivesnapshotsquery)){
      $log .= "Deleted old system records from active snapshot where timestamp < ".$newtick."\n";  
    }
    $deleterewardactivesnapshotsquery = "DELETE FROM act_snapshot_missionrewards";
    if ($deleterewardactivesnapshotsresult = mysqli_query($con, $deleterewardactivesnapshotsquery)){
      $log .= "Deleted old missionrewards from active snapshot\n";
    }
    $deleteexplorationdataactivesnapshotquery = "DELETE FROM act_snapshot_missionrewards";
    if ($deleteexplorationdataactivesnapshotresult = mysqli_query($con, $deleteexplorationdataactivesnapshotquery)){
      $log .= "Deleted old explorationdata from active snapshot\n";
    }

    $deletedeliverydataactivesnapshotquery = "DELETE FROM act_snapshot_cargodeliveries";
    if ($deletedeliverydataactivesnapshotresult = mysqli_query($con, $deletedeliverydataactivesnapshotquery)){
      $log .= "Deleted old cargo delivery data from active snapshot\n";
    }
    $deletepurchasedataactivesnapshotquery = "DELETE FROM act_snapshot_cargopurchases";
    if ($deletepurchasedataactivesnapshotresult = mysqli_query($con, $deletepurchasedataactivesnapshotquery)){
      $log .= "Deleted old cargo purchase data from active snapshot\n";
    }

    $resyncactivesnapshotquery = "UPDATE act_snapshot_conflicts SET tickid = '$newtickid' WHERE timestamp >= '$newtick' AND tickid = '$oldtickid'";
    if ($resyncactivesnapshotresult = mysqli_query($con, $resyncactivesnapshotquery)){
      $log .= mysqli_affected_rows($con)." active snapshot conflict records updated to match new tickid\n";
    }
    $resyncactivesnapshotquery = "UPDATE act_snapshot_factions SET tickid = '$newtickid' WHERE timestamp >= '$newtick' AND tickid = '$oldtickid'";
    if ($resyncactivesnapshotresult = mysqli_query($con, $resyncactivesnapshotquery)){
      $log .= mysqli_affected_rows($con)." active snapshot factions records updated to match new tickid\n";
    }
    $resyncactivesnapshotquery = "UPDATE act_snapshot_systems SET tickid = '$newtickid' WHERE timestamp >= '$newtick' AND tickid = '$oldtickid'";
    if ($resyncactivesnapshotresult = mysqli_query($con, $resyncactivesnapshotquery)){
      $log .= mysqli_affected_rows($con)." active snapshot system records updated to match new tickid\n";
    }

    $deleteconflictdataquery = "DELETE FROM data_conflicts WHERE timestamp < '$oldtick'";
    if ($deleteconflictdataresult = mysqli_query($con, $deleteconflictdataquery)){
      $log .= "Deleted all records from data_conflicts where timestamp < ".$oldtick."\n";
    }
    $deletefactiondataquery = "DELETE FROM data_factions WHERE timestamp < '$oldtick'";
    if ($deletefactiondataresult = mysqli_query($con, $deletefactiondataquery)){
      $log .= "Deleted all records from data_factions where timestamp < ".$oldtick."\n";
    }
    $deletesystemdataquery = "DELETE FROM data_systems WHERE timestamp < '$oldtick'";
    if ($deletesystemdataresult = mysqli_query($con, $deletesystemdataquery)){
      $log .= "Deleted all records from data_systems where timestamp < ".$oldtick."\n";
    }
  }

  // START SYSTEM DATA GATHERING, final results are stored in array: $tempsystemslist
  $tempsystemslist = array();
  $finalsystemslist = array();
  $systemrecords = 0;
  $systemrecordsunique = 0;
  $systemdataquery = "SELECT SystemAddress, StarSystem FROM data_systems WHERE timestamp >= '$oldtick' AND timestamp <= '$newtick'";
  if ($systemdataresult = mysqli_query($con, $systemdataquery)){
    $systemrecords = mysqli_num_rows($systemdataresult);
    $log .= $systemrecords." records found with data_systems dated between last two ticks\n";

    while($row = mysqli_fetch_array($systemdataresult , MYSQLI_ASSOC)) {
      $systemaddress = $row['SystemAddress'];
      $systemname = $row['StarSystem'];
      $systemexistsinarray = false;

      if (count($tempsystemslist) > 0) {
        $i = 0;
        while($i < count($tempsystemslist)) {
          if ($tempsystemslist[$i]['SystemAddress'] == $systemaddress && $tempsystemslist[$i]['StarSystem'] == $systemname) {
            $log .= $systemname." records already processed, skipping duplicates\n";
            $systemexistsinarray = true;
          }
          $i++;
        }
      }

      if (!$systemexistsinarray) {
        $systemrecordsunique++;
        $intersectsystemdataquery = "SELECT StarSystem, SystemAddress FROM data_systems WHERE timestamp >= '$oldtick' AND timestamp <= '$newtick' AND SystemAddress = '$systemaddress' INTERSECT SELECT StarSystem, SystemAddress FROM data_systems WHERE timestamp >= '$oldtick' AND timestamp <= '$newtick' AND SystemAddress = '$systemaddress'";
        if ($intersectsystemdataresult = mysqli_query($con, $intersectsystemdataquery)){
          $intersectsystemrecords = mysqli_num_rows($intersectsystemdataresult);
          if ($intersectsystemrecords > 0) {
            if ($intersectsystemrecords > 1) {
              $log .= "Multiple data_systems records found for system: ".$systemname."\n";
            }

            $rowid = 0;
            $i = 0;
            $highest = 0;
            $datetime = 0;
            $rowarray = array();
            while ($i < ($intersectsystemrecords)) {
              $intersectsystemquery = "SELECT * FROM data_systems WHERE timestamp >= '$oldtick' AND timestamp <= '$newtick' AND SystemAddress = '$systemaddress'";
              if ($intersectsystemresult = mysqli_query($con, $intersectsystemquery)){
                while($row3 = mysqli_fetch_array($intersectsystemresult, MYSQLI_ASSOC)) {
                  if (strtotime($row3['timestamp']) > strtotime($datetime)) {
                    $datetime = $row3['timestamp'];
                  }
                  $StarSystem = addslashes($row3['StarSystem']);
                  $SystemAddress = $row3['SystemAddress'];
                  $Population = $row3['Population'];
                  $SystemAllegiance = $row3['SystemAllegiance'];
                  $SystemGovernment = $row3['SystemGovernment'];
                  $SystemSecurity = $row3['SystemSecurity'];
                  $SystemEconomy = $row3['SystemEconomy'];
                  $SystemSecondEconomy = $row3['SystemSecondEconomy'];
                  $ControllingFaction = $row3['ControllingFaction'];
                  $FactionState = $row3['FactionState'];
                  $intersectsystemcount = 0;
                  $intersectsystemcountquery = "SELECT id FROM data_systems WHERE StarSystem = '$StarSystem' AND SystemAddress = '$SystemAddress' AND Population = '$Population' AND SystemAllegiance = '$SystemAllegiance' AND SystemGovernment = '$SystemGovernment' AND SystemSecurity = '$SystemSecurity' AND SystemEconomy = '$SystemEconomy' AND SystemSecondEconomy = '$SystemSecondEconomy' AND ControllingFaction = '$ControllingFaction' AND FactionState = '$FactionState' AND timestamp >= '$oldtick' AND timestamp <= '$newtick'";
                  if ($intersectsystemcountresult = mysqli_query($con, $intersectsystemcountquery)) {
                    $intersectsystemcount = mysqli_num_rows($intersectsystemcountresult);
                  }

                  if ($intersectsystemcount >= $highest) {
                    $highest = $intersectsystemcount;
                    $rowarray = $row3;
                  }
                  $i++;
                }
              } else {
                $log .= "Couldn't enumerate intersected data_systems : ".$systemid." (".$systemname.")\n";
              }
            }
            if ($datetime > 0) {
              $rowarray['timestamp'] = $datetime;
            }
            $tempsystemslist[] = $rowarray;

          } else {
            $log .= "Couldn't consolidate intersected data_systems : ".$systemid." (".$systemname.")\n";
          }
        } else {
          $log .= "Couldn't consolidate data_systems: ".$systemid." (".$systemname.")\n";
        }
      }
    }
  } else {
    $log .= "Couldn't fetch system data\n";
  }
  // ALL SYSTEM DATA GATHERED, stored in $tempsystemslist
  if($dryrun) {
    echo "<pre>";
    print_r($tempsystemslist);
    echo "</pre>";
  }
  $log .= $systemrecordsunique." records left after processing, ".($systemrecords - $systemrecordsunique)." duplicate records ignored\n";
  $log .= "\n\n";

  // START FACTION DATA GATHERING, final results are stored in array: $tempfactionslist
  $tempfactionslist = array();
  $finalfactionslist = array();
  $factionrecords = 0;
  $factionrecordsunique = 0;
  $factiondataquery = "SELECT SystemAddress, StarSystem, Name FROM data_factions WHERE timestamp >= '$oldtick' AND timestamp <= '$newtick'";
  if ($factiondataresult = mysqli_query($con, $factiondataquery)){
    $factionrecords = mysqli_num_rows($factiondataresult);
    $log .= $factionrecords." records found with data_factions dated between last two ticks\n";

    while($row = mysqli_fetch_array($factiondataresult , MYSQLI_ASSOC)) {
      $factionsystemaddress = $row['SystemAddress'];
      $factionsystemname = addslashes($row['StarSystem']);
  	$factionname = addslashes($row['Name']);
      $factionexistsinarray = false;

      if (count($tempfactionslist) > 0) {
        $i = 0;
        while($i < count($tempfactionslist)) {
          if ($tempfactionslist[$i]['SystemAddress'] == $factionsystemaddress && $tempfactionslist[$i]['StarSystem'] == $factionsystemname && $tempfactionslist[$i]['Name'] == $factionname) {
            $log .= $factionname." records already processed, skipping duplicates\n";
            $factionexistsinarray = true;
          }
          $i++;
        }
      }

      if (!$factionexistsinarray) {
        $factionrecordsunique++;
        $intersectfactiondataquery = "SELECT StarSystem, SystemAddress, Name FROM data_factions WHERE timestamp >= '$oldtick' AND timestamp <= '$newtick' AND SystemAddress = '$factionsystemaddress' AND Name = '$factionname' INTERSECT SELECT StarSystem, SystemAddress, Name FROM data_factions WHERE timestamp >= '$oldtick' AND timestamp <= '$newtick' AND SystemAddress = '$factionsystemaddress' AND Name = '$factionname'";
        if ($intersectfactiondataresult = mysqli_query($con, $intersectfactiondataquery)){
          $intersectfactionrecords = mysqli_num_rows($intersectfactiondataresult);
          if ($intersectfactionrecords > 0) {
            if ($intersectfactionrecords > 1) {
              $log .= "Multiple data_factions records found for faction: ".$factionname." (".$factionsystemname.")\n";
            }

            $rowid = 0;
            $i = 0;
            $highest = 0;
            $datetime = 0;
            $rowarray = array();
            while ($i < ($intersectfactionrecords)) {
              $intersectfactionquery = "SELECT * FROM data_factions WHERE timestamp >= '$oldtick' AND timestamp <= '$newtick' AND SystemAddress = '$factionsystemaddress' AND Name = '$factionname'";
              if ($intersectfactionresult = mysqli_query($con, $intersectfactionquery)){
                while($row3 = mysqli_fetch_array($intersectfactionresult, MYSQLI_ASSOC)) {
                  if (strtotime($row3['timestamp']) > strtotime($datetime)) {
                    $datetime = $row3['timestamp'];
                  }
                  $factionsystemname = addslashes($row3['StarSystem']);
                  $factionsystemaddress = $row3['SystemAddress'];
                  $Name = addslashes($row3['Name']);
                  $Government = $row3['Government'];
                  $Influence = $row3['Influence'];
                  $Allegiance = $row3['Allegiance'];
                  $Happiness = $row3['Happiness'];
                  $stateBlight = $row3['stateBlight'];
                  $stateBoom = $row3['stateBoom'];
                  $stateBust = $row3['stateBust'];
                  $stateCivilLiberty = $row3['stateCivilLiberty'];
                  $stateCivilUnrest = $row3['stateCivilUnrest'];
                  $stateCivilWar = $row3['stateCivilWar'];
                  $stateColdWar = $row3['stateColdWar'];
                  $stateColonisation = $row3['stateColonisation'];
                  $stateDamaged = $row3['stateDamaged'];
                  $stateDrought = $row3['stateDrought'];
                  $stateElection = $row3['stateElection'];
                  $stateExpansion = $row3['stateExpansion'];
                  $stateFamine = $row3['stateFamine'];
                  $stateHistoricEvent = $row3['stateHistoricEvent'];
                  $stateInfrastructureFailure = $row3['stateInfrastructureFailure'];
                  $stateInvestment = $row3['stateInvestment'];
                  $stateLockdown = $row3['stateLockdown'];
                  $stateNaturalDisaster = $row3['stateNaturalDisaster'];
                  $stateOutbreak = $row3['stateOutbreak'];
                  $statePirateAttack = $row3['statePirateAttack'];
                  $statePublicHoliday = $row3['statePublicHoliday'];
                  $stateRetreat = $row3['stateRetreat'];
                  $stateRevolution = $row3['stateRevolution'];
                  $stateTechnologicalLeap = $row3['stateTechnologicalLeap'];
                  $stateTerroristAttack = $row3['stateTerroristAttack'];
                  $stateTradeWar = $row3['stateTradeWar'];
                  $stateUnderRepairs = $row3['stateUnderRepairs'];
                  $stateWar = $row3['stateWar'];
                  $recBlight = $row3['recBlight'];
                  $recBlightTrend = $row3['recBlighttrend'];
                  $recBoom = $row3['recBoom'];
                  $recBoomTrend = $row3['recBoomtrend'];
                  $recBust = $row3['recBust'];
                  $recBustTrend = $row3['recBusttrend'];
                  $recCivilLiberty = $row3['recCivilLiberty'];
                  $recCivilLibertyTrend = $row3['recCivilLibertytrend'];
                  $recCivilUnrest = $row3['recCivilUnrest'];
                  $recCivilUnrestTrend = $row3['recCivilUnresttrend'];
                  $recCivilWar = $row3['recCivilWar'];
                  $recCivilWarTrend = $row3['recCivilWartrend'];
                  $recColdWar = $row3['recColdWar'];
                  $recColdWarTrend = $row3['recColdWartrend'];
                  $recColonisation = $row3['recColonisation'];
                  $recColonisationTrend = $row3['recColonisationtrend'];
                  $recDamaged = $row3['recDamaged'];
                  $recDamagedTrend = $row3['recDamagedtrend'];
                  $recDrought = $row3['recDrought'];
                  $recDroughtTrend = $row3['recDroughttrend'];
                  $recElection = $row3['recElection'];
                  $recElectionTrend = $row3['recElectiontrend'];
                  $recExpansion = $row3['recExpansion'];
                  $recExpansionTrend = $row3['recExpansiontrend'];
                  $recFamine = $row3['recFamine'];
                  $recFamineTrend = $row3['recFaminetrend'];
                  $recHistoricEvent = $row3['recHistoricEvent'];
                  $recHistoricEventTrend = $row3['recHistoricEventtrend'];
                  $recInfrastructureFailure = $row3['recInfrastructureFailure'];
                  $recInfrastructureFailureTrend = $row3['recInfrastructureFailuretrend'];
                  $recInvestment = $row3['recInvestment'];
                  $recInvestmentTrend = $row3['recInvestmenttrend'];
                  $recLockdown = $row3['recLockdown'];
                  $recLockdownTrend = $row3['recLockdowntrend'];
                  $recNaturalDisaster = $row3['recNaturalDisaster'];
                  $recNaturalDisasterTrend = $row3['recNaturalDisastertrend'];
                  $recOutbreak = $row3['recOutbreak'];
                  $recOutbreakTrend = $row3['recOutbreaktrend'];
                  $recPirateAttack = $row3['recPirateAttack'];
                  $recPirateAttackTrend = $row3['recPirateAttacktrend'];
                  $recPublicHoliday = $row3['recPublicHoliday'];
                  $recPublicHolidayTrend = $row3['recPublicHolidaytrend'];
                  $recRetreat = $row3['recRetreat'];
                  $recRetreatTrend = $row3['recRetreattrend'];
                  $recRevolution = $row3['recRevolution'];
                  $recRevolutionTrend = $row3['recRevolutiontrend'];
                  $recTechnologicalLeap = $row3['recTechnologicalLeap'];
                  $recTechnologicalLeapTrend = $row3['recTechnologicalLeaptrend'];
                  $recTerroristAttack = $row3['recTerroristAttack'];
                  $recTerroristAttackTrend = $row3['recTerroristAttacktrend'];
                  $recTradeWar = $row3['recTradeWar'];
                  $recTradeWarTrend = $row3['recTradeWartrend'];
                  $recUnderRepairs = $row3['recUnderRepairs'];
                  $recUnderRepairsTrend = $row3['recUnderRepairstrend'];
                  $recWar = $row3['recWar'];
                  $recWarTrend = $row3['recWartrend'];
                  $pendingBlight = $row3['pendingBlight'];
                  $pendingBlightTrend = $row3['pendingBlighttrend'];
                  $pendingBoom = $row3['pendingBoom'];
                  $pendingBoomTrend = $row3['pendingBoomtrend'];
                  $pendingBust = $row3['pendingBust'];
                  $pendingBustTrend = $row3['pendingBusttrend'];
                  $pendingCivilLiberty = $row3['pendingCivilLiberty'];
                  $pendingCivilLibertyTrend = $row3['pendingCivilLibertytrend'];
                  $pendingCivilUnrest = $row3['pendingCivilUnrest'];
                  $pendingCivilUnrestTrend = $row3['pendingCivilUnresttrend'];
                  $pendingCivilWar = $row3['pendingCivilWar'];
                  $pendingCivilWarTrend = $row3['pendingCivilWartrend'];
                  $pendingColdWar = $row3['pendingColdWar'];
                  $pendingColdWarTrend = $row3['pendingColdWartrend'];
                  $pendingColonisation = $row3['pendingColonisation'];
                  $pendingColonisationTrend = $row3['pendingColonisationtrend'];
                  $pendingDamaged = $row3['pendingDamaged'];
                  $pendingDamagedTrend = $row3['pendingDamagedtrend'];
                  $pendingDrought = $row3['pendingDrought'];
                  $pendingDroughtTrend = $row3['pendingDroughttrend'];
                  $pendingElection = $row3['pendingElection'];
                  $pendingElectionTrend = $row3['pendingElectiontrend'];
                  $pendingExpansion = $row3['pendingExpansion'];
                  $pendingExpansionTrend = $row3['pendingExpansiontrend'];
                  $pendingFamine = $row3['pendingFamine'];
                  $pendingFamineTrend = $row3['pendingFaminetrend'];
                  $pendingHistoricEvent = $row3['pendingHistoricEvent'];
                  $pendingHistoricEventTrend = $row3['pendingHistoricEventtrend'];
                  $pendingInfrastructureFailure = $row3['pendingInfrastructureFailure'];
                  $pendingInfrastructureFailureTrend = $row3['pendingInfrastructureFailuretrend'];
                  $pendingInvestment = $row3['pendingInvestment'];
                  $pendingInvestmentTrend = $row3['pendingInvestmenttrend'];
                  $pendingLockdown = $row3['pendingLockdown'];
                  $pendingLockdownTrend = $row3['pendingLockdowntrend'];
                  $pendingNaturalDisaster = $row3['pendingNaturalDisaster'];
                  $pendingNaturalDisasterTrend = $row3['pendingNaturalDisastertrend'];
                  $pendingOutbreak = $row3['pendingOutbreak'];
                  $pendingOutbreakTrend = $row3['pendingOutbreaktrend'];
                  $pendingPirateAttack = $row3['pendingPirateAttack'];
                  $pendingPirateAttackTrend = $row3['pendingPirateAttacktrend'];
                  $pendingPublicHoliday = $row3['pendingPublicHoliday'];
                  $pendingPublicHolidayTrend = $row3['pendingPublicHolidaytrend'];
                  $pendingRetreat = $row3['pendingRetreat'];
                  $pendingRetreatTrend = $row3['pendingRetreattrend'];
                  $pendingRevolution = $row3['pendingRevolution'];
                  $pendingRevolutionTrend = $row3['pendingRevolutiontrend'];
                  $pendingTechnologicalLeap = $row3['pendingTechnologicalLeap'];
                  $pendingTechnologicalLeapTrend = $row3['pendingTechnologicalLeaptrend'];
                  $pendingTerroristAttack = $row3['pendingTerroristAttack'];
                  $pendingTerroristAttackTrend = $row3['pendingTerroristAttacktrend'];
                  $pendingTradeWar = $row3['pendingTradeWar'];
                  $pendingTradeWarTrend = $row3['pendingTradeWartrend'];
                  $pendingUnderRepairs = $row3['pendingUnderRepairs'];
                  $pendingUnderRepairsTrend = $row3['pendingUnderRepairstrend'];
                  $pendingWar = $row3['pendingWar'];
                  $pendingWarTrend = $row3['pendingWartrend'];

                  $intersectfactioncount = 0;
                  $intersectfactioncountquery = "SELECT id FROM data_factions WHERE SystemAddress = '$factionsystemaddress' AND Name = '$Name' AND Government = '$Government' AND Influence = '$Influence' AND Allegiance = '$Allegiance' AND Happiness = '$Happiness' AND stateBlight = '$stateBlight' AND stateBoom = '$stateBoom' AND stateBust = '$stateBust' AND stateCivilLiberty = '$stateCivilLiberty' AND stateCivilUnrest = '$stateCivilUnrest' AND stateCivilWar = '$stateCivilWar' AND stateColdWar = '$stateColdWar' AND stateColonisation = '$stateColonisation' AND stateDamaged = '$stateDamaged' AND stateDrought = '$stateDrought' AND stateElection = '$stateElection' AND stateExpansion = '$stateExpansion' AND stateFamine = '$stateFamine' AND stateHistoricEvent = '$stateHistoricEvent' AND stateInfrastructureFailure = '$stateInfrastructureFailure' AND stateInvestment = '$stateInvestment' AND stateLockdown = '$stateLockdown' AND stateNaturalDisaster = '$stateNaturalDisaster' AND stateOutbreak = '$stateOutbreak' AND statePirateAttack = '$statePirateAttack' AND statePublicHoliday = '$statePublicHoliday' AND stateRetreat = '$stateRetreat' AND stateRevolution = '$stateRevolution' AND stateTechnologicalLeap = '$stateTechnologicalLeap' AND stateTerroristAttack = '$stateTerroristAttack' AND stateTradeWar = '$stateTradeWar' AND stateUnderRepairs = '$stateUnderRepairs' AND stateWar = '$stateWar' AND recBlight = '$recBlight' AND recBoom = '$recBoom' AND recBust = '$recBust' AND recCivilLiberty = '$recCivilLiberty' AND recCivilUnrest = '$recCivilUnrest' AND recCivilWar = '$recCivilWar' AND recColdWar = '$recColdWar' AND recColonisation = '$recColonisation' AND recDamaged = '$recDamaged' AND recDrought = '$recDrought' AND recElection = '$recElection' AND recExpansion = '$recExpansion' AND recFamine = '$recFamine' AND recHistoricEvent = '$recHistoricEvent' AND recInfrastructureFailure = '$recInfrastructureFailure' AND recInvestment = '$recInvestment' AND recLockdown = '$recLockdown' AND recNaturalDisaster = '$recNaturalDisaster' AND recOutbreak = '$recOutbreak' AND recPirateAttack = '$recPirateAttack' AND recPublicHoliday = '$recPublicHoliday' AND recRetreat = '$recRetreat' AND recRevolution = '$recRevolution' AND recTechnologicalLeap = '$recTechnologicalLeap' AND recTerroristAttack = '$recTerroristAttack' AND recTradeWar = '$recTradeWar' AND recUnderRepairs = '$recUnderRepairs' AND recWar = '$recWar' AND pendingBlight = '$pendingBlight' AND pendingBoom = '$pendingBoom' AND pendingBust = '$pendingBust' AND pendingCivilLiberty = '$pendingCivilLiberty' AND pendingCivilUnrest = '$pendingCivilUnrest' AND pendingCivilWar = '$pendingCivilWar' AND pendingColdWar = '$pendingColdWar' AND pendingColonisation = '$pendingColonisation' AND pendingDamaged = '$pendingDamaged' AND pendingDrought = '$pendingDrought' AND pendingElection = '$pendingElection' AND pendingExpansion = '$pendingExpansion' AND pendingFamine = '$pendingFamine' AND pendingHistoricEvent = '$pendingHistoricEvent' AND pendingInfrastructureFailure = '$pendingInfrastructureFailure' AND pendingInvestment = '$pendingInvestment' AND pendingLockdown = '$pendingLockdown' AND pendingNaturalDisaster = '$pendingNaturalDisaster' AND pendingOutbreak = '$pendingOutbreak' AND pendingPirateAttack = '$pendingPirateAttack' AND pendingPublicHoliday = '$pendingPublicHoliday' AND pendingRetreat = '$pendingRetreat' AND pendingRevolution = '$pendingRevolution' AND pendingTechnologicalLeap = '$pendingTechnologicalLeap' AND pendingTerroristAttack = '$pendingTerroristAttack' AND pendingTradeWar = '$pendingTradeWar' AND pendingUnderRepairs = '$pendingUnderRepairs' AND pendingWar = '$pendingWar AND timestamp >= '$oldtick' AND timestamp <= '$newtick'";

                  if ($intersectfactioncountresult = mysqli_query($con, $intersectfactioncountquery)) {
                    $intersectfactioncount = mysqli_num_rows($intersectfactioncountresult);
                  }

                  if ($intersectfactioncount >= $highest) {
                    $highest = $intersectfactioncount;
                    $rowarray = $row3;
                  }
                  $i++;
                }
              } else {
                $log .= "Couldn't enumerate intersected data_factions : ".$factionname." (".$factionsystemname.")\n";
              }
            }
            if ($datetime > 0) {
              $rowarray['timestamp'] = $datetime;
            }
            $tempfactionslist[] = $rowarray;

          } else {
            $log .= "Couldn't consolidate intersected data_factions : ".$factionname." (".$factionsystemname.")\n";
          }
        } else {
          $log .= "Couldn't consolidate data_factions: ".$factionname." (".$factionsystemname.")\n";
        }
      }
    }
  } else {
    $log .= "Couldn't fetch faction data\n";
  }
  // ALL FACTION DATA GATHERED, stored in $tempfactionslist

  if($dryrun) {
    echo "<pre>";
    print_r($tempfactionslist);
    echo "</pre>";
  }
  $log .= $factionrecordsunique." records left after processing, ".($factionrecords - $factionrecordsunique)." duplicate records ignored\n";
  $log .= "\n\n";









  // START CONFLICT DATA GATHERING, final results are stored in array: $tempconflictslist
  $tempconflictslist = array();
  $finalconflictslist = array();
  $conflictrecords = 0;
  $conflictrecordsunique = 0;
  $conflictdataquery = "SELECT SystemAddress, StarSystem, conflicttype, conflictstatus, conflictfaction1name, conflictfaction2name FROM data_conflicts WHERE timestamp >= '$oldtick' AND timestamp <= '$newtick'";
  if ($conflictdataresult = mysqli_query($con, $conflictdataquery)){
    $conflictrecords = mysqli_num_rows($conflictdataresult);
    $log .= $conflictrecords." records found with data_conflicts dated between last two ticks\n";

    while($row = mysqli_fetch_array($conflictdataresult , MYSQLI_ASSOC)) {
      $conflictsystemaddress = $row['SystemAddress'];
      $conflictsystemname = addslashes($row['StarSystem']);
      $conflicttype = $row['conflicttype'];
      $conflictstatus = $row['conflictstatus'];
      $conflictfaction1name = addslashes($row['conflictfaction1name']);
      $conflictfaction2name = addslashes($row['conflictfaction2name']);
      $conflictexistsinarray = false;

      if (count($tempconflictslist) > 0) {
        $i = 0;
        while($i < count($tempconflictslist)) {
          if ($tempconflictslist[$i]['SystemAddress'] == $conflictsystemaddress && $tempconflictslist[$i]['StarSystem'] == $conflictsystemname && $tempconflictslist[$i]['conflicttype'] == $conflicttype && $tempconflictslist[$i]['conflictstatus'] == $conflictstatus && $tempconflictslist[$i]['conflictfaction1name'] == $conflictfaction1name && $tempconflictslist[$i]['conflictfaction2name'] == $conflictfaction2name) {
            $log .= "Conflict records (".$conflicttype.": ".$conflictfaction1name." vs ".$conflictfaction2name.") already processed, skipping duplicates\n";
            $conflictexistsinarray = true;
          }
          $i++;
        }
      }


      if (!$conflictexistsinarray) {
        $conflictrecordsunique++;
        $intersectconflictdataquery = "SELECT StarSystem, SystemAddress FROM data_conflicts WHERE timestamp >= '$oldtick' AND timestamp <= '$newtick' AND SystemAddress = '$conflictsystemaddress' AND conflicttype = '$conflicttype' AND conflictfaction1name = '$conflictfaction1name' AND conflictfaction2name = '$conflictfaction2name' INTERSECT SELECT StarSystem, SystemAddress FROM data_conflicts WHERE timestamp >= '$oldtick' AND timestamp <= '$newtick' AND SystemAddress = '$conflictsystemaddress' AND conflicttype = '$conflicttype' AND conflictfaction1name = '$conflictfaction1name' AND conflictfaction2name = '$conflictfaction2name'";
        if ($intersectconflictdataresult = mysqli_query($con, $intersectconflictdataquery)){
          $intersectconflictrecords = mysqli_num_rows($intersectconflictdataresult);
          if ($intersectconflictrecords > 0) {
            if ($intersectconflictrecords > 1) {
              $log .= "Multiple data_conflicts records found for conflict: ".$conflicttype." (".$conflictfaction1name." vs ".$conflictfaction2name.")\n";
            }

            $rowid = 0;
            $i = 0;
            $highest = 0;
            $datetime = 0;
            $rowarray = array();
            while ($i < ($intersectconflictrecords)) {
              $intersectconflictquery = "SELECT * FROM data_conflicts WHERE timestamp >= '$oldtick' AND timestamp <= '$newtick' AND SystemAddress = '$conflictsystemaddress' AND conflicttype = '$conflicttype' AND conflictfaction1name = '$conflictfaction1name' AND conflictfaction2name = '$conflictfaction2name'";
              if ($intersectconflictresult = mysqli_query($con, $intersectconflictquery)){
                while($row3 = mysqli_fetch_array($intersectconflictresult, MYSQLI_ASSOC)) {
                  if (strtotime($row3['timestamp']) > strtotime($datetime)) {
                    $datetime = $row3['timestamp'];
                  }
                  $StarSystem = addslashes($row3['StarSystem']);
                  $SystemAddress = $row3['SystemAddress'];
                  $type = $row3['conflicttype'];
                  $status = $row3['conflictstatus'];
                  $faction1name = $row3['conflictfaction1name'];
                  $faction1stake = $row3['conflictfaction1stake'];
                  $faction1windays = $row3['conflictfaction1windays'];
                  $faction2name = $row3['conflictfaction2name'];
                  $faction2stake = $row3['conflictfaction2stake'];
                  $faction2windays = $row3['conflictfaction2windays'];
                  $intersectconflictcount = 0;

                  $intersectconflictcountquery = "SELECT id FROM data_systems WHERE StarSystem = '$StarSystem' AND SystemAddress = '$SystemAddress' AND conflicttype = '$type' AND conflictstatus = '$status' AND conflictfaction1name = '$faction1name' AND conflictfaction1stake = '$faction1stake' AND conflictfaction1windays = '$faction1windays' AND conflictfaction2name = '$faction2name' AND conflictfaction2stake = '$faction2stake' AND conflictfaction2windays = '$faction2windays' AND timestamp >= '$oldtick' AND timestamp <= '$newtick'";
                  if ($intersectconflictcountresult = mysqli_query($con, $intersectconflictcountquery)) {
                    $intersectconflictcount = mysqli_num_rows($intersectconflictcountresult);
                  }

                  if ($intersectconflictcount >= $highest) {
                    $highest = $intersectconflictcount;
                    $rowarray = $row3;
                  }
                  $i++;
                }
              } else {
                $log .= "Couldn't enumerate intersected data_conflicts : ".$conflicttype." (".$conflictfaction1name." vs ".$conflictfaction2name.")\n";
              }
            }
            if ($datetime > 0) {
              $rowarray['timestamp'] = $datetime;
            }
            $tempconflictslist[] = $rowarray;

          } else {
            $log .= "Couldn't consolidate intersected data_conflicts : ".$conflicttype." (".$conflictfaction1name." vs ".$conflictfaction2name.")\n";
          }
        } else {
          $log .= "Couldn't consolidate data_conflicts: ".$conflicttype." (".$conflictfaction1name." vs ".$conflictfaction2name.")\n";
        }
      }
    }
  } else {
    $log .= "Couldn't fetch conflict data\n";
  }
  // ALL CONFLICT DATA GATHERED, stored in $tempconflictslist

  if($dryrun) {
    echo "<pre>";
    print_r($tempconflictslist);
    echo "</pre>";
  }
  $log .= $conflictrecordsunique." records left after processing, ".($conflictrecords - $conflictrecordsunique)." duplicate records ignored\n";
  $log .= "\n\n";









  // START INFLUENCE DATA GATHERING, final results are stored in array: $tempinfluencelist
  $tempinfluencelist = array();
  $factionsarray = array();
  $factionlistquery = "SELECT faction1name, faction2name FROM data_missionrewards WHERE timestamp > '$oldtick' AND timestamp < '$newtick'";
  if($factionlistresult = mysqli_query($con, $factionlistquery)){
    while($row = mysqli_fetch_array($factionlistresult, MYSQLI_ASSOC)) {
      $factionsarray[] = $row['faction1name'];
      if ($row['faction2name'] != NULL) {
        $factionsarray[] = $row['faction2name'];
      }
    }
  }
  $factionarray = array_unique($factionsarray);


  foreach($factionarray as $factionname) {
    $systemsarray = array();
    $systeminfquery = "SELECT faction1name, faction1address, faction2name, faction2address FROM data_missionrewards WHERE timestamp > '$oldtick' AND timestamp < '$newtick' AND (faction1name = '$factionname' OR faction2name = '$factionname')";
    if($systeminfresult = mysqli_query($con, $systeminfquery)){
      while($row2 = mysqli_fetch_array($systeminfresult, MYSQLI_ASSOC)) {
        if ($row2['faction1name'] == $factionname) {
          $systemsarray[] = $row2['faction1address'];
        }
        if ($row2['faction2name'] != NULL) {
          if ($row2['faction2name'] == $factionname) {
            $systemsarray[] = $row2['faction2address'];
          }
        }
      }
    }
    $systemarray = array_unique($systemsarray);
    $influencetrend = '';
    $influencetimestamp = '';
    foreach($systemarray as $factionaddress) {
      $influenceamount = 0;
      $influencelistquery = "SELECT timestamp, faction1name, faction1address, faction1system, faction1reward, faction1trend, faction2name, faction2address, faction2system, faction2reward, faction2trend FROM data_missionrewards WHERE timestamp > '$oldtick' AND timestamp < '$newtick' AND ((faction1name = '$factionname' AND faction1address = '$factionaddress') OR (faction2name = '$factionname' AND faction2address = '$factionaddress'))";
      if($influencelistresult = mysqli_query($con, $influencelistquery)){
        while($row3 = mysqli_fetch_array($influencelistresult, MYSQLI_ASSOC)) {
          if ($row3['faction1name'] == $factionname && $row3['faction1address'] == $factionaddress) {
            $influencesystem = $row3['faction1system'];
            $influenceamount = $influenceamount + $row3['faction1reward'];
            $influencetrend = $row3['faction1trend'];
            $influencetimestamp = $row3['timestamp'];
          }
          if ($row3['faction2name'] != NULL) {
            if($row3['faction2name'] == $factionname && $row3['faction2address'] == $factionaddress) {
              $influencesystem = $row3['faction2system'];
              $influenceamount = $influenceamount + $row3['faction2reward'];
              $influencetrend = $row3['faction2trend'];
              $influencetimestamp = $row3['timestamp'];
            }
          }
          if ($influencesystem == '') {
            $influencesystem = 'Unknown';
          }
        }
        $tempinfluencelist[] = array(
        "faction" => $factionname,
        "address" => $factionaddress,
        "system" => $influencesystem,
        "amount" => $influenceamount,
        "direction" => $influencetrend,
        "timestamp" => $influencetimestamp
        );

      } else {
        $log .= "Couldn't consolidate missionreward data: ".$factionaddress." (".$factionname.")\n";
      }
    }
  }
  // ALL INFLUENCE DATA GATHERED, stored in $tempinfluencelist

  if($dryrun) {
    echo "<pre>";
    print_r($tempinfluencelist);
    echo "</pre>";
  }
  $log .= "\n\n";







  // START EXPLORATION DATA GATHERING, final results are stored in array: $tempexplorationlist
  $tempexplorationlist = array();
  $stationsarray = array();
  $stationlistquery = "SELECT StationName FROM data_explorationdata WHERE timestamp > '$oldtick' AND timestamp < '$newtick'";
  if($stationlistresult = mysqli_query($con, $stationlistquery)){
    while($row = mysqli_fetch_array($stationlistresult, MYSQLI_ASSOC)) {
      $stationsarray[] = $row['StationName'];
    }
  }
  $stationsarray = array_unique($stationsarray);


  $explorationtimestamp = '';
  foreach($stationsarray as $stationname) {
    $explorationbase = 0;
    $explorationbonus = 0;
    $explorationtotal = 0;
    $explorationsystemcount = 0;
    $explorationbodycount = 0;
    $explorationlistquery = "SELECT timestamp, StarSystem, SystemAddress, base, bonus, total, systemcount, bodycount FROM data_explorationdata WHERE timestamp > '$oldtick' AND timestamp < '$newtick' AND StationName = '$stationname'";
    if($explorationlistresult = mysqli_query($con, $explorationlistquery)){
      while($row3 = mysqli_fetch_array($explorationlistresult, MYSQLI_ASSOC)) {
        $explorationsystem = addslashes($row3['StarSystem']);
        $explorationaddress = $row3['SystemAddress'];
        $explorationbase = $explorationbase + $row3['base'];
        $explorationbonus = $explorationbonus + $row3['bonus'];
        $explorationtotal = $explorationtotal + $row3['total'];
        $explorationsystemcount = $explorationsystemcount + $row3['systemcount'];
        $explorationbodycount = $explorationbodycount + $row3['bodycount'];
        $explorationlastupdate = $row3['timestamp'];
      }
      $tempexplorationlist[] = array(
      "starsystem" => $explorationsystem,
      "systemaddress" => $explorationaddress,
      "stationname" => $stationname,
      "base" => $explorationbase,
      "bonus" => $explorationbonus,
      "total" => $explorationtotal,
      "systems" => $explorationsystemcount,
      "bodies" => $explorationbodycount,
      "timestamp" => $explorationlastupdate
      );
    } else {
      $log .= "Couldn't consolidate exploration data: ".$stationname." (Systems: ".$explorationsystemcount." / Bodies: ".$explorationbodycount.")\n";
    }
  }
  // ALL EXPLORATION DATA GATHERED, stored in $tempexplorationlist

  if($dryrun) {
    echo "<pre>";
    print_r($tempexplorationlist);
    echo "</pre>";
  }
  $log .= "\n\n";










  // START CARGO DELIVERY DATA GATHERING, final results are stored in array: $tempdeliverylist
  $tempdeliverylist = array();
  $stationsarray = array();
  $commoditiesarray = array();
  $stationlistquery = "SELECT MarketID FROM data_cargodeliveries WHERE timestamp > '$oldtick' AND timestamp < '$newtick'";
  if($stationlistresult = mysqli_query($con, $stationlistquery)){
    while($row = mysqli_fetch_array($stationlistresult, MYSQLI_ASSOC)) {
      $stationsarray[] = $row['MarketID'];
    }
  }
  $stationsarray = array_unique($stationsarray);


  $deliverytimestamp = '';
  foreach($stationsarray as $MarketID) {
    
    $commoditylistquery = "SELECT commodity FROM data_cargodeliveries WHERE timestamp > '$oldtick' AND timestamp < '$newtick' AND MarketID = '$MarketID'";
    if($commoditylistresult = mysqli_query($con, $commoditylistquery)){
      while($row3 = mysqli_fetch_array($commoditylistresult, MYSQLI_ASSOC)) {
        $commoditiesarray[] = $row3['commodity'];
      }
    }
    $commoditiesarray = array_unique($commoditiesarray);
    
    foreach($commoditiesarray as $commodity) {
      $amount = 0;
      $value = 0;
      $profit = 0;

      $deliverylistquery = "SELECT timestamp, StarSystem, SystemAddress, StationName, MarketID, amount, value, profit FROM data_cargodeliveries WHERE timestamp > '$oldtick' AND timestamp < '$newtick' AND MarketID = '$MarketID' AND commodity = '$commodity'";
      if($deliverylistresult = mysqli_query($con, $deliverylistquery)){
        while($row4 = mysqli_fetch_array($deliverylistresult, MYSQLI_ASSOC)) {
          $StarSystem = addslashes($row4['StarSystem']);
          $StationName = addslashes($row4['StationName']);
          $SystemAddress = $row4['SystemAddress'];
          $amount = $amount + $row4['amount'];
          $value = $value + $row4['value'];
          $profit = $profit + $row4['profit'];
          $lastupdate = $row4['timestamp'];
        }
        $tempdeliverylist[] = array(
          "StarSystem" => $StarSystem,
          "SystemAddress" => $SystemAddress,
          "StationName" => $StationName,
          "MarketID" => $MarketID,
          "commodity" => $commodity,
          "amount" => $amount,
          "value" => $value,
          "profit" => $profit,
          "timestamp" => $lastupdate
        );
      } else {
        $log .= "Couldn't consolidate cargo delivery data: ".$commodity." (".$MarketID.")\n";
      }
    }
  }
  // ALL CARGO DELIVERY DATA GATHERED, stored in $tempdeliverylist

  if($dryrun) {
    echo "<pre>";
    print_r($tempdeliverylist);
    echo "</pre>";
  }
  $log .= "\n\n";










  // START CARGO PURCHASE DATA GATHERING, final results are stored in array: $temppurchaselist
  $temppurchaselist = array();
  $stationsarray = array();
  $commoditiesarray = array();
  $stationlistquery = "SELECT MarketID FROM data_cargopurchases WHERE timestamp > '$oldtick' AND timestamp < '$newtick'";
  if($stationlistresult = mysqli_query($con, $stationlistquery)){
    while($row = mysqli_fetch_array($stationlistresult, MYSQLI_ASSOC)) {
      $stationsarray[] = $row['MarketID'];
    }
  }
  $stationsarray = array_unique($stationsarray);


  $deliverytimestamp = '';
  foreach($stationsarray as $MarketID) {
    
    $commoditylistquery = "SELECT commodity FROM data_cargopurchases WHERE timestamp > '$oldtick' AND timestamp < '$newtick' AND MarketID = '$MarketID'";
    if($commoditylistresult = mysqli_query($con, $commoditylistquery)){
      while($row3 = mysqli_fetch_array($commoditylistresult, MYSQLI_ASSOC)) {
        $commoditiesarray[] = $row3['commodity'];
      }
    }
    $commoditiesarray = array_unique($commoditiesarray);
    
    foreach($commoditiesarray as $commodity) {
      $amount = 0;
      $value = 0;
      $totalcost = 0;

      $purchaselistquery = "SELECT timestamp, StarSystem, SystemAddress, StationName, MarketID, amount, value, total FROM data_cargopurchases WHERE timestamp > '$oldtick' AND timestamp < '$newtick' AND MarketID = '$MarketID' AND commodity = '$commodity'";
      if($purchaselistresult = mysqli_query($con, $purchaselistquery)){
        while($row4 = mysqli_fetch_array($purchaselistresult, MYSQLI_ASSOC)) {
          $StarSystem = addslashes($row4['StarSystem']);
          $StationName = addslashes($row4['StationName']);
          $SystemAddress = $row4['SystemAddress'];
          $amount = $amount + $row4['amount'];
          $value = $value + $row4['value'];
          $totalcost = $totalcost + $row4['total'];
          $lastupdate = $row4['timestamp'];
        }
        $temppurchaselist[] = array(
          "StarSystem" => $StarSystem,
          "SystemAddress" => $SystemAddress,
          "StationName" => $StationName,
          "MarketID" => $MarketID,
          "commodity" => $commodity,
          "amount" => $amount,
          "value" => $value,
          "totalcost" => $totalcost,
          "timestamp" => $lastupdate
        );
      } else {
        $log .= "Couldn't consolidate cargo purchase data: ".$commodity." (".$MarketID.")\n";
      }
    }
  }
  // ALL CARGO PURCHASE DATA GATHERED, stored in $temppurchaselist

  if($dryrun) {
    echo "<pre>";
    print_r($temppurchaselist);
    echo "</pre>";
  }
  $log .= "\n\n";











  if(!$dryrun) {
    foreach ($tempsystemslist as $element) {
      $datetime = $element['timestamp'];
      $StarSystem = addslashes($element['StarSystem']);
      $SystemAddress = $element['SystemAddress'];
      $Population = $element['Population'];
      $SystemAllegiance = $element['SystemAllegiance'];
      $SystemGovernment = $element['SystemGovernment'];
      $SystemSecurity = $element['SystemSecurity'];
      $SystemEconomy = $element['SystemEconomy'];
      $SystemSecondEconomy = $element['SystemSecondEconomy'];
      $ControllingFaction = $element['ControllingFaction'];
      $FactionState = $element['FactionState'];

      $insertsystemsnapshot = "INSERT INTO snapshot_systems (tickid, timestamp, StarSystem, SystemAddress, Population, SystemAllegiance, SystemGovernment, SystemSecurity, SystemEconomy, SystemSecondEconomy, ControllingFaction, FactionState) VALUES ('$oldtickid', '$datetime', '$StarSystem', '$SystemAddress', '$Population', '$SystemAllegiance', '$SystemGovernment', '$SystemSecurity', '$SystemEconomy', '$SystemSecondEconomy', '$ControllingFaction', '$FactionState')";
      if (!mysqli_query($con, $insertsystemsnapshot)) {
        $log .= "SQL error, couldnt add system ".$StarSystem." (".$SystemAddress.") snapshot to database.\n".mysqli_error($con);
      } else {
        $log .= "System ".$StarSystem ." (".$SystemAddress.") snapshot added to database.\n";
      }
    }
    $log .= "\n";

    foreach ($tempfactionslist as $element2) {
      $datetime = $element2['timestamp'];
      $StarSystem = addslashes($element2['StarSystem']);
      $SystemAddress = $element2['SystemAddress'];
      $Name = addslashes($element2['Name']);
      $Government = $element2['Government'];
      $Influence = $element2['Influence'];
      $Allegiance = $element2['Allegiance'];
      $Happiness = $element2['Happiness'];
      $stateBlight = $element2['stateBlight'];
      $stateBoom = $element2['stateBoom'];
      $stateBust = $element2['stateBust'];
      $stateCivilLiberty = $element2['stateCivilLiberty'];
      $stateCivilUnrest = $element2['stateCivilUnrest'];
      $stateCivilWar = $element2['stateCivilWar'];
      $stateColdWar = $element2['stateColdWar'];
      $stateColonisation = $element2['stateColonisation'];
      $stateDamaged = $element2['stateDamaged'];
      $stateDrought = $element2['stateDrought'];
      $stateElection = $element2['stateElection'];
      $stateExpansion = $element2['stateExpansion'];
      $stateFamine = $element2['stateFamine'];
      $stateHistoricEvent = $element2['stateHistoricEvent'];
      $stateInfrastructureFailure = $element2['stateInfrastructureFailure'];
      $stateInvestment = $element2['stateInvestment'];
      $stateLockdown = $element2['stateLockdown'];
      $stateNaturalDisaster = $element2['stateNaturalDisaster'];
      $stateOutbreak = $element2['stateOutbreak'];
      $statePirateAttack = $element2['statePirateAttack'];
      $statePublicHoliday = $element2['statePublicHoliday'];
      $stateRetreat = $element2['stateRetreat'];
      $stateRevolution = $element2['stateRevolution'];
      $stateTechnologicalLeap = $element2['stateTechnologicalLeap'];
      $stateTerroristAttack = $element2['stateTerroristAttack'];
      $stateTradeWar = $element2['stateTradeWar'];
      $stateUnderRepairs = $element2['stateUnderRepairs'];
      $stateWar = $element2['stateWar'];
      $recBlight = $element2['recBlight'];
      $recBlightTrend = $element2['recBlightTrend'];
      if (empty($recBlightTrend)) {
        $recBlightTrend = 'NULL';
      }
      $recBoom = $element2['recBoom'];
      $recBoomTrend = $element2['recBoomTrend'];
      if (empty($recBoomTrend)) {
        $recBoomTrend = 'NULL';
      }
      $recBust = $element2['recBust'];
      $recBustTrend = $element2['recBustTrend'];
      if (empty($recBustTrend)) {
        $recBustTrend = 'NULL';
      }
      $recCivilLiberty = $element2['recCivilLiberty'];
      $recCivilLibertyTrend = $element2['recCivilLibertyTrend'];
      if (empty($recCivilLibertyTrend)) {
        $recCivilLibertyTrend = 'NULL';
      }
      $recCivilUnrest = $element2['recCivilUnrest'];
      $recCivilUnrestTrend = $element2['recCivilUnrestTrend'];
      if (empty($recCivilUnrestTrend)) {
        $recCivilUnrestTrend = 'NULL';
      }
      $recCivilWar = $element2['recCivilWar'];
      $recCivilWarTrend = $element2['recCivilWarTrend'];
      if (empty($recCivilWarTrend)) {
        $recCivilWarTrend = 'NULL';
      }
      $recColdWar = $element2['recColdWar'];
      $recColdWarTrend = $element2['recColdWarTrend'];
      if (empty($recColdWarTrend)) {
        $recColdWarTrend = 'NULL';
      }
      $recColonisation = $element2['recColonisation'];
      $recColonisationTrend = $element2['recColonisationTrend'];
      if (empty($recColonisationTrend)) {
        $recColonisationTrend = 'NULL';
      }
      $recDamaged = $element2['recDamaged'];
      $recDamagedTrend = $element2['recDamagedTrend'];
      if (empty($recDamagedTrend)) {
        $recDamagedTrend = 'NULL';
      }
      $recDrought = $element2['recDrought'];
      $recDroughtTrend = $element2['recDroughtTrend'];
      if (empty($recDroughtTrend)) {
        $recDroughtTrend = 'NULL';
      }
      $recElection = $element2['recElection'];
      $recElectionTrend = $element2['recElectionTrend'];
      if (empty($recElectionTrend)) {
        $recElectionTrend = 'NULL';
      }
      $recExpansion = $element2['recExpansion'];
      $recExpansionTrend = $element2['recExpansionTrend'];
      if (empty($recExpansionTrend)) {
        $recExpansionTrend = 'NULL';
      }
      $recFamine = $element2['recFamine'];
      $recFamineTrend = $element2['recFamineTrend'];
      if (empty($recFamineTrend)) {
        $recFamineTrend = 'NULL';
      }
      $recHistoricEvent = $element2['recHistoricEvent'];
      $recHistoricEventTrend = $element2['recHistoricEventTrend'];
      if (empty($recHistoricEventTrend)) {
        $recHistoricEventTrend = 'NULL';
      }
      $recInfrastructureFailure = $element2['recInfrastructureFailure'];
      $recInfrastructureFailureTrend = $element2['recInfrastructureFailureTrend'];
      if (empty($recInfrastructureFailureTrend)) {
        $recInfrastructureFailureTrend = 'NULL';
      }
      $recInvestment = $element2['recInvestment'];
      $recInvestmentTrend = $element2['recInvestmentTrend'];
      if (empty($recInvestmentTrend)) {
        $recInvestmentTrend = 'NULL';
      }
      $recLockdown = $element2['recLockdown'];
      $recLockdownTrend = $element2['recLockdownTrend'];
      if (empty($recLockdownTrend)) {
        $recLockdownTrend = 'NULL';
      }
      $recNaturalDisaster = $element2['recNaturalDisaster'];
      $recNaturalDisasterTrend = $element2['recNaturalDisasterTrend'];
      if (empty($recNaturalDisasterTrend)) {
        $recNaturalDisasterTrend = 'NULL';
      }
      $recOutbreak = $element2['recOutbreak'];
      $recOutbreakTrend = $element2['recOutbreakTrend'];
      if (empty($recOutbreakTrend)) {
        $recOutbreakTrend = 'NULL';
      }
      $recPirateAttack = $element2['recPirateAttack'];
      $recPirateAttackTrend = $element2['recPirateAttackTrend'];
      if (empty($recPirateAttackTrend)) {
        $recPirateAttackTrend = 'NULL';
      }
      $recPublicHoliday = $element2['recPublicHoliday'];
      $recPublicHolidayTrend = $element2['recPublicHolidayTrend'];
      if (empty($recPublicHolidayTrend)) {
        $recPublicHolidayTrend = 'NULL';
      }
      $recRetreat = $element2['recRetreat'];
      $recRetreatTrend = $element2['recRetreatTrend'];
      if (empty($recRetreatTrend)) {
        $recRetreatTrend = 'NULL';
      }
      $recRevolution = $element2['recRevolution'];
      $recRevolutionTrend = $element2['recRevolutionTrend'];
      if (empty($recRevolutionTrend)) {
        $recRevolutionTrend = 'NULL';
      }
      $recTechnologicalLeap = $element2['recTechnologicalLeap'];
      $recTechnologicalLeapTrend = $element2['recTechnologicalLeapTrend'];
      if (empty($recTechnologicalLeapTrend)) {
        $recTechnologicalLeapTrend = 'NULL';
      }
      $recTerroristAttack = $element2['recTerroristAttack'];
      $recTerroristAttackTrend = $element2['recTerroristAttackTrend'];
      if (empty($recTerroristAttackTrend)) {
        $recTerroristAttackTrend = 'NULL';
      }
      $recTradeWar = $element2['recTradeWar'];
      $recTradeWarTrend = $element2['recTradeWarTrend'];
      if (empty($recTradeWarTrend)) {
        $recTradeWarTrend = 'NULL';
      }
      $recUnderRepairs = $element2['recUnderRepairs'];
      $recUnderRepairsTrend = $element2['recUnderRepairsTrend'];
      if (empty($recUnderRepairsTrend)) {
        $recUnderRepairsTrend = 'NULL';
      }
      $recWar = $element2['recWar'];
      $recWarTrend = $element2['recWarTrend'];
      if (empty($recWarTrend)) {
        $recWarTrend = 'NULL';
      }
      $pendingBlight = $element2['pendingBlight'];
      $pendingBlightTrend = $element2['pendingBlightTrend'];
      if (empty($pendingBlightTrend)) {
        $pendingBlightTrend = 'NULL';
      }
      $pendingBoom = $element2['pendingBoom'];
      $pendingBoomTrend = $element2['pendingBoomTrend'];
      if (empty($pendingBoomTrend)) {
        $pendingBoomTrend = 'NULL';
      }
      $pendingBust = $element2['pendingBust'];
      $pendingBustTrend = $element2['pendingBustTrend'];
      if (empty($pendingBustTrend)) {
        $pendingBustTrend = 'NULL';
      }
      $pendingCivilLiberty = $element2['pendingCivilLiberty'];
      $pendingCivilLibertyTrend = $element2['pendingCivilLibertyTrend'];
      if (empty($pendingCivilLibertyTrend)) {
        $pendingCivilLibertyTrend = 'NULL';
      }
      $pendingCivilUnrest = $element2['pendingCivilUnrest'];
      $pendingCivilUnrestTrend = $element2['pendingCivilUnrestTrend'];
      if (empty($pendingCivilUnrestTrend)) {
        $pendingCivilUnrestTrend = 'NULL';
      }
      $pendingCivilWar = $element2['pendingCivilWar'];
      $pendingCivilWarTrend = $element2['pendingCivilWarTrend'];
      if (empty($pendingCivilWarTrend)) {
        $pendingCivilWarTrend = 'NULL';
      }
      $pendingColdWar = $element2['pendingColdWar'];
      $pendingColdWarTrend = $element2['pendingColdWarTrend'];
      if (empty($pendingColdWarTrend)) {
        $pendingColdWarTrend = 'NULL';
      }
      $pendingColonisation = $element2['pendingColonisation'];
      $pendingColonisationTrend = $element2['pendingColonisationTrend'];
      if (empty($pendingColonisationTrend)) {
        $pendingColonisationTrend = 'NULL';
      }
      $pendingDamaged = $element2['pendingDamaged'];
      $pendingDamagedTrend = $element2['pendingDamagedTrend'];
      if (empty($pendingDamagedTrend)) {
        $pendingDamagedTrend = 'NULL';
      }
      $pendingDrought = $element2['pendingDrought'];
      $pendingDroughtTrend = $element2['pendingDroughtTrend'];
      if (empty($pendingDroughtTrend)) {
        $pendingDroughtTrend = 'NULL';
      }
      $pendingElection = $element2['pendingElection'];
      $pendingElectionTrend = $element2['pendingElectionTrend'];
      if (empty($pendingElectionTrend)) {
        $pendingElectionTrend = 'NULL';
      }
      $pendingExpansion = $element2['pendingExpansion'];
      $pendingExpansionTrend = $element2['pendingExpansionTrend'];
      if (empty($pendingExpansionTrend)) {
        $pendingExpansionTrend = 'NULL';
      }
      $pendingFamine = $element2['pendingFamine'];
      $pendingFamineTrend = $element2['pendingFamineTrend'];
      if (empty($pendingFamineTrend)) {
        $pendingFamineTrend = 'NULL';
      }
      $pendingHistoricEvent = $element2['pendingHistoricEvent'];
      $pendingHistoricEventTrend = $element2['pendingHistoricEventTrend'];
      if (empty($pendingHistoricEventTrend)) {
        $pendingHistoricEventTrend = 'NULL';
      }
      $pendingInfrastructureFailure = $element2['pendingInfrastructureFailure'];
      $pendingInfrastructureFailureTrend = $element2['pendingInfrastructureFailureTrend'];
      if (empty($pendingInfrastructureFailureTrend)) {
        $pendingInfrastructureFailureTrend = 'NULL';
      }
      $pendingInvestment = $element2['pendingInvestment'];
      $pendingInvestmentTrend = $element2['pendingInvestmentTrend'];
      if (empty($pendingInvestmentTrend)) {
        $pendingInvestmentTrend = 'NULL';
      }
      $pendingLockdown = $element2['pendingLockdown'];
      $pendingLockdownTrend = $element2['pendingLockdownTrend'];
      if (empty($pendingLockdownTrend)) {
        $pendingLockdownTrend = 'NULL';
      }
      $pendingNaturalDisaster = $element2['pendingNaturalDisaster'];
      $pendingNaturalDisasterTrend = $element2['pendingNaturalDisasterTrend'];
      if (empty($pendingNaturalDisasterTrend)) {
        $pendingNaturalDisasterTrend = 'NULL';
      }
      $pendingOutbreak = $element2['pendingOutbreak'];
      $pendingOutbreakTrend = $element2['pendingOutbreakTrend'];
      if (empty($pendingOutbreakTrend)) {
        $pendingOutbreakTrend = 'NULL';
      }
      $pendingPirateAttack = $element2['pendingPirateAttack'];
      $pendingPirateAttackTrend = $element2['pendingPirateAttackTrend'];
      if (empty($pendingPirateAttackTrend)) {
        $pendingPirateAttackTrend = 'NULL';
      }
      $pendingPublicHoliday = $element2['pendingPublicHoliday'];
      $pendingPublicHolidayTrend = $element2['pendingPublicHolidayTrend'];
      if (empty($pendingPublicHolidayTrend)) {
        $pendingPublicHolidayTrend = 'NULL';
      }
      $pendingRetreat = $element2['pendingRetreat'];
      $pendingRetreatTrend = $element2['pendingRetreatTrend'];
      if (empty($pendingRetreatTrend)) {
        $pendingRetreatTrend = 'NULL';
      }
      $pendingRevolution = $element2['pendingRevolution'];
      $pendingRevolutionTrend = $element2['pendingRevolutionTrend'];
      if (empty($pendingRevolutionTrend)) {
        $pendingRevolutionTrend = 'NULL';
      }
      $pendingTechnologicalLeap = $element2['pendingTechnologicalLeap'];
      $pendingTechnologicalLeapTrend = $element2['pendingTechnologicalLeapTrend'];
      if (empty($pendingTechnologicalLeapTrend)) {
        $pendingTechnologicalLeapTrend = 'NULL';
      }
      $pendingTerroristAttack = $element2['pendingTerroristAttack'];
      $pendingTerroristAttackTrend = $element2['pendingTerroristAttackTrend'];
      if (empty($pendingTerroristAttackTrend)) {
        $pendingTerroristAttackTrend = 'NULL';
      }
      $pendingTradeWar = $element2['pendingTradeWar'];
      $pendingTradeWarTrend = $element2['pendingTradeWarTrend'];
      if (empty($pendingTradeWarTrend)) {
        $pendingTradeWarTrend = 'NULL';
      }
      $pendingUnderRepairs = $element2['pendingUnderRepairs'];
      $pendingUnderRepairsTrend = $element2['pendingUnderRepairsTrend'];
      if (empty($pendingUnderRepairsTrend)) {
        $pendingUnderRepairsTrend = 'NULL';
      }
      $pendingWar = $element2['pendingWar'];
      $pendingWarTrend = $element2['pendingWarTrend'];
      if (empty($pendingWarTrend)) {
        $pendingWarTrend = 'NULL';
      }


      $insertfactionsnapshot = "INSERT INTO snapshot_factions (tickid, timestamp, Name, StarSystem, SystemAddress, Government, Influence, Allegiance, Happiness, stateBlight, stateBoom, stateBust, stateCivilLiberty, stateCivilUnrest, stateCivilWar, stateColdWar, stateColonisation, stateDamaged, stateDrought, stateElection, stateExpansion, stateFamine, stateHistoricEvent, stateInfrastructureFailure, stateInvestment, stateLockdown, stateNaturalDisaster, stateOutbreak, statePirateAttack, statePublicHoliday, stateRetreat, stateRevolution, stateTechnologicalLeap, stateTerroristAttack, stateTradeWar,stateUnderRepairs, stateWar, recBlight, recBlightTrend, recBoom, recBoomTrend, recBust, recBustTrend, recCivilLiberty, recCivilLibertyTrend, recCivilUnrest, recCivilUnrestTrend, recCivilWar, recCivilWarTrend, recColdWar, recColdWarTrend, recColonisation, recColonisationTrend, recDamaged, recDamagedTrend, recDrought, recDroughtTrend, recElection, recElectionTrend, recExpansion, recExpansionTrend, recFamine, recFamineTrend, recHistoricEvent, recHistoricEventTrend, recInfrastructureFailure, recInfrastructureFailureTrend, recInvestment, recInvestmentTrend, recLockdown, recLockdownTrend, recNaturalDisaster, recNaturalDisasterTrend, recOutbreak, recOutbreakTrend, recPirateAttack, recPirateAttackTrend, recPublicHoliday, recPublicHolidayTrend, recRetreat, recRetreatTrend, recRevolution, recRevolutionTrend, recTechnologicalLeap, recTechnologicalLeapTrend, recTerroristAttack, recTerroristAttackTrend, recTradeWar, recTradeWarTrend, recUnderRepairs, recUnderRepairsTrend, recWar, recWarTrend, pendingBlight, pendingBlightTrend, pendingBoom, pendingBoomTrend, pendingBust, pendingBustTrend, pendingCivilLiberty, pendingCivilLibertyTrend, pendingCivilUnrest, pendingCivilUnrestTrend, pendingCivilWar, pendingCivilWarTrend, pendingColdWar, pendingColdWarTrend, pendingColonisation, pendingColonisationTrend, pendingDamaged, pendingDamagedTrend, pendingDrought, pendingDroughtTrend, pendingElection, pendingElectionTrend, pendingExpansion, pendingExpansionTrend, pendingFamine, pendingFamineTrend, pendingHistoricEvent, pendingHistoricEventTrend, pendingInfrastructureFailure, pendingInfrastructureFailureTrend, pendingInvestment, pendingInvestmentTrend, pendingLockdown, pendingLockdownTrend, pendingNaturalDisaster, pendingNaturalDisasterTrend, pendingOutbreak, pendingOutbreakTrend, pendingPirateAttack, pendingPirateAttackTrend, pendingPublicHoliday, pendingPublicHolidayTrend, pendingRetreat, pendingRetreatTrend, pendingRevolution, pendingRevolutionTrend, pendingTechnologicalLeap, pendingTechnologicalLeapTrend, pendingTerroristAttack, pendingTerroristAttackTrend, pendingTradeWar, pendingTradeWarTrend, pendingUnderRepairs, pendingUnderRepairsTrend, pendingWar, pendingWarTrend) VALUES ('$oldtickid', '$datetime', '$Name', '$StarSystem', '$SystemAddress', '$Government', '$Influence', '$Allegiance', '$Happiness', '$stateBlight', '$stateBoom', '$stateBust', '$stateCivilLiberty', '$stateCivilUnrest', '$stateCivilWar', '$stateColdWar', '$stateColonisation', '$stateDamaged', '$stateDrought', '$stateElection', '$stateExpansion', '$stateFamine', '$stateHistoricEvent', '$stateInfrastructureFailure', '$stateInvestment', '$stateLockdown', '$stateNaturalDisaster', '$stateOutbreak', '$statePirateAttack', '$statePublicHoliday', '$stateRetreat', '$stateRevolution', '$stateTechnologicalLeap', '$stateTerroristAttack', '$stateTradeWar', '$stateUnderRepairs', '$stateWar', '$recBlight', $recBlightTrend, '$recBoom', $recBoomTrend, '$recBust', $recBustTrend, '$recCivilLiberty', $recCivilLibertyTrend, '$recCivilUnrest', $recCivilUnrestTrend, '$recCivilWar', $recCivilWarTrend, '$recColdWar', $recColdWarTrend, '$recColonisation', $recColonisationTrend, '$recDamaged', $recDamagedTrend, '$recDrought', $recDroughtTrend, '$recElection', $recElectionTrend, '$recExpansion', $recExpansionTrend, '$recFamine', $recFamineTrend, '$recHistoricEvent', $recHistoricEventTrend, '$recInfrastructureFailure', $recInfrastructureFailureTrend, '$recInvestment', $recInvestmentTrend, '$recLockdown', $recLockdownTrend, '$recNaturalDisaster', $recNaturalDisasterTrend, '$recOutbreak', $recOutbreakTrend, '$recPirateAttack', $recPirateAttackTrend, '$recPublicHoliday', $recPublicHolidayTrend, '$recRetreat', $recRetreatTrend, '$recRevolution', $recRevolutionTrend, '$recTechnologicalLeap', $recTechnologicalLeapTrend, '$recTerroristAttack', $recTerroristAttackTrend, '$recTradeWar', $recTradeWarTrend, '$recUnderRepairs', $recUnderRepairsTrend, '$recWar', $recWarTrend, '$pendingBlight', $pendingBlightTrend, '$pendingBoom', $pendingBoomTrend, '$pendingBust', $pendingBustTrend, '$pendingCivilLiberty', $pendingCivilLibertyTrend, '$pendingCivilUnrest', $pendingCivilUnrestTrend, '$pendingCivilWar', $pendingCivilWarTrend, '$pendingColdWar', $pendingColdWarTrend, '$pendingColonisation', $pendingColonisationTrend, '$pendingDamaged', $pendingDamagedTrend, '$pendingDrought', $pendingDroughtTrend, '$pendingElection', $pendingElectionTrend, '$pendingExpansion', $pendingExpansionTrend, '$pendingFamine', $pendingFamineTrend, '$pendingHistoricEvent', $pendingHistoricEventTrend, '$pendingInfrastructureFailure', $pendingInfrastructureFailureTrend, '$pendingInvestment', $pendingInvestmentTrend, '$pendingLockdown', $pendingLockdownTrend, '$pendingNaturalDisaster', $pendingNaturalDisasterTrend, '$pendingOutbreak', $pendingOutbreakTrend, '$pendingPirateAttack', $pendingPirateAttackTrend, '$pendingPublicHoliday', $pendingPublicHolidayTrend, '$pendingRetreat', $pendingRetreatTrend, '$pendingRevolution', $pendingRevolutionTrend, '$pendingTechnologicalLeap', $pendingTechnologicalLeapTrend, '$pendingTerroristAttack', $pendingTerroristAttackTrend, '$pendingTradeWar', $pendingTradeWarTrend, '$pendingUnderRepairs', $pendingUnderRepairsTrend, '$pendingWar', $pendingWarTrend)";
      if (!mysqli_query($con, $insertfactionsnapshot)) {
        $log .= "\n\n\n".$insertfactionsnapshot."\n\n\n";
        $log .= "SQL error, couldnt add faction ".$Name." (".$StarSystem." / ".$SystemAddress.") snapshot to database.\n".mysqli_error($con);
      } else {
        $log .= "Faction ".$Name." (".$StarSystem." / ".$SystemAddress.") snapshot added to database.\n";
      }
    }
    $log .= "\n";

    foreach ($tempconflictslist as $element3) {
      $datetime = $element3['timestamp'];
      $StarSystem = addslashes($element3['StarSystem']);
      $SystemAddress = $element3['SystemAddress'];
      $type = $element3['conflicttype'];
      $status = $element3['conflictstatus'];
      $faction1name = addslashes($element3['conflictfaction1name']);
      $faction1stake = addslashes($element3['conflictfaction1stake']);
      $faction1windays = $element3['conflictfaction1windays'];
      $faction2name = addslashes($element3['conflictfaction2name']);
      $faction2stake = addslashes($element3['conflictfaction2stake']);
      $faction2windays = $element3['conflictfaction2windays'];


      $insertconflictsnapshot = "INSERT INTO snapshot_conflicts (tickid, timestamp, StarSystem, SystemAddress, conflicttype, conflictstatus, conflictfaction1name, conflictfaction1stake, conflictfaction1windays, conflictfaction2name, conflictfaction2stake, conflictfaction2windays) VALUES ('$oldtickid', '$datetime', '$StarSystem', '$SystemAddress', '$type', '$status', '$faction1name', '$faction1stake', '$faction1windays', '$faction2name', '$faction2stake', '$faction2windays')";
      if (!mysqli_query($con, $insertconflictsnapshot)) {
        $log .= "SQL error, couldnt add conflict ".$type." (".$faction1name." vs ".$faction2name.") snapshot to database.\n".mysqli_error($con);
      } else {
        $log .= "Conflict ".$type." (".$faction1name." vs ".$faction2name.") snapshot added to database.\n";
      }
    }
    $log .= "\n";

    foreach ($tempinfluencelist as $element4) {
      $influencefaction = addslashes($element4['faction']);
      $influenceaddress = $element4['address'];
      $influencesystem = addslashes($element4['system']);
      $influenceamount = $element4['amount'];
      $influencedirection = $element4['direction'];
      $influencetimestamp = $element4['timestamp'];


      $insertinfluencesnapshot = "INSERT INTO snapshot_missionrewards (tickid, timestamp, StarSystem, SystemAddress, rewardfaction, rewardtotal, rewardtrend) VALUES ('$oldtickid', '$influencetimestamp', '$influencesystem', '$influenceaddress', '$influencefaction', '$influenceamount', '$influencedirection')";

      if (!mysqli_query($con, $insertinfluencesnapshot)) {
        $log .= "SQL error, couldnt add missionrewards ".$influencefaction." (".$influencesystem." / ".$influenceaddress.") snapshot to database.\n".mysqli_error($con);
      } else {
        $log .= "Missionrewards ".$influencefaction." (".$influencesystem." / ".$influenceaddress.") snapshot added to database.\n";
      }
    }
    $log .= "\n";

    foreach ($tempexplorationlist as $element5) {
      $explorationsystem = addslashes($element5['starsystem']);
      $explorationaddress = $element5['systemaddress'];
      $explorationstationname = addslashes($element5['stationname']);
      $explorationbase = $element5['base'];
      $explorationbonus = $element5['bonus'];
      $explorationtotal = $element5['total'];
      $explorationsystemcount = $element5['systems'];
      $explorationbodycount = $element5['bodies'];
      $influencetimestamp = $element5['timestamp'];


      $insertexplorationsnapshot = "INSERT INTO snapshot_explorationdata (tickid, timestamp, StarSystem, SystemAddress, StationName, base, bonus, total, systemcount, bodycount) VALUES ('$oldtickid', '$influencetimestamp', '$explorationsystem', '$explorationaddress', '$explorationstationname', '$explorationbase', '$explorationbonus', '$explorationtotal', '$explorationsystemcount', '$explorationbodycount')";

      if (!mysqli_query($con, $insertexplorationsnapshot)) {
        $log .= "SQL error, couldnt add exploration data ".$explorationstationname." ".$influencefaction." (Systems: ".$explorationsystemcount." / Bodies: ".$explorationbodycount.") snapshot to database.\n".mysqli_error($con);
      } else {
        $log .= "Exploration data ".$explorationstationname." ".$influencefaction." (Systems: ".$explorationsystemcount." / Bodies: ".$explorationbodycount.") snapshot added to database.\n";
      }
    }
    $log .= "\n";

    foreach ($tempdeliverylist as $element6) {
      $deliverysystem = addslashes($element6['StarSystem']);
      $deliveryaddress = $element6['SystemAddress'];
      $deliverystationname = addslashes($element6['StationName']);
      $deliverymarketid = $element6['MarketID'];
      $deliverycommodity = $element6['commodity'];
      $deliveryamount = $element6['amount'];
      $deliveryvalue = $element6['value'];
      $deliveryprofit = $element6['profit'];
      $deliverytimestamp = $element6['timestamp'];



      $insertdeliverysnapshot = "INSERT INTO snapshot_cargodeliveries (tickid, timestamp, StarSystem, SystemAddress, StationName, MarketID, commodity, amount, value, profit) VALUES ('$oldtickid', '$deliverytimestamp', '$deliverysystem', '$deliveryaddress', '$deliverystationname', '$deliverymarketid', '$deliverycommodity', '$deliveryamount', '$deliveryvalue', '$deliveryprofit')";
      if (!mysqli_query($con, $insertdeliverysnapshot)) {
        $log .= "SQL error, couldnt add cargo delivery data ".$deliverycommodity." at ".$deliverystationname." (System: ".$deliverysystem.") snapshot to database.\n".mysqli_error($con);
      } else {
        $log .= "Cargo delivery data ".$deliverycommodity." at ".$deliverystationname." (System: ".$deliverysystem.") snapshot added to database.\n";
      }
    }
    $log .= "\n";

    foreach ($temppurchaselist as $element7) {
      $purchasesystem = addslashes($element7['StarSystem']);
      $purchaseaddress = $element7['SystemAddress'];
      $purchasestationname = addslashes($element7['StationName']);
      $purchasemarketid = $element7['MarketID'];
      $purchasecommodity = $element7['commodity'];
      $purchaseamount = $element7['amount'];
      $purchasevalue = $element7['value'];
      $purchasetotal = $element7['totalcost'];
      $purchasetimestamp = $element7['timestamp'];

      $insertpurchasesnapshot = "INSERT INTO snapshot_cargopurchases (tickid, timestamp, StarSystem, SystemAddress, StationName, MarketID, commodity, amount, value, total) VALUES ('$oldtickid', '$purchasetimestamp', '$purchasesystem', '$purchaseaddress', '$purchasestationname', '$purchasemarketid', '$purchasecommodity', '$purchaseamount', '$purchasevalue', '$purchasetotal')";
      if (!mysqli_query($con, $insertpurchasesnapshot)) {
        $log .= "SQL error, couldnt add cargo purchase data ".$purchasecommodity." at ".$purchasestationname." (System: ".$purchasesystem.") snapshot to database.\n".mysqli_error($con);
      } else {
        $log .= "Cargo purchase data ".$purchasecommodity." at ".$purchasestationname." (System: ".$purchasesystem.") snapshot added to database.\n";
      }
    }
    $log .= "\n";
  }
}
if ($apilogprocessor) {
  file_put_contents($logfile, $log);
}
?>