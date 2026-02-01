<?php

/**
 * Real-World Example: Sales Analysis
 * 
 * This example demonstrates a practical use case: analyzing sales data
 * from multiple CSV files to generate business insights.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use CQL\CQL;

echo "=== Real-World Example: Sales Analysis ===\n\n";

// Create sales representatives CSV
$repsFile = 'sales_reps.csv';
$repsData = <<<CSV
rep_id,name,region,hire_date
1,Alice Johnson,North,2020-01-15
2,Bob Smith,South,2019-06-20
3,Charlie Brown,East,2021-03-10
4,Diana Prince,West,2020-11-05
CSV;
file_put_contents($repsFile, $repsData);

// Create sales transactions CSV
$salesFile = 'sales_transactions.csv';
$salesData = <<<CSV
transaction_id,rep_id,product,quantity,unit_price,date
1001,1,Widget A,10,50,2024-01-15
1002,1,Widget B,5,75,2024-01-16
1003,2,Widget A,15,50,2024-01-17
1004,3,Widget C,8,100,2024-01-18
1005,1,Widget A,12,50,2024-01-19
1006,4,Widget B,20,75,2024-01-20
1007,2,Widget C,6,100,2024-01-21
1008,3,Widget A,25,50,2024-01-22
1009,4,Widget B,10,75,2024-01-23
1010,1,Widget C,4,100,2024-01-24
CSV;
file_put_contents($salesFile, $salesData);

echo "Scenario: Analyze Q1 2024 sales performance by representative\n\n";

// Query: Get all sales with rep information
$query = "
    DEFINE 'sales_reps.csv' AS reps WITH HEADERS
    DEFINE 'sales_transactions.csv' AS sales WITH HEADERS
    SELECT reps.name, reps.region, sales.product, sales.quantity, sales.unit_price, sales.date
    FROM reps
    JOIN sales ON reps.rep_id = sales.rep_id
";

$cql = new CQL();
$results = $cql->execute($query);

// Analyze results
$repSales = [];
$regionSales = [];
$productSales = [];

foreach ($results as $row) {
    $rep = $row['reps.name'];
    $region = $row['reps.region'];
    $product = $row['sales.product'];
    $total = (float)$row['sales.quantity'] * (float)$row['sales.unit_price'];
    
    // Aggregate by rep
    if (!isset($repSales[$rep])) {
        $repSales[$rep] = ['region' => $region, 'total' => 0, 'transactions' => 0];
    }
    $repSales[$rep]['total'] += $total;
    $repSales[$rep]['transactions']++;
    
    // Aggregate by region
    if (!isset($regionSales[$region])) {
        $regionSales[$region] = 0;
    }
    $regionSales[$region] += $total;
    
    // Aggregate by product
    if (!isset($productSales[$product])) {
        $productSales[$product] = ['quantity' => 0, 'revenue' => 0];
    }
    $productSales[$product]['quantity'] += (int)$row['sales.quantity'];
    $productSales[$product]['revenue'] += $total;
}

// Display Sales by Representative
echo "=== Sales by Representative ===\n";
echo str_repeat('-', 70) . "\n";
printf("%-20s %-10s %-15s %-12s\n", "Rep Name", "Region", "Total Sales", "Transactions");
echo str_repeat('-', 70) . "\n";

arsort($repSales);
foreach ($repSales as $rep => $data) {
    printf("%-20s %-10s $%-14s %-12d\n",
        $rep,
        $data['region'],
        number_format($data['total']),
        $data['transactions']
    );
}

// Display Sales by Region
echo "\n=== Sales by Region ===\n";
echo str_repeat('-', 40) . "\n";
printf("%-15s %-20s\n", "Region", "Total Sales");
echo str_repeat('-', 40) . "\n";

arsort($regionSales);
foreach ($regionSales as $region => $total) {
    printf("%-15s $%-19s\n", $region, number_format($total));
}

// Display Product Performance
echo "\n=== Product Performance ===\n";
echo str_repeat('-', 50) . "\n";
printf("%-15s %-12s %-15s\n", "Product", "Units Sold", "Revenue");
echo str_repeat('-', 50) . "\n";

uasort($productSales, fn($a, $b) => $b['revenue'] <=> $a['revenue']);
foreach ($productSales as $product => $data) {
    printf("%-15s %-12d $%-14s\n",
        $product,
        $data['quantity'],
        number_format($data['revenue'])
    );
}

// Calculate overall metrics
$totalRevenue = array_sum($regionSales);
$totalTransactions = array_sum(array_column($repSales, 'transactions'));
$avgTransactionValue = $totalRevenue / $totalTransactions;

echo "\n=== Overall Metrics ===\n";
echo "Total Revenue: $" . number_format($totalRevenue) . "\n";
echo "Total Transactions: " . $totalTransactions . "\n";
echo "Average Transaction Value: $" . number_format($avgTransactionValue, 2) . "\n";

// Find top performer
$topRep = array_key_first($repSales);
$topRegion = array_key_first($regionSales);

echo "\n=== Top Performers ===\n";
echo "Top Sales Rep: $topRep (\$" . number_format($repSales[$topRep]['total']) . ")\n";
echo "Top Region: $topRegion (\$" . number_format($regionSales[$topRegion]) . ")\n";

// Cleanup
unlink($repsFile);
unlink($salesFile);

echo "\n✓ Analysis completed!\n";
echo "\nThis example demonstrates:\n";
echo "- Joining multiple CSV files\n";
echo "- Aggregating data in PHP after querying\n";
echo "- Generating business insights from raw data\n";
echo "- Real-world sales analysis workflow\n";
