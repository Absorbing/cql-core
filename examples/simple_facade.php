<?php

/**
 * Simple Facade Example
 * 
 * This example demonstrates the simplified CQL facade for easy usage.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use CQL\CQL;

echo "=== CQL Facade Example ===\n\n";

// Create test data
$csvFile = 'products.csv';
$csvData = <<<CSV
id,name,price,stock
1,Laptop,1200,5
2,Mouse,25,50
3,Keyboard,75,30
4,Monitor,350,10
CSV;
file_put_contents($csvFile, $csvData);

// ============================================================================
// Example 1: Basic Usage
// ============================================================================
echo "=== Example 1: Basic Usage ===\n\n";

$cql = new CQL();

$query = "
    DEFINE 'products.csv' AS products WITH HEADERS
    SELECT name, price
    FROM products
    WHERE price > 50
";

$results = $cql->execute($query);

echo "Results (" . $results->count() . " products):\n";
foreach ($results as $row) {
    echo "- {$row['name']}: \${$row['price']}\n";
}

// ============================================================================
// Example 2: Convenience Methods
// ============================================================================
echo "\n=== Example 2: Convenience Methods ===\n\n";

// Get results as array
$array = $cql->query($query);
echo "query() returns array: " . count($array) . " items\n";

// Get first result
$first = $cql->first($query);
echo "first() returns: {$first['name']} at \${$first['price']}\n";

// Get count only
$count = $cql->count($query);
echo "count() returns: $count\n";

// ============================================================================
// Example 3: Static Factory Methods
// ============================================================================
echo "\n=== Example 3: Static Factory Methods ===\n\n";

// Automatic mode (default)
$auto = CQL::auto();
echo "Auto mode: " . ($auto->getStreaming() === null ? "automatic" : "explicit") . "\n";

// Streaming mode
$streaming = CQL::streaming();
echo "Streaming mode: " . ($streaming->getStreaming() ? "enabled" : "disabled") . "\n";

// Normal mode
$normal = CQL::normal();
echo "Normal mode: " . ($normal->getStreaming() === false ? "memory-based" : "streaming") . "\n";

// Auto with custom threshold
$customAuto = CQL::auto(10 * 1024 * 1024); // 10MB
echo "Custom auto threshold: " . round($customAuto->getAutoStreamingThreshold() / 1024 / 1024, 2) . " MB\n";

// ============================================================================
// Example 4: Configuration Options
// ============================================================================
echo "\n=== Example 4: Configuration Options ===\n\n";

// Configure via constructor
$cql1 = new CQL([
    'streaming' => true,
    'autoStreamingThreshold' => 20 * 1024 * 1024
]);
echo "Constructor config: streaming=" . ($cql1->getStreaming() ? "true" : "false") . "\n";

// Configure via methods (fluent interface)
$cql2 = (new CQL())
    ->setStreaming(true)
    ->setAutoStreamingThreshold(30 * 1024 * 1024);
echo "Fluent config: threshold=" . round($cql2->getAutoStreamingThreshold() / 1024 / 1024) . " MB\n";

// ============================================================================
// Example 5: One-Liner Queries
// ============================================================================
echo "\n=== Example 5: One-Liner Queries ===\n\n";

// Quick query
$products = (new CQL())->query("
    DEFINE 'products.csv' AS p WITH HEADERS
    SELECT name FROM p WHERE stock < 20
");

echo "Low stock products:\n";
foreach ($products as $product) {
    echo "- {$product['name']}\n";
}

// Quick count
$highPriceCount = (new CQL())->count("
    DEFINE 'products.csv' AS p WITH HEADERS
    SELECT * FROM p WHERE price > 100
");

echo "\nHigh-price products: $highPriceCount\n";

// ============================================================================
// Example 6: Error Handling
// ============================================================================
echo "\n=== Example 6: Error Handling ===\n\n";

try {
    $cql = new CQL();
    $results = $cql->execute("
        DEFINE 'nonexistent.csv' AS data WITH HEADERS
        SELECT * FROM data
    ");
} catch (\CQL\Exceptions\DataSourceException $e) {
    echo "Caught DataSourceException: {$e->getMessage()}\n";
} catch (\Exception $e) {
    echo "Caught Exception: {$e->getMessage()}\n";
}

// Cleanup
unlink($csvFile);

echo "\n✓ Example completed!\n\n";

echo "Summary:\n";
echo "- Use 'new CQL()' for simple queries\n";
echo "- Use static factories (CQL::auto(), CQL::streaming(), CQL::normal())\n";
echo "- Convenience methods: query(), first(), count()\n";
echo "- Fluent configuration with setStreaming() and setAutoStreamingThreshold()\n";
echo "- All exceptions are properly typed for easy error handling\n";
