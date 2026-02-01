<?php

/**
 * Mode Selection Example
 * 
 * This example demonstrates the three ways to control streaming mode:
 * 1. Explicit Normal Mode (streaming: false)
 * 2. Explicit Streaming Mode (streaming: true)
 * 3. Automatic Mode (streaming: null or omitted)
 */

require_once __DIR__ . '/../vendor/autoload.php';

use CQL\CQL;

// Create test files of different sizes
echo "Creating test files...\n";

// Small file (< 1KB)
$smallFile = 'small_data.csv';
file_put_contents($smallFile, "id,name,value\n1,Alice,100\n2,Bob,200\n3,Charlie,300");

// Medium file (~50KB)
$mediumFile = 'medium_data.csv';
$handle = fopen($mediumFile, 'w');
fputcsv($handle, ['id', 'name', 'value']);
for ($i = 1; $i <= 1000; $i++) {
    fputcsv($handle, [$i, "User$i", rand(1, 1000)]);
}
fclose($handle);

// Large file (~500KB)
$largeFile = 'large_data.csv';
$handle = fopen($largeFile, 'w');
fputcsv($handle, ['id', 'name', 'value']);
for ($i = 1; $i <= 10000; $i++) {
    fputcsv($handle, [$i, "User$i", rand(1, 1000)]);
}
fclose($handle);

$smallSize = filesize($smallFile);
$mediumSize = filesize($mediumFile);
$largeSize = filesize($largeFile);

echo "Small file: " . round($smallSize / 1024, 2) . " KB\n";
echo "Medium file: " . round($mediumSize / 1024, 2) . " KB\n";
echo "Large file: " . round($largeSize / 1024, 2) . " KB\n\n";

// ============================================================================
// Example 1: Explicit Normal Mode
// ============================================================================
echo "=== Example 1: Explicit Normal Mode ===\n";
echo "Use when: You know files are small and want maximum performance\n\n";

$query = "DEFINE '$smallFile' AS data WITH HEADERS SELECT * FROM data WHERE value > 150";

// Force normal mode
$cql = CQL::normal();
$results = $cql->execute($query);

echo "Mode: Normal (explicit)\n";
echo "Results: " . $results->count() . " rows\n\n";

// ============================================================================
// Example 2: Explicit Streaming Mode
// ============================================================================
echo "=== Example 2: Explicit Streaming Mode ===\n";
echo "Use when: You know files are large or memory is limited\n\n";

$query = "DEFINE '$largeFile' AS data WITH HEADERS SELECT * FROM data WHERE value > 500";

// Force streaming mode
$cql = CQL::streaming();
$results = $cql->execute($query);

echo "Mode: Streaming (explicit)\n";
echo "Results: " . $results->count() . " rows\n\n";

// ============================================================================
// Example 3: Automatic Mode (Default 50MB Threshold)
// ============================================================================
echo "=== Example 3: Automatic Mode (Default 50MB Threshold) ===\n";
echo "Use when: File sizes vary or are unknown\n\n";

foreach ([$smallFile, $mediumFile, $largeFile] as $file) {
    $query = "DEFINE '$file' AS data WITH HEADERS SELECT * FROM data WHERE value > 500";
    
    // Automatic mode (default)
    $cql = CQL::auto();
    $results = $cql->execute($query);
    
    $fileSize = filesize($file);
    $threshold = $cql->getAutoStreamingThreshold();
    $mode = $fileSize > $threshold ? "Streaming" : "Normal";
    
    echo "File: $file (" . round($fileSize / 1024, 2) . " KB)\n";
    echo "Threshold: " . round($threshold / 1024 / 1024, 2) . " MB\n";
    echo "Selected mode: $mode\n";
    echo "Reason: File is " . ($fileSize > $threshold ? "larger" : "smaller") . " than threshold\n\n";
}

// ============================================================================
// Example 4: Automatic Mode with Custom Threshold
// ============================================================================
echo "=== Example 4: Automatic Mode with Custom Threshold (100KB) ===\n";
echo "Use when: You want automatic selection with a specific threshold\n\n";

$customThreshold = 100 * 1024; // 100KB

foreach ([$smallFile, $mediumFile, $largeFile] as $file) {
    $query = "DEFINE '$file' AS data WITH HEADERS SELECT * FROM data WHERE value > 500";
    
    // Automatic mode with custom threshold
    $cql = CQL::auto($customThreshold);
    $results = $cql->execute($query);
    
    $fileSize = filesize($file);
    $mode = $fileSize > $customThreshold ? "Streaming" : "Normal";
    
    echo "File: $file (" . round($fileSize / 1024, 2) . " KB)\n";
    echo "Threshold: " . round($customThreshold / 1024, 2) . " KB\n";
    echo "Selected mode: $mode\n\n";
}

// ============================================================================
// Example 5: Recommended Pattern for Production
// ============================================================================
echo "=== Example 5: Recommended Pattern for Production ===\n\n";

function executeQuery(string $file, string $query): void
{
    // Use automatic mode with environment-specific threshold
    $threshold = getenv('CQL_STREAMING_THRESHOLD') ?: (50 * 1024 * 1024); // 50MB default
    $cql = CQL::auto((int)$threshold);
    
    // Log the selected mode
    $fileSize = filesize(str_replace(['\'', '"'], '', $file));
    $mode = $fileSize > (int)$threshold ? "streaming" : "normal";
    error_log("Executing query on $file (" . round($fileSize / 1024, 2) . " KB) using $mode mode");
    
    $results = $cql->execute($query);
    echo "Processed " . $results->count() . " rows using $mode mode\n";
}

$query = "DEFINE '$mediumFile' AS data WITH HEADERS SELECT * FROM data WHERE value > 500";
executeQuery($mediumFile, $query);

// Cleanup
unlink($smallFile);
unlink($mediumFile);
unlink($largeFile);

echo "\n✓ Example completed!\n\n";

echo "Summary:\n";
echo "- Use explicit modes when you know file characteristics\n";
echo "- Use automatic mode (recommended) for dynamic workloads\n";
echo "- Adjust threshold based on available memory and performance needs\n";
echo "- Default 50MB threshold works well for most applications\n";
