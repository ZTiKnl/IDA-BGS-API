<?PHP
// include config variables
include('config.inc.php');

$logfile = $loglocation.$logapioutput;
if ($apilogoutput) {
  $log = "API triggered\n";
  file_put_contents($logfile, $log);
}

// connect to db
include($securedbcreds);
$con = mysqli_connect($servername,$username,$password,$database);
if (mysqli_connect_errno()) {
  if ($apilogoutput) {
    $log = file_get_contents($logfile);
    $log .= "Couldn't connect to database\n";
    file_put_contents($logfile, $log);
  }
  json_response(401, 'sql connection error');
  exit();
}

// collect GET vars, apikey, requested data
$data = $_GET;
if (!$data) {
  if ($apilogoutput) {
    $log = file_get_contents($logfile);
    $log .= "400: empty request\n";
    file_put_contents($logfile, $log);
  }
  json_response(400, 'empty request');
  exit();
} else {
  if (!$data['apikey']) {
    if ($apilogoutput) {
      $log = file_get_contents($logfile);
      $log .= "401: No apikey\n";
      file_put_contents($logfile, $log);
    }
    json_response(401, 'need an API key to continue');
    exit();
  } else {
    $apikey = filter_var($data['apikey'], FILTER_SANITIZE_SPECIAL_CHARS);
  }
  if (!$data['request']) {
    if ($apilogoutput) {
      $log = file_get_contents($logfile);
      $log .= "402: no request type given\n";
      file_put_contents($logfile, $log);
    }
    json_response(402, 'need to know what data to provide');
    exit();
  } else {
    $requesttype = filter_var($data['request'], FILTER_SANITIZE_SPECIAL_CHARS);
  }
  if (isset($data['userid'])) {
    $checkid = filter_var($data['userid'], FILTER_SANITIZE_SPECIAL_CHARS);
  }
}

//check API key and get $checkid
$apiquery = "SELECT id FROM apikeys WHERE apikey = '$apikey'";
if($apiresult = mysqli_query($con, $apiquery)){
  if(mysqli_num_rows($apiresult) > 0){
    if ($apilogoutput) {
      $log = file_get_contents($logfile);
      $log .= "APIkey matches\n";
      file_put_contents($logfile, $log);
    }
    
    if(isset($checkid)) {
      $api2query = "SELECT id FROM apikeys WHERE userid = '$checkid'";
      if($api2result = mysqli_query($con, $api2query)){
        while($row2 = mysqli_fetch_array($api2result, MYSQLI_ASSOC)) {
          $checkid = $row2['id'];
        }
      } else {
        $checkid = 0;
      }
    }
    
    
  } else {
    if ($apilogoutput) {
      $log = file_get_contents($logfile);
      $log .= "403: Unknown APIkey\n";
      file_put_contents($logfile, $log);
    }
    json_response(403, 'no matching APIkey');
    exit();
  }
} else {
  if ($apilogoutput) {
    $log = file_get_contents($logfile);
    $log .= "404: SQL query error: ".mysqli_error($con)."\n";
    file_put_contents($logfile, $log);
  }
  json_response(404, 'sql query error', mysqli_error($con));
  exit();
}

// collect tick data
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








// determine $requesttype, gather data, push back json array

/*
tickdata
{
  {"newtickid":"54321","newtick":datetime},
  {"oldtickid":"54321","oldtick":datetime}
}
*/
if ($requesttype == 'tickdata') {
  $tickdata = array("newtick" => $newtick, "newtickid" => $newtickid, "oldtick" => $oldtick, "oldtickid" => $oldtickid);
  
  if ($tickdata) {
    echo json_encode($tickdata);
    if ($apilogoutput) {
      $log = file_get_contents($logfile);
      $log .= "Success, all done\n";
      file_put_contents($logfile, $log);
    }
    exit();
  } else {
    if ($apilogoutput) {
      $log = file_get_contents($logfile);
      $log .= "405: No results\n";
      file_put_contents($logfile, $log);
    }
    json_response(405, 'No results found');
    exit();
  }
}






/*
systemlist
{
  {"systemname":"","systemaddress":30,"influence":20.22,"updatetime":datetime,"uptodate":true},
  {"systemname":"","systemaddress":30,"influence":20.22,"updatetime":datetime,"uptodate":true},
  {"systemname":"","systemaddress":30,"influence":20.22,"updatetime":datetime,"uptodate":true}
}
*/
if ($requesttype == 'systemlist') {
  $systemlistdata = array();
  $systemlistquery = "SELECT systemname, systemaddress FROM systemlist ORDER BY systemname ASC";
  if ($systemlistresult = mysqli_query($con, $systemlistquery)){
    if (mysqli_num_rows($systemlistresult) > 0) {
      $systemcounter = 0;
      while($row = mysqli_fetch_array($systemlistresult, MYSQLI_ASSOC)) {
        $systemname = $row['systemname'];
        $systemaddress = $row['systemaddress'];
        $systeminfluence = getpmfinfluence($systemaddress, $con, $pmfname);
        $updatetime = getlastupdatetime($systemaddress, $con);
        $uptodate = uptodate($updatetime, $newtick);
        $systemlistdata[] = array("systemname" => $systemname, "systemaddress" => $systemaddress, "influence" => $systeminfluence, "updatetime" => $updatetime, "uptodate" => $uptodate);
      }
      echo json_encode($systemlistdata);
      if ($apilogoutput) {
        $log = file_get_contents($logfile);
        $log .= "Success, all done\n";
        file_put_contents($logfile, $log);
      }
      exit();
    } else {
      if ($apilogoutput) {
        $log = file_get_contents($logfile);
        $log .= "407: No results\n";
        file_put_contents($logfile, $log);
      }
      json_response(407, 'No results found');
      exit();
    }
  } else {
    if ($apilogoutput) {
      $log = file_get_contents($logfile);
      $log .= "406: SQL query error: ".mysqli_error($con)."\n";
      file_put_contents($logfile, $log);
    }
    json_response(406, 'sql query error', mysqli_error($con));
    exit();
  }
}


/*
systemoverview
{
  {"systemname":"","influence":30,"updatetime":datetime,"uptodate":true},
}
*/



/*
allsystemsoverview
{
  {"systemname":"","influence":30,"updatetime":datetime,"uptodate":true},
  {"systemname":"","influence":30,"updatetime":datetime,"uptodate":true},
  {"systemname":"","influence":30,"updatetime":datetime,"uptodate":true}
}
*/


