# CQL Core Examples

This directory contains practical examples demonstrating various features of CQL Core.

## Running Examples

All examples are self-contained and can be run directly:

```bash
php examples/basic_query.php
php examples/join_example.php
php examples/expressions_example.php
php examples/streaming_example.php
php examples/mode_selection_example.php
php examples/real_world_sales_analysis.php
```

## Examples Overview

### 0. simple_facade.php
**Demonstrates**: CQL Facade - simplified API

The easiest way to use CQL Core with a clean, simple interface.

**Key Concepts**:
- CQL facade class
- Convenience methods (query(), first(), count())
- Static factory methods
- Configuration options
- Fluent interface

### 1. basic_query.php
**Demonstrates**: Basic query syntax, filtering with WHERE clause

A simple example showing how to query a CSV file and filter results.

**Key Concepts**:
- DEFINE statement
- SELECT with specific columns
- WHERE clause with comparison operators
- Result iteration

### 2. join_example.php
**Demonstrates**: Joining multiple CSV files

Shows how to combine data from multiple CSV files using JOIN operations.

**Key Concepts**:
- Multiple DEFINE statements
- JOIN syntax
- Qualified column names (table.column)
- Aggregating results in PHP

### 3. expressions_example.php
**Demonstrates**: Mathematical expressions in WHERE clauses

Examples of using mathematical operators to filter and calculate values.

**Key Concepts**:
- Mathematical expressions (*, +, -, /, %)
- Complex WHERE conditions
- Calculated values in filters

### 4. streaming_example.php
**Demonstrates**: Streaming mode for large files

Performance comparison between normal and streaming modes.

**Key Concepts**:
- Streaming mode (`streaming: true`)
- Memory usage comparison
- Performance benchmarking
- File size utilities

### 5. mode_selection_example.php
**Demonstrates**: Three streaming mode options

Complete guide to controlling streaming mode selection.

**Key Concepts**:
- Explicit normal mode (`streaming: false`)
- Explicit streaming mode (`streaming: true`)
- Automatic mode (`streaming: null`)
- Custom thresholds
- Mode inspection

### 6. real_world_sales_analysis.php
**Demonstrates**: Real-world business intelligence use case

A comprehensive example showing how to analyze sales data from multiple sources.

**Key Concepts**:
- Multi-table joins
- Data aggregation in PHP
- Business metrics calculation
- Report generation

### 7. aggregate_functions_example.php
**Demonstrates**: Aggregate functions (COUNT, SUM, AVG, MIN, MAX)

Shows how to use aggregate functions with and without GROUP BY.

**Key Concepts**:
- COUNT(*) and COUNT(column)
- SUM, AVG, MIN, MAX functions
- GROUP BY clause
- Aggregating entire datasets
- Grouping by categories

### 8. date_functions_example.php
**Demonstrates**: Date functions (YEAR, MONTH, DAY, DATE)

Examples of extracting date components and date-based analysis.

**Key Concepts**:
- YEAR, MONTH, DAY extraction
- DATE formatting
- GROUP BY with date functions
- Filtering by date components
- Monthly/quarterly analysis

## Common Patterns

### Basic Query Pattern

```php
use CQL\CQL;

$cql = new CQL();

$results = $cql->execute("
    DEFINE 'data.csv' AS data WITH HEADERS
    SELECT column1, column2
    FROM data
    WHERE column1 > 100
");

foreach ($results as $row) {
    // Process row
}

// Or use convenience methods
$array = $cql->query($query);   // Returns array
$first = $cql->first($query);   // Returns first row
$count = $cql->count($query);   // Returns count
```

### JOIN Pattern

```php
$query = "
    DEFINE 'table1.csv' AS t1 WITH HEADERS
    DEFINE 'table2.csv' AS t2 WITH HEADERS
    SELECT t1.col1, t2.col2
    FROM t1
    JOIN t2 ON t1.id = t2.foreign_id
";

// Note: Column names in results are namespaced (e.g., t1.col1, t2.col2)
foreach ($results as $row) {
    echo $row['t1.col1'] . ' - ' . $row['t2.col2'];
}
```

### Streaming Pattern

```php
use CQL\CQL;

// Automatic mode (recommended)
$cql = CQL::auto();
$results = $cql->execute($query);

// Explicit streaming for large files
$cql = CQL::streaming();
$results = $cql->execute($query);

// Explicit normal mode for small files
$cql = CQL::normal();
$results = $cql->execute($query);

// Custom threshold
$cql = CQL::auto(10 * 1024 * 1024); // 10MB
$results = $cql->execute($query);
```

### Aggregate Functions Pattern

```php
use CQL\CQL;

$cql = new CQL();

// Without GROUP BY (aggregate entire dataset)
$query = "
    DEFINE 'sales.csv' AS sales WITH HEADERS
    SELECT COUNT(*) AS total_orders,
           SUM(amount) AS total_revenue,
           AVG(amount) AS avg_order
    FROM sales
";

$results = $cql->execute($query);
$stats = $results->first();
echo "Total: {$stats['total_orders']}, Revenue: {$stats['total_revenue']}";

// With GROUP BY
$query = "
    DEFINE 'sales.csv' AS sales WITH HEADERS
    SELECT category,
           COUNT(*) AS count,
           SUM(amount) AS revenue
    FROM sales
    GROUP BY category
";

foreach ($cql->execute($query) as $row) {
    echo "{$row['category']}: {$row['count']} orders, \${$row['revenue']}";
}
```

### Date Functions Pattern

```php
use CQL\CQL;

$cql = new CQL();

// Extract date components
$query = "
    DEFINE 'orders.csv' AS orders WITH HEADERS
    SELECT customer,
           YEAR(order_date) AS year,
           MONTH(order_date) AS month
    FROM orders
";

// Group by month
$query = "
    DEFINE 'orders.csv' AS orders WITH HEADERS
    SELECT MONTH(order_date) AS month,
           COUNT(*) AS orders,
           SUM(amount) AS revenue
    FROM orders
    GROUP BY MONTH(order_date)
";

// Filter by date
$query = "
    DEFINE 'orders.csv' AS orders WITH HEADERS
    SELECT customer, amount
    FROM orders
    WHERE MONTH(order_date) = 2
";
```

## Tips

1. **Column Names in JOINs**: When using JOINs, column names in results are namespaced with the table alias (e.g., `customers.name`, `orders.product`)

2. **File Paths**: Use relative or absolute paths in DEFINE statements. Paths with quotes are automatically cleaned.

3. **Memory Management**: For files > 50MB, use streaming mode to reduce memory usage

4. **Error Handling**: Wrap queries in try-catch blocks to handle exceptions gracefully

5. **Performance**: Use WHERE clauses to filter data early and reduce result set size

## Creating Your Own Examples

When creating new examples:

1. Make them self-contained (create and clean up test files)
2. Include clear comments explaining what's being demonstrated
3. Show both the query and the results
4. Handle errors appropriately
5. Clean up temporary files in a finally block or at the end

## Need Help?

- Check the main [README.md](../README.md) for complete documentation
- Review the test suite in `tests/` for more usage examples
