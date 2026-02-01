<?php

/**
 * Mathematical Expressions Example
 * 
 * This example demonstrates using mathematical expressions in WHERE clauses.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use CQL\CQL;

echo "=== Mathematical Expressions Example ===\n\n";

// Create products CSV
$productsFile = 'products.csv';
$productsData = <<<CSV
product_id,name,price,quantity,discount_percent
1,Laptop,1200,5,10
2,Mouse,25,50,5
3,Keyboard,75,30,15
4,Monitor,350,10,20
5,USB Cable,15,100,0
6,Webcam,80,20,25
CSV;
file_put_contents($productsFile, $productsData);

echo "Product Inventory:\n";
echo str_repeat('-', 70) . "\n";
printf("%-15s %-10s %-10s %-15s\n", "Product", "Price", "Quantity", "Discount %");
echo str_repeat('-', 70) . "\n";

$lines = explode("\n", trim($productsData));
array_shift($lines); // Remove header
foreach ($lines as $line) {
    $parts = explode(',', $line);
    printf("%-15s $%-9s %-10s %-15s\n", $parts[1], $parts[2], $parts[3], $parts[4] . '%');
}

echo "\n";

// Example 1: Find products where total inventory value > $1000
echo "=== Example 1: High-Value Inventory (price * quantity > 1000) ===\n\n";

$query1 = "
    DEFINE 'products.csv' AS products WITH HEADERS
    SELECT name, price, quantity
    FROM products
    WHERE price * quantity > 1000
";

$cql = new CQL();
$results = $cql->execute($query1);

echo "Results (" . $results->count() . " products):\n";
foreach ($results as $row) {
    $total = (float)$row['price'] * (float)$row['quantity'];
    echo "- {$row['name']}: \${$row['price']} × {$row['quantity']} = $" . number_format($total) . "\n";
}

// Example 2: Find products with significant discounts (> 15%)
echo "\n=== Example 2: Significant Discounts (discount > 15) ===\n\n";

$query2 = "
    DEFINE 'products.csv' AS products WITH HEADERS
    SELECT name, price, discount_percent
    FROM products
    WHERE discount_percent > 15
";

$cql = new CQL();
$results = $cql->execute($query2);

echo "Results (" . $results->count() . " products):\n";
foreach ($results as $row) {
    $discountAmount = (float)$row['price'] * ((float)$row['discount_percent'] / 100);
    $finalPrice = (float)$row['price'] - $discountAmount;
    echo "- {$row['name']}: \${$row['price']} - {$row['discount_percent']}% = $" . number_format($finalPrice, 2) . "\n";
}

// Example 3: Complex expression - low stock high value items
echo "\n=== Example 3: Low Stock High Value (quantity < 25 AND price > 50) ===\n\n";

$query3 = "
    DEFINE 'products.csv' AS products WITH HEADERS
    SELECT name, price, quantity
    FROM products
    WHERE quantity < 25
";

$cql = new CQL();
$results = $cql->execute($query3);

// Filter further in PHP (CQL doesn't support AND in WHERE yet)
$filtered = [];
foreach ($results as $row) {
    if ((float)$row['price'] > 50) {
        $filtered[] = $row;
    }
}

echo "Results (" . count($filtered) . " products need restocking):\n";
foreach ($filtered as $row) {
    echo "- {$row['name']}: Only {$row['quantity']} units at \${$row['price']} each\n";
}

// Cleanup
unlink($productsFile);

echo "\n✓ Example completed!\n";