/*
report
{
  {
    "systemscount":23,
    "systemsupdatodate"=23
  }.
  {
    "systemname":"asdasd",
    "systemaddress"="dsadsa",
    "type"="influencedrop",
    "status"="Pending",
    "direction"="Down",
    "amount"=4.22,
    "total"=70.4,
    "updatetime"=435345,
    "uptodate"=true
  }.
  {
    "systemname":"asdasd",
    "systemaddress"="dsadsa",
    "type"="conflict",
    "conflictfaction1"="conflictfaction1",
    "conflictfaction1score"=3,
    "conflictfaction1stake"="station",
    "conflictfaction2"="conflictfaction2",
    "conflictfaction2score"=1,
    "conflictfaction2stake"="station",
    "updatetime"=345345,
    "uptodate"=true
  }
}
*/
if ($requesttype == 'report') {
  $reportdata = array();
  $influenceproximitydata = array();
  $expansionpending = false;
  $expansionpendingtrend;
  $expansionrecovering = false;
  $expansionrecoveringtrend;
  $expansionactive = false;
  $systemquery = "SELECT systemname, systemaddress FROM systemlist ORDER BY systemname ASC";
  if ($systemresult = mysqli_query($con, $systemquery)){
    if (mysqli_num_rows($systemresult) > 0) {
      $systemcounter = 0;
      $systemuptodatecount = 0;
      while($row = mysqli_fetch_array($systemresult, MYSQLI_ASSOC)) {
        $systemname = addslashes($row['systemname']);
        $systemaddress = $row['systemaddress'];

        $systemuptodate = false;
        $systemupdatetime = 0;
        $tickid;

        $systemcheckactivesnapshotquery = "SELECT tickid, timestamp FROM act_snapshot_systems WHERE tickid = '$newtickid' AND SystemAddress = '$systemaddress'";
        if ($systemcheckactivesnapshotresult = mysqli_query($con, $systemcheckactivesnapshotquery)){
          if (mysqli_num_rows($systemcheckactivesnapshotresult) > 0) {
            $systemuptodate = true;
            $systemuptodatecount++;
            while($row2 = mysqli_fetch_array($systemcheckactivesnapshotresult, MYSQLI_ASSOC)) {
              $tickid = $row2['tickid'];
              $systemupdatetime = $row2['timestamp'];
            }
          } else {

            $systemchecksnapshotsquery = "SELECT tickid, timestamp FROM snapshot_systems WHERE tickid = '$oldtickid' AND SystemAddress = '$systemaddress' ORDER BY timestamp DESC LIMIT 1";
            if ($systemchecksnapshotsresult = mysqli_query($con, $systemchecksnapshotsquery)){
              if (mysqli_num_rows($systemchecksnapshotsresult) > 0) {
                while($row3 = mysqli_fetch_array($systemchecksnapshotsresult, MYSQLI_ASSOC)) {
                  $tickid = $row3['tickid'];
                  $systemupdatetime = $row3['timestamp'];
                }
              } else {
                $tickid = 'unknown';
                $systemupdatetime = getlastupdatetime($systemaddress, $con);

              }
            }
          }
        }





        // INFLUENCE RISE/DROP WARNING SYSTEM
        $influencedatainactivesnapshot = false;
        $influencedatainsnapshots = false;
        $factioninfluencearray = array();

        $influenceactivesnapshotquery = "SELECT Influence FROM act_snapshot_factions WHERE tickid = '$newtickid' AND SystemAddress = '$systemaddress' AND Name = '".$pmfname."' ORDER BY tickid DESC LIMIT 1";
        if ($influenceactivesnapshotresult = mysqli_query($con, $influenceactivesnapshotquery)){
          if (mysqli_num_rows($influenceactivesnapshotresult) > 0) {
            $influencedatainactivesnapshot = true;
            while($row2 = mysqli_fetch_array($influenceactivesnapshotresult, MYSQLI_ASSOC)) {
              $factioninfluencearray[] = $row2['Influence'];
            }
          }
        }

        if ($influencedatainactivesnapshot) {
          $limiter = 1;
        } else {
          $limiter = 2;
        }
        $influencesnapshotquery = "SELECT Influence FROM snapshot_factions WHERE SystemAddress = '$systemaddress' AND Name = '".$pmfname."' ORDER BY tickid DESC LIMIT ".$limiter; 
        if ($influencesnapshotresult = mysqli_query($con, $influencesnapshotquery)){
          if (mysqli_num_rows($influencesnapshotresult) > 0) {
            // use data from activesnapshot
            $influencedatainsnapshots = true;
            while($row3 = mysqli_fetch_array($influencesnapshotresult, MYSQLI_ASSOC)) {
              $factioninfluencearray[] = $row3['Influence'];
            }
          }
        }
        $direction;
        if ($factioninfluencearray[0] < $factioninfluencearray[1]) {
          $direction = 'down';
        } elseif ($factioninfluencearray[0] > $factioninfluencearray[1]) {
          $direction = 'up';
        } elseif ($factioninfluencearray[0] == $factioninfluencearray[1]) {
          $direction = 'stable';
        }
        $influencechangeamount = round(abs(($factioninfluencearray[0] * 100) - ($factioninfluencearray[1] * 100)), 2);

        if ($influencechangeamount > $systeminfluencewarningpercentage) {
          $type = '';
          if ($direction == 'up') { $type = 'influenceraise'; }
          if ($direction == 'stable') { $type = 'influencestable'; }
          if ($direction == 'down') { $type = 'influencedrop'; }
          $systemamount = round(abs(($factioninfluencearray[0] * 100) - ($factioninfluencearray[1] * 100)), 2);
          $systemtotal = round(($factioninfluencearray[0] * 100), 2);
          $updatetime = $systemupdatetime;
          $uptodate = $systemuptodate;
          $reportdata[] = array(
            "systemname" => $systemname, 
            "systemaddress" => $systemaddress, 
            "reporttype" => 'influence',
            "type" => $type, 
            "amount" => $systemamount, 
            "total" => $systemtotal, 
            "updatetime" => $updatetime, 
            "uptodate" => $uptodate
          );
        }
        // INFLUENCE RISE/DROP WARNING SYSTEM





        // INFLUENCE PROXIMITY WARNING SYSTEM
        $influencedatainactivesnapshot = false;
        $influencedatainsnapshots = false;

        $influenceactivesnapshotquery = "SELECT timestamp, Name, StarSystem, SystemAddress, Influence FROM act_snapshot_factions WHERE tickid = '$newtickid' AND SystemAddress = '$systemaddress' ORDER BY Name ASC";
        if ($influenceactivesnapshotresult = mysqli_query($con, $influenceactivesnapshotquery)){
          if (mysqli_num_rows($influenceactivesnapshotresult) > 0) {
            $influencedatainactivesnapshot = true;
            $tempinfluenceproximityarray = array();
            while($row2 = mysqli_fetch_array($influenceactivesnapshotresult, MYSQLI_ASSOC)) {
              $updatetime = $row2['timestamp'];
              $factionname = $row2['Name'];
              $factioninfluence = round(($row2['Influence'] * 100), 2);

              if ($factionname != 'Pilots\' Federation Local Branch') {
                $tempinfluenceproximityarray[] = array(
                  "timestamp" => $updatetime,
                  "factionname" => $factionname,
                  "factionsystem" => $systemname,  
                  "factionaddress" => $systemaddress,
                  "factioninfluence" => $factioninfluence
                );
              }
            }

            $tempinfluenceproximitycount = count($tempinfluenceproximityarray);
            $tempinfluenceproximitycounter = 0;
            while($tempinfluenceproximitycounter < $tempinfluenceproximitycount) {
              if ($tempinfluenceproximityarray[$tempinfluenceproximitycounter]['factionname'] == $pmfname) {
                $factiontimestamp = $tempinfluenceproximityarray[$tempinfluenceproximitycounter]['timestamp'];
                $factionname = $tempinfluenceproximityarray[$tempinfluenceproximitycounter]['factionname'];
                $factionsystem = $systemname;
                $factionaddress = $systemaddress;
                $factioninfluence = $tempinfluenceproximityarray[$tempinfluenceproximitycounter]['factioninfluence'];

                foreach ($tempinfluenceproximityarray as $faction) {
                  if ($faction['factionname'] != $pmfname) {
                    $testinfluence = $faction['factioninfluence'];
                    $difference = round(abs(($factioninfluence - $testinfluence)), 2);
                    if ($difference < 5) {
                      if ($factiontimestamp > $newtick) {
                        $uptodate = true;
                      } else {
                        $uptodate = false;
                      }
                      $influenceproximitydata[] = array(
                        "timestamp" => $factiontimestamp,
                        "reporttype" => 'influenceproximity',
                        "factionproximity" => $difference,
                        "factionsystem" => $factionsystem,
                        "factionaddress" => $factionaddress,
                        "factionname" => $factionname,
                        "factioninfluence" => $factioninfluence,
                        "faction2name" => $faction['factionname'],
                        "faction2influence" => $testinfluence,
                        "uptodate" => $uptodate
                      );

                    }
                  }
                }
              }

              $tempinfluenceproximitycounter++;
            }
          }
        }
        // INFLUENCE PROXIMITY WARNING SYSTEM

/*
            echo "<br /><br />influenceproximitydata<pre>";
            print_r($influenceproximitydata);
            echo "</pre><br /><br />";
*/






        // CONFLICT WARNING SYSTEM
        $conflictarray = array();
        $conflictdatainactivesnapshot = false;
        $conflictdatainsnapshots = false;

        if ($systemuptodate) {
          $conflictactivesnapshotquery = "SELECT conflicttype, conflictstatus, conflictfaction1name, conflictfaction1stake, conflictfaction1windays, conflictfaction2name, conflictfaction2stake, conflictfaction2windays FROM act_snapshot_conflicts WHERE tickid = '$newtickid' AND SystemAddress = '$systemaddress' AND (conflictfaction1name = '".$pmfname."' OR conflictfaction2name = '".$pmfname."') ORDER BY tickid DESC";
          if ($conflictactivesnapshotresult = mysqli_query($con, $conflictactivesnapshotquery)){
            if (mysqli_num_rows($conflictactivesnapshotresult) > 0) {
              $conflictdatainactivesnapshot = true;
              $i = 0;
              while($row3 = mysqli_fetch_array($conflictactivesnapshotresult, MYSQLI_ASSOC)) {
                $conflictarray[$i]['conflicttype'] = $row3['conflicttype'];
                $conflictarray[$i]['conflictstatus'] = $row3['conflictstatus'];
                $conflictarray[$i]['conflictfaction1name'] = addslashes($row3['conflictfaction1name']);
                $conflictarray[$i]['conflictfaction1stake'] = addslashes($row3['conflictfaction1stake']);
                $conflictarray[$i]['conflictfaction1windays'] = $row3['conflictfaction1windays'];
                $conflictarray[$i]['conflictfaction2name'] = addslashes($row3['conflictfaction2name']);
                $conflictarray[$i]['conflictfaction2stake'] = addslashes($row3['conflictfaction2stake']);
                $conflictarray[$i]['conflictfaction2windays'] = $row3['conflictfaction2windays'];
                $i++;
              }
            }
          }
        } else {
          $conflictsnapshotquery = "SELECT conflicttype, conflictstatus, conflictfaction1name, conflictfaction1stake, conflictfaction1windays, conflictfaction2name, conflictfaction2stake, conflictfaction2windays FROM snapshot_conflicts WHERE tickid = '$oldtickid' AND SystemAddress = '$systemaddress' AND (conflictfaction1name = '".$pmfname."' OR conflictfaction2name = '".$pmfname."') ORDER BY tickid DESC";
          if ($conflictsnapshotresult = mysqli_query($con, $conflictsnapshotquery)){
            if (mysqli_num_rows($conflictsnapshotresult) > 0) {
              $conflictdatainsnapshots = true;
              $i = 0;
              while($row4 = mysqli_fetch_array($conflictsnapshotresult, MYSQLI_ASSOC)) {
                $conflictarray[$i]['conflicttype'] = $row4['conflicttype'];
                $conflictarray[$i]['conflictstatus'] = $row4['conflictstatus'];
                $conflictarray[$i]['conflictfaction1name'] = addslashes($row4['conflictfaction1name']);
                $conflictarray[$i]['conflictfaction1stake'] = addslashes($row4['conflictfaction1stake']);
                $conflictarray[$i]['conflictfaction1windays'] = $row4['conflictfaction1windays'];
                $conflictarray[$i]['conflictfaction2name'] = addslashes($row4['conflictfaction2name']);
                $conflictarray[$i]['conflictfaction2stake'] = addslashes($row4['conflictfaction2stake']);
                $conflictarray[$i]['conflictfaction2windays'] = $row4['conflictfaction2windays'];
                $i++;
              }
            }
          }
        }
        if ($conflictdatainactivesnapshot || $conflictdatainsnapshots) {
          foreach ($conflictarray as &$conflict) {
            $direction;
            if (
              ($conflict['conflictfaction1name'] == $pmfname && $conflict['conflictfaction1windays'] < $conflict['conflictfaction2windays'])
              || 
              ($conflict['conflictfaction2name'] == $pmfname && $conflict['conflictfaction2windays'] < $conflict['conflictfaction1windays'])
            ) {
              $direction = 'down';
            } elseif (
              ($conflict['conflictfaction1name'] == $pmfname && $conflict['conflictfaction1windays'] > $conflict['conflictfaction2windays'])
              || 
              ($conflict['conflictfaction2name'] == $pmfname && $conflict['conflictfaction2windays'] > $conflict['conflictfaction1windays'])
            ) {
              $direction = 'up';
            } elseif ($conflict['conflictfaction1windays'] == $conflict['conflictfaction2windays']) {
              $direction = 'draw';
            }
            $conflicttype = $conflict['conflicttype'];
            $conflictstatus = $conflict['conflictstatus'];
            $conflictfaction1name = $conflict['conflictfaction1name'];
            $conflictfaction1stake = $conflict['conflictfaction1stake'];
            $conflictfaction1windays = $conflict['conflictfaction1windays'];
            $conflictfaction2name = $conflict['conflictfaction2name'];
            $conflictfaction2stake = $conflict['conflictfaction2stake'];
            $conflictfaction2windays = $conflict['conflictfaction2windays'];
            $uptodate = $systemuptodate;
            $reportdata[] = array(
              "systemname" => $systemname, 
              "systemaddress" => $systemaddress, 
              "reporttype" => 'conflict',
              "type" => $conflicttype, 
              "status" => $conflictstatus,
              "direction" => $direction,
              "conflictfaction1" => $conflictfaction1name, 
              "conflictfaction1score" => $conflictfaction1windays, 
              "conflictfaction1stake" => $conflictfaction1stake, 
              "conflictfaction2" => $conflictfaction2name, 
              "conflictfaction2score" => $conflictfaction2windays, 
              "conflictfaction2stake" => $conflictfaction2stake, 
              "updatetime" => $systemupdatetime, 
              "uptodate" => $systemuptodate
            );
          }
        }
        // CONFLICT WARNING SYSTEM





        // STATE WARNING SYSTEM
        $statearray = array();
        $statedatainactivesnapshot = false;
        $statedatainsnapshots = false;

        if ($systemuptodate) {
          $stateactivesnapshotquery = "SELECT * FROM act_snapshot_factions WHERE tickid = '$newtickid' AND SystemAddress = '$systemaddress' AND Name = '".$pmfname."' ORDER BY tickid DESC LIMIT 1";
          if ($stateactivesnapshotresult = mysqli_query($con, $stateactivesnapshotquery)){
            if (mysqli_num_rows($stateactivesnapshotresult) > 0) {
              $statedatainactivesnapshot = true;
              $i = 0;
              while($row3 = mysqli_fetch_array($stateactivesnapshotresult, MYSQLI_ASSOC)) {
                $statearray[$i]['stateid'] = $row3['id'];
                $statearray[$i]['statetimestamp'] = $row3['timestamp'];
                $statearray[$i]['stateName'] = addslashes($row3['Name']);
                $statearray[$i]['statefactionsystem'] = $row3['SystemAddress'];
                $statearray[$i]['statefactionaddress'] = addslashes($row3['StarSystem']);
                $statearray[$i]['stateGovernment'] = $row3['Government'];
                $statearray[$i]['stateInfluence'] = $row3['Influence'];
                $statearray[$i]['stateAllegiance'] = $row3['Allegiance'];
                $statearray[$i]['stateHappiness'] = $row3['Happiness'];
                $statearray[$i]['stateTerroristAttack'] = $row3['stateTerroristAttack'];
                $statearray[$i]['pendingTerroristAttack'] = $row3['pendingTerroristAttack'];
                $statearray[$i]['pendingTerroristAttacktrend'] = $row3['pendingTerroristAttacktrend'];
                $statearray[$i]['recTerroristAttack'] = $row3['recTerroristAttack'];
                $statearray[$i]['recTerroristAttacktrend'] = $row3['recTerroristAttacktrend'];
                $statearray[$i]['statePirateAttack'] = $row3['statePirateAttack'];
                $statearray[$i]['pendingPirateAttack'] = $row3['pendingPirateAttack'];
                $statearray[$i]['pendingPirateAttacktrend'] = $row3['pendingPirateAttacktrend'];
                $statearray[$i]['recPirateAttack'] = $row3['recPirateAttack'];
                $statearray[$i]['recPirateAttacktrend'] = $row3['recPirateAttacktrend'];
                $statearray[$i]['stateRetreat'] = $row3['stateRetreat'];
                $statearray[$i]['pendingRetreat'] = $row3['pendingRetreat'];
                $statearray[$i]['pendingRetreattrend'] = $row3['pendingRetreattrend'];
                $statearray[$i]['recRetreat'] = $row3['recRetreat'];
                $statearray[$i]['recRetreattrend'] = $row3['recRetreattrend'];
                $statearray[$i]['stateLockdown'] = $row3['stateLockdown'];
                $statearray[$i]['pendingLockdown'] = $row3['pendingLockdown'];
                $statearray[$i]['pendingLockdowntrend'] = $row3['pendingLockdowntrend'];
                $statearray[$i]['recLockdown'] = $row3['recLockdown'];
                $statearray[$i]['recLockdowntrend'] = $row3['recLockdowntrend'];
                $statearray[$i]['stateFamine'] = $row3['stateFamine'];
                $statearray[$i]['pendingFamine'] = $row3['pendingFamine'];
                $statearray[$i]['pendingFaminetrend'] = $row3['pendingFaminetrend'];
                $statearray[$i]['recFamine'] = $row3['recFamine'];
                $statearray[$i]['recFaminetrend'] = $row3['recFaminetrend'];
                $statearray[$i]['stateExpansion'] = $row3['stateExpansion'];
                $statearray[$i]['pendingExpansion'] = $row3['pendingExpansion'];
                $statearray[$i]['pendingExpansiontrend'] = $row3['pendingExpansiontrend'];
                $statearray[$i]['recExpansion'] = $row3['recExpansion'];
                $statearray[$i]['recExpansiontrend'] = $row3['recExpansiontrend'];
                $statearray[$i]['stateDrought'] = $row3['stateDrought'];
                $statearray[$i]['pendingDrought'] = $row3['pendingDrought'];
                $statearray[$i]['pendingDroughttrend'] = $row3['pendingDroughttrend'];
                $statearray[$i]['recDrought'] = $row3['recDrought'];
                $statearray[$i]['recDroughttrend'] = $row3['recDroughttrend'];
                $statearray[$i]['stateCivilUnrest'] = $row3['stateCivilUnrest'];
                $statearray[$i]['pendingCivilUnrest'] = $row3['pendingCivilUnrest'];
                $statearray[$i]['pendingCivilUnresttrend'] = $row3['pendingCivilUnresttrend'];
                $statearray[$i]['recCivilUnrest'] = $row3['recCivilUnrest'];
                $statearray[$i]['recCivilUnresttrend'] = $row3['recCivilUnresttrend'];
                $statearray[$i]['stateBust'] = $row3['stateBust'];
                $statearray[$i]['pendingBust'] = $row3['pendingBust'];
                $statearray[$i]['pendingBusttrend'] = $row3['pendingBusttrend'];
                $statearray[$i]['recBust'] = $row3['recBust'];
                $statearray[$i]['recBusttrend'] = $row3['recBusttrend'];
                $statearray[$i]['stateBlight'] = $row3['stateBlight'];
                $statearray[$i]['pendingBlight'] = $row3['pendingBlight'];
                $statearray[$i]['pendingBlighttrend'] = $row3['pendingBlighttrend'];
                $statearray[$i]['recBlight'] = $row3['recBlight'];
                $statearray[$i]['recBlighttrend'] = $row3['recBlighttrend'];
                $statearray[$i]['stateTradeWar'] = $row3['stateTradeWar'];
                $statearray[$i]['pendingTradeWar'] = $row3['pendingTradeWar'];
                $statearray[$i]['pendingTradeWartrend'] = $row3['pendingTradeWartrend'];
                $statearray[$i]['recTradeWar'] = $row3['recTradeWar'];
                $statearray[$i]['recTradeWartrend'] = $row3['recTradeWartrend'];
                $statearray[$i]['stateColdWar'] = $row3['stateColdWar'];
                $statearray[$i]['pendingColdWar'] = $row3['pendingColdWar'];
                $statearray[$i]['pendingColdWartrend'] = $row3['pendingColdWartrend'];
                $statearray[$i]['recColdWar'] = $row3['recColdWar'];
                $statearray[$i]['recColdWartrend'] = $row3['recColdWartrend'];
                $i++;
              }
            }
          }
        } else {
          $statesnapshotquery = "SELECT * FROM snapshot_factions WHERE tickid = '$oldtickid' AND SystemAddress = '$systemaddress' AND Name = '".$pmfname."' ORDER BY tickid DESC LIMIT 1";
          if ($statesnapshotresult = mysqli_query($con, $statesnapshotquery)){
            if (mysqli_num_rows($statesnapshotresult) > 0) {
              $statedatainsnapshots = true;
              $i = 0;
              while($row4 = mysqli_fetch_array($statesnapshotresult, MYSQLI_ASSOC)) {
                $statearray[$i]['stateid'] = $row4['id'];
                $statearray[$i]['statetimestamp'] = $row4['timestamp'];
                $statearray[$i]['stateName'] = addslashes($row4['Name']);
                $statearray[$i]['statefactionsystem'] = $row4['SystemAddress'];
                $statearray[$i]['statefactionaddress'] = addslashes($row4['StarSystem']);
                $statearray[$i]['stateGovernment'] = $row4['Government'];
                $statearray[$i]['stateInfluence'] = $row4['Influence'];
                $statearray[$i]['stateAllegiance'] = $row4['Allegiance'];
                $statearray[$i]['stateHappiness'] = $row4['Happiness'];
                $statearray[$i]['stateTerroristAttack'] = $row4['stateTerroristAttack'];
                $statearray[$i]['pendingTerroristAttack'] = $row4['pendingTerroristAttack'];
                $statearray[$i]['pendingTerroristAttacktrend'] = $row4['pendingTerroristAttacktrend'];
                $statearray[$i]['recTerroristAttack'] = $row4['recTerroristAttack'];
                $statearray[$i]['recTerroristAttacktrend'] = $row4['recTerroristAttacktrend'];
                $statearray[$i]['statePirateAttack'] = $row4['statePirateAttack'];
                $statearray[$i]['pendingPirateAttack'] = $row4['pendingPirateAttack'];
                $statearray[$i]['pendingPirateAttacktrend'] = $row4['pendingPirateAttacktrend'];
                $statearray[$i]['recPirateAttack'] = $row4['recPirateAttack'];
                $statearray[$i]['recPirateAttacktrend'] = $row4['recPirateAttacktrend'];
                $statearray[$i]['stateRetreat'] = $row4['stateRetreat'];
                $statearray[$i]['pendingRetreat'] = $row4['pendingRetreat'];
                $statearray[$i]['pendingRetreattrend'] = $row4['pendingRetreattrend'];
                $statearray[$i]['recRetreat'] = $row4['recRetreat'];
                $statearray[$i]['recRetreattrend'] = $row4['recRetreattrend'];
                $statearray[$i]['stateLockdown'] = $row4['stateLockdown'];
                $statearray[$i]['pendingLockdown'] = $row4['pendingLockdown'];
                $statearray[$i]['pendingLockdowntrend'] = $row4['pendingLockdowntrend'];
                $statearray[$i]['recLockdown'] = $row4['recLockdown'];
                $statearray[$i]['recLockdowntrend'] = $row4['recLockdowntrend'];
                $statearray[$i]['stateFamine'] = $row4['stateFamine'];
                $statearray[$i]['pendingFamine'] = $row4['pendingFamine'];
                $statearray[$i]['pendingFaminetrend'] = $row4['pendingFaminetrend'];
                $statearray[$i]['recFamine'] = $row4['recFamine'];
                $statearray[$i]['recFaminetrend'] = $row4['recFaminetrend'];
                $statearray[$i]['stateExpansion'] = $row4['stateExpansion'];
                $statearray[$i]['pendingExpansion'] = $row4['pendingExpansion'];
                $statearray[$i]['pendingExpansiontrend'] = $row4['pendingExpansiontrend'];
                $statearray[$i]['recExpansion'] = $row4['recExpansion'];
                $statearray[$i]['recExpansiontrend'] = $row4['recExpansiontrend'];
                $statearray[$i]['stateDrought'] = $row4['stateDrought'];
                $statearray[$i]['pendingDrought'] = $row4['pendingDrought'];
                $statearray[$i]['pendingDroughttrend'] = $row4['pendingDroughttrend'];
                $statearray[$i]['recDrought'] = $row4['recDrought'];
                $statearray[$i]['recDroughttrend'] = $row4['recDroughttrend'];
                $statearray[$i]['stateCivilUnrest'] = $row4['stateCivilUnrest'];
                $statearray[$i]['pendingCivilUnrest'] = $row4['pendingCivilUnrest'];
                $statearray[$i]['pendingCivilUnresttrend'] = $row4['pendingCivilUnresttrend'];
                $statearray[$i]['recCivilUnrest'] = $row4['recCivilUnrest'];
                $statearray[$i]['recCivilUnresttrend'] = $row4['recCivilUnresttrend'];
                $statearray[$i]['stateBust'] = $row4['stateBust'];
                $statearray[$i]['pendingBust'] = $row4['pendingBust'];
                $statearray[$i]['pendingBusttrend'] = $row4['pendingBusttrend'];
                $statearray[$i]['recBust'] = $row4['recBust'];
                $statearray[$i]['recBusttrend'] = $row4['recBusttrend'];
                $statearray[$i]['stateBlight'] = $row4['stateBlight'];
                $statearray[$i]['pendingBlight'] = $row4['pendingBlight'];
                $statearray[$i]['pendingBlighttrend'] = $row4['pendingBlighttrend'];
                $statearray[$i]['recBlight'] = $row4['recBlight'];
                $statearray[$i]['recBlighttrend'] = $row4['recBlighttrend'];
                $statearray[$i]['stateTradeWar'] = $row4['stateTradeWar'];
                $statearray[$i]['pendingTradeWar'] = $row4['pendingTradeWar'];
                $statearray[$i]['pendingTradeWartrend'] = $row4['pendingTradeWartrend'];
                $statearray[$i]['recTradeWar'] = $row4['recTradeWar'];
                $statearray[$i]['recTradeWartrend'] = $row4['recTradeWartrend'];
                $statearray[$i]['stateColdWar'] = $row4['stateColdWar'];
                $statearray[$i]['pendingColdWar'] = $row4['pendingColdWar'];
                $statearray[$i]['pendingColdWartrend'] = $row4['pendingColdWartrend'];
                $statearray[$i]['recColdWar'] = $row4['recColdWar'];
                $statearray[$i]['recColdWartrend'] = $row4['recColdWartrend'];
                $i++;
              }
            }
          }
        }

        if ($statedatainactivesnapshot || $statedatainsnapshots) {
          foreach ($statearray as &$state) {
            $stateid = $state['stateid'];
            $statetimestamp = $state['statetimestamp'];
            $stateName = $state['stateName'];
            $statefactionsystem = $state['statefactionsystem'];
            $statefactionaddress = $state['statefactionaddress'];
            $stateGovernment = $state['stateGovernment'];
            $stateInfluence = $state['stateInfluence'];
            $stateAllegiance = $state['stateAllegiance'];
            $stateHappiness = $state['stateHappiness'];
            $uptodate = $systemuptodate;
            $addstatetoreport = false;
            $statetype;
            $statestatus;
            $statetrend;

            if ($state['stateTerroristAttack'] == 1 || $state['pendingTerroristAttack'] == 1 || $state['recTerroristAttack'] == 1) {
              $statetype = 'Terrorist Attack';
              $addstatetoreport = true;
              if ($state['recTerroristAttack'] == 1) {
                $statestatus = 'Recovering';
                $statetrend = $state['recTerroristAttacktrend'];
              } else if ($state['pendingTerroristAttack'] == 1) {
                $statestatus = 'Pending';
                $statetrend = $state['pendingTerroristAttacktrend'];
              } else {
                $statestatus = 'Active';
                $statetrend = false;
              }
            }
            if ($state['statePirateAttack'] == 1 || $state['pendingPirateAttack'] == 1 || $state['recPirateAttack'] == 1) {
              $statetype = 'Pirate Attack';
              $addstatetoreport = true;
              if ($state['recPirateAttack'] == 1) {
                $statestatus = 'Recovering';
                $statetrend = $state['recPirateAttacktrend'];
              } else if ($state['pendingPirateAttack'] == 1) {
                $statestatus = 'Pending';
                $statetrend = $state['pendingPirateAttacktrend'];
              } else {
                $statestatus = 'Active';
                $statetrend = false;
              }
            }
            if ($state['stateRetreat'] == 1 || $state['pendingRetreat'] == 1 || $state['recRetreat'] == 1) {
              $statetype = 'Retreat';
              $addstatetoreport = true;
              if ($state['recRetreat'] == 1) {
                $statestatus = 'Recovering';
                $statetrend = $state['recRetreattrend'];
              } else if ($state['pendingRetreat'] == 1) {
                $statestatus = 'Pending';
                $statetrend = $state['pendingRetreattrend'];
              } else {
                $statestatus = 'Active';
                $statetrend = false;
              }
            }
            if ($state['stateLockdown'] == 1 || $state['pendingLockdown'] == 1 || $state['recLockdown'] == 1) {
              $statetype = 'Lockdown';
              $addstatetoreport = true;
              if ($state['recLockdown'] == 1) {
                $statestatus = 'Recovering';
                $statetrend = $state['recLockdowntrend'];
              } else if ($state['pendingLockdown'] == 1) {
                $statestatus = 'Pending';
                $statetrend = $state['pendingLockdowntrend'];
              } else {
                $statestatus = 'Active';
                $statetrend = false;
              }
            }
            if ($state['stateFamine'] == 1 || $state['pendingFamine'] == 1 || $state['recFamine'] == 1) {
              $statetype = 'Famine';
              $addstatetoreport = true;
              if ($state['recFamine'] == 1) {
                $statestatus = 'Recovering';
                $statetrend = $state['recFaminetrend'];
              } else if ($state['pendingFamine'] == 1) {
                $statestatus = 'Pending';
                $statetrend = $state['pendingFaminetrend'];
              } else {
                $statestatus = 'Active';
                $statetrend = false;
              }
            }
            if ($statedatainactivesnapshot) {
              if ($state['stateExpansion'] == 1 || $state['pendingExpansion'] == 1 || $state['recExpansion'] == 1) {
                $addstatetoreport = false;
                if ($state['recExpansion'] == 1) {
                  $expansionrecovering = true;
                  $expansionrecoveringtrend = $state['recExpansiontrend'];
                } else if ($state['pendingExpansion'] == 1) {
                  $expansionpending = true;
                  $expansionpendingtrend = $state['pendingExpansiontrend'];
                } else {
                  $expansionactive = true;
                }
              }
            }
            if ($state['stateDrought'] == 1 || $state['pendingDrought'] == 1 || $state['recDrought'] == 1) {
              $statetype = 'Drought';
              $addstatetoreport = true;
              if ($state['recDrought'] == 1) {
                $statestatus = 'Recovering';
                $statetrend = $state['recDroughttrend'];
              } else if ($state['pendingDrought'] == 1) {
                $statestatus = 'Pending';
                $statetrend = $state['pendingDroughttrend'];
              } else {
                $statestatus = 'Active';
                $statetrend = false;
              }
            }
            if ($state['stateCivilUnrest'] == 1 || $state['pendingCivilUnrest'] == 1 || $state['recCivilUnrest'] == 1) {
              $statetype = 'Civil Unrest';
              $addstatetoreport = true;
              if ($state['recCivilUnrest'] == 1) {
                $statestatus = 'Recovering';
                $statetrend = $state['recCivilUnresttrend'];
              } else if ($state['pendingCivilUnrest'] == 1) {
                $statestatus = 'Pending';
                $statetrend = $state['pendingCivilUnresttrend'];
              } else {
                $statestatus = 'Active';
                $statetrend = false;
              }
            }
            if ($state['stateBust'] == 1 || $state['pendingBust'] == 1 || $state['recBust'] == 1) {
              $statetype = 'Bust';
              $addstatetoreport = true;
              if ($state['recBust'] == 1) {
                $statestatus = 'Recovering';
                $statetrend = $state['recBusttrend'];
              } else if ($state['pendingBust'] == 1) {
                $statestatus = 'Pending';
                $statetrend = $state['pendingBusttrend'];
              } else {
                $statestatus = 'Active';
                $statetrend = false;
              }
            }
            if ($state['stateBlight'] == 1 || $state['pendingBlight'] == 1 || $state['recBlight'] == 1) {
              $statetype = 'Blight';
              $addstatetoreport = true;
              if ($state['recBlight'] == 1) {
                $statestatus = 'Recovering';
                $statetrend = $state['recBlighttrend'];
              } else if ($state['pendingBlight'] == 1) {
                $statestatus = 'Pending';
                $statetrend = $state['pendingBlighttrend'];
              } else {
                $statestatus = 'Active';
                $statetrend = false;
              }
            }
            if ($state['stateTradeWar'] == 1 || $state['pendingTradeWar'] == 1 || $state['recTradeWar'] == 1) {
              $statetype = 'Trade War';
              echo $statetype;
              $addstatetoreport = true;
              if ($state['recTradeWar'] == 1) {
                $statestatus = 'Recovering';
                $statetrend = $state['recTradeWartrend'];
              } else if ($state['pendingTradeWar'] == 1) {
                $statestatus = 'Pending';
                $statetrend = $state['pendingTradeWartrend'];
              } else {
                $statestatus = 'Active';
                $statetrend = false;
              }
            }
            if ($state['stateColdWar'] == 1 || $state['pendingColdWar'] == 1 || $state['recColdWar'] == 1) {
              $statetype = 'Cold War';;
              $addstatetoreport = true;
              if ($state['recColdWar'] == 1) {
                $statestatus = 'Recovering';
                $statetrend = $state['recColdWartrend'];
              } else if ($state['pendingColdWar'] == 1) {
                $statestatus = 'Pending';
                $statetrend = $state['pendingColdWartrend'];
              } else {
                $statestatus = 'Active';
                $statetrend = false;
              }
            }

            if ($addstatetoreport == true) {
              $reportdata[] = array(
                "systemname" => $systemname, 
                "systemaddress" => $systemaddress, 
                "reporttype" => 'state',
                "type" => $statetype, 
                "status" => $statestatus,
                "direction" => $statetrend,
                "updatetime" => $systemupdatetime, 
                "uptodate" => $systemuptodate
              );
            }
          }
        }
        // STATE WARNING SYSTEM

        $systemcounter++;
      }




      $overviewdata[] = array(
        "systemcount" => $systemcounter, 
        "systemuptodatecount" => $systemuptodatecount, 
        "reporttype" => 'overview'
      );

      $expansiondata = array();
      if ($expansionrecovering || $expansionpending || $expansionactive) {
        $statetype = 'Expansion';
        if ($expansionrecovering) {
          $statestatus = 'Recovering';
          $statetrend = $expansionrecoveringtrend;
          $expansiondata[] = array(
            "systemname" => 'All systems', 
            "systemaddress" => 0, 
            "reporttype" => 'expansion',
            "type" => $statetype, 
            "status" => $statestatus,
            "direction" => $statetrend
          );
        }
        if ($expansionpending) {
          $statestatus = 'Pending';
          $statetrend = $expansionpendingtrend;
          $expansiondata[] = array(
            "systemname" => 'All systems', 
            "systemaddress" => 0, 
            "reporttype" => 'expansion',
            "type" => $statetype, 
            "status" => $statestatus,
            "direction" => $statetrend
          );
        }
        if ($expansionactive) {
          $statestatus = 'Active';
          $statetrend = false;
          $expansiondata[] = array(
            "systemname" => 'All systems', 
            "systemaddress" => 0, 
            "reporttype" => 'expansion',
            "type" => $statetype, 
            "status" => $statestatus,
            "direction" => $statetrend
          );              
        }
      }


      $res = array_merge($overviewdata, $expansiondata);
      $res2 = array_merge($res, $influenceproximitydata);
      $finalres = array_merge($res2, $reportdata);

      if ($finalres) {
        echo json_encode($finalres);
        if ($apilogoutput) {
          $log = file_get_contents($logfile);
          $log .= "Success, all done\n";
          file_put_contents($logfile, $log);
        }
        exit();
      } else {
        if ($apilogoutput) {
          $log = file_get_contents($logfile);
          $log .= "413: No data\n";
          file_put_contents($logfile, $log);
        }
        json_response(413, 'No data');
        exit();
      }
    } else {
      if ($apilogoutput) {
        $log = file_get_contents($logfile);
        $log .= "412: No data: ".mysqli_error($con)."\n";
        file_put_contents($logfile, $log);
      }
      json_response(412, 'No data', mysqli_error($con));
      exit();
    }
  } else {
    if ($apilogoutput) {
      $log = file_get_contents($logfile);
      $log .= "411: SQL query error: ".mysqli_error($con)."\n";
      file_put_contents($logfile, $log);
    }
    json_response(411, 'sql query error', mysqli_error($con));
    exit();
  }


}









