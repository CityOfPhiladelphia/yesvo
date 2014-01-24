<?php
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
require('lib/novo.php');
require('lib/phpQuery-onefile.php');

if( ! defined('STDIN')) die('Please use via command line');

/**
 * Writes array of data to CSV file
 * Returns path to file
 */
function write_data($data, $filename) {
    $filename = preg_replace("/[^A-Za-z0-9_\-]/", '-', $filename);
    $path = 'data/' . $filename . '.csv';
    $fp = fopen($path, 'w') or die('Failed to open file');
    
    foreach ($data as $fields) {
        fputcsv($fp, $fields);
    }
    
    fclose($fp);
    return $path;
}

/**
 * Merge CSV files and go easy on memory
 * http://stackoverflow.com/a/5417964/633406
 */
function join_files(array $files, $filename) {
    if(!is_array($files)) {
        throw new Exception('`$files` must be an array');
    }
    $filename = preg_replace("/[^A-Za-z0-9_\-]/", '-', $filename);
    $path = 'data/' . $filename . '.csv';
    
    $wH = fopen($path, "w+");
    
    $headers = false;
    foreach($files as $file) {
        $fh = fopen($file, "r");
        
        // Print headers if they're not set already, otherwise skip them (they must be on the first line for this to work)
        $headers_line = fgets($fh);
        if( ! $headers) {
            fwrite($wH, $headers_line);
            $headers = true;
        }
        
        while(!feof($fh)) {
            // If line is not empty
            if($line = fgets($fh)) {
                fwrite($wH, $line);
            }
        }
        fclose($fh);
        unset($fh);
        //fwrite($wH, "\n"); //usually last line doesn't have a newline
    }
    fclose($wH);
    unset($wH);
    return $path;
}

// Get command line arguments
$options = getopt("i:u:p:d:e:", array('id:', 'username:', 'password:', 'data:', 'env:'));
if( ! isset($options['i'])) die("No report id provided. Use -i 1234\n");
if( ! isset($options['u'])) die("No username provided. Use -u usernamehere\n");
if( ! isset($options['p'])) die("No password provided. Use -p passwordhere\n");
$report_id = $options['i'];
$delimiter = ',';
$username = $options['u'];
$password = $options['p'];
$environment = isset($options['e']) ? $options['e'] : 'production';
$param_sets = isset($options['d']) ? $options['d'] : '';

// Initialize Novo Object
$novo = new Novo(array('username' => $username, 'password' => $password, 'environment' => $environment));

// Ensure $param_sets is an array of params
if( ! is_array($param_sets)) $param_sets = array($param_sets);

$paths = array();

foreach($param_sets as $params) {
    if($params) {
        $params = explode($delimiter, $params);
        echo print_r($params, true);
    }
    
    // Fetch data
    if($data = $novo->report($report_id, $params, false)) { // false to disable converting to associative array
        $filename = $report_id . ($params ? '_' . implode('_', $params) : '');
        $paths []= write_data($data, $filename);
        echo "Successfully wrote to " . $paths[sizeof($paths)-1] . "\n";
    } else {
        echo "No data retrieved for " . ($params ? implode(',', $params) : 'report') . "\n";
    }
}

// Merge CSV files
if( ! empty($paths) && sizeof($paths) > 1) {
    $merged_filename = 'Merge_' . rand();
    $merged_path = join_files($paths, $merged_filename);
    echo "Merged file written to " . $merged_path . "\n";
    // TODO: Delete source paths
}