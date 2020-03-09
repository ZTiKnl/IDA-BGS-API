<?PHP
// include config variables
include('config.inc.php');

$logfile = $loglocation.$logapi;

$log = "API triggered\n";
file_put_contents($logfile, $log);

// connect to db
include($securedbcreds);
$con = mysqli_connect($servername,$username,$password,$database);
if (mysqli_connect_errno()) {
  $log = file_get_contents($logfile);
  $log .= "Couldn't connect to database\n";
  file_put_contents($logfile, $log);
  json_response(401, 'sql connection error');
  exit();
}

// fetch and check
$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
  $log = file_get_contents($logfile);
  $log .= "No data received\n";
  file_put_contents($logfile, $log);
  json_response(400, 'no data received');
  exit();
} else {
  $dataevent = $data['event'];
/*
  // Enable section in case of data conflicts
  $log = file_get_contents($logfile);
  $log .= print_r($data, TRUE)."\n\n";
  file_put_contents($logfile, $log);
  // Enable section in case of data conflicts
*/
}

$oldtick = '';
$oldtickid = 0;
$newtick = '';
$newtickid = 0;

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
}

//check API key and get api id
$apikey = $data['key'];
$apiquery = "SELECT id FROM apikeys WHERE apikey = '$apikey'";
$apiid = 0;
if($apiresult = mysqli_query($con, $apiquery)){
  if(mysqli_num_rows($apiresult) > 0){
    $log = file_get_contents($logfile);
    $log .= "APIkey matches\n";
    file_put_contents($logfile, $log);

    while($row = mysqli_fetch_array($apiresult, MYSQLI_ASSOC)) {
      $apiid = $row['id'];
    }


    if ($dataevent == 'FSDJump') {
      $log = file_get_contents($logfile);
      $log .= "FSDJump event\n";
      file_put_contents($logfile, $log);

      $idafaction = false;
      if (isset($data['Factions'])) {
        foreach($data['Factions'] as $idadata) {
          if ($idadata['Name'] == $pmfname) {
            $idafaction = true;
          }
        }
      }

      $StarSystem = addslashes($data['StarSystem']);
      $SystemAddress = $data['SystemAddress'];

      if ($idafaction) {
        $log = file_get_contents($logfile);
        $log .= "Data contains correct faction, proceeding\n";
        file_put_contents($logfile, $log);

        $timestamp = strtotime($data['timestamp']);
        $datetimeobj = date_create_from_format('U', $timestamp);
        $datetime = date_format($datetimeobj, 'Y-m-d H:i:s');

        $Population = $data['Population'];
        $SystemAllegiance = $data['SystemAllegiance'];
        $SystemGovernment = str_replace('$government_', "", $data['SystemGovernment']);
          $SystemGovernment = str_replace(';', "", $SystemGovernment);
        $SystemSecurity = str_replace('$SYSTEM_SECURITY_', "", $data['SystemSecurity']);
          $SystemSecurity = str_replace(';', "", $SystemSecurity);
        $SystemEconomy = str_replace('$economy_', "", $data['SystemEconomy']);
          $SystemEconomy = str_replace(';', "", $SystemEconomy);
        $SystemSecondEconomy = str_replace('$economy_', "", $data['SystemSecondEconomy']);
          $SystemSecondEconomy = str_replace(';', "", $SystemSecondEconomy);
        $SystemFactionName = addslashes($data['SystemFaction']['Name']);
        if (isset($data['SystemFaction']['FactionState'])) {
          $SystemFactionState = $data['SystemFaction']['FactionState'];
        } else {
          $SystemFactionState = '';
        }

        $systemlistquery = "SELECT * FROM systemlist WHERE systemname = '$StarSystem' AND systemaddress = '$SystemAddress'";
        if($systemlistresult = mysqli_query($con, $systemlistquery)){
          if(mysqli_num_rows($systemlistresult) < 1){
            $insertsystemlist = "INSERT INTO systemlist (systemname, systemaddress)  VALUES ('$StarSystem', '$SystemAddress')";
            if (mysqli_query($con, $insertsystemlist)) {
              $log = file_get_contents($logfile);
              $log .= "Added system (".$StarSystem." / ".$SystemAddress.") to systemlist\n";
              $log .= $insertsystemlist."\n";
              file_put_contents($logfile, $log);
            }
          } else {
              $log = file_get_contents($logfile);
              $log .= "System (".$StarSystem." / ".$SystemAddress.") already in systemlist\n";
              file_put_contents($logfile, $log);
          }
        }

        $insertsystemdata = "INSERT INTO data_systems (timestamp, StarSystem, SystemAddress, Population, SystemAllegiance, SystemGovernment, SystemSecurity, SystemEconomy, SystemSecondEconomy, ControllingFaction, FactionState)  VALUES ('$datetime', '$StarSystem', '$SystemAddress', '$Population', '$SystemAllegiance', '$SystemGovernment', '$SystemSecurity', '$SystemEconomy', '$SystemSecondEconomy', '$SystemFactionName', '$SystemFactionState')";

        if (mysqli_query($con, $insertsystemdata)) {
          $log = file_get_contents($logfile);
          $log .= "Added system (".$StarSystem." / ".$SystemAddress.") to data_systems\n";
          $log .= $insertsystemdata."\n";
          file_put_contents($logfile, $log);

          // check if entry already exists for same tick/systemaddress, delete these rows
          $systemsnapshotquery = "SELECT * FROM act_snapshot_systems WHERE tickid = '$newtickid' AND SystemAddress = '$SystemAddress'";
          if($systemsnapshotresult = mysqli_query($con, $systemsnapshotquery)){
            if(mysqli_num_rows($systemsnapshotresult) > 0){
              while($row = mysqli_fetch_array($systemsnapshotresult, MYSQLI_ASSOC)) {
                $rownumber = $row['id'];
                $systemsnapshotdeletequery = "DELETE FROM act_snapshot_systems WHERE id = '$rownumber'";
                if (mysqli_query($con, $systemsnapshotdeletequery)) {
                  $log = file_get_contents($logfile);
                  $log .= "Removed system (".$StarSystem." / ".$SystemAddress.") from active snapshot\n";
                  file_put_contents($logfile, $log);
                } else {
                  $log = file_get_contents($logfile);
                  $log .= "Couldn't remove system (".$StarSystem." / ".$SystemAddress.") from active snapshot: ".mysqli_error($con)."\n";
                  file_put_contents($logfile, $log);
                }
              }
            }
          }
          $snapshotsystemdata = "INSERT INTO act_snapshot_systems (tickid, timestamp, StarSystem, SystemAddress, Population, SystemAllegiance, SystemGovernment, SystemSecurity, SystemEconomy, SystemSecondEconomy, ControllingFaction, FactionState)  VALUES ('$newtickid', '$datetime', '$StarSystem', '$SystemAddress', '$Population', '$SystemAllegiance', '$SystemGovernment', '$SystemSecurity', '$SystemEconomy', '$SystemSecondEconomy', '$SystemFactionName', '$SystemFactionState')";
          if (mysqli_query($con, $snapshotsystemdata)) {
            $log = file_get_contents($logfile);
            $log .= "Added system (".$StarSystem." / ".$SystemAddress.") to active snapshot\n";
            $log .= $snapshotsystemdata."\n";
            file_put_contents($logfile, $log);
          } else {
            $log = file_get_contents($logfile);
            $log .= "Couldn't add system (".$StarSystem." / ".$SystemAddress.") to active snapshot: ".mysqli_error($con)."\n";
            file_put_contents($logfile, $log);
          }

          // for each faction entry, gather data, and insert
          $sqlerror = false;
          foreach($data['Factions'] as $factiondata) {
            $Name = addslashes($factiondata['Name']);
            $Government = $factiondata['Government'];
            $Influence = $factiondata['Influence'];
            if ($Influence == 0 || $Influence == 1) {
              $Influence = number_format($Influence, 2, '.', '');
            }
            $Allegiance = $factiondata['Allegiance'];
            $Happiness = str_replace('$Faction_', '', $factiondata['Happiness']);
            $Happiness = str_replace(';', '', $Happiness);
            if ($Happiness == 'HappinessBand1') {
              $Happiness = 'Elated';
            } else if ($Happiness == 'HappinessBand2') {
              $Happiness = 'Happy';
            } else if ($Happiness == 'HappinessBand3') {
              $Happiness = 'Discontented';
            } else if ($Happiness == 'HappinessBand4') {
              $Happiness = 'Unhappy';
            } else if ($Happiness == 'HappinessBand5') {
              $Happiness = 'Despondent';
            } else {
              $Happiness = addslashes($factiondata['Happiness']);
            }

            $isstateBlight = 0;
            $isstateBoom = 0;
            $isstateBust = 0;
            $isstateCivilLiberty = 0;
            $isstateCivilUnrest = 0;
            $isstateCivilWar = 0;
            $isstateColdWar = 0;
            $isstateColonisation = 0;
            $isstateDamaged = 0;
            $isstateDrought = 0;
            $isstateElection = 0;
            $isstateExpansion = 0;
            $isstateFamine = 0;
            $isstateHistoricEvent = 0;
            $isstateInfrastructureFailure = 0;
            $isstateInvestment = 0;
            $isstateLockdown = 0;
            $isstateNaturalDisaster = 0;
            $isstateOutbreak = 0;
            $isstatePirateAttack = 0;
            $isstatePublicHoliday = 0;
            $isstateRetreat = 0;
            $isstateRevolution = 0;
            $isstateTechnologicalLeap = 0;
            $isstateTerroristAttack = 0;
            $isstateTradeWar = 0;
            $isstateUnderRepairs = 0;
            $isstateWar = 0;
            if (isset($factiondata['ActiveStates'])) {
              foreach($factiondata['ActiveStates'] as $factionstatedata) {
                if ($factionstatedata['State'] == 'Blight') {
                  $isstateBlight = 1;
                }
                if ($factionstatedata['State'] == 'Boom') {
                  $isstateBoom = 1;
                }
                if ($factionstatedata['State'] == 'Bust') {
                  $isstateBust = 1;
                }
                if ($factionstatedata['State'] == 'CivilLiberty') {
                  $isstateCivilLiberty = 1;
                }
                if ($factionstatedata['State'] == 'CivilUnrest') {
                  $isstateCivilUnrest = 1;
                }
                if ($factionstatedata['State'] == 'CivilWar') {
                  $isstateCivilWar = 1;
                }
                if ($factionstatedata['State'] == 'ColdWar') {
                  $isstateColdWar = 1;
                }
                if ($factionstatedata['State'] == 'Colonisation') {
                  $isstateColonisation = 1;
                }
                if ($factionstatedata['State'] == 'Damaged') {
                  $isstateDamaged = 1;
                }
                if ($factionstatedata['State'] == 'Drought') {
                  $isstateDrought = 1;
                }
                if ($factionstatedata['State'] == 'Election') {
                  $isstateElection = 1;
                }
                if ($factionstatedata['State'] == 'Expansion') {
                  $isstateExpansion = 1;
                }
                if ($factionstatedata['State'] == 'Famine') {
                  $isstateFamine = 1;
                }
                if ($factionstatedata['State'] == 'HistoricEvent') {
                  $isstateHistoricEvent = 1;
                }
                if ($factionstatedata['State'] == 'InfrastructureFailure') {
                  $isstateInfrastructureFailure = 1;
                }
                if ($factionstatedata['State'] == 'Investment') {
                  $isstateInvestment = 1;
                }
                if ($factionstatedata['State'] == 'Lockdown') {
                  $isstateLockdown = 1;
                }
                if ($factionstatedata['State'] == 'NaturalDisaster') {
                  $isstateNaturalDisaster = 1;
                }
                if ($factionstatedata['State'] == 'Outbreak') {
                  $isstateOutbreak = 1;
                }
                if ($factionstatedata['State'] == 'PirateAttack') {
                  $isstatePirateAttack = 1;
                }
                if ($factionstatedata['State'] == 'PublicHoliday') {
                  $isstatePublicHoliday = 1;
                }
                if ($factionstatedata['State'] == 'Retreat') {
                  $isstateRetreat = 1;
                }
                if ($factionstatedata['State'] == 'Revolution') {
                  $isstateRevolution = 1;
                }
                if ($factionstatedata['State'] == 'TechnologicalLeap') {
                  $isstateTechnologicalLeap = 1;
                }
                if ($factionstatedata['State'] == 'TerroristAttack') {
                  $isstateTerroristAttack = 1;
                }
                if ($factionstatedata['State'] == 'TradeWar') {
                  $isstateTradeWar = 1;
                }
                if ($factionstatedata['State'] == 'UnderRepairs') {
                  $isstateUnderRepairs = 1;
                }
                if ($factionstatedata['State'] == 'War') {
                  $isstateWar = 1;
                }
              }
            }

            $isrecBlight = 0;
            $isrecBlightTrend = 'NULL';
            $isrecBoom = 0;
            $isrecBoomTrend = 'NULL';
            $isrecBust = 0;
            $isrecBustTrend = 'NULL';
            $isrecCivilLiberty = 0;
            $isrecCivilLibertyTrend = 'NULL';
            $isrecCivilUnrest = 0;
            $isrecCivilUnrestTrend = 'NULL';
            $isrecCivilWar = 0;
            $isrecCivilWarTrend = 'NULL';
            $isrecColdWar = 0;
            $isrecColdWarTrend = 'NULL';
            $isrecColonisation = 0;
            $isrecColonisationTrend = 'NULL';
            $isrecDamaged = 0;
            $isrecDamagedTrend = 'NULL';
            $isrecDrought = 0;
            $isrecDroughtTrend = 'NULL';
            $isrecElection = 0;
            $isrecElectionTrend = 'NULL';
            $isrecExpansion = 0;
            $isrecExpansionTrend = 'NULL';
            $isrecFamine = 0;
            $isrecFamineTrend = 'NULL';
            $isrecHistoricEvent = 0;
            $isrecHistoricEventTrend = 'NULL';
            $isrecInfrastructureFailure = 0;
            $isrecInfrastructureFailureTrend = 'NULL';
            $isrecInvestment = 0;
            $isrecInvestmentTrend = 'NULL';
            $isrecLockdown = 0;
            $isrecLockdownTrend = 'NULL';
            $isrecNaturalDisaster = 0;
            $isrecNaturalDisasterTrend = 'NULL';
            $isrecOutbreak = 0;
            $isrecOutbreakTrend = 'NULL';
            $isrecPirateAttack = 0;
            $isrecPirateAttackTrend = 'NULL';
            $isrecPublicHoliday = 0;
            $isrecPublicHolidayTrend = 'NULL';
            $isrecRetreat = 0;
            $isrecRetreatTrend = 'NULL';
            $isrecRevolution = 0;
            $isrecRevolutionTrend = 'NULL';
            $isrecTechnologicalLeap = 0;
            $isrecTechnologicalLeapTrend = 'NULL';
            $isrecTerroristAttack = 0;
            $isrecTerroristAttackTrend = 'NULL';
            $isrecTradeWar = 0;
            $isrecTradeWarTrend = 'NULL';
            $isrecUnderRepairs = 0;
            $isrecUnderRepairsTrend = 'NULL';
            $isrecWar = 0;
            $isrecWarTrend = 'NULL';

            if (isset($factiondata['RecoveringStates'])) {
              foreach($factiondata['RecoveringStates'] as $factionstatedata) {
                if ($factionstatedata['State'] == 'Blight') {
                  $isrecBlight = 1;
                  $isrecBlightTrend = $factionstatedata['Trend'];
                }
                if ($factionstatedata['State'] == 'Boom') {
                  $isrecBoom = 1;
                  $isrecBoomTrend = $factionstatedata['Trend'];
                }
                if ($factionstatedata['State'] == 'Bust') {
                  $isrecBust = 1;
                  $isrecBustTrend = $factionstatedata['Trend'];
                }
                if ($factionstatedata['State'] == 'CivilLiberty') {
                  $isrecCivilLiberty = 1;
                  $isrecCivilLibertyTrend = $factionstatedata['Trend'];
                }
                if ($factionstatedata['State'] == 'CivilUnrest') {
                  $isrecCivilUnrest = 1;
                  $isrecCivilUnrestTrend = $factionstatedata['Trend'];
                }
                if ($factionstatedata['State'] == 'CivilWar') {
                  $isrecCivilWar = 1;
                  $isrecCivilWarTrend = $factionstatedata['Trend'];
                }
                if ($factionstatedata['State'] == 'ColdWar') {
                  $isrecColdWar = 1;
                  $isrecColdWarTrend = $factionstatedata['Trend'];
                }
                if ($factionstatedata['State'] == 'Colonisation') {
                  $isrecColonisation = 1;
                  $isrecColonisationTrend = $factionstatedata['Trend'];
                }
                if ($factionstatedata['State'] == 'Damaged') {
                  $isrecDamaged = 1;
                  $isrecDamagedTrend = $factionstatedata['Trend'];
                }
                if ($factionstatedata['State'] == 'Drought') {
                  $isrecDrought = 1;
                  $isrecDroughtTrend = $factionstatedata['Trend'];
                }
                if ($factionstatedata['State'] == 'Election') {
                  $isrecElection = 1;
                  $isrecElectionTrend = $factionstatedata['Trend'];
                }
                if ($factionstatedata['State'] == 'Expansion') {
                  $isrecExpansion = 1;
                  $isrecExpansionTrend = $factionstatedata['Trend'];
                }
                if ($factionstatedata['State'] == 'Famine') {
                  $isrecFamine = 1;
                  $isrecFamineTrend = $factionstatedata['Trend'];
                }
                if ($factionstatedata['State'] == 'HistoricEvent') {
                  $isrecHistoricEvent = 1;
                  $isrecHistoricEventTrend = $factionstatedata['Trend'];
                }
                if ($factionstatedata['State'] == 'InfrastructureFailure') {
                  $isrecInfrastructureFailure = 1;
                  $isrecInfrastructureFailureTrend = $factionstatedata['Trend'];
                }
                if ($factionstatedata['State'] == 'Investment') {
                  $isrecInvestment = 1;
                  $isrecInvestmentTrend = $factionstatedata['Trend'];
                }
                if ($factionstatedata['State'] == 'Lockdown') {
                  $isrecLockdown = 1;
                  $isrecLockdownTrend = $factionstatedata['Trend'];
                }
                if ($factionstatedata['State'] == 'NaturalDisaster') {
                  $isrecNaturalDisaster = 1;
                  $isrecNaturalDisasterTrend = $factionstatedata['Trend'];
                }
                if ($factionstatedata['State'] == 'Outbreak') {
                  $isrecOutbreak = 1;
                  $isrecOutbreakTrend = $factionstatedata['Trend'];
                }
                if ($factionstatedata['State'] == 'PirateAttack') {
                  $isrecPirateAttack = 1;
                  $isrecPirateAttackTrend = $factionstatedata['Trend'];
                }
                if ($factionstatedata['State'] == 'PublicHoliday') {
                  $isrecPublicHoliday = 1;
                  $isrecPublicHolidayTrend = $factionstatedata['Trend'];
                }
                if ($factionstatedata['State'] == 'Retreat') {
                  $isrecRetreat = 1;
                  $isrecRetreatTrend = $factionstatedata['Trend'];
                }
                if ($factionstatedata['State'] == 'Revolution') {
                  $isrecRevolution = 1;
                  $isrecRevolutionTrend = $factionstatedata['Trend'];
                }
                if ($factionstatedata['State'] == 'TechnologicalLeap') {
                  $isrecTechnologicalLeap = 1;
                  $isrecTechnologicalLeapTrend = $factionstatedata['Trend'];
                }
                if ($factionstatedata['State'] == 'TerroristAttack') {
                  $isrecTerroristAttack = 1;
                  $isrecTerroristAttackTrend = $factionstatedata['Trend'];
                }
                if ($factionstatedata['State'] == 'TradeWar') {
                  $isrecTradeWar = 1;
                  $isrecTradeWarTrend = $factionstatedata['Trend'];
                }
                if ($factionstatedata['State'] == 'UnderRepairs') {
                  $isrecUnderRepairs = 1;
                  $isrecUnderRepairsTrend = $factionstatedata['Trend'];
                }
                if ($factionstatedata['State'] == 'War') {
                  $isrecWar = 1;
                  $isrecWarTrend = $factionstatedata['Trend'];
                }
              }
            }

            $ispendingBlight = 0;
            $ispendingBlightTrend = 'NULL';
            $ispendingBoom = 0;
            $ispendingBoomTrend = 'NULL';
            $ispendingBust = 0;
            $ispendingBustTrend = 'NULL';
            $ispendingCivilLiberty = 0;
            $ispendingCivilLibertyTrend = 'NULL';
            $ispendingCivilUnrest = 0;
            $ispendingCivilUnrestTrend = 'NULL';
            $ispendingCivilWar = 0;
            $ispendingCivilWarTrend = 'NULL';
            $ispendingColdWar = 0;
            $ispendingColdWarTrend = 'NULL';
            $ispendingColonisation = 0;
            $ispendingColonisationTrend = 'NULL';
            $ispendingDamaged = 0;
            $ispendingDamagedTrend = 'NULL';
            $ispendingDrought = 0;
            $ispendingDroughtTrend = 'NULL';
            $ispendingElection = 0;
            $ispendingElectionTrend = 'NULL';
            $ispendingExpansion = 0;
            $ispendingExpansionTrend = 'NULL';
            $ispendingFamine = 0;
            $ispendingFamineTrend = 'NULL';
            $ispendingHistoricEvent = 0;
            $ispendingHistoricEventTrend = 'NULL';
            $ispendingInfrastructureFailure = 0;
            $ispendingInfrastructureFailureTrend = 'NULL';
            $ispendingInvestment = 0;
            $ispendingInvestmentTrend = 'NULL';
            $ispendingLockdown = 0;
            $ispendingLockdownTrend = 'NULL';
            $ispendingNaturalDisaster = 0;
            $ispendingNaturalDisasterTrend = 'NULL';
            $ispendingOutbreak = 0;
            $ispendingOutbreakTrend = 'NULL';
            $ispendingPirateAttack = 0;
            $ispendingPirateAttackTrend = 'NULL';
            $ispendingPublicHoliday = 0;
            $ispendingPublicHolidayTrend = 'NULL';
            $ispendingRetreat = 0;
            $ispendingRetreatTrend = 'NULL';
            $ispendingRevolution = 0;
            $ispendingRevolutionTrend = 'NULL';
            $ispendingTechnologicalLeap = 0;
            $ispendingTechnologicalLeapTrend = 'NULL';
            $ispendingTerroristAttack = 0;
            $ispendingTerroristAttackTrend = 'NULL';
            $ispendingTradeWar = 0;
            $ispendingTradeWarTrend = 'NULL';
            $ispendingUnderRepairs = 0;
            $ispendingUnderRepairsTrend = 'NULL';
            $ispendingWar = 0;
            $ispendingWarTrend = 'NULL';

            if (isset($factiondata['PendingStates'])) {
              foreach($factiondata['PendingStates'] as $factionstatedata) {
                if ($factionstatedata['State'] == 'Blight') {
                  $ispendingBlight = 1;
                  $ispendingBlightTrend = $factionstatedata['Trend'];
                }
                if ($factionstatedata['State'] == 'Boom') {
                  $ispendingBoom = 1;
                  $ispendingBoomTrend = $factionstatedata['Trend'];
                }
                if ($factionstatedata['State'] == 'Bust') {
                  $ispendingBust = 1;
                  $ispendingBustTrend = $factionstatedata['Trend'];
                }
                if ($factionstatedata['State'] == 'CivilLiberty') {
                  $ispendingCivilLiberty = 1;
                  $ispendingCivilLibertyTrend = $factionstatedata['Trend'];
                }
                if ($factionstatedata['State'] == 'CivilUnrest') {
                  $ispendingCivilUnrest = 1;
                  $ispendingCivilUnrestTrend = $factionstatedata['Trend'];
                }
                if ($factionstatedata['State'] == 'CivilWar') {
                  $ispendingCivilWar = 1;
                  $ispendingCivilWarTrend = $factionstatedata['Trend'];
                }
                if ($factionstatedata['State'] == 'ColdWar') {
                  $ispendingColdWar = 1;
                  $ispendingColdWarTrend = $factionstatedata['Trend'];
                }
                if ($factionstatedata['State'] == 'Colonisation') {
                  $ispendingColonisation = 1;
                  $ispendingColonisationTrend = $factionstatedata['Trend'];
                }
                if ($factionstatedata['State'] == 'Damaged') {
                  $ispendingDamaged = 1;
                  $ispendingDamagedTrend = $factionstatedata['Trend'];
                }
                if ($factionstatedata['State'] == 'Drought') {
                  $ispendingDrought = 1;
                  $ispendingDroughtTrend = $factionstatedata['Trend'];
                }
                if ($factionstatedata['State'] == 'Election') {
                  $ispendingElection = 1;
                  $ispendingElectionTrend = $factionstatedata['Trend'];
                }
                if ($factionstatedata['State'] == 'Expansion') {
                  $ispendingExpansion = 1;
                  $ispendingExpansionTrend = $factionstatedata['Trend'];
                }
                if ($factionstatedata['State'] == 'Famine') {
                  $ispendingFamine = 1;
                  $ispendingFamineTrend = $factionstatedata['Trend'];
                }
                if ($factionstatedata['State'] == 'HistoricEvent') {
                  $ispendingHistoricEvent = 1;
                  $ispendingHistoricEventTrend = $factionstatedata['Trend'];
                }
                if ($factionstatedata['State'] == 'InfrastructureFailure') {
                  $ispendingInfrastructureFailure = 1;
                  $ispendingInfrastructureFailureTrend = $factionstatedata['Trend'];
                }
                if ($factionstatedata['State'] == 'Investment') {
                  $ispendingInvestment = 1;
                  $ispendingInvestmentTrend = $factionstatedata['Trend'];
                }
                if ($factionstatedata['State'] == 'Lockdown') {
                  $ispendingLockdown = 1;
                  $ispendingLockdownTrend = $factionstatedata['Trend'];
                }
                if ($factionstatedata['State'] == 'NaturalDisaster') {
                  $ispendingNaturalDisaster = 1;
                  $ispendingNaturalDisasterTrend = $factionstatedata['Trend'];
                }
                if ($factionstatedata['State'] == 'Outbreak') {
                  $ispendingOutbreak = 1;
                  $ispendingOutbreakTrend = $factionstatedata['Trend'];
                }
                if ($factionstatedata['State'] == 'PirateAttack') {
                  $ispendingPirateAttack = 1;
                  $ispendingPirateAttackTrend = $factionstatedata['Trend'];
                }
                if ($factionstatedata['State'] == 'PublicHoliday') {
                  $ispendingPublicHoliday = 1;
                  $ispendingPublicHolidayTrend = $factionstatedata['Trend'];
                }
                if ($factionstatedata['State'] == 'Retreat') {
                  $ispendingRetreat = 1;
                  $ispendingRetreatTrend = $factionstatedata['Trend'];
                }
                if ($factionstatedata['State'] == 'Revolution') {
                  $ispendingRevolution = 1;
                  $ispendingRevolutionTrend = $factionstatedata['Trend'];
                }
                if ($factionstatedata['State'] == 'TechnologicalLeap') {
                  $ispendingTechnologicalLeap = 1;
                  $ispendingTechnologicalLeapTrend = $factionstatedata['Trend'];
                }
                if ($factionstatedata['State'] == 'TerroristAttack') {
                  $ispendingTerroristAttack = 1;
                  $ispendingTerroristAttackTrend = $factionstatedata['Trend'];
                }
                if ($factionstatedata['State'] == 'TradeWar') {
                  $ispendingTradeWar = 1;
                  $ispendingTradeWarTrend = $factionstatedata['Trend'];
                }
                if ($factionstatedata['State'] == 'UnderRepairs') {
                  $ispendingUnderRepairs = 1;
                  $ispendingUnderRepairsTrend = $factionstatedata['Trend'];
                }
                if ($factionstatedata['State'] == 'War') {
                  $ispendingWar = 1;
                  $ispendingWarTrend = $factionstatedata['Trend'];
                }
              }
            }

            $insertfactiondata = "INSERT INTO data_factions (timestamp, StarSystem, SystemAddress, Name, Government, Influence, Allegiance, Happiness, stateBlight, stateBoom, stateBust, stateCivilLiberty, stateCivilUnrest, stateCivilWar, stateColdWar, stateColonisation, stateDamaged, stateDrought, stateElection, stateExpansion, stateFamine, stateHistoricEvent, stateInfrastructureFailure, stateInvestment, stateLockdown, stateNaturalDisaster, stateOutbreak, statePirateAttack, statePublicHoliday, stateRetreat, stateRevolution, stateTechnologicalLeap, stateTerroristAttack, stateTradeWar, stateUnderRepairs, stateWar, recBlight, recBlightTrend, recBoom, recBoomTrend, recBust, recBustTrend, recCivilLiberty, recCivilLibertyTrend, recCivilUnrest, recCivilUnrestTrend, recCivilWar, recCivilWarTrend, recColdWar, recColdWarTrend, recColonisation, recColonisationTrend, recDamaged, recDamagedTrend, recDrought, recDroughtTrend, recElection, recElectionTrend, recExpansion, recExpansionTrend, recFamine, recFamineTrend, recHistoricEvent, recHistoricEventTrend, recInfrastructureFailure, recInfrastructureFailureTrend, recInvestment, recInvestmentTrend, recLockdown, recLockdownTrend, recNaturalDisaster, recNaturalDisasterTrend, recOutbreak, recOutbreakTrend, recPirateAttack, recPirateAttackTrend, recPublicHoliday, recPublicHolidayTrend, recRetreat, recRetreatTrend, recRevolution, recRevolutionTrend, recTechnologicalLeap, recTechnologicalLeapTrend, recTerroristAttack, recTerroristAttackTrend, recTradeWar, recTradeWarTrend, recUnderRepairs, recUnderRepairsTrend, recWar, recWarTrend, pendingBlight, pendingBlightTrend, pendingBoom, pendingBoomTrend, pendingBust, pendingBustTrend, pendingCivilLiberty, pendingCivilLibertyTrend, pendingCivilUnrest, pendingCivilUnrestTrend, pendingCivilWar, pendingCivilWarTrend, pendingColdWar, pendingColdWarTrend, pendingColonisation, pendingColonisationTrend, pendingDamaged, pendingDamagedTrend, pendingDrought, pendingDroughtTrend, pendingElection, pendingElectionTrend, pendingExpansion, pendingExpansionTrend, pendingFamine, pendingFamineTrend, pendingHistoricEvent, pendingHistoricEventTrend, pendingInfrastructureFailure, pendingInfrastructureFailureTrend, pendingInvestment, pendingInvestmentTrend, pendingLockdown, pendingLockdownTrend, pendingNaturalDisaster, pendingNaturalDisasterTrend, pendingOutbreak, pendingOutbreakTrend, pendingPirateAttack, pendingPirateAttackTrend, pendingPublicHoliday, pendingPublicHolidayTrend, pendingRetreat, pendingRetreatTrend, pendingRevolution, pendingRevolutionTrend, pendingTechnologicalLeap, pendingTechnologicalLeapTrend, pendingTerroristAttack, pendingTerroristAttackTrend, pendingTradeWar, pendingTradeWarTrend, pendingUnderRepairs, pendingUnderRepairsTrend, pendingWar, pendingWarTrend) VALUES ('$datetime', '$StarSystem', '$SystemAddress', '$Name', '$Government', '$Influence', '$Allegiance', '$Happiness', '$isstateBlight', '$isstateBoom', '$isstateBust', '$isstateCivilLiberty', '$isstateCivilUnrest', '$isstateCivilWar', '$isstateColdWar', '$isstateColonisation', '$isstateDamaged', '$isstateDrought', '$isstateElection', '$isstateExpansion', '$isstateFamine', '$isstateHistoricEvent', '$isstateInfrastructureFailure', '$isstateInvestment', '$isstateLockdown', '$isstateNaturalDisaster', '$isstateOutbreak', '$isstatePirateAttack', '$isstatePublicHoliday', '$isstateRetreat', '$isstateRevolution', '$isstateTechnologicalLeap', '$isstateTerroristAttack', '$isstateTradeWar', '$isstateUnderRepairs', '$isstateWar', '$isrecBlight', $isrecBlightTrend, '$isrecBoom', $isrecBoomTrend, '$isrecBust', $isrecBustTrend, '$isrecCivilLiberty', $isrecCivilLibertyTrend, '$isrecCivilUnrest', $isrecCivilUnrestTrend, '$isrecCivilWar', $isrecCivilWarTrend, '$isrecColdWar', $isrecColdWarTrend, '$isrecColonisation', $isrecColonisationTrend, '$isrecDamaged', $isrecDamagedTrend, '$isrecDrought', $isrecDroughtTrend, '$isrecElection', $isrecElectionTrend, '$isrecExpansion', $isrecExpansionTrend, '$isrecFamine', $isrecFamineTrend, '$isrecHistoricEvent', $isrecHistoricEventTrend, '$isrecInfrastructureFailure', $isrecInfrastructureFailureTrend, '$isrecInvestment', $isrecInvestmentTrend, '$isrecLockdown', $isrecLockdownTrend, '$isrecNaturalDisaster', $isrecNaturalDisasterTrend, '$isrecOutbreak', $isrecOutbreakTrend, '$isrecPirateAttack', $isrecPirateAttackTrend, '$isrecPublicHoliday', $isrecPublicHolidayTrend, '$isrecRetreat', $isrecRetreatTrend, '$isrecRevolution', $isrecRevolutionTrend, '$isrecTechnologicalLeap', $isrecTechnologicalLeapTrend, '$isrecTerroristAttack', $isrecTerroristAttackTrend, '$isrecTradeWar', $isrecTradeWarTrend, '$isrecUnderRepairs', $isrecUnderRepairsTrend, '$isrecWar', $isrecWarTrend, '$ispendingBlight', $ispendingBlightTrend, '$ispendingBoom', $ispendingBoomTrend, '$ispendingBust', $ispendingBustTrend, '$ispendingCivilLiberty', $ispendingCivilLibertyTrend, '$ispendingCivilUnrest', $ispendingCivilUnrestTrend, '$ispendingCivilWar', $ispendingCivilWarTrend, '$ispendingColdWar', $ispendingColdWarTrend, '$ispendingColonisation', $ispendingColonisationTrend, '$ispendingDamaged', $ispendingDamagedTrend, '$ispendingDrought', $ispendingDroughtTrend, '$ispendingElection', $ispendingElectionTrend, '$ispendingExpansion', $ispendingExpansionTrend, '$ispendingFamine', $ispendingFamineTrend, '$ispendingHistoricEvent', $ispendingHistoricEventTrend, '$ispendingInfrastructureFailure', $ispendingInfrastructureFailureTrend, '$ispendingInvestment', $ispendingInvestmentTrend, '$ispendingLockdown', $ispendingLockdownTrend, '$ispendingNaturalDisaster', $ispendingNaturalDisasterTrend, '$ispendingOutbreak', $ispendingOutbreakTrend, '$ispendingPirateAttack', $ispendingPirateAttackTrend, '$ispendingPublicHoliday', $ispendingPublicHolidayTrend, '$ispendingRetreat', $ispendingRetreatTrend, '$ispendingRevolution', $ispendingRevolutionTrend, '$ispendingTechnologicalLeap', $ispendingTechnologicalLeapTrend, '$ispendingTerroristAttack', $ispendingTerroristAttackTrend, '$ispendingTradeWar', $ispendingTradeWarTrend, '$ispendingUnderRepairs', $ispendingUnderRepairsTrend, '$ispendingWar', $ispendingWarTrend)";

            if (!mysqli_query($con, $insertfactiondata)) {
              $sqlerror = true;
              $sqlerrormessage = mysqli_error($con);
            }
            if (!$sqlerror) {
              $log = file_get_contents($logfile);
              $log .= "Added faction (".$Name." / ".$StarSystem.") to factiondata\n";
              $log .= $insertfactiondata."\n";
              file_put_contents($logfile, $log);
            }

            // check if entry already exists for same tick/faction/starsystem/systemaddress, delete these rows
            $factionsnapshotquery = "SELECT * FROM act_snapshot_factions WHERE tickid = '$newtickid' AND Name = '$Name' AND StarSystem = '$StarSystem' AND SystemAddress = '$SystemAddress'";
            if($factionsnapshotresult = mysqli_query($con, $factionsnapshotquery)){
              if(mysqli_num_rows($factionsnapshotresult) > 0){
                while($row = mysqli_fetch_array($factionsnapshotresult, MYSQLI_ASSOC)) {
                  $rownumber = $row['id'];
                  $factionsnapshotdeletequery = "DELETE FROM act_snapshot_factions WHERE id = '$rownumber'";
                  if (mysqli_query($con, $factionsnapshotdeletequery)) {
                    $log = file_get_contents($logfile);
                    $log .= "Removed faction (".$Name." / ".$StarSystem.") from active snapshot\n";
                    file_put_contents($logfile, $log);
                  } else {
                    $log = file_get_contents($logfile);
                    $log .= "Couldn't remove faction (".$Name." / ".$StarSystem.") from active snapshot: ".mysqli_error($con)."\n";
                    file_put_contents($logfile, $log);
                  }
                }
              }
            }

            $snapshotfactiondata = "INSERT INTO act_snapshot_factions (tickid, timestamp, StarSystem, SystemAddress, Name, Government, Influence, Allegiance, Happiness, stateBlight, stateBoom, stateBust, stateCivilLiberty, stateCivilUnrest, stateCivilWar, stateColdWar, stateColonisation, stateDamaged, stateDrought, stateElection, stateExpansion, stateFamine, stateHistoricEvent, stateInfrastructureFailure, stateInvestment, stateLockdown, stateNaturalDisaster, stateOutbreak, statePirateAttack, statePublicHoliday, stateRetreat, stateRevolution, stateTechnologicalLeap, stateTerroristAttack, stateTradeWar, stateUnderRepairs, stateWar, recBlight, recBlightTrend, recBoom, recBoomTrend, recBust, recBustTrend, recCivilLiberty, recCivilLibertyTrend, recCivilUnrest, recCivilUnrestTrend, recCivilWar, recCivilWarTrend, recColdWar, recColdWarTrend, recColonisation, recColonisationTrend, recDamaged, recDamagedTrend, recDrought, recDroughtTrend, recElection, recElectionTrend, recExpansion, recExpansionTrend, recFamine, recFamineTrend, recHistoricEvent, recHistoricEventTrend, recInfrastructureFailure, recInfrastructureFailureTrend, recInvestment, recInvestmentTrend, recLockdown, recLockdownTrend, recNaturalDisaster, recNaturalDisasterTrend, recOutbreak, recOutbreakTrend, recPirateAttack, recPirateAttackTrend, recPublicHoliday, recPublicHolidayTrend, recRetreat, recRetreatTrend, recRevolution, recRevolutionTrend, recTechnologicalLeap, recTechnologicalLeapTrend, recTerroristAttack, recTerroristAttackTrend, recTradeWar, recTradeWarTrend, recUnderRepairs, recUnderRepairsTrend, recWar, recWarTrend, pendingBlight, pendingBlightTrend, pendingBoom, pendingBoomTrend, pendingBust, pendingBustTrend, pendingCivilLiberty, pendingCivilLibertyTrend, pendingCivilUnrest, pendingCivilUnrestTrend, pendingCivilWar, pendingCivilWarTrend, pendingColdWar, pendingColdWarTrend, pendingColonisation, pendingColonisationTrend, pendingDamaged, pendingDamagedTrend, pendingDrought, pendingDroughtTrend, pendingElection, pendingElectionTrend, pendingExpansion, pendingExpansionTrend, pendingFamine, pendingFamineTrend, pendingHistoricEvent, pendingHistoricEventTrend, pendingInfrastructureFailure, pendingInfrastructureFailureTrend, pendingInvestment, pendingInvestmentTrend, pendingLockdown, pendingLockdownTrend, pendingNaturalDisaster, pendingNaturalDisasterTrend, pendingOutbreak, pendingOutbreakTrend, pendingPirateAttack, pendingPirateAttackTrend, pendingPublicHoliday, pendingPublicHolidayTrend, pendingRetreat, pendingRetreatTrend, pendingRevolution, pendingRevolutionTrend, pendingTechnologicalLeap, pendingTechnologicalLeapTrend, pendingTerroristAttack, pendingTerroristAttackTrend, pendingTradeWar, pendingTradeWarTrend, pendingUnderRepairs, pendingUnderRepairsTrend, pendingWar, pendingWarTrend) VALUES ('$newtickid', '$datetime', '$StarSystem', '$SystemAddress', '$Name', '$Government', '$Influence', '$Allegiance', '$Happiness', '$isstateBlight', '$isstateBoom', '$isstateBust', '$isstateCivilLiberty', '$isstateCivilUnrest', '$isstateCivilWar', '$isstateColdWar', '$isstateColonisation', '$isstateDamaged', '$isstateDrought', '$isstateElection', '$isstateExpansion', '$isstateFamine', '$isstateHistoricEvent', '$isstateInfrastructureFailure', '$isstateInvestment', '$isstateLockdown', '$isstateNaturalDisaster', '$isstateOutbreak', '$isstatePirateAttack', '$isstatePublicHoliday', '$isstateRetreat', '$isstateRevolution', '$isstateTechnologicalLeap', '$isstateTerroristAttack', '$isstateTradeWar', '$isstateUnderRepairs', '$isstateWar', '$isrecBlight', $isrecBlightTrend, '$isrecBoom', $isrecBoomTrend, '$isrecBust', $isrecBustTrend, '$isrecCivilLiberty', $isrecCivilLibertyTrend, '$isrecCivilUnrest', $isrecCivilUnrestTrend, '$isrecCivilWar', $isrecCivilWarTrend, '$isrecColdWar', $isrecColdWarTrend, '$isrecColonisation', $isrecColonisationTrend, '$isrecDamaged', $isrecDamagedTrend, '$isrecDrought', $isrecDroughtTrend, '$isrecElection', $isrecElectionTrend, '$isrecExpansion', $isrecExpansionTrend, '$isrecFamine', $isrecFamineTrend, '$isrecHistoricEvent', $isrecHistoricEventTrend, '$isrecInfrastructureFailure', $isrecInfrastructureFailureTrend, '$isrecInvestment', $isrecInvestmentTrend, '$isrecLockdown', $isrecLockdownTrend, '$isrecNaturalDisaster', $isrecNaturalDisasterTrend, '$isrecOutbreak', $isrecOutbreakTrend, '$isrecPirateAttack', $isrecPirateAttackTrend, '$isrecPublicHoliday', $isrecPublicHolidayTrend, '$isrecRetreat', $isrecRetreatTrend, '$isrecRevolution', $isrecRevolutionTrend, '$isrecTechnologicalLeap', $isrecTechnologicalLeapTrend, '$isrecTerroristAttack', $isrecTerroristAttackTrend, '$isrecTradeWar', $isrecTradeWarTrend, '$isrecUnderRepairs', $isrecUnderRepairsTrend, '$isrecWar', $isrecWarTrend, '$ispendingBlight', $ispendingBlightTrend, '$ispendingBoom', $ispendingBoomTrend, '$ispendingBust', $ispendingBustTrend, '$ispendingCivilLiberty', $ispendingCivilLibertyTrend, '$ispendingCivilUnrest', $ispendingCivilUnrestTrend, '$ispendingCivilWar', $ispendingCivilWarTrend, '$ispendingColdWar', $ispendingColdWarTrend, '$ispendingColonisation', $ispendingColonisationTrend, '$ispendingDamaged', $ispendingDamagedTrend, '$ispendingDrought', $ispendingDroughtTrend, '$ispendingElection', $ispendingElectionTrend, '$ispendingExpansion', $ispendingExpansionTrend, '$ispendingFamine', $ispendingFamineTrend, '$ispendingHistoricEvent', $ispendingHistoricEventTrend, '$ispendingInfrastructureFailure', $ispendingInfrastructureFailureTrend, '$ispendingInvestment', $ispendingInvestmentTrend, '$ispendingLockdown', $ispendingLockdownTrend, '$ispendingNaturalDisaster', $ispendingNaturalDisasterTrend, '$ispendingOutbreak', $ispendingOutbreakTrend, '$ispendingPirateAttack', $ispendingPirateAttackTrend, '$ispendingPublicHoliday', $ispendingPublicHolidayTrend, '$ispendingRetreat', $ispendingRetreatTrend, '$ispendingRevolution', $ispendingRevolutionTrend, '$ispendingTechnologicalLeap', $ispendingTechnologicalLeapTrend, '$ispendingTerroristAttack', $ispendingTerroristAttackTrend, '$ispendingTradeWar', $ispendingTradeWarTrend, '$ispendingUnderRepairs', $ispendingUnderRepairsTrend, '$ispendingWar', $ispendingWarTrend)";
            if (mysqli_query($con, $snapshotfactiondata)) {
              $log = file_get_contents($logfile);
              $log .= "Added faction (".$Name." / ".$StarSystem.") to active snapshot\n";
              $log .= $snapshotfactiondata."\n";
              file_put_contents($logfile, $log);
            } else {
              $log = file_get_contents($logfile);
              $log .= "Couldn't add faction (".$Name." / ".$StarSystem.") to active snapshot: ".mysqli_error($con)."\n";
              $log .= $snapshotfactiondata."\n";
              file_put_contents($logfile, $log);
            }

          }
          if (!$sqlerror) {
            // for each conflict entry, gather data, and insert
            $sqlerror = false;
            if (isset($data['Conflicts'])) {
              foreach($data['Conflicts'] as $conflictdata) {
                $conflicttype = $conflictdata['WarType'];
                $conflictstatus = $conflictdata['Status'];
                if ($conflictstatus == 'active') {
                  $conflictstatus = 'Active';
                } elseif ($conflictstatus == 'pending') {
                  $conflictstatus = 'Pending';
                }
                if ($conflicttype == 'war') {
                  $conflicttype = 'War';
                } elseif ($conflicttype == 'civilwar') {
                  $conflicttype = 'Civil War';
                } elseif ($conflicttype == 'coldwar') {
                  $conflicttype = 'Cold War';
                } elseif ($conflicttype == 'election') {
                  $conflicttype = 'Election';
                } elseif ($conflicttype == 'civilliberty') {
                  $conflicttype = 'Civil Liberty';
                } elseif ($conflicttype == 'civilunrest') {
                  $conflicttype = 'Civil Unrest';
                }
                $conflictfaction1name = addslashes($conflictdata['Faction1']['Name']);
                $conflictfaction1stake = addslashes($conflictdata['Faction1']['Stake']);
                $conflictfaction1windays = $conflictdata['Faction1']['WonDays'];
                $conflictfaction2name = addslashes($conflictdata['Faction2']['Name']);
                $conflictfaction2stake = addslashes($conflictdata['Faction2']['Stake']);
                $conflictfaction2windays = $conflictdata['Faction2']['WonDays'];

                $insertconflictdata = "INSERT INTO data_conflicts (timestamp, StarSystem, SystemAddress, conflicttype, conflictstatus, conflictfaction1name, conflictfaction1stake, conflictfaction1windays, conflictfaction2name, conflictfaction2stake, conflictfaction2windays) VALUES ('$datetime', '$StarSystem', '$SystemAddress', '$conflicttype', '$conflictstatus', '$conflictfaction1name', '$conflictfaction1stake', '$conflictfaction1windays', '$conflictfaction2name', '$conflictfaction2stake', '$conflictfaction2windays')";
                if (!mysqli_query($con, $insertconflictdata)) {
                  $sqlerror = true;
                  $sqlerrormessage = mysqli_error($con);
                }
                if (!$sqlerror) {
                  $log = file_get_contents($logfile);
                  $log .= "Added conflict ".$conflicttype." (".$conflictfaction1name." / ".$conflictfaction2name.") to conflictdata\n";
                  $log .= $insertconflictdata."\n";
                  file_put_contents($logfile, $log);
                }

                // check if entry already exists for same tick/starsystem/systemaddress/conflicttype/conflictstatus/conflictfaction1/conflictfaction2, delete these rows
                $conflictsnapshotquery = "SELECT * FROM act_snapshot_conflicts WHERE tickid = '$newtickid' AND StarSystem = '$StarSystem' AND SystemAddress = '$SystemAddress' AND conflicttype = '$conflicttype' AND conflictstatus = '$conflictstatus' AND ((conflictfaction1name = '$conflictfaction1name' AND conflictfaction2name = '$conflictfaction2name') OR (conflictfaction1name = '$conflictfaction2name' AND conflictfaction2name = '$conflictfaction1name'))";
                if($conflictsnapshotresult = mysqli_query($con, $conflictsnapshotquery)){
                  if(mysqli_num_rows($conflictsnapshotresult) > 0){
                    while($row = mysqli_fetch_array($conflictsnapshotresult, MYSQLI_ASSOC)) {
                      $rownumber = $row['id'];
                      $conflictsnapshotdeletequery = "DELETE FROM act_snapshot_conflicts WHERE id = '$rownumber'";
                      if (mysqli_query($con, $conflictsnapshotdeletequery)) {
                        $log = file_get_contents($logfile);
                        $log .= "Removed conflict ".$conflicttype." (".$conflictfaction1name." / ".$conflictfaction2name.") from active snapshot\n";
                        file_put_contents($logfile, $log);
                      } else {
                        $log = file_get_contents($logfile);
                        $log .= "Couldn't remove conflict ".$conflicttype." (".$conflictfaction1name." / ".$conflictfaction2name.") from active snapshot: ".mysqli_error($con)."\n";
                        file_put_contents($logfile, $log);
                      }
                    }
                  }
                }

                $snapshotconflictdata = "INSERT INTO act_snapshot_conflicts (tickid, timestamp, StarSystem, SystemAddress, conflicttype, conflictstatus, conflictfaction1name, conflictfaction1stake, conflictfaction1windays, conflictfaction2name, conflictfaction2stake, conflictfaction2windays) VALUES ('$newtickid', '$datetime', '$StarSystem', '$SystemAddress', '$conflicttype', '$conflictstatus', '$conflictfaction1name', '$conflictfaction1stake', '$conflictfaction1windays', '$conflictfaction2name', '$conflictfaction2stake', '$conflictfaction2windays')";
                if (mysqli_query($con, $snapshotconflictdata)) {
                  $log = file_get_contents($logfile);
                  $log .= "Added conflict ".$conflicttype." (".$conflictfaction1name." / ".$conflictfaction2name.") to active snapshot\n";
                  $log .= $snapshotconflictdata."\n";
                  file_put_contents($logfile, $log);
                } else {
                  $log = file_get_contents($logfile);
                  $log .= "Couldn't add conflict ".$conflicttype." (".$conflictfaction1name." / ".$conflictfaction2name.") to active snapshot: ".mysqli_error($con)."\n";
                  $log .= $snapshotconflictdata."\n";
                  file_put_contents($logfile, $log);
                }

              }
            }
            if (!$sqlerror) {
              $log = file_get_contents($logfile);
              $log .= "Success, all done\n";
              file_put_contents($logfile, $log);
              mysqli_close($con);
              json_response(200, 'Success');
              exit();
            } else {
              $log = file_get_contents($logfile);
              $log .= "Couldn't add conflict ".$conflicttype." (".$conflictfaction1name." / ".$conflictfaction2name.") to active snapshot: ".mysqli_error($con)."\n";
              file_put_contents($logfile, $log);
              json_response(406, 'sql query error', $sqlerrormessage);
              exit();
            }
          } else {
            $log = file_get_contents($logfile);
            $log .= "Couldn't add faction (".$Name." / ".$StarSystem.") to factiondata: ".$sqlerrormessage."\n";
            file_put_contents($logfile, $log);
            json_response(405, 'sql query error', $sqlerrormessage);
            exit();
          }
        } else {
          $log = file_get_contents($logfile);
          $log .= "SQL query error: ".mysqli_error($con)."\n";
          file_put_contents($logfile, $log);
          json_response(404, 'sql query error', mysqli_error($con));
          exit();
        }
      } else {
        $log = file_get_contents($logfile);
        $log .= "Data doesn't contain correct faction\n";
        file_put_contents($logfile, $log);

        // check if systemname/systemaddress is in systemlist table
        // if it is, remove systemname/systemaddress

        $systemlistquery = "SELECT * FROM systemlist WHERE systemname = '$StarSystem' AND systemaddress = '$SystemAddress'";
        if($systemlistresult = mysqli_query($con, $systemlistquery)){
          if(mysqli_num_rows($systemlistresult) > 0){
            while($row = mysqli_fetch_array($systemlistresult, MYSQLI_ASSOC)) {
              $rownumber = $row['id'];
              $systemlistresultdeletequery = "DELETE FROM systemlist WHERE id = '$rownumber' AND systemname = '$StarSystem' AND systemaddress = '$SystemAddress'";
              if (mysqli_query($con, $systemlistresultdeletequery)) {
                $log = file_get_contents($logfile);
                $log .= "Removed system (".$StarSystem." / ".$SystemAddress.") from systemlist\n";
                file_put_contents($logfile, $log);
              } else {
                $log = file_get_contents($logfile);
                $log .= "Couldn't remove system (".$StarSystem." / ".$SystemAddress.") from systemlist: ".mysqli_error($con)."\n";
                file_put_contents($logfile, $log);
              }
            }
          }
        }

        mysqli_close($con);
        json_response(201, 'Success');
      }



    } elseif ($dataevent == 'MissionCompleted') {
      $log = file_get_contents($logfile);
      $log .= "MissionCompleted event\n";
      file_put_contents($logfile, $log);

      $idafaction = false;
      if ($data['Faction'] == $pmfname || (isset($data['TargetFaction']) && $data['TargetFaction'] == $pmfname)) {
        $idafaction = true;
      }

      if ($idafaction) {
        $log = file_get_contents($logfile);
        $log .= "Data contains correct faction, proceeding\n";
        file_put_contents($logfile, $log);

        $timestamp = strtotime($data['timestamp']);
        $datetimeobj = date_create_from_format('U', $timestamp);
        $datetime = date_format($datetimeobj, 'Y-m-d H:i:s');

        $MissionID = $data['MissionID'];
/*
        $Faction = $data['Faction'];
        $TargetFaction = $data['TargetFaction'];
        $DestinationSystem = $data['DestinationSystem'];
*/
        $reward = array();
        $influence = 0;
        $factionaddress = 0;
        $factionname = 'Unknown';

        foreach($data['FactionEffects'] as $effects) {
          if ($effects['Faction']) {
            $factionname = $effects['Faction'];
          }
          foreach($effects['Influence'] as $effect) {
            if ($effect['SystemAddress']) {
              $factionaddress = $effect['SystemAddress'];

              $systemlistquery = "SELECT * FROM systemlist WHERE systemaddress = '$factionaddress' LIMIT 1";
              if($systemlistresult = mysqli_query($con, $systemlistquery)){
                if(mysqli_num_rows($systemlistresult) < 1){
                  $factionsystem = 'Unknown';
                } else {
                  while($row = mysqli_fetch_array($systemlistresult, MYSQLI_ASSOC)) {
                    $factionsystem = $row['systemname'];
                  }
                }
              } else {
                $factionsystem = 'Unknown';
              }
            }
            if (!$effect['Trend']) {
              $trend = 'Unknown';
            } else {
              $trend = $effect['Trend'];
            }
            if (!$effect['Influence']) {
              $influence = 0;
            } else {
              if ($effect['Influence'] == '+++++') {
                $influence = 5;
              }
              if ($effect['Influence'] == '++++') {
                $influence = 4;
              }
              if ($effect['Influence'] == '+++') {
                $influence = 3;
              }
              if ($effect['Influence'] == '++') {
                $influence = 2;
              }
              if ($effect['Influence'] == '+') {
                $influence = 1;
              }
              if ($effect['Influence'] == '-') {
                $influence = -1;
              }
              if ($effect['Influence'] == '--') {
                $influence = -2;
              }
              if ($effect['Influence'] == '---') {
                $influence = -3;
              }
              if ($effect['Influence'] == '----') {
                $influence = -4;
              }
              if ($effect['Influence'] == '-----') {
                $influence = -5;
              }
            }

            $reward[] = array( 
              "factionaddress" => $factionaddress,  
              "factionsystem" => $factionsystem,  
              "factionname" => $factionname,  
              "trend" => $trend,  
              "influence" => $influence
            );
          }
        }

        if (count($reward) > 1) {
          $faction2address = $reward[1]['factionaddress'];
          $faction2system = addslashes($reward[1]['factionsystem']);
          $faction2name = addslashes($reward[1]['factionname']);
          $faction2reward = $reward[1]['influence'];
          $faction2trend = $reward[1]['trend'];
        } else {
          $faction2address = 0;
          $faction2system = '';
          $faction2name = '';
          $faction2reward = 0;
          $faction2trend = '';
        }
        if (count($reward) > 0) {
          $faction1address = $reward[0]['factionaddress'];
          $faction1system = addslashes($reward[0]['factionsystem']);
          $faction1name = addslashes($reward[0]['factionname']);
          $faction1reward = $reward[0]['influence'];
          $faction1trend = $reward[0]['trend'];
        }
        if (count($reward) < 1) {
          $log = file_get_contents($logfile);
          $log .= "Success, no data to push\n";
          file_put_contents($logfile, $log);
          mysqli_close($con);
          json_response(200, 'Success');
          exit();
        } else {
          $insertrewarddata = "INSERT INTO data_missionrewards (missionid, timestamp, userid, faction1address, faction1system, faction1name, faction1reward, faction1trend, faction2address, faction2system, faction2name, faction2reward, faction2trend)  VALUES ('$MissionID', '$datetime', '$apiid', '$faction1address', '$faction1system', '$faction1name', '$faction1reward', '$faction1trend', '$faction2address', '$faction2system', '$faction2name', '$faction2reward', '$faction2trend')";
          if (mysqli_query($con, $insertrewarddata)) {
            $log = file_get_contents($logfile);
            if ($faction2name) {
              $log .= "Added reward (".$faction1name." / ".$faction2name.") to data_missionrewards\n";
            } else {
              $log .= "Added reward (".$faction1name.") to data_missionrewards\n";
            }
            file_put_contents($logfile, $log);

            foreach($reward as $factionreward) {
              $factionaddress = $factionreward['factionaddress'];
              $factionsystem = addslashes($factionreward['factionsystem']);
              $factionname = $factionreward['factionname'];
              $factioninfreward = $factionreward['influence'];
              $factiontrend = $factionreward['trend'];

              $rewardsnapshotquery = "SELECT * FROM act_snapshot_missionrewards WHERE tickid = '$newtickid' AND SystemAddress = '$factionaddress' AND rewardfaction = '$factionname'";
              if($rewardsnapshotresult = mysqli_query($con, $rewardsnapshotquery)){
                if(mysqli_num_rows($rewardsnapshotresult) > 0){
                  while($row = mysqli_fetch_array($rewardsnapshotresult, MYSQLI_ASSOC)) {
                    $rownumber = $row['id'];
                    $rewardsnapshotdeletequery = "DELETE FROM act_snapshot_missionrewards WHERE id = '$rownumber'";
                    if (mysqli_query($con, $rewardsnapshotdeletequery)) {
                      $log = file_get_contents($logfile);
                      $log .= "Removed missionrewards for ".$factionname." (".$factionsystem.") from active snapshot\n";
                      file_put_contents($logfile, $log);
                    } else {
                      $log = file_get_contents($logfile);
                      $log .= "Couldn't remove missionrewards for ".$factionname." (".$factionsystem.") from active snapshot: ".mysqli_error($con)."\n";
                      file_put_contents($logfile, $log);
                    }
                  }
                } else {
                  $log = file_get_contents($logfile);
                  $log .= "No missionrewards to remove for ".$factionname." (".$factionsystem.") from active snapshot\n";
                  file_put_contents($logfile, $log);                  
                }
              } else {
                $log = file_get_contents($logfile);
                $log .= "SQL error: ".$rewardsnapshotquery."\n";
                file_put_contents($logfile, $log);
              }
              
              $rewardcountquery = "SELECT * FROM data_missionrewards WHERE timestamp > '$newtick' AND (faction1address = '$factionaddress' OR faction2address = '$factionaddress') AND (faction1name = '$factionname' OR faction2name = '$factionname')";
              if($rewardcountresult = mysqli_query($con, $rewardcountquery)){
                if(mysqli_num_rows($rewardcountresult) > 0){
                  $amount = 0;
                  $trend = '';
                  while($row2 = mysqli_fetch_array($rewardcountresult, MYSQLI_ASSOC)) {
                    if ($row2['faction1name'] == $factionname) {
                      $amount = $amount + $row2['faction1reward'];
                    } else if ($row2['faction2name'] == $factionname) {
                      $amount = $amount + $row2['faction2reward'];
                    }
                  }
                } else {
                  $amount = $factioninfreward;
                }

                $insertrewarddatasnapshot = "INSERT INTO act_snapshot_missionrewards (tickid, timestamp, StarSystem, SystemAddress, rewardfaction, rewardtotal, rewardtrend )  VALUES ('$newtickid', '$datetime', '$factionsystem', '$factionaddress', '$factionname', '$amount', '$factiontrend')";
                if (mysqli_query($con, $insertrewarddatasnapshot)) {
                  $log = file_get_contents($logfile);
                  $log .= "Added missionreward totals ".$amount." (".$factionname." / ".$factionsystem.") to active snapshot\n";
                  file_put_contents($logfile, $log);
                } else {
                  $log = file_get_contents($logfile);
                  $log .= "Couldn't add missionreward totals ".$amount." (".$factionname." / ".$factionsystem.") to active snapshot: ".mysqli_error($con)."\n";
                  file_put_contents($logfile, $log);
                }
              } else {
                $log = file_get_contents($logfile);
                $log .= "SQL error: ".$rewardcountquery."\n";
                file_put_contents($logfile, $log);
              }
            }
            $log = file_get_contents($logfile);
            $log .= "Success, all done\n";
            file_put_contents($logfile, $log);
            mysqli_close($con);
            json_response(200, 'Success');
            exit();
          } else {
            $log = file_get_contents($logfile);
            $log .= "SQL query error: ".mysqli_error($con)."\n".$insertrewarddata."\n";
            file_put_contents($logfile, $log);
            json_response(407, 'sql query error', mysqli_error($con));
            exit();
          }
        }
      } else {
        $log = file_get_contents($logfile);
        $log .= "Data doesn't contain correct faction\n";
        file_put_contents($logfile, $log);
        mysqli_close($con);
        json_response(201, 'Success');
      }




















    } elseif ($dataevent == 'MultiSellExplorationData') {
      $log = file_get_contents($logfile);
      $log .= "API not ready yet (MultiSellExplorationData)\n";
      file_put_contents($logfile, $log);
      json_response(202, 'API not ready yet');
      exit();
    } elseif ($dataevent == 'SellExplorationData') {
      $log = file_get_contents($logfile);
      $log .= "API not ready yet (SellExplorationData)\n";
      file_put_contents($logfile, $log);
      json_response(202, 'API not ready yet');
      exit();
    } elseif ($dataevent == 'RedeemVoucher') {
      $log = file_get_contents($logfile);
      $log .= "API not ready yet (RedeemVoucher)\n";
      file_put_contents($logfile, $log);
      json_response(202, 'API not ready yet');
      exit();
    } elseif ($dataevent == 'MarketSell') {
      $log = file_get_contents($logfile);
      $log .= "API not ready yet (MarketSell)\n";
      file_put_contents($logfile, $log);
      json_response(202, 'API not ready yet');
      exit();
    }
  } else {
    $log = file_get_contents($logfile);
    $log .= "Unknown APIkey\n";
    file_put_contents($logfile, $log);
    json_response(403, 'no matching APIkey');
    exit();
  }
} else {
  $log = file_get_contents($logfile);
  $log .= "SQL query error: ".mysqli_error($con)."\n";
  file_put_contents($logfile, $log);
  json_response(402, 'sql query error', mysqli_error($con));
  exit();
}

function json_response($code = 200, $message = null, $error = null) {
  // clear the old headers
  header_remove();
  // set the actual code
  http_response_code($code);
  // set the header to make sure cache is forced
  header("Cache-Control: no-transform,public,max-age=300,s-maxage=900");
  // treat this as json
  header('Content-Type: application/json');
  header('Status: '.$code);
  echo json_encode(array(
    'status' => $code,
    'message' => $message,
    'error' => $error
  ));
}
?>