/*
requestapikey
{"apikey":"246857463485","new":true}
*/

if ($requesttype == 'apikey') {
  $userid = 0;
  if ($data['user']) {
    $userid = filter_var($data['user'], FILTER_SANITIZE_SPECIAL_CHARS);
  } else {
    if ($apilogoutput) {
      $log = file_get_contents($logfile);
      $log .= "408: No userid presented\n";
      file_put_contents($logfile, $log);
    }
    json_response(408, 'need a user to create this key for');
    exit();
  }

  $apikey = 0;
  // check if userid already exist, if so, fetch apikey
  $checkapikeyquery = "SELECT userid, apikey FROM apikeys WHERE userid = '".$userid."'";
  if ($checkapikeyresult = mysqli_query($con, $checkapikeyquery)){
    if (mysqli_num_rows($checkapikeyresult) > 0) {
      while($row = mysqli_fetch_array($checkapikeyresult, MYSQLI_ASSOC)) {
        $apikey = $row['apikey'];
      }
    }
  } else {
    if ($apilogoutput) {
      $log = file_get_contents($logfile);
      $log .= "409: SQL query error: ".mysqli_error($con)."\n";
      file_put_contents($logfile, $log);
    }
    json_response(409, 'sql query error', mysqli_error($con));
    exit();
  }

  if ($apikey !== 0) {
    $apikeydata = array("apikey" => $apikey, "new" => false);
    echo json_encode($apikeydata);
    if ($apilogoutput) {
      $log = file_get_contents($logfile);
      $log .= "Success, all done\n";
      file_put_contents($logfile, $log);
    }
    exit();
  } else {
    $newapikey = sha1($userid);
    $addapikeyquery = "INSERT INTO apikeys (apikey, userid) VALUES ('".$newapikey."', '".$userid."');";
    if (mysqli_query($con, $addapikeyquery)){
      $apikeydata = array("apikey" => $newapikey, "new" => true);
      echo json_encode($apikeydata);
      if ($apilogoutput) {
        $log = file_get_contents($logfile);
        $log .= "Success, all done\n";
        file_put_contents($logfile, $log);
      }
      exit();
    } else {
      if ($apilogoutput) {
        $log = file_get_contents($logfile);
        $log .= "410: SQL query error: ".$userid." / ".$newapikey." ".mysqli_error($con)."\n";
        file_put_contents($logfile, $log);
      }
      json_response(410, 'sql query error', mysqli_error($con));
      exit();
    }
  }
}





