<?PHP
// include config variables
include('config.inc.php');

$logfile = $loglocation.$logtickdetect;

// connect to db
include($securedbcreds);
$con = mysqli_connect($servername,$username,$password,$database) or die("SQL connection error");

$data = json_decode(file_get_contents('https://elitebgs.app/api/ebgs/v4/ticks'), true);
if (!$data) {
  echo "Couldnt get data from elitebgs tick api";
  exit();
}

$ticktimestamp = strtotime($data[0]['time']);
$tickdatetimeobj = date_create_from_format('U', $ticktimestamp);
$newtick = date_format($tickdatetimeobj, 'Y-m-d H:i:s');

//check last tick data
$lasttickquery = "SELECT * FROM dailyticks ORDER BY id DESC LIMIT 1";
if ($lasttickresult = mysqli_query($con, $lasttickquery)){
  if (mysqli_num_rows($lasttickresult) > 0) {
    $row = mysqli_fetch_array($lasttickresult, MYSQLI_ASSOC);
    $current .= "Found latest recorded tick in db:\n";
    $current .= "  Known Tick:  ".$row['timestamp']."\n";
    $current .= "  Latest Tick: ".$newtick."\n";
    if ($row['timestamp'] == $newtick) {
      $oldsameasnew = true;
    } else {
      $oldsameasnew = false;
    }
  }
  if (mysqli_num_rows($lasttickresult) < 1) {
    $oldsameasnew = false; 
  }
  if (!$oldsameasnew) {
    $current .= "New tick detected, adding to database...<br />\n";

    $inserttickdata = "INSERT INTO dailyticks (timestamp) VALUES ('$newtick')";
    if (!mysqli_query($con, $inserttickdata)) {
      $logfile = 'tickdetect.log';
      $current .= "SQL error, couldnt add tick to database.\n";
      file_put_contents($logfile, $current);
      exit();
    } else {
      $current .= "Tick added to database, all done.\n";
      file_put_contents($logfile, $current);
      include('tickprocessor.php');
    }
  } else {
    $current .= "No new tick detected, exiting...\n";
    file_put_contents($logfile, $current);
    echo "No new tick detected, exiting...<br />\n";
    exit();
  }
}
?>



