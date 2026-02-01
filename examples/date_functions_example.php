<?php

/**
 * Date Functions Example
 * 
 * This example demonstrates using date functions (YEAR, MONTH, DAY)
 * for date-based analysis and grouping.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use CQL\CQL;

echo "=== Date Functions Example ===\n\n";

// Create orders data with dates
$ordersFile = 'orders_with_dates.csv';
$ordersData = <<<CSV
order_id,customer,amount,order_date
1,Alice,250,2024-01-15
2,Bob,180,2024-01-22
3,Charlie,320,2024-02-05
4,Diana,150,2024-02-14
5,Eve,420,2024-02-28
6,Frank,290,2024-03-10
7,Grace,380,2024-03-15
8,Henry,210,2024-03-22
9,Ivy,340,2024-04-05
10,Jack,190,2024-04-18
11,Kate,280,2024-04-25
12,Leo,450,2024-05-08
CSV;
file_put_contents($ordersFile, $ordersData);

echo "Sample Orders Data:\n";
echo str_repeat('-', 60) . "\n";
printf("%-10s %-15s %-10s %-15s\n", "Order ID", "Customer", "Amount", "Date");
echo str_repeat('-', 60) . "\n";

$lines = explode("\n", trim($ordersData));
array_shift($lines); // Remove header
foreach (array_slice($lines, 0, 6) as $line) {
    $parts = explode(',', $line);
    printf("%-10s %-15s $%-9s %-15s\n", $parts[0], $parts[1], $parts[2], $parts[3]);
}
echo "... and 6 more rows\n\n";

// Example 1: Extract year, month, day from dates
echo "=== Example 1: Extract Date Components ===\n\n";

$query1 = "
    DEFINE 'orders_with_dates.csv' AS orders WITH HEADERS
    SELECT customer, amount, order_date,
           YEAR(order_date) AS year,
           MONTH(order_date) AS month,
           DAY(order_date) AS day
    FROM orders
";

$cql = new CQL();
$results = $cql->execute($query1);

echo str_repeat('-', 80) . "\n";
printf("%-15s %-10s %-15s %-8s %-8s %-8s\n", "Customer", "Amount", "Date", "Year", "Month", "Day");
echo str_repeat('-', 80) . "\n";

$count = 0;
foreach ($results as $row) {
    if ($count++ >= 5) break;
    printf("%-15s $%-9s %-15s %-8s %-8s %-8s\n",
        $row['customer'],
        $row['amount'],
        $row['order_date'],
        $row['year'],
        $row['month'],
        $row['day']
    );
}
echo "... and more rows\n";

// Example 2: Group by month and calculate monthly revenue
echo "\n=== Example 2: Monthly Revenue Analysis ===\n\n";

$query2 = "
    DEFINE 'orders_with_dates.csv' AS orders WITH HEADERS
    SELECT MONTH(order_date) AS month,
           COUNT(*) AS order_count,
           SUM(amount) AS total_revenue,
           AVG(amount) AS avg_order_value
    FROM orders
    GROUP BY MONTH(order_date)
";

$results = $cql->execute($query2);

echo str_repeat('-', 70) . "\n";
printf("%-10s %-15s %-20s %-20s\n", "Month", "Orders", "Total Revenue", "Avg Order Value");
echo str_repeat('-', 70) . "\n";

$monthNames = ['', 'January', 'February', 'March', 'April', 'May', 'June', 
               'July', 'August', 'September', 'October', 'November', 'December'];

foreach ($results as $row) {
    $monthName = $monthNames[$row['month']] ?? "Month {$row['month']}";
    printf("%-10s %-15s $%-19s $%-19s\n",
        $monthName,
        $row['order_count'],
        number_format($row['total_revenue']),
        number_format($row['avg_order_value'], 2)
    );
}

// Example 3: Filter by specific month
echo "\n=== Example 3: February Orders Only ===\n\n";

$query3 = "
    DEFINE 'orders_with_dates.csv' AS orders WITH HEADERS
    SELECT customer, amount, order_date
    FROM orders
    WHERE MONTH(order_date) = 2
";

$results = $cql->execute($query3);

echo "Orders placed in February:\n";
echo str_repeat('-', 50) . "\n";
printf("%-15s %-10s %-15s\n", "Customer", "Amount", "Date");
echo str_repeat('-', 50) . "\n";

foreach ($results as $row) {
    printf("%-15s $%-9s %-15s\n",
        $row['customer'],
        $row['amount'],
        $row['order_date']
    );
}

// Example 4: Q1 2024 analysis (January-March)
echo "\n=== Example 4: Q1 2024 Summary ===\n\n";

$query4 = "
    DEFINE 'orders_with_dates.csv' AS orders WITH HEADERS
    SELECT COUNT(*) AS total_orders,
           SUM(amount) AS total_revenue,
           MIN(amount) AS smallest_order,
           MAX(amount) AS largest_order
    FROM orders
    WHERE MONTH(order_date) <= 3
";

$results = $cql->execute($query4);

foreach ($results as $row) {
    echo "Q1 2024 Performance:\n";
    echo "  Total Orders: {$row['total_orders']}\n";
    echo "  Total Revenue: $" . number_format($row['total_revenue']) . "\n";
    echo "  Smallest Order: $" . number_format($row['smallest_order']) . "\n";
    echo "  Largest Order: $" . number_format($row['largest_order']) . "\n";
}

// Cleanup
unlink($ordersFile);

echo "\n✓ Example completed!\n\n";

echo "Summary of Date Functions:\n";
echo "- YEAR(date): Extract year from date (returns integer)\n";
echo "- MONTH(date): Extract month from date (returns 1-12)\n";
echo "- DAY(date): Extract day from date (returns 1-31)\n";
echo "- DATE(date): Format date as YYYY-MM-DD\n";
echo "- Can be used in SELECT, WHERE, and GROUP BY clauses\n";
echo "- Supports standard date formats (YYYY-MM-DD, etc.)\n";