if ($requesttype == 'uptodate') {
  $uptodate = true;
  $systemquery = "SELECT systemaddress FROM systemlist ORDER BY systemname ASC";
  if ($systemresult = mysqli_query($con, $systemquery)){
    if (mysqli_num_rows($systemresult) > 0) {
      $systemcounter = 0;
      while($row = mysqli_fetch_array($systemresult, MYSQLI_ASSOC)) {
        $systemaddress = $row['systemaddress'];

        $systemuptodate = false;
        $systemupdatetime = 0;
        $tickid;

        $systemcheckactivesnapshotquery = "SELECT tickid, timestamp FROM act_snapshot_systems WHERE tickid = '$newtickid' AND SystemAddress = '$systemaddress'";
        if ($systemcheckactivesnapshotresult = mysqli_query($con, $systemcheckactivesnapshotquery)){
          if (mysqli_num_rows($systemcheckactivesnapshotresult) === 0) {
            $uptodate = false;
          }
        }
      }
      $uptodatedata = array("uptodate" => $uptodate);
      echo json_encode($uptodatedata);
      if ($apilogoutput) {
        $log = file_get_contents($logfile);
        $log .= "Success, all done\n";
        file_put_contents($logfile, $log);
      }
      exit();
    } else {
      if ($apilogoutput) {
        $log = file_get_contents($logfile);
        $log .= "414: No data: ".mysqli_error($con)."\n";
        file_put_contents($logfile, $log);
      }
      json_response(414, 'No data', mysqli_error($con));
      exit();
    }
  } else {
    if ($apilogoutput) {
      $log = file_get_contents($logfile);
      $log .= "413: SQL query error: ".mysqli_error($con)."\n";
      file_put_contents($logfile, $log);
    }
    json_response(413, 'sql query error', mysqli_error($con));
    exit();
  }
}
























