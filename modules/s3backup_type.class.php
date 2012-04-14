<?php

/**
 * Extend this class to make a new backup module.
 * Your extended module must be named "s3backup_type" and saved in the file
 * "modules/s3backup_type.class.php", where "type" is the type the user will 
 * specify in their backup set in the config file (ie: type=files).
 */
abstract class s3backup_type {

	/**
	 * An array of variables that must be set in the config file before
	 * the backup can be run. These will be checked for you before the
	 * checkVars or createBackup methods are run (you can safely assume
	 * they're set to something by that point).
	 */
	public $requiredVars = array();
	
	/**
	 * Verifies basic thigs like the existance of files, database connectivity, etc
	 * before the backup is started.
	 * @param string $set The name of the backup set
	 * @return boolean True on success
	 * @throws Exception If any required variable is missing
	 */
	function checkVars($set) {
		return true;
	}

	/**
	 * Creates a backup tarfile in the $tarfile location using $settings
	 * @param string $tempfile The full path to the (empty) temp to fill
	 * @param string $set The name of the backup set (used when calling getSetValue)
	 * @see getSetValue()
	 * @return string The file extension including the preceeding dot (eg: ".tar.gz", ".gz", etc)
	 * @throws Exception If an error occurs while creating the tar file
	 */
	abstract function createBackup($tempfile,$set);

}


