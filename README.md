# IDA-BGS API  
 webapi for receiving data from the IDA-BGS plugin for EDMC  

## What it does:  
This API receives JSON data packages from the [IDA-BGS EDMC plugin](https://github.com/ZTiKnl/IDA-BGS) to track faction data.  
Data sent to the API can be displayed by the [IDA-BGS FrontEnd website](https://github.com/ZTiKnl/IDA-BGS-FrontEnd)  

## How to use:  
1. Download the .PHP files to your website  
2. Create required SQL tables for data storage (see .sql files for details)  
3. Create a cron job, that runs php ./tickdetect.php  

## Cron based tick detection and data mining:  
tickdetect.php is configured to get the latest tick datetime from [EliteBGS Tick Bot](https://elitebgs.app)  
Once a tick is detected, tickdetect.php triggers tickprocessor.php to start mining the records timestamped between the last tick, and the one before that  

## Data mining:
tickprocessor.php should mine for data 1x per 24hrs, but ticks have been off before (due to things like updates), so there are no guarantees when the next tick will happen.  
Once a tick has completed, every record with a timestamp in that tick, will be collected and merged.  
Conflicting records will be compared by counting the amount of duplicate records, the record with the most exact duplications wins the election and becomes the snapshot for that system/faction.  

## Changes to make for use by another faction
Want to make this work for another faction, no changes are required to the files in this repo.  
Some changes need to be made to the [IDA-BGS EDMC plugin](https://github.com/ZTiKnl/IDA-BGS)  

## Database security:
Make sure you never have your database username/password inside your publicly reachable .php files.
I have placed a file 'db.inc.php' outside the /public_html/ folder (which is the root for my website).  
This is why you can see an include() function in every file that references `../private/db.inc.php`  
As far as I am aware, this is the most secure way to store database credentials.  

## Disclaimer
This API is still under construction, ~~bugs~~ new features WILL appear unexpectedly.  
There is no license on this code, feel free to use it as you see fit.  
Patches are always welcome.  

## Thanks  
- Everybody who helped test the EDMC Plugin (devnull, Optimus Stan)  
- HammerPiano, wouldnt have gotten the plugin working so fast without your advice  
- [EliteBGS Tick Bot](https://EliteBGS.app)  
- Everyone at EDCD: Community Developers Discord channel (Phelbore, Athanasius, Gazelle, tez,  Garud,  T'kael, VerticalBlank, anyone else I missed?)