/*
influence
{
  {
    "timestamp":"000",
    "systemaddress":"asdasd",
    "systemname":"asdasd",
    "factionname"="dsadsa",
    "influence"="conflict",
    "direction"=0,
  }
}
*/
if ($requesttype == 'influence') {
  $reportdata = array();
  $influencequery = "SELECT timestamp, StarSystem, SystemAddress, rewardfaction, rewardtotal, rewardtrend FROM act_snapshot_missionrewards WHERE tickid = '$newtickid' ORDER BY StarSystem ASC, rewardfaction ASC";

  if ($influenceresult = mysqli_query($con, $influencequery)){
    if (mysqli_num_rows($influenceresult) > 0) {
      while($row = mysqli_fetch_array($influenceresult, MYSQLI_ASSOC)) {
        $infrewardtimestamp = $row['timestamp'];
        $infrewardsystem = $row['StarSystem'];
        $infrewardaddress = $row['SystemAddress'];
        $infrewardfaction = $row['rewardfaction'];
        $infrewardtotal = $row['rewardtotal'];
        $infrewardtrend = $row['rewardtrend'];

        if ($infrewardsystem != '' && $infrewardsystem != 'Unknown' && $infrewardsystem != NULL) {
          $reportdata[] = array(
            "timestamp" => $infrewardtimestamp, 
            "systemname" => $infrewardsystem, 
            "factionname" => $infrewardfaction, 
            "influence" => $infrewardtotal, 
            "direction" => $infrewardtrend
          );
        }
      }



      echo json_encode($reportdata);
      if ($apilogoutput) {
        $log = file_get_contents($logfile);
        $log .= "Success, all done\n";
        file_put_contents($logfile, $log);
      }
      exit();

    } else {
      if ($apilogoutput) {
        $log = file_get_contents($logfile);
        $log .= "416: No data: ".mysqli_error($con)."\n";
        file_put_contents($logfile, $log);
      }
      json_response(416, 'No data', mysqli_error($con));
      exit();
    }
  } else {
    if ($apilogoutput) {
      $log = file_get_contents($logfile);
      $log .= "415: SQL query error: ".mysqli_error($con)."\n";
      file_put_contents($logfile, $log);
    }
    json_response(415, 'sql query error', mysqli_error($con));
    exit();
  }
}


