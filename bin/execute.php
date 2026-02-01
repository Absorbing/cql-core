<?php

/**
 * CQL Execute - Command-line tool for executing CQL queries
 * 
 * Usage: php bin/execute.php "DEFINE 'users.csv' AS users WITH HEADERS SELECT * FROM users WHERE age > 18"
 */

require_once __DIR__ . '/../vendor/autoload.php';

use CQL\CQL;

if ($argc < 2) {
    echo "CQL Execute - Execute CQL queries from the command line\n\n";
    echo "Usage: php bin/execute.php \"QUERY\"\n\n";
    echo "Example:\n";
    echo "  php bin/execute.php \"DEFINE 'users.csv' AS users WITH HEADERS SELECT name, age FROM users WHERE age > 18\"\n\n";
    echo "Options:\n";
    echo "  --streaming    Force streaming mode\n";
    echo "  --normal       Force normal mode (load into memory)\n";
    echo "  --json         Output as JSON (default)\n";
    echo "  --table        Output as table\n";
    echo "  --count        Output count only\n";
    exit(1);
}

// Parse arguments
$query = '';
$streaming = null;
$format = 'json';
$countOnly = false;

for ($i = 1; $i < $argc; $i++) {
    $arg = $argv[$i];
    
    if ($arg === '--streaming') {
        $streaming = true;
    } elseif ($arg === '--normal') {
        $streaming = false;
    } elseif ($arg === '--json') {
        $format = 'json';
    } elseif ($arg === '--table') {
        $format = 'table';
    } elseif ($arg === '--count') {
        $countOnly = true;
    } else {
        $query .= ($query ? ' ' : '') . $arg;
    }
}

try {
    // Create CQL instance with options
    $cql = new CQL(['streaming' => $streaming]);
    
    // Execute query
    if ($countOnly) {
        $count = $cql->count($query);
        echo "$count\n";
    } else {
        $results = $cql->execute($query);
        
        if ($format === 'json') {
            foreach ($results as $row) {
                echo json_encode($row, JSON_PRETTY_PRINT) . "\n";
            }
        } elseif ($format === 'table') {
            $array = $results->toArray();
            if (empty($array)) {
                echo "No results\n";
            } else {
                // Print header
                $keys = array_keys($array[0]);
                echo implode("\t", $keys) . "\n";
                echo str_repeat('-', count($keys) * 20) . "\n";
                
                // Print rows
                foreach ($array as $row) {
                    echo implode("\t", $row) . "\n";
                }
            }
        }
    }
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
