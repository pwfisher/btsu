<?php

require_once(dirname(__FILE__).'/s3common.php');

// if --help, display help and exit
if (array_key_exists('help',$_ARGS) || array_key_exists('h',$_ARGS)) {
    print "s3restore [options] target\n";
    print "  target          The directory to place restored files in\n";
    print "  --help          Display this text and exit\n";
    print "  --set=[set]     Restore/list the specified set only (default is all sets)\n";
    print "  --config=[path] Path to config file (defaults to ~/s3backup.ini)\n";
    print "  --list          Lists all available backups and exits. If --set or --date are\n";
    print "                  specified, list only those sets/dates instad of everything.\n";
    print "  --date=[date]   Restores all the backups from this date to subdirectories in\n";
    print "                  target dir. [date] is YYYY-MM-DD\n";
    print "  --datetime=[datetime] \n";
    print "                  Restores a single backup exactly matching [datetime] to target\n";
    print "                  dir. [datetime] is in the format YYYY-MM-DD.HH:MM:SS (this is\n";
    print "                  the same format you will get back from --list)\n";
    print "  --quiet         Only output critical messages / errors\n";
    print "  --verbose       Output additional info about the restore process\n";
    exit;
}

init();

// check that we have at least one backup set
if (!$_SETS) {
	dieError("No backup sets defined, please edit the config file at $_INI_PATH\n");
}

// check some variable formatting
$date = null;
$datetime = null;
$time = null;
if (array_key_exists('date',$_ARGS)) {
    $date = $_ARGS['date'];
    $time = @strtotime($date);
    if (date('Y-m-d',$time)!=$date) {
        dieError("Invalid date / date format: $date\n");
    }
}
if (array_key_exists('datetime',$_ARGS)) {
    $datetime = $_ARGS['datetime'];
    $time = @strtotime($datetime);
    if (date('Y-m-d.H:i:s',$time)!=$datetime) {
        dieError("Invalid datetime / datetime format: $datetime\n");
    }
}

$restored = false;
$shouldrestore = false;
foreach ($_SETS as $set) {
    // variables required for every backup set, regardless of type
    $requiredVars = array('access_id','secret_key','bucket_name');

    // we need certain variables in order to create the backup / upload it
    // check those first before we do any actual backing up
    foreach ($requiredVars as $var) {
        $$var = getSetValue($set,$var);
        if (is_null($$var) || $$var==="") {
            printError("Missing required variable '$var' for set $set in your config file (should be in [$set] or [defaults])\n");
            continue 2;
        }
    }

    if (isset($s3)) {
        unset($s3);
    }
    $s3 = new S3($access_id,$secret_key);

    if (array_key_exists('list',$_ARGS)) {
        print "\n$set:\n";
        $foundmatches = false;
        $objects = $s3->getBucket($bucket_name);
        $pattern = '/^'.preg_quote(getSetValue($set,'s3_file_prefix')).'(';
        if ($time) {
            $pattern .= preg_quote(date('Y-m-d',$time),'/');
        }
        else {
            $pattern .= '[0-9]{4}\-[0-9]{2}\-[0-9]{2}';
        }
        $pattern .= '\.[0-9]{2}\:[0-9]{2}\:[0-9]{2}';
        $pattern .= ')\/'.preg_quote($set).'/';
        foreach ($objects as $manifest=>$data) {
            if (preg_match($pattern,$manifest,$temp)) {
                $foundmatches = true;
                $size = $data['size'];
                if ($size>1024*1024) {
                    $size = (round(($size/1024/1024)*10)/10)." GB   ";
                }
                if ($size>1024*1024) {
                    $size = (round(($size/1024/1024)*10)/10)." MB   ";
                }
                elseif ($size>1024) {
                    $size = ($size/1024)." KB   ";
                }
                else {
                    $size = $size." bytes";
                }
                print "  $temp[1]   ".str_pad($size,12,' ',STR_PAD_LEFT)."\n";
            }
        }
        if (!$foundmatches) {
            print "  No backups found for this backup set";
            if ($time) {
                print " (try without --date)";
            }
            print "\n";
        }
    }

    elseif ($date || $datetime) {
        $shouldrestore = true;

        // must specify a target where to save the files
        if (!array_key_exists(0,$_ARGS)) {
            dieError("Missing target directory to restore to\n");
        }
        if (!file_exists($_ARGS[0])) {
            // try and create it
            @mkdir($_ARGS[0]);
            if (!file_exists($_ARGS[0])) {
                dieError("Target directory does not exist, and failed while attempting to create it\n");
            }
        }
        if (!is_dir($_ARGS[0])) {
            dieError("Target is not a directory\n");
        }
        if (!is_writable($_ARGS[0])) {
            dieError("Target directory is not writable (check permissions)\n");
        }

        // try and find matching backups
        $objects = $s3->getBucket($bucket_name);
        $pattern = '/^'.preg_quote(getSetValue($set,'s3_file_prefix'),'/').'(';
        if ($datetime) {
            $pattern .= preg_quote(date('Y-m-d.H:i:s',$time),'/');
        }
        else {
            $pattern .= preg_quote(date('Y-m-d',$time),'/').'\.[0-9]{2}\:[0-9]{2}\:[0-9]{2}';
        }
        $pattern .= ')\/('.preg_quote($set).'.*)$/';
        foreach ($objects as $manifest=>$data) {
            if (preg_match($pattern,$manifest,$temp)) {
                printQuiet("  Restoring $manifest...\n");
                // if they specified a single backup to restore, throw it in the directory
                if ($datetime) {
                    $localfile = $_ARGS[0].'/'.$temp[2];
                }
                // else throw it in a subdir with the date/time of the backup
                else {
                    @mkdir($_ARGS[0].'/'.$temp[1]);
                    if (!file_exists($_ARGS[0].'/'.$temp[1])) {
                        dieError("Unable to create directory $_ARGS[0]/$temp[1] (check permissions)\n");
                    }
                    $localfile = $_ARGS[0].'/'.$temp[1].'/'.$temp[2];
                }
                $s3->getObject($bucket_name, $manifest, $localfile);
                $restored = true;
            }
        }
    }

    else {
        dieError("Nothing to do! You must specify one of --list, --date, or --datetime\n");
    }

}

if ($shouldrestore && !$restored) {
    dieError("Nothing restored, check your dates or use --list to check availability\n");
}
