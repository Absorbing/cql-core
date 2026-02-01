<?php

/**
 * Basic Query Example
 * 
 * This example demonstrates the simplest way to query a CSV file.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use CQL\CQL;

// Create a sample CSV file
$csvFile = 'employees.csv';
$csvData = <<<CSV
id,name,department,salary,years
1,Alice Johnson,Engineering,95000,5
2,Bob Smith,Marketing,75000,3
3,Charlie Brown,Engineering,105000,8
4,Diana Prince,Sales,85000,4
5,Eve Wilson,Engineering,92000,6
CSV;

file_put_contents($csvFile, $csvData);

echo "=== Basic CQL Query Example ===\n\n";

// Query: Find all engineers earning more than $90,000
$query = "
    DEFINE 'employees.csv' AS emp WITH HEADERS
    SELECT name, salary, years
    FROM emp
    WHERE salary > 90000
";

echo "Query:\n$query\n";

// Execute the query using the CQL facade
$cql = new CQL();
$results = $cql->execute($query);

// Display results
echo "Results (" . $results->count() . " rows):\n";
echo str_repeat('-', 60) . "\n";
printf("%-20s %-15s %-10s\n", "Name", "Salary", "Years");
echo str_repeat('-', 60) . "\n";

foreach ($results as $row) {
    printf("%-20s $%-14s %-10s\n", 
        $row['name'], 
        number_format((float)$row['salary']), 
        $row['years']
    );
}

// Cleanup
unlink($csvFile);

echo "\n✓ Example completed!\n";
