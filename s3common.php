<?php

/**
 * btsu!
 *
 * Backup and restore scripts for Amazon S3.
 * 
 * Copyright (c) 2012, Travis Richardson
 * All rights reserved.
 * 
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *     * Redistributions of source code must retain the above copyright
 *       notice, this list of conditions and the following disclaimer.
 *     * Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *     * Neither the name of the Travis Richardson nor the
 *       names of its contributors may be used to endorse or promote products
 *       derived from this software without specific prior written permission.
 * 
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL TRAVIS RICHARDSON BE LIABLE FOR ANY
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

define('START_TIME',time());

if (!file_exists(dirname(__FILE__).'/S3.php')) {
    dirError("Missing required file ".dirname(__FILE__)."/S3.php\n");
}
require_once(dirname(__FILE__)."/S3.php");

$_ARGS = parseArgs($_SERVER['argv']);

function init() {
    global $_ARGS,$_INI_PATH,$_INI,$_SETS;

    if (array_key_exists('conf',$_ARGS) && $_ARGS['conf']) {
        $_INI_PATH = $_ARGS['conf'];
    }
    else {
        $_INI_PATH = $_SERVER['HOME'].'/s3backup.ini';
    }

    if (!file_exists($_INI_PATH)) {
        dieError("Config file missing: $_INI_PATH\n");
    }
    if (!is_readable($_INI_PATH)) {
        dieError("Unable to read from $_INI_PATH\n");
    }

    $_INI = parse_ini_file($_INI_PATH,true);
    if (!$_INI) {
        dieError("Badly formatted config file $_INI_PATH\n");
    }

    $_SETS = array();
    foreach ($_INI as $set=>$settings) {
        if ($set=='defaults') {
            continue;
        }
        // if --set was specified, only show that one
        if (!array_key_exists('set',$_ARGS) || $_ARGS['set']==$set) {
            // sets cannot contain special chars, spaces, etc
            if (preg_match('/[^a-zA-Z0-9\.\-\_]/',$set)) {
                dirError("Invalid set name '$set' (a-z, A-Z, 0-9, periods, dashes, and underscores only)\n");
            }
            $_SETS[] = $set;
            // verify we have this type and include it
            if (!array_key_exists('type',$settings)) {
                dieError("Missing required 'type' for backup set $set\n");
            }
            $type = $settings['type'];
            // check the module exists
            if (!file_exists(dirname(__FILE__).'/modules/s3backup_'.$type.'.class.php')) {
                dieError("Missing '$type' module for backup set $set\n");
            }
            require_once(dirname(__FILE__).'/modules/s3backup_'.$type.'.class.php');
        }
    }
}


/**
 * Fetches a value from the ini file for the specified backup set.
 * If the backup set does not contain the value requested, return
 * the default value instead. If the default section does not 
 * contain the value, return null.
 * @param string $set The name of the backup set to get the value for.
 * @param string $key The name of the value in the backup set to retrieve the value for.
 * @return string The value you requested
 */
function getSetValue($set,$key) {
    global $_INI;

    if (!array_key_exists($set,$_INI)) {
        throw new Exception('Missing backup set '.$set);
    }

    // if the set has this value, return that
    if (array_key_exists($key,$_INI[$set])) {
        return $_INI[$set][$key];
    }

    // if it exists in the defaults, return that
    if (array_key_exists('defaults',$_INI) && array_key_exists($key,$_INI['defaults'])) {
        return $_INI['defaults'][$key];
    }

    // else null
    return null;
}

function printVerbose($message) {
    global $_ARGS;
    if (array_key_exists('verbose',$_ARGS)) {
        print $message;
    }
}

function printQuiet($message) {
    global $_ARGS;
    if (!array_key_exists('quiet',$_ARGS)) {
        print $message;
    }
}

$_ERR_OUT = null;
/**
 * Prints a message to stderr
 */
function printError($error) {
    global $_ERR_OUT;

    if (!$_ERR_OUT) {
        $_ERR_OUT = fopen('php://stderr','wb');
    }

    fwrite($_ERR_OUT,'ERROR: '.$error);
}

/**
 * Prints a message to stderr and then exits with an error code (default 1)
 */
function dieError($error,$code=1) {
    printError($error);
    die($code);
}

/**
 * parseArgs Command Line Interface (CLI) utility function.
 * @usage               $args = parseArgs($_SERVER['argv']);
 * @author              Patrick Fisher <patrick@pwfisher.com>
 * @source              https://github.com/pwfisher/CommandLine.php
 */
function parseArgs($argv){
    array_shift($argv); $o = array();
    foreach ($argv as $a){
        if (substr($a,0,2) == '--'){ $eq = strpos($a,'=');
            if ($eq !== false){ $o[substr($a,2,$eq-2)] = substr($a,$eq+1); }
            else { $k = substr($a,2); if (!isset($o[$k])){ $o[$k] = true; } } }
        else if (substr($a,0,1) == '-'){
            if (substr($a,2,1) == '='){ $o[substr($a,1,1)] = substr($a,3); }
            else { foreach (str_split(substr($a,1)) as $k){ if (!isset($o[$k])){ $o[$k] = true; } } } }
        else { $o[] = $a; } }
    return $o;
}
