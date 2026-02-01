<?php

/**
 * JOIN Example
 * 
 * This example demonstrates how to join multiple CSV files.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use CQL\CQL;

echo "=== JOIN Example ===\n\n";

// Create customers CSV
$customersFile = 'customers.csv';
$customersData = <<<CSV
customer_id,name,email,city
1,Alice Johnson,alice@example.com,New York
2,Bob Smith,bob@example.com,Los Angeles
3,Charlie Brown,charlie@example.com,Chicago
4,Diana Prince,diana@example.com,Houston
CSV;
file_put_contents($customersFile, $customersData);

// Create orders CSV
$ordersFile = 'orders.csv';
$ordersData = <<<CSV
order_id,customer_id,product,amount,date
101,1,Laptop,1200,2024-01-15
102,1,Mouse,25,2024-01-16
103,2,Keyboard,75,2024-01-17
104,3,Monitor,350,2024-01-18
105,1,USB Cable,15,2024-01-19
106,4,Laptop,1200,2024-01-20
CSV;
file_put_contents($ordersFile, $ordersData);

// Query: Find all orders with customer information
$query = "
    DEFINE 'customers.csv' AS customers WITH HEADERS
    DEFINE 'orders.csv' AS orders WITH HEADERS
    SELECT customers.name, customers.city, orders.product, orders.amount, orders.date
    FROM customers
    JOIN orders ON customers.customer_id = orders.customer_id
    WHERE orders.amount > 50
";

echo "Query: Join customers and orders, filter by amount > \$50\n\n";

$cql = new CQL();
$results = $cql->execute($query);

echo "Results (" . $results->count() . " orders):\n";
echo str_repeat('-', 80) . "\n";
printf("%-20s %-15s %-15s %-10s %-12s\n", "Customer", "City", "Product", "Amount", "Date");
echo str_repeat('-', 80) . "\n";

foreach ($results as $row) {
    printf("%-20s %-15s %-15s $%-9s %-12s\n",
        $row['customers.name'],
        $row['customers.city'],
        $row['orders.product'],
        number_format((float)$row['orders.amount']),
        $row['orders.date']
    );
}

// Calculate total
$total = 0;
foreach ($results as $row) {
    $total += (float)$row['orders.amount'];
}

echo str_repeat('-', 80) . "\n";
echo "Total: $" . number_format($total) . "\n";

// Cleanup
unlink($customersFile);
unlink($ordersFile);

echo "\n✓ Example completed!\n";
