<?php

require_once(dirname(__FILE__)."/s3backup_type.class.php");

class s3backup_mysql extends s3backup_type {

	function createBackup($tempfile,$set) {
		$host = getSetValue($set,'mysql_host');
		$port = getSetValue($set,'mysql_port');
		$user = getSetValue($set,'mysql_username');
		$password = getSetValue($set,'mysql_password');
		$database = getSetValue($set,'mysql_database');
		$all = getSetValue($set,'mysql_all');

		$output = array();
		$command = 'mysqldump';
		if ($host) {
			$command .= ' --host='.escapeshellarg($host);
		}
		if ($port) {
			$command .= ' --port='.escapeshellarg($port);
		}
		if ($user) {
			$command .= ' --user='.escapeshellarg($user);
		}
		if ($password) {
			$command .= ' --password='.escapeshellarg($password);
		}
		// if all was specified, or if no database was specified, backup everything
		if ($all || !$database) {
			$command .= ' --all-databases';
		}
		else {
			$command .= ' '.escapeshellarg($database);
		}

		exec('mysqldump --all-databases | gzip >'.escapeshellarg($tempfile),$output,$return);
		if ($return!=0) {
			throw new Exception(implode("\n",$output));
		}
		return ".sql.gz";
	}

}
