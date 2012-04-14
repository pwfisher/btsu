<?php

require_once(dirname(__FILE__).'/s3common.php');

// if --help, display help and exit
if (array_key_exists('help',$_ARGS) || array_key_exists('h',$_ARGS)) {
    print "s3backup [options]\n";
    print "  --help          Display this text and exit\n";
    print "  --set=[set]     Backup the specified set only (normally backs up all sets)\n";
    print "  --config=[path] Path to config file (defaults to ~/s3backup.ini)\n";
    print "  --prune         Only prune files from the S3 bucket, don't run the backup\n";
    print "  --noprune       Do not prune files after the backup\n";
    print "  --nodelete      Print what would be pruned, but don't actually delete from S3\n";
    print "  --quiet         Only output critical messages / errors\n";
    print "  --verbose       Output additional info about the backup and pruning process\n";
    exit;
}

init();

// check that we have at least one backup set
if (!$_SETS) {
	dieError("No backup sets defined, please edit the config file at $_INI_PATH\n");
}

// loop through the backup sets
foreach ($_SETS as $set) {
	// create the module object required and ask for a backup
	if (isset($module)) {
		unset($module);
	}
	$type = strtolower(getSetValue($set,'type'));
	try {
		$modulename = "s3backup_".$type;
		$module = new $modulename();
	}
	catch (Exception $e) {
		printError("Unable to start module: ".$e->getMessage()."\n");
		continue;
	}

	// variables required for every backup set, regardless of type
	$requiredVars = array_merge(array('access_id','secret_key','bucket_name'),$module->requiredVars);

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

	if (!array_key_exists('prune',$_ARGS) || !$_ARGS['prune']) {

		printQuiet("Running backup set '$set'...\n");

		try {
			$module->checkVars($set);
		}
		catch (Exception $e) {
			printError($e->getMessage()."\n");
			continue;
		}

		// where will we be creating tar files before we upload them?
		$tempdir = getSetValue($set,'temp_dir');
		if (!$tempdir) {
			$tempdir = sys_get_temp_dir();
		}

		// create a temp tar file for the module to use
		$tempfile = $tempdir.'/'.uniqid('s3',true);

		// make sure we can write to it
		@touch($tempfile);
		if (!file_exists($tempfile)) {
			@unlink($tempfile);
			printError("Unable to create temp file $tempfile\n");
			continue;
		}

		try {
			$fileext = $module->createBackup($tempfile,$set);
		}
		catch (Exception $e) {
			@unlink($tempfile);
			printError($e->getMessage()."\n");
			continue;
		}

		// upload the tempfile to s3
		try {
			$remotename = getSetValue($set,'s3_file_prefix').date('Y-m-d.H:i:s',START_TIME).'/'.$set.$fileext;
			$s3->putObjectFile($tempfile,$bucket_name,$remotename,S3::ACL_PRIVATE);
		}
		catch (Exception $e) {
			@unlink($tempfile);
			printError("Unable to upload file to S3: ".$e->getMessage()."\n");
			continue;
		}

		// delete the tempfile (successfully uploaded)
		@unlink($tempfile);

	} // end backup
	else {
		printQuiet("Not backing up $set because of --prune flag\n");
	}

	// prune the set's files
	if (!array_key_exists('noprune',$_ARGS) || !$_ARGS['noprune']) {

		printQuiet("Pruning '$set'...\n");

		// if retain_days is not set or is zero, we'll never prune anything, so don't bother
		$retain_days = getSetValue($set,'retain_days');
		if (!$retain_days) {
			printQuiet("retain_days is not set (or is empty) which means we'll never find anything to prune. Skipping pruning.\n");
			continue;
		}

		// set the other vars
		$retain_weeks = getSetValue($set,'retain_weeks');
		$retain_months = getSetValue($set,'retain_months');
		$retain_years = getSetValue($set,'retain_years');

		// get a list of all the files in the bucket
		$objects = $s3->getBucket($bucket_name);

		// init some commonly used vars to keep code cleaner / more efficient
		$hour = date('H',START_TIME);
		$min = date('i',START_TIME);
		$sec = date('S',START_TIME);
		$month = date('m',START_TIME);
		$day = date('d',START_TIME);
		$year = date('Y',START_TIME);

		// figure out which ones we'll delete
		foreach ($objects as $manifest=>$data) {
			$time = $data['time'];

			// if it's less than "retain_days" old, we're keeping it
			if ($time<START_TIME-mktime($hour,$min,$sec,$month,$day-$retain_days,$year)) {
				printVerbose("Keeping $manfest (within retained days limit)\n");
				continue;
			}

			// if it's on a sunday, and it's less than retain_weeks old, we're keeping it
			if (date('w',$time)==0 && (!$retain_weeks || $time<mktime($hour,$min,$sec,$month,$day-(7*$retain_weeks),$year))) {
				printVerbose("Keeping $manfest (on Sunday and within retained weeks limit)\n");
				continue;
			}

			//  if it's on the first of the month, and it's less than retain_months old, we're keeping it
			if (date('j',$time)==1 && (!$retain_months || $time<mktime($hour,$min,$sec,$month-$retain_months,$day,$year))) {
				printVerbose("Keeping $manfest (on the first and within retained months limit)\n");
				continue;
			}

			//  if it's on the first of the year, and it's less than retain_years old, we're keeping it
			if (date('z',$time)==0 && (!$retain_years || $time<mktime($hour,$min,$sec,$month,$day,$year-$retain_years))) {
				printVerbose("Keeping $manfest (on Jan 1st and within retained years limit)\n");
				continue;
			}

			// if we're here, that means we don't want it and we need to delete it
			if (array_key_exists('nodelete',$_ARGS)) {
				printQuiet("Pretending to prune/delete $manifest from S3\n");
			}
			else {
				printQuiet("Pruning/deleting $manifest from S3\n");
				//$s3->deleteObject($bucket_name,$manifest);
			}
		} // end looping through bucket objects

	} // end pruning
	else {
		printQuiet("Not pruning $set because of --noprune flag\n");
	}

	unset($s3);
}
