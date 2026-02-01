<?php

/**
 * Streaming Mode Example
 * 
 * This example demonstrates how to use streaming mode for processing
 * large CSV files with minimal memory usage.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use CQL\CQL;
use CQL\Data\CSVDataSource;

// Create a sample large CSV file
echo "Creating sample large CSV file...\n";
$filename = 'large_sales_data.csv';
$handle = fopen($filename, 'w');

// Write headers
fputcsv($handle, ['id', 'product', 'quantity', 'price', 'date', 'region']);

// Generate 10,000 sample rows
for ($i = 1; $i <= 10000; $i++) {
    fputcsv($handle, [
        $i,
        'Product' . rand(1, 100),
        rand(1, 100),
        rand(10, 1000),
        date('Y-m-d', strtotime("-" . rand(0, 365) . " days")),
        ['North', 'South', 'East', 'West'][rand(0, 3)]
    ]);
}
fclose($handle);

// Check file size
$source = new CSVDataSource($filename);
echo "File created: {$source->getFileSizeFormatted()}\n\n";

// Query to find high-value sales
$query = "
    DEFINE '{$filename}' AS sales WITH HEADERS
    SELECT product, quantity, price, region
    FROM sales
    WHERE price > 500 AND quantity > 50
";

echo "=== Comparison: Normal vs Streaming Mode ===\n\n";

// Test 1: Normal Mode
echo "--- Normal Mode ---\n";
$startMemory = memory_get_usage();
$startTime = microtime(true);

$cql = CQL::normal();
$results = $cql->execute($query);

$normalCount = $results->count();
$normalMemory = memory_get_usage() - $startMemory;
$normalTime = (microtime(true) - $startTime) * 1000;

echo "Results: {$normalCount} rows\n";
echo "Memory: " . round($normalMemory / 1024, 2) . " KB\n";
echo "Time: " . round($normalTime, 2) . " ms\n\n";

// Test 2: Streaming Mode
echo "--- Streaming Mode ---\n";
$startMemory = memory_get_usage();
$startTime = microtime(true);

$cql = CQL::streaming();
$results = $cql->execute($query);

$streamCount = $results->count();
$streamMemory = memory_get_usage() - $startMemory;
$streamTime = (microtime(true) - $startTime) * 1000;

echo "Results: {$streamCount} rows\n";
echo "Memory: " . round($streamMemory / 1024, 2) . " KB\n";
echo "Time: " . round($streamTime, 2) . " ms\n\n";

// Calculate savings
$memorySavings = (($normalMemory - $streamMemory) / $normalMemory) * 100;
echo "=== Summary ===\n";
echo "Memory savings: " . round($memorySavings, 1) . "%\n";
echo "Time difference: " . round($streamTime - $normalTime, 2) . " ms\n\n";

// Show sample results
echo "=== Sample Results (first 5) ===\n";
$count = 0;
foreach ($results as $row) {
    if ($count++ >= 5) break;
    echo json_encode($row) . "\n";
}

// Cleanup
unlink($filename);

echo "\n✓ Example completed!\n";
echo "\nRecommendation: Use streaming mode for files larger than 50MB\n";
echo "to significantly reduce memory usage with minimal performance impact.\n";
