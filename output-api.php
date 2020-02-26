<?PHP
// include config variables
include('config.inc.php');

$logfile = $loglocation.$logapiout;

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

// collect GET vars, apikey, requested data
$data = $_GET;
if (!$data) {
  $log = file_get_contents($logfile);
  $log .= "400: empty request\n";
  file_put_contents($logfile, $log);
  json_response(400, 'empty request');
  exit();
} else {
  if (!$data['apikey']) {
    $log = file_get_contents($logfile);
    $log .= "401: No apikey\n";
    file_put_contents($logfile, $log);
    json_response(401, 'need an API key to continue');
    exit();
  } else {
    $apikey = filter_var($data['apikey'], FILTER_SANITIZE_SPECIAL_CHARS);
  }
  if (!$data['request']) {
    $log = file_get_contents($logfile);
    $log .= "402: no request type given\n";
    file_put_contents($logfile, $log);
    json_response(402, 'need to know what data to provide');
    exit();
  } else {
    $requesttype = filter_var($data['request'], FILTER_SANITIZE_SPECIAL_CHARS);
  }
}

//check API key
$apiquery = "SELECT * FROM apikeys WHERE apikey = '$apikey'";
if($apiresult = mysqli_query($con, $apiquery)){
  if(mysqli_num_rows($apiresult) > 0){
    $log = file_get_contents($logfile);
    $log .= "APIkey matches\n";
    file_put_contents($logfile, $log);
  } else {
    $log = file_get_contents($logfile);
    $log .= "403: Unknown APIkey\n";
    file_put_contents($logfile, $log);
    json_response(403, 'no matching APIkey');
    exit();
  }
} else {
  $log = file_get_contents($logfile);
  $log .= "404: SQL query error: ".mysqli_error($con)."\n";
  file_put_contents($logfile, $log);
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
    $log = file_get_contents($logfile);
    $log .= "Success, all done\n";
    file_put_contents($logfile, $log);
    exit();
  } else {
    $log = file_get_contents($logfile);
    $log .= "405: No results\n";
    file_put_contents($logfile, $log);
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
      $log = file_get_contents($logfile);
      $log .= "Success, all done\n";
      file_put_contents($logfile, $log);
      exit();
    } else {
      $log = file_get_contents($logfile);
      $log .= "407: No results\n";
      file_put_contents($logfile, $log);
      json_response(407, 'No results found');
      exit();
    }
  } else {
    $log = file_get_contents($logfile);
    $log .= "406: SQL query error: ".mysqli_error($con)."\n";
    file_put_contents($logfile, $log);
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

  $systemquery = "SELECT systemname, systemaddress FROM systemlist ORDER BY systemname ASC";
  if ($systemresult = mysqli_query($con, $systemquery)){
    if (mysqli_num_rows($systemresult) > 0) {
      $systemcounter = 0;
      while($row = mysqli_fetch_array($systemresult, MYSQLI_ASSOC)) {
        $systemname = addslashes($row['systemname']);
        $systemaddress = $row['systemaddress'];

        $systemuptodate = false;
        $systemupdatetime = 0;
        $tickid;

        $systemcheckactivesnapshotquery = "SELECT tickid, timestamp FROM activesnapshot WHERE tickid = '$newtickid' AND SystemAddress = '$systemaddress'";
        if ($systemcheckactivesnapshotresult = mysqli_query($con, $systemcheckactivesnapshotquery)){
          if (mysqli_num_rows($systemcheckactivesnapshotresult) > 0) {
            $systemuptodate = true;
            while($row2 = mysqli_fetch_array($systemcheckactivesnapshotresult, MYSQLI_ASSOC)) {
              $tickid = $row2['tickid'];
              $systemupdatetime = $row2['timestamp'];
            }
          } else {

            $systemchecksnapshotsquery = "SELECT tickid, timestamp FROM snapshots WHERE tickid = '$oldtickid' AND SystemAddress = '$systemaddress' ORDER BY timestamp DESC LIMIT 1";
            if ($systemchecksnapshotsresult = mysqli_query($con, $systemchecksnapshotsquery)){
              if (mysqli_num_rows($systemchecksnapshotsresult) > 0) {
                while($row3 = mysqli_fetch_array($systemchecksnapshotsresult, MYSQLI_ASSOC)) {
                  $tickid = $row3['tickid'];
                  $systemupdatetime = $row3['timestamp'];
                }
              } else {
                $tickid = 'unknown';
                $systemupdatetime = 'unknown';
              }
            }
          }
        }





        // INFLUENCE WARNING SYSTEM
        $influencedatainactivesnapshot = false;
        $influencedatainsnapshots = false;
        $factioninfluencearray = array();

        $influenceactivesnapshotquery = "SELECT Influence FROM activesnapshot WHERE tickid = '$newtickid' AND isfaction = '1' AND factionaddress = '$systemaddress' AND Name = '".$pmfname."' ORDER BY tickid DESC LIMIT 1";
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
        $influencesnapshotquery = "SELECT Influence FROM snapshots WHERE isfaction = '1' AND factionaddress = '$systemaddress' AND Name = '".$pmfname."' ORDER BY tickid DESC LIMIT ".$limiter; 
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
            "type" => $type, 
            "amount" => $systemamount, 
            "total" => $systemtotal, 
            "updatetime" => $updatetime, 
            "uptodate" => $uptodate
          );
        }

        // INFLUENCE WARNING SYSTEM



        // CONFLICT WARNING SYSTEM
        $conflictarray = array();
        $conflictdatainactivesnapshot = false;
        $conflictdatainsnapshots = false;

        if (!$systemuptodate) {
          $conflictactivesnapshotquery = "SELECT conflicttype, conflictstatus, conflictfaction1name, conflictfaction1stake, conflictfaction1windays, conflictfaction2name, conflictfaction2stake, conflictfaction2windays FROM activesnapshot WHERE tickid = '$newtickid' AND isconflict = '1' AND SystemAddress = '$systemaddress' AND (conflictfaction1name = '".$pmfname."' OR conflictfaction2name = '".$pmfname."') ORDER BY tickid DESC";
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
          $conflictsnapshotquery = "SELECT conflicttype, conflictstatus, conflictfaction1name, conflictfaction1stake, conflictfaction1windays, conflictfaction2name, conflictfaction2stake, conflictfaction2windays FROM snapshots WHERE tickid = '$oldtickid' AND isconflict = '1' AND SystemAddress = '$systemaddress' AND (conflictfaction1name = '".$pmfname."' OR conflictfaction2name = '".$pmfname."') ORDER BY tickid DESC";
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
//            print_r($reportdata);
          }
        }
      }
      if ($reportdata) {
        echo json_encode($reportdata);
        $log = file_get_contents($logfile);
        $log .= "Success, all done\n";
        file_put_contents($logfile, $log);
        exit();
      } else {
        $log = file_get_contents($logfile);
        $log .= "413: No data\n";
        file_put_contents($logfile, $log);
        json_response(413, 'No data');
        exit();
      }
    } else {
      $log = file_get_contents($logfile);
      $log .= "412: No data: ".mysqli_error($con)."\n";
      file_put_contents($logfile, $log);
      json_response(412, 'No data', mysqli_error($con));
      exit();
    }
  } else {
    $log = file_get_contents($logfile);
    $log .= "411: SQL query error: ".mysqli_error($con)."\n";
    file_put_contents($logfile, $log);
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
    $log = file_get_contents($logfile);
    $log .= "408: No userid presented\n";
    file_put_contents($logfile, $log);
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
    $log = file_get_contents($logfile);
    $log .= "409: SQL query error: ".mysqli_error($con)."\n";
    file_put_contents($logfile, $log);
    json_response(409, 'sql query error', mysqli_error($con));
    exit();
  }

  if ($apikey !== 0) {
    $apikeydata = array("apikey" => $apikey, "new" => false);
    echo json_encode($apikeydata);
    $log = file_get_contents($logfile);
    $log .= "Success, all done\n";
    file_put_contents($logfile, $log);
    exit();
  } else {
    $newapikey = sha1($userid);
    $addapikeyquery = "INSERT INTO apikeys (apikey, userid) VALUES ('".$newapikey."', '".$userid."');";
    if (mysqli_query($con, $addapikeyquery)){
      $apikeydata = array("apikey" => $newapikey, "new" => true);
      echo json_encode($apikeydata);
      $log = file_get_contents($logfile);
      $log .= "Success, all done\n";
      file_put_contents($logfile, $log);
      exit();
    } else {
      $log = file_get_contents($logfile);
      $log .= "410: SQL query error: ".$userid." / ".$newapikey." ".mysqli_error($con)."\n";
      file_put_contents($logfile, $log);
      json_response(410, 'sql query error', mysqli_error($con));
      exit();
    }
  }
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
  $pmfinfluenceactivesnapshotquery = "SELECT Influence FROM activesnapshot WHERE isfaction = 1 AND factionaddress = '".$systemaddress."' AND Name = '".$pmfname."' ORDER BY timestamp DESC LIMIT 1";
  if ($pmfinfluenceactivesnapshotresult = mysqli_query($con, $pmfinfluenceactivesnapshotquery)){
    if (mysqli_num_rows($pmfinfluenceactivesnapshotresult) > 0) {
      while($row = mysqli_fetch_array($pmfinfluenceactivesnapshotresult, MYSQLI_ASSOC)) {
        $pmfinfluence = round(($row['Influence'] * 100), 2);
      }
    } else {
      $pmfinfluencesnapshotsquery = "SELECT Influence FROM snapshots WHERE isfaction = 1 AND factionaddress = '".$systemaddress."' AND Name = '".$pmfname."' ORDER BY timestamp DESC LIMIT 1";
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
  $lastupdatequery = "SELECT timestamp FROM activesnapshot WHERE issystem = 1 AND SystemAddress = '".$systemaddress."' ORDER BY timestamp DESC LIMIT 1";
  if ($lastupdateresult = mysqli_query($con, $lastupdatequery)){
    if (mysqli_num_rows($lastupdateresult) > 0) {
      while($row = mysqli_fetch_array($lastupdateresult, MYSQLI_ASSOC)) {
        $lastupdatetime = $row['timestamp'];
      }
    } else {
      $updatequery = "SELECT timestamp FROM snapshots WHERE issystem = 1 AND SystemAddress = '".$systemaddress."' ORDER BY timestamp DESC LIMIT 1";
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