/*
myinfluence
{
  {
    "lastupdate":"000",
    "systemaddress":"asdasd",
    "systemname":"asdasd",
    "factionname"="dsadsa",
    "influence"=15
  }
}
*/
if ($requesttype == 'myinfluence') {
  $reportdata = array();
  $influencesystemsquery = "SELECT faction1address, faction2address FROM data_missionrewards WHERE timestamp > '$newtick' AND userid = '$checkid'";
  if ($influencesystemsresult = mysqli_query($con, $influencesystemsquery)){
    if (mysqli_num_rows($influencesystemsresult) > 0) {
      $systemsarray = array();
      while($row = mysqli_fetch_array($influencesystemsresult, MYSQLI_ASSOC)) {
        if ($row['faction1address'] != NULL && $row['faction1address'] != '' && $row['faction1address'] != 0) {
          $systemsarray[] = $row['faction1address'];
        }
        if ($row['faction2address'] != NULL && $row['faction2address'] != '' && $row['faction2address'] != 0) {
          $systemsarray[] = $row['faction2address'];
        }
      }
      $systemsarray = array_unique($systemsarray);
      sort($systemsarray);
      $factionsarray = array();
      foreach ($systemsarray as $infsystemaddress) {
        $influencesystemfactionsquery = "SELECT faction1address, faction1name, faction2address, faction2name FROM data_missionrewards WHERE timestamp > '$newtick' AND userid = '$checkid' AND (faction1address = '$infsystemaddress' || faction2address = '$infsystemaddress')";
        if ($influencesystemfactionsresult = mysqli_query($con, $influencesystemfactionsquery)){
          if (mysqli_num_rows($influencesystemfactionsresult) > 0) {
            while($row2 = mysqli_fetch_array($influencesystemfactionsresult, MYSQLI_ASSOC)) {
              $factionsarray[] = $row2['faction1name'];
              if ($row2['faction2name'] != NULL && $row2['faction2address'] != '') {
                $factionsarray[] = $row2['faction1name'];
              }
            }
            $factionsarray = array_unique($factionsarray);
            sort($factionsarray);
            
            foreach ($factionsarray as $inffactionname) {
              $total = 0;
              $inftimestamp = 0;
              $infsystemname = '';
              $influencetotalquery = "SELECT timestamp, faction1address, faction1system, faction1name, faction1reward, faction2address, faction2system, faction2name, faction2reward FROM data_missionrewards WHERE timestamp > '$newtick' AND userid = '$checkid' AND (faction1address = '$infsystemaddress' || faction2address = '$infsystemaddress') AND (faction1name = '$inffactionname' || faction2name = '$inffactionname') ORDER BY timestamp ASC";
              if ($influencetotalresult = mysqli_query($con, $influencetotalquery)){
                if (mysqli_num_rows($influencetotalresult) > 0) {
                  while($row3 = mysqli_fetch_array($influencetotalresult, MYSQLI_ASSOC)) {
                    if ($row3['faction1name'] == $inffactionname && $row3['faction1address'] == $infsystemaddress) {
                      $inftimestamp = $row3['timestamp'];
                      $infsystemname = $row3['faction1system'];
                      $reward = $row3['faction1reward'];
                      $total = $total + $reward;
                    }
                    if ($row3['faction2name'] == $inffactionname && $row3['faction2address'] == $infsystemaddress) {
                      $inftimestamp = $row3['timestamp'];
                      $infsystemname = $row3['faction2system'];
                      $reward = $row3['faction2reward'];
                      $total = $total + $reward;
                    }
                  }
                  
                }
              }
              if ($infsystemname != '' && $infsystemname != 'Unknown') {
                $reportdata[] = array(
                  "lastupdate" => $inftimestamp, 
                  "systemaddress" => $infsystemaddress, 
                  "systemname" => $infsystemname, 
                  "factionname" => $inffactionname, 
                  "influence" => $total
                );
              }
            }
          }
        }
      }

      echo json_encode($reportdata);
      if ($apilogoutput) {
        $log = file_get_contents($logfile);
        $log .= "Success, all done\n";
        file_put_contents($logfile, $log);
      }
      exit();

    } else {
      if ($apilogoutput) {
        $log = file_get_contents($logfile);
        $log .= "418: No data: ".mysqli_error($con)."\n";
        file_put_contents($logfile, $log);
      }
      json_response(418, 'No data', mysqli_error($con));
      exit();
    }
  } else {
    if ($apilogoutput) {
      $log = file_get_contents($logfile);
      $log .= "417: SQL query error: ".mysqli_error($con)."\n";
      file_put_contents($logfile, $log);
    }
    json_response(417, 'sql query error', mysqli_error($con));
    exit();
  }
}






/*
explorationdata
{
  {
    "lastupdate":"000",
    "systemaddress":"asdasd",
    "systemname":"asdasd",
    "stationname"="dsadsa",
    "base"=0,
    "bonus"=0,
    "total"=0,
    "systemcount"=0,
    "bodycount"=0
  }
}
*/
if ($requesttype == 'explorationdata' || $requesttype == 'exploration') {
  $reportdata = array();
  $explorationdataquery = "SELECT timestamp, StarSystem, SystemAddress, StationName, base, bonus, total, systemcount, bodycount FROM act_snapshot_explorationdata WHERE tickid = '$newtickid' ORDER BY StarSystem ASC, StationName ASC, timestamp ASC";

  if ($explorationdataresult = mysqli_query($con, $explorationdataquery)){
    if (mysqli_num_rows($explorationdataresult) > 0) {
      while($row = mysqli_fetch_array($explorationdataresult, MYSQLI_ASSOC)) {
        $explotimestamp = $row['timestamp'];
        $explosystem = addslashes($row['StarSystem']);
        $exploaddress = $row['SystemAddress'];
        $explostation = addslashes($row['StationName']);
        $explobase = $row['base'];
        $explobonus = $row['bonus'];
        $explototal = $row['total'];
        $explosystems = $row['systemcount'];
        $explobodies = $row['bodycount'];

        $reportdata[] = array(
          "lastupdate" => $explotimestamp, 
          "systemname" => $explosystem, 
          "systemaddress" => $exploaddress, 
          "stationname" => $explostation, 
          "base" => $explobase, 
          "bonus" => $explobonus, 
          "total" => $explototal, 
          "systems" => $explosystems, 
          "bodies" => $explobodies, 
        );
      }

      echo json_encode($reportdata);
      if ($apilogoutput) {
        $log = file_get_contents($logfile);
        $log .= "Success, all done\n";
        file_put_contents($logfile, $log);
      }
      exit();

    } else {
      if ($apilogoutput) {
        $log = file_get_contents($logfile);
        $log .= "420: No data: ".mysqli_error($con)."\n";
        file_put_contents($logfile, $log);
      }
      json_response(420, 'No data', mysqli_error($con));
      exit();
    }
  } else {
    if ($apilogoutput) {
      $log = file_get_contents($logfile);
      $log .= "419: SQL query error: ".mysqli_error($con)."\n";
      file_put_contents($logfile, $log);
    }
    json_response(419, 'sql query error', mysqli_error($con));
    exit();
  }
}


