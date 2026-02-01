<?php

/**
 * Aggregate Functions Example
 * 
 * This example demonstrates using aggregate functions (COUNT, SUM, AVG, MIN, MAX)
 * with and without GROUP BY clauses.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use CQL\CQL;

echo "=== Aggregate Functions Example ===\n\n";

// Create sales data CSV
$salesFile = 'sales_data.csv';
$salesData = <<<CSV
order_id,product,category,quantity,price,date,region
1,Laptop,Electronics,2,1200,2024-01-15,North
2,Mouse,Electronics,5,25,2024-01-16,North
3,Desk,Furniture,1,350,2024-01-17,South
4,Chair,Furniture,4,150,2024-01-18,South
5,Monitor,Electronics,3,300,2024-01-19,East
6,Keyboard,Electronics,10,75,2024-01-20,North
7,Lamp,Furniture,2,45,2024-01-21,West
8,Webcam,Electronics,1,80,2024-01-22,East
9,Bookshelf,Furniture,1,200,2024-01-23,South
10,Headphones,Electronics,6,50,2024-01-24,West
CSV;
file_put_contents($salesFile, $salesData);

echo "Sample Sales Data:\n";
echo str_repeat('-', 80) . "\n";
printf("%-10s %-15s %-15s %-10s %-10s %-12s %-10s\n", "Order ID", "Product", "Category", "Quantity", "Price", "Date", "Region");
echo str_repeat('-', 80) . "\n";

$lines = explode("\n", trim($salesData));
array_shift($lines); // Remove header
foreach (array_slice($lines, 0, 5) as $line) {
    $parts = explode(',', $line);
    printf("%-10s %-15s %-15s %-10s $%-9s %-12s %-10s\n", $parts[0], $parts[1], $parts[2], $parts[3], $parts[4], $parts[5], $parts[6]);
}
echo "... and 5 more rows\n\n";

// Example 1: Basic aggregates without GROUP BY
echo "=== Example 1: Overall Statistics (No GROUP BY) ===\n\n";

$query1 = "
    DEFINE 'sales_data.csv' AS sales WITH HEADERS
    SELECT COUNT(*) AS total_orders, 
           SUM(quantity) AS total_items, 
           AVG(price) AS avg_price,
           MIN(price) AS min_price,
           MAX(price) AS max_price
    FROM sales
";

$cql = new CQL();
$results = $cql->execute($query1);

foreach ($results as $row) {
    echo "Total Orders: {$row['total_orders']}\n";
    echo "Total Items Sold: {$row['total_items']}\n";
    echo "Average Price: $" . number_format($row['avg_price'], 2) . "\n";
    echo "Minimum Price: $" . number_format($row['min_price'], 2) . "\n";
    echo "Maximum Price: $" . number_format($row['max_price'], 2) . "\n";
}

// Example 2: Aggregates with GROUP BY (by category)
echo "\n=== Example 2: Sales by Category (GROUP BY) ===\n\n";

$query2 = "
    DEFINE 'sales_data.csv' AS sales WITH HEADERS
    SELECT category,
           COUNT(*) AS order_count,
           SUM(quantity) AS total_quantity,
           SUM(price) AS total_revenue
    FROM sales
    GROUP BY category
";

$results = $cql->execute($query2);

echo str_repeat('-', 70) . "\n";
printf("%-20s %-15s %-15s %-15s\n", "Category", "Orders", "Quantity", "Revenue");
echo str_repeat('-', 70) . "\n";

foreach ($results as $row) {
    printf("%-20s %-15s %-15s $%-14s\n",
        $row['category'],
        $row['order_count'],
        $row['total_quantity'],
        number_format($row['total_revenue'])
    );
}

// Example 3: Aggregates with GROUP BY (by region)
echo "\n=== Example 3: Sales by Region (GROUP BY) ===\n\n";

$query3 = "
    DEFINE 'sales_data.csv' AS sales WITH HEADERS
    SELECT region,
           COUNT(*) AS orders,
           AVG(price) AS avg_price,
           MAX(quantity) AS max_quantity
    FROM sales
    GROUP BY region
";

$results = $cql->execute($query3);

echo str_repeat('-', 60) . "\n";
printf("%-15s %-15s %-15s %-15s\n", "Region", "Orders", "Avg Price", "Max Quantity");
echo str_repeat('-', 60) . "\n";

foreach ($results as $row) {
    printf("%-15s %-15s $%-14s %-15s\n",
        $row['region'],
        $row['orders'],
        number_format($row['avg_price'], 2),
        $row['max_quantity']
    );
}

// Example 4: COUNT with specific column
echo "\n=== Example 4: Count High-Value Orders (price > 100) ===\n\n";

$query4 = "
    DEFINE 'sales_data.csv' AS sales WITH HEADERS
    SELECT COUNT(*) AS high_value_orders
    FROM sales
    WHERE price > 100
";

$results = $cql->execute($query4);

foreach ($results as $row) {
    echo "High-value orders (>$100): {$row['high_value_orders']}\n";
}

// Cleanup
unlink($salesFile);

echo "\n✓ Example completed!\n\n";

echo "Summary of Aggregate Functions:\n";
echo "- COUNT(*): Count all rows\n";
echo "- COUNT(column): Count non-null values in column\n";
echo "- SUM(column): Sum of numeric values\n";
echo "- AVG(column): Average of numeric values\n";
echo "- MIN(column): Minimum value\n";
echo "- MAX(column): Maximum value\n";
echo "- GROUP BY: Group rows by column(s) before aggregating\n";
