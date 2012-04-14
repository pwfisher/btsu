<?php

require_once(dirname(__FILE__)."/s3backup_type.class.php");

class s3backup_files extends s3backup_type {

	public $requiredVars = array('path');

	function checkVars($set) {
		$path = getSetValue($set,'path');
		if (!file_exists($path)) {
			throw new Exception("Backup path '$path' doesn't exist\n");
		}
		if (!is_readable($path)) {
			throw new Exception("Backup path '$path' is not readable (check permissions)\n");
		}
		return true;
	}

	function createBackup($tempfile,$set) {
		$path = getSetValue($set,'path');
		$output = array();
		// @todo We should be using proc_open here to read the stdout and stderr seperately...
		if (is_dir($path)) {
			exec('cd '.escapeshellarg($path).' && tar -cPvzf '.escapeshellarg($tempfile).' *',$output,$return);
		}
		else {
			exec('cd '.escapeshellarg(dirname($path)).' && tar -cPvzf '.escapeshellarg($tempfile).' '.escapeshellarg($path),$output,$return);
		}
		if ($return!=0) {
			throw new Exception(implode("\n",$output));
		}
		return ".tar.gz";
	}

}
