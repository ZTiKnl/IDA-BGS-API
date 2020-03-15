<?php
date_default_timezone_set("Europe/London");

$securedbcreds = '../../private/db.inc.php';

$loglocation = '../log/';
$apiloginput = true;
$logapiinput = '/input/api-input_'.time().'.log';
$apilogoutput = true;
$logapioutput = '/output/api-output_'.time().'.log';
$apilogtickdetect = false;
$logtickdetect = '/tickdetect/api-tickdetect_'.time().'.log';
$apilogprocessor = true;
$logtickprocessor = '/tickprocessor/api-tickprocessor_'.time().'.log';

$siteurl = 'https://ida-bgs.ztik.nl/';
$sitetitle = 'Independent Defence Agency - BGS';

$pmfname = 'Independent Defence Agency';
$pmfshortname = 'IDA';

$systeminfluencewarningpercentage = 7;
$influenceproximitywarningpercentage = 4;
?>