/*
myexplorationdata
{
  {
    "lastupdate":"000",
    "systemaddress":"asdasd",
    "systemname":"asdasd",
    "stationname"="dsadsa",
    "base"=0,
    "bonus"=0,
    "total"=0,
    "systemcount"=0,
    "bodycount"=0
  }
}
*/
if ($requesttype == 'myexplorationdata' || $requesttype == 'myexploration') {
  $reportdata = array();
  $explorationdataquery = "SELECT timestamp, StarSystem, SystemAddress, StationName, base, bonus, total, systemcount, bodycount FROM data_explorationdata WHERE timestamp > '$newtick' AND userid = '$checkid' ORDER BY StarSystem ASC, StationName ASC, timestamp ASC";

  if ($explorationdataresult = mysqli_query($con, $explorationdataquery)){
    if (mysqli_num_rows($explorationdataresult) > 0) {
      while($row = mysqli_fetch_array($explorationdataresult, MYSQLI_ASSOC)) {
        $explotimestamp = $row['timestamp'];
        $explosystem = addslashes($row['StarSystem']);
        $exploaddress = $row['SystemAddress'];
        $explostation = addslashes($row['StationName']);
        $explobase = $row['base'];
        $explobonus = $row['bonus'];
        $explototal = $row['total'];
        $explosystems = $row['systemcount'];
        $explobodies = $row['bodycount'];

        $reportdata[] = array(
          "lastupdate" => $explotimestamp, 
          "systemname" => $explosystem, 
          "systemaddress" => $exploaddress, 
          "stationname" => $explostation, 
          "base" => $explobase, 
          "bonus" => $explobonus, 
          "total" => $explototal, 
          "systems" => $explosystems, 
          "bodies" => $explobodies, 
        );
      }

      echo json_encode($reportdata);
      if ($apilogoutput) {
        $log = file_get_contents($logfile);
        $log .= "Success, all done\n";
        file_put_contents($logfile, $log);
      }
      exit();

    } else {
      if ($apilogoutput) {
        $log = file_get_contents($logfile);
        $log .= "422: No data: ".mysqli_error($con)."\n";
        file_put_contents($logfile, $log);
      }
      json_response(422, 'No data', mysqli_error($con));
      exit();
    }
  } else {
    if ($apilogoutput) {
      $log = file_get_contents($logfile);
      $log .= "421: SQL query error: ".mysqli_error($con)."\n";
      file_put_contents($logfile, $log);
    }
    json_response(421, 'sql query error', mysqli_error($con));
    exit();
  }
}



/*
purchases
{
  {
    "lastupdate":"000",
    "systemaddress":"asdasd",
    "systemname":"asdasd",
    "stationname"="dsadsa",
    "commodity"="asdasdas",
    "amount"=0,
    "value"=0,
    "total"=0,
  }
}
*/
if ($requesttype == 'purchase' || $requesttype == 'purchases') {
  $reportdata = array();
  $purchasedataquery = "SELECT timestamp, StarSystem, SystemAddress, StationName, commodity, amount, value, total FROM act_snapshot_cargopurchases WHERE tickid = '$newtickid' ORDER BY StarSystem ASC, StationName ASC, timestamp ASC";

  if ($purchasedataresult = mysqli_query($con, $purchasedataquery)){
    if (mysqli_num_rows($purchasedataresult) > 0) {
      while($row = mysqli_fetch_array($purchasedataresult, MYSQLI_ASSOC)) {
        $purchasetimestamp = $row['timestamp'];
        $purchasesystem = addslashes($row['StarSystem']);
        $purchaseaddress = $row['SystemAddress'];
        $purchasestation = addslashes($row['StationName']);
        $purchasecommodity = addslashes($row['commodity']);
        $purchaseamount = $row['amount'];
        $purchasevalue = $row['value'];
        $purchasetotal = $row['total'];

        $reportdata[] = array(
          "lastupdate" => $purchasetimestamp, 
          "systemname" => $purchasesystem, 
          "systemaddress" => $purchaseaddress, 
          "stationname" => $purchasestation, 
          "commodity" => $purchasecommodity, 
          "amount" => $purchaseamount, 
          "value" => $purchasevalue, 
          "total" => $purchasetotal
        );
      }

      echo json_encode($reportdata);
      if ($apilogoutput) {
        $log = file_get_contents($logfile);
        $log .= "Success, all done\n";
        file_put_contents($logfile, $log);
      }
      exit();

    } else {
      if ($apilogoutput) {
        $log = file_get_contents($logfile);
        $log .= "424: No data: ".mysqli_error($con)."\n";
        file_put_contents($logfile, $log);
      }
      json_response(424, 'No data', mysqli_error($con));
      exit();
    }
  } else {
    if ($apilogoutput) {
      $log = file_get_contents($logfile);
      $log .= "423: SQL query error: ".mysqli_error($con)."\n";
      file_put_contents($logfile, $log);
    }
    json_response(423, 'sql query error', mysqli_error($con));
    exit();
  }
}


/*
mypurchases
{
  {
    "lastupdate":"000",
    "systemaddress":"asdasd",
    "systemname":"asdasd",
    "stationname"="dsadsa",
    "commodity"="asdasdas",
    "amount"=0,
    "value"=0,
    "total"=0,
  }
}
*/

if ($requesttype == 'mypurchases') {
  $reportdata = array();
  $marketarray = array();
  $marketquery = "SELECT MarketID FROM data_cargopurchases WHERE timestamp > '$newtick' AND userid = '$checkid'";
  if ($marketresult = mysqli_query($con, $marketquery)){
    if (mysqli_num_rows($marketresult) > 0) {
      while($row = mysqli_fetch_array($marketresult, MYSQLI_ASSOC)) {
        $marketarray[] = $row['MarketID'];
      }
    }
  }
  $marketarray = array_unique($marketarray);

  $commodityarray = array();
  foreach ($marketarray as $MarketID) {
    $commodityquery = "SELECT commodity FROM data_cargopurchases WHERE timestamp > '$newtick' AND userid = '$checkid' AND MarketID = '$MarketID'";
    if ($commodityresult = mysqli_query($con, $commodityquery)){
      if (mysqli_num_rows($commodityresult) > 0) {
        while($row2 = mysqli_fetch_array($commodityresult, MYSQLI_ASSOC)) {
          $commodityarray[] = $row2['commodity'];
        }
      }
    }
    $commodityarray = array_unique($commodityarray);
    foreach ($commodityarray as $commodity) {
      $purchasedataquery = "SELECT timestamp, StarSystem, SystemAddress, StationName, commodity, amount, value, total FROM data_cargopurchases WHERE timestamp > '$newtick' AND userid = '$checkid' AND MarketID = '$MarketID' AND commodity = '$commodity' ORDER BY StarSystem ASC, StationName ASC, timestamp ASC";
      if ($purchasedataresult = mysqli_query($con, $purchasedataquery)){
        if (mysqli_num_rows($purchasedataresult) > 0) {
          $purchasetimestamp = 0;
          $purchasesystem = '';
          $purchaseaddress = '';
          $purchasestation = '';
          $purchasecommodity = '';
          $purchaseamount = 0;
          $purchasevalue = 0;
          $purchasetotal = 0;
          while($row3 = mysqli_fetch_array($purchasedataresult, MYSQLI_ASSOC)) {
            $purchasetimestamp = $row3['timestamp'];
            $purchasesystem = addslashes($row3['StarSystem']);
            $purchaseaddress = $row3['SystemAddress'];
            $purchasestation = addslashes($row3['StationName']);
            $purchasecommodity = addslashes($row3['commodity']);
            $purchaseamount = $purchaseamount + $row3['amount'];
            $purchasevalue = $purchasevalue + $row3['value'];
            $purchasetotal = $purchasetotal + $row3['total'];
          }
          $reportdata[] = array(
            "lastupdate" => $purchasetimestamp, 
            "systemname" => $purchasesystem, 
            "systemaddress" => $purchaseaddress, 
            "stationname" => $purchasestation, 
            "commodity" => $purchasecommodity, 
            "amount" => $purchaseamount, 
            "value" => $purchasevalue, 
            "total" => $purchasetotal
          );
        } else {
          if ($apilogoutput) {
            $log = file_get_contents($logfile);
            $log .= "430: No data: ".mysqli_error($con)."\n";
            file_put_contents($logfile, $log);
          }
          json_response(430, 'No data', mysqli_error($con));
          exit();
        }
      } else {
        if ($apilogoutput) {
          $log = file_get_contents($logfile);
          $log .= "429: SQL query error: ".mysqli_error($con)."\n";
          file_put_contents($logfile, $log);
        }
        json_response(429, 'sql query error', mysqli_error($con));
        exit();
      }
    }
  }
  echo json_encode($reportdata);
  if ($apilogoutput) {
    $log = file_get_contents($logfile);
    $log .= "Success, all done\n";
    file_put_contents($logfile, $log);
  }
  exit();
}























/*
deliveries
{
  {
    "lastupdate":"000",
    "systemaddress":"asdasd",
    "systemname":"asdasd",
    "stationname"="dsadsa",
    "commodity"="asdasdas",
    "amount"=0,
    "value"=0,
    "profit"=0,
  }
}
*/
if ($requesttype == 'deliveries') {
  $reportdata = array();

  $deliverydataquery = "SELECT timestamp, StarSystem, SystemAddress, StationName, commodity, amount, value, profit FROM act_snapshot_cargodeliveries WHERE tickid = '$newtickid' ORDER BY StarSystem ASC, StationName ASC, timestamp ASC";

  if ($deliverydataresult = mysqli_query($con, $deliverydataquery)){
    if (mysqli_num_rows($deliverydataresult) > 0) {
      while($row = mysqli_fetch_array($deliverydataresult, MYSQLI_ASSOC)) {
        $deliverytimestamp = $row['timestamp'];
        $deliverysystem = addslashes($row['StarSystem']);
        $deliveryaddress = $row['SystemAddress'];
        $deliverystation = addslashes($row['StationName']);
        $deliverycommodity = addslashes($row['commodity']);
        $deliveryamount = $row['amount'];
        $deliveryvalue = $row['value'];
        $deliveryprofit = $row['profit'];

        $reportdata[] = array(
          "lastupdate" => $deliverytimestamp, 
          "systemname" => $deliverysystem, 
          "systemaddress" => $deliveryaddress, 
          "stationname" => $deliverystation, 
          "commodity" => $deliverycommodity, 
          "amount" => $deliveryamount, 
          "value" => $deliveryvalue, 
          "profit" => $deliveryprofit
        );
      }

      echo json_encode($reportdata);
      if ($apilogoutput) {
        $log = file_get_contents($logfile);
        $log .= "Success, all done\n";
        file_put_contents($logfile, $log);
      }
      exit();

    } else {
      if ($apilogoutput) {
        $log = file_get_contents($logfile);
        $log .= "428: No data: ".mysqli_error($con)."\n";
        file_put_contents($logfile, $log);
      }
      json_response(428, 'No data', mysqli_error($con));
      exit();
    }
  } else {
    if ($apilogoutput) {
      $log = file_get_contents($logfile);
      $log .= "427: SQL query error: ".mysqli_error($con)."\n";
      file_put_contents($logfile, $log);
    }
    json_response(427, 'sql query error', mysqli_error($con));
    exit();
  }
}


/*
mydeliveries
{
  {
    "lastupdate":"000",
    "systemaddress":"asdasd",
    "systemname":"asdasd",
    "stationname"="dsadsa",
    "commodity"="asdasdas",
    "amount"=0,
    "value"=0,
    "profit"=0,
  }
}
*/
if ($requesttype == 'mydeliveries') {
  $reportdata = array();
  $marketarray = array();
  $marketquery = "SELECT MarketID FROM data_cargodeliveries WHERE timestamp > '$newtick' AND userid = '$checkid'";
  if ($marketresult = mysqli_query($con, $marketquery)){
    if (mysqli_num_rows($marketresult) > 0) {
      while($row = mysqli_fetch_array($marketresult, MYSQLI_ASSOC)) {
        $marketarray[] = $row['MarketID'];
      }
    }
  }
  $marketarray = array_unique($marketarray);

  $commodityarray = array();
  foreach ($marketarray as $MarketID) {
    $commodityquery = "SELECT commodity FROM data_cargodeliveries WHERE timestamp > '$newtick' AND userid = '$checkid' AND MarketID = '$MarketID'";
    if ($commodityresult = mysqli_query($con, $commodityquery)){
      if (mysqli_num_rows($commodityresult) > 0) {
        while($row2 = mysqli_fetch_array($commodityresult, MYSQLI_ASSOC)) {
          $commodityarray[] = $row2['commodity'];
        }
      }
    }
    $commodityarray = array_unique($commodityarray);
    foreach ($commodityarray as $commodity) {
      $deliverydataquery = "SELECT timestamp, StarSystem, SystemAddress, StationName, commodity, amount, value, profit FROM data_cargodeliveries WHERE timestamp > '$newtick' AND userid = '$checkid' AND MarketID = '$MarketID' AND commodity = '$commodity' ORDER BY StarSystem ASC, StationName ASC, timestamp ASC";
      if ($deliverydataresult = mysqli_query($con, $deliverydataquery)){
        if (mysqli_num_rows($deliverydataresult) > 0) {
          $deliverytimestamp = 0;
          $deliverysystem = '';
          $deliveryaddress = '';
          $deliverystation = '';
          $deliverycommodity = '';
          $deliveryamount = 0;
          $deliveryvalue = 0;
          $deliveryprofit = 0;
          while($row3 = mysqli_fetch_array($deliverydataresult, MYSQLI_ASSOC)) {
            $deliverytimestamp = $row3['timestamp'];
            $deliverysystem = addslashes($row3['StarSystem']);
            $deliveryaddress = $row3['SystemAddress'];
            $deliverystation = addslashes($row3['StationName']);
            $deliverycommodity = addslashes($row3['commodity']);
            $deliveryamount = $deliveryamount + $row3['amount'];
            $deliveryvalue = $deliveryvalue + $row3['value'];
            $deliveryprofit = $deliveryprofit + $row3['profit'];
          }
          $reportdata[] = array(
            "lastupdate" => $deliverytimestamp, 
            "systemname" => $deliverysystem, 
            "systemaddress" => $deliveryaddress, 
            "stationname" => $deliverystation, 
            "commodity" => $deliverycommodity, 
            "amount" => $deliveryamount, 
            "value" => $deliveryvalue, 
            "profit" => $deliveryprofit
          );
        } else {
          if ($apilogoutput) {
            $log = file_get_contents($logfile);
            $log .= "430: No data: ".mysqli_error($con)."\n";
            file_put_contents($logfile, $log);
          }
          json_response(430, 'No data', mysqli_error($con));
          exit();
        }
      } else {
        if ($apilogoutput) {
          $log = file_get_contents($logfile);
          $log .= "429: SQL query error: ".mysqli_error($con)."\n";
          file_put_contents($logfile, $log);
        }
        json_response(429, 'sql query error', mysqli_error($con));
        exit();
      }
    }
  }
  echo json_encode($reportdata);
  if ($apilogoutput) {
    $log = file_get_contents($logfile);
    $log .= "Success, all done\n";
    file_put_contents($logfile, $log);
  }
  exit();
}





















function json_response($code = 444, $message = 'undefined error') {
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
    'message' => $message
  ));
}


function getpmfinfluence($systemaddress, $con, $pmfname) {
  $pmfinfluence;
  $pmfinfluenceactivesnapshotquery = "SELECT Influence FROM act_snapshot_factions WHERE SystemAddress = '".$systemaddress."' AND Name = '".$pmfname."' ORDER BY timestamp DESC LIMIT 1";
  if ($pmfinfluenceactivesnapshotresult = mysqli_query($con, $pmfinfluenceactivesnapshotquery)){
    if (mysqli_num_rows($pmfinfluenceactivesnapshotresult) > 0) {
      while($row = mysqli_fetch_array($pmfinfluenceactivesnapshotresult, MYSQLI_ASSOC)) {
        $pmfinfluence = round(($row['Influence'] * 100), 2);
      }
    } else {
      $pmfinfluencesnapshotsquery = "SELECT Influence FROM snapshot_factions WHERE SystemAddress = '".$systemaddress."' AND Name = '".$pmfname."' ORDER BY timestamp DESC LIMIT 1";
      if ($pmfinfluencesnapshotsresult = mysqli_query($con, $pmfinfluencesnapshotsquery)){
        if (mysqli_num_rows($pmfinfluencesnapshotsresult) > 0) {
          while($row2 = mysqli_fetch_array($pmfinfluencesnapshotsresult, MYSQLI_ASSOC)) {
            $pmfinfluence = round(($row2['Influence'] * 100), 2);
          }
        }
      }
    }
  }
  if (!empty($pmfinfluence)) {
    return $pmfinfluence;
  } else {
    return;
  }
}

function getlastupdatetime($systemaddress, $con) {
  $lastupdatetime;
  $lastupdatequery = "SELECT timestamp FROM act_snapshot_systems WHERE SystemAddress = '".$systemaddress."' ORDER BY timestamp DESC LIMIT 1";
  if ($lastupdateresult = mysqli_query($con, $lastupdatequery)){
    if (mysqli_num_rows($lastupdateresult) > 0) {
      while($row = mysqli_fetch_array($lastupdateresult, MYSQLI_ASSOC)) {
        $lastupdatetime = $row['timestamp'];
      }
    } else {
      $updatequery = "SELECT timestamp FROM snapshot_systems WHERE SystemAddress = '".$systemaddress."' ORDER BY timestamp DESC LIMIT 1";
      if ($updateresult = mysqli_query($con, $updatequery)){
        if (mysqli_num_rows($updateresult) > 0) {
          while($row2 = mysqli_fetch_array($updateresult, MYSQLI_ASSOC)) {
            $lastupdatetime = $row2['timestamp'];
          }
        }
      }
    }
  }
  if (!empty($lastupdatetime)) {
    return $lastupdatetime;
  } else {
    return;
  }
}

function uptodate($datetime, $newtick) {
  if (strtotime($newtick) > strtotime($datetime)) {
    return false;
  } else {
    return true;
  }
}

?>