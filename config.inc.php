<?php
date_default_timezone_set("Europe/London");

$securedbcreds = '../../private/db.inc.php';

$loglocation = '../log/';
$logapi = 'inputapi-'.time().'.log';
$logapiout = 'outputapi-'.time().'.log';
$logtickdetect = 'tickdetect-'.time().'.log';
$logtickprocessor = 'tickprocessor-'.time().'.log';

$siteurl = 'https://ida-bgs.ztik.nl/';
$sitetitle = 'Independent Defence Agency - BGS';

$pmfname = 'Independent Defence Agency';
$pmfshortname = 'IDA';

$systeminfluencewarningpercentage = 7;
$influenceproximitywarningpercentage = 4;
?>