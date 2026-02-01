# CQL Core

[![Build Status](https://github.com/Absorbing/cql-core/actions/workflows/phpunit.yaml/badge.svg)](https://github.com/Absorbing/cql-core/actions/workflows/phpunit.yaml)
![Version](https://img.shields.io/badge/version-0.1.0-blue)
![PHP](https://img.shields.io/badge/php-8.4%2B-purple)
![License](https://img.shields.io/badge/license-MIT-green)

**CQL (CSV Query Language)** is a SQL-like query language designed specifically for querying and manipulating CSV files in PHP. It provides a familiar SQL syntax for filtering, joining, and transforming CSV data without requiring a database.

> **Note**: This is v0.1.0 - a read-only query library. Write operations (INSERT, UPDATE, DELETE) are planned for future releases. See [CHANGELOG.md](CHANGELOG.md) for details.

## Features

- **SQL-like Syntax** - Write queries using familiar SELECT, FROM, WHERE, JOIN, and GROUP BY statements
- **Aggregate Functions** - COUNT, SUM, AVG, MIN, MAX for data analysis
- **Date Functions** - YEAR, MONTH, DAY, DATE for date-based analysis
- **Multiple Data Sources** - Define and query multiple CSV files in a single query
- **JOIN Support** - Perform INNER, LEFT, and RIGHT joins between CSV files
- **Streaming Mode** - Process large files (>50MB) with minimal memory usage
- **Expression Engine** - Support for mathematical and logical expressions in WHERE clauses
- **Column Aliasing** - Rename columns in your result set
- **Wildcard Selection** - Select all columns or prefix-specific columns with `*` syntax
- **Header Detection** - Automatically handle CSVs with or without headers
- **Simple Facade API** - Clean, intuitive interface with convenience methods
- **Type-Safe** - Built with PHP 8.4+ features including readonly classes and enums
- **Extensible** - Modular operator system for easy extension
- **Well Tested** - 50 tests with 186 assertions covering core functionality

## Requirements

- PHP 8.4 or higher
- Composer

## Installation

```bash
composer require absorbing/cql-core
```

## Quick Start

```php
<?php

require 'vendor/autoload.php';

use CQL\CQL;

// Create a CQL instance
$cql = new CQL();

// Execute a query
$results = $cql->execute("
    DEFINE 'users.csv' AS users WITH HEADERS
    SELECT name, age
    FROM users
    WHERE age > 25
");

// Iterate over results
foreach ($results as $row) {
    echo "{$row['name']} is {$row['age']} years old\n";
}

// Or use convenience methods
$array = $cql->query($query);        // Returns array
$first = $cql->first($query);        // Returns first row
$count = $cql->count($query);        // Returns count
```

### Minimum Query

The simplest valid CQL query requires:

```sql
DEFINE 'file.csv' AS alias WITH HEADERS
SELECT *
FROM alias
```

### Complete Query

A query with all optional features:

```sql
DEFINE 'users.csv' AS users WITH HEADERS
DEFINE 'orders.csv' AS orders WITH HEADERS
SELECT users.name, orders.product, orders.amount
FROM users
LEFT JOIN orders ON users.id = orders.user_id
WHERE orders.amount > 100
```

## Query Syntax

### Language Reference

CQL queries follow a SQL-like syntax with specific requirements:

**Required Components:**
- `DEFINE` - At least one data source definition
- `SELECT` - Column selection (required)
- `FROM` - Primary data source (required)

**Optional Components:**
- `WHERE` - Filter conditions
- `JOIN` - Join additional data sources
- `AS` - Aliases for tables and columns

**Query Structure:**
```sql
DEFINE 'file.csv' AS alias [WITH|WITHOUT HEADERS] [COLUMNS (col1, col2, ...)]
[DEFINE 'file2.csv' AS alias2 ...]
SELECT column1, column2, ... | *
FROM alias
[JOIN alias2 ON alias.key = alias2.key]
[WHERE condition]
```

### CQL API

The `CQL` class provides a simple interface for executing queries:

```php
use CQL\CQL;

// Basic usage
$cql = new CQL();
$results = $cql->execute($query);

// Convenience methods
$array = $cql->query($query);   // Returns array
$first = $cql->first($query);   // Returns first row
$count = $cql->count($query);   // Returns row count

// Static factory methods
$cql = CQL::auto();              // Automatic mode (default)
$cql = CQL::streaming();         // Always stream
$cql = CQL::normal();            // Never stream
$cql = CQL::auto(10 * 1024 * 1024); // Custom 10MB threshold

// Configuration
$cql = new CQL([
    'streaming' => true,
    'autoStreamingThreshold' => 20 * 1024 * 1024
]);

// Fluent configuration
$cql = (new CQL())
    ->setStreaming(true)
    ->setAutoStreamingThreshold(30 * 1024 * 1024);
```

### DEFINE Statement

The `DEFINE` statement declares a CSV data source and assigns it an alias.

**Syntax:**
```sql
DEFINE 'path/to/file.csv' AS alias [WITH|WITHOUT HEADERS] [COLUMNS (col1, col2, ...)]
```

**Parameters:**

| Parameter | Required | Description | Default |
|-----------|----------|-------------|---------|
| `'path/to/file.csv'` | Yes | Path to CSV file (single or double quotes) | - |
| `AS alias` | No | Table alias | Auto-generated from filename |
| `WITH HEADERS` | No | CSV has header row | - |
| `WITHOUT HEADERS` | No | CSV has no headers (generates column_1, column_2, etc.) | Default if neither specified |
| `COLUMNS (...)` | No | Explicitly define column names | Auto-generated or from headers |

**Examples:**

```sql
-- Minimal: CSV with headers
DEFINE 'users.csv' AS users WITH HEADERS

-- CSV without headers (auto-generates column names)
DEFINE 'data.csv' AS data WITHOUT HEADERS

-- Explicitly define column names
DEFINE 'data.csv' AS data WITHOUT HEADERS COLUMNS (id, name, email)

-- Auto-generate alias from filename (becomes 'users')
DEFINE 'users.csv' WITH HEADERS

-- Multiple data sources
DEFINE 'users.csv' AS users WITH HEADERS
DEFINE 'orders.csv' AS orders WITH HEADERS
```

**Rules:**
- At least one DEFINE is required per query
- Aliases must be unique within a query
- Aliases are case-sensitive
- File paths are relative to execution directory

### SELECT Statement

Select specific columns or use wildcards to select all columns.

**Syntax:**
```sql
SELECT column1, column2, ... | * | alias.*
SELECT column AS alias
```

**Options:**

| Option | Description | Example |
|--------|-------------|---------|
| `column` | Select specific column by name | `SELECT name` |
| `alias.column` | Select column with table qualifier | `SELECT users.name` |
| `*` | Select all columns from all tables | `SELECT *` |
| `alias.*` | Select all columns from specific table | `SELECT users.*` |
| `column AS alias` | Rename column in results | `SELECT name AS full_name` |

**Examples:**

```sql
-- Select specific columns
SELECT name, age FROM users

-- Select all columns
SELECT * FROM users

-- Select all columns from specific table (useful in JOINs)
SELECT users.*, orders.total FROM users JOIN orders ON users.id = orders.user_id

-- Column aliasing
SELECT name AS full_name, age AS years FROM users

-- Qualified column names
SELECT users.name, users.email FROM users

-- Mix of qualified and unqualified
SELECT users.name, age FROM users
```

**Rules:**
- At least one column or wildcard is required
- Column names are case-sensitive
- In JOINs, ambiguous columns should be qualified with table alias
- Wildcards can be mixed with specific columns

### FROM Statement

Specify the primary data source for the query.

**Syntax:**
```sql
FROM alias
```

**Parameters:**

| Parameter | Required | Description |
|-----------|----------|-------------|
| `alias` | Yes | Must match a DEFINE alias |

**Examples:**

```sql
FROM users
FROM data
FROM my_table
```

**Rules:**
- Exactly one FROM clause is required
- Alias must be defined in a DEFINE statement
- Alias is case-sensitive

### WHERE Clause

Filter rows based on conditions using comparison and logical operators.

**Syntax:**
```sql
WHERE condition
WHERE column operator value
WHERE expression operator expression
```

**Supported Operators:**

| Category | Operator | Description |
|----------|----------|-------------|
| **Comparison** | `=` | Equal |
| | `!=`, `<>` | Not equal |
| | `>` | Greater than |
| | `<` | Less than |
| | `>=` | Greater than or equal |
| | `<=` | Less than or equal |
| **Logical** | `AND` | Logical AND |
| | `OR` | Logical OR |
| | `NOT` | Logical NOT |
| **Mathematical** | `+` | Addition |
| | `-` | Subtraction |
| | `*` | Multiplication |
| | `/` | Division |
| | `%` | Modulus |
| | `^` | Power (exponentiation) |

**Examples:**

```sql
-- Simple comparison
WHERE age > 18

-- Logical operators
WHERE age > 18 AND status = 'active'
WHERE age < 18 OR age > 65

-- Mathematical expressions
WHERE price * quantity > 1000
WHERE (salary + bonus) > 100000

-- String comparison
WHERE name = 'John'
WHERE status != 'inactive'

-- Parentheses for precedence
WHERE (age > 18 AND age < 65) OR status = 'premium'
```

**Rules:**
- WHERE clause is optional
- Only one condition is currently supported (no complex AND/OR chains)
- Column names must exist in the data source
- String values must be in single quotes
- Numeric values don't need quotes

**Limitations:**
- Complex nested conditions not yet supported
- IN operator not yet implemented
- LIKE operator not yet implemented
- BETWEEN operator not yet implemented

### JOIN Clause

Join multiple CSV files together.

**Syntax:**
```sql
[INNER|LEFT|RIGHT] JOIN right_alias ON left_alias.key = right_alias.key
```

**Join Types:**

| Join Type | Description |
|-----------|-------------|
| `INNER JOIN` (default) | Returns rows with matches in both tables |
| `LEFT JOIN` | Returns all rows from left table, matched rows from right |
| `RIGHT JOIN` | Returns all rows from right table, matched rows from left |

**Parameters:**

| Parameter | Required | Description |
|-----------|----------|-------------|
| `right_alias` | Yes | Alias of table to join (must be defined) |
| `ON` | Yes | Join condition keyword |
| `left_alias.key` | Yes | Column from left table |
| `right_alias.key` | Yes | Column from right table |

**Examples:**

```sql
-- Inner join (default)
DEFINE 'users.csv' AS users WITH HEADERS
DEFINE 'orders.csv' AS orders WITH HEADERS
SELECT users.name, orders.product
FROM users
JOIN orders ON users.id = orders.user_id

-- Left join
SELECT users.name, orders.product
FROM users
LEFT JOIN orders ON users.id = orders.user_id

-- Multiple joins
DEFINE 'users.csv' AS users WITH HEADERS
DEFINE 'orders.csv' AS orders WITH HEADERS
DEFINE 'products.csv' AS products WITH HEADERS
SELECT users.name, orders.order_id, products.name
FROM users
JOIN orders ON users.id = orders.user_id
JOIN products ON orders.product_id = products.id
```

**Rules:**
- JOIN is optional
- Multiple JOINs are supported
- Both tables must be defined with DEFINE
- Join keys must exist in both tables
- Join condition must use `=` operator
- Column names in results are namespaced (e.g., `users.name`, `orders.product`)

**Limitations:**
- Only equality joins supported (no `>`, `<`, etc.)
- No self-joins
- FULL OUTER JOIN not yet implemented

### Aggregate Functions

Perform calculations across multiple rows.

**Syntax:**
```sql
SELECT function(column) [AS alias]
SELECT function(*)
```

**Supported Functions:**

| Function | Description | Example |
|----------|-------------|---------|
| `COUNT(*)` | Count all rows | `COUNT(*) AS total` |
| `COUNT(column)` | Count non-null values | `COUNT(name) AS users` |
| `SUM(column)` | Sum of numeric values | `SUM(amount) AS total_sales` |
| `AVG(column)` | Average of numeric values | `AVG(price) AS avg_price` |
| `MIN(column)` | Minimum value | `MIN(age) AS youngest` |
| `MAX(column)` | Maximum value | `MAX(salary) AS highest_paid` |

**Examples:**

```sql
-- Overall statistics (no GROUP BY)
DEFINE 'sales.csv' AS sales WITH HEADERS
SELECT COUNT(*) AS total_orders,
       SUM(amount) AS total_revenue,
       AVG(amount) AS avg_order_value
FROM sales

-- Group by category
SELECT category,
       COUNT(*) AS order_count,
       SUM(quantity) AS total_quantity
FROM sales
GROUP BY category

-- Multiple aggregates with GROUP BY
SELECT region,
       COUNT(*) AS orders,
       AVG(price) AS avg_price,
       MAX(quantity) AS max_quantity
FROM sales
GROUP BY region

-- Filter before aggregating
SELECT category, SUM(amount) AS revenue
FROM sales
WHERE amount > 100
GROUP BY category
```

**Rules:**
- Aggregate functions can be used with or without GROUP BY
- Without GROUP BY, aggregates apply to entire dataset
- With GROUP BY, aggregates apply to each group
- Can mix aggregates with GROUP BY columns in SELECT
- Aliases are recommended for aggregate results

**Limitations:**
- HAVING clause not yet implemented
- Cannot filter on aggregate results (use PHP post-processing)
- Cannot use aggregates in WHERE clause

### Date Functions

Extract date components and perform date-based analysis.

**Syntax:**
```sql
SELECT function(date_column) [AS alias]
WHERE function(date_column) operator value
GROUP BY function(date_column)
```

**Supported Functions:**

| Function | Description | Returns | Example |
|----------|-------------|---------|---------|
| `YEAR(date)` | Extract year | Integer (2024) | `YEAR(order_date)` |
| `MONTH(date)` | Extract month | Integer (1-12) | `MONTH(order_date)` |
| `DAY(date)` | Extract day | Integer (1-31) | `DAY(order_date)` |
| `DATE(date)` | Format as date | String (YYYY-MM-DD) | `DATE(created_at)` |

**Examples:**

```sql
-- Extract date components
DEFINE 'orders.csv' AS orders WITH HEADERS
SELECT customer,
       order_date,
       YEAR(order_date) AS year,
       MONTH(order_date) AS month,
       DAY(order_date) AS day
FROM orders

-- Group by month
SELECT MONTH(order_date) AS month,
       COUNT(*) AS order_count,
       SUM(amount) AS revenue
FROM orders
GROUP BY MONTH(order_date)

-- Filter by date component
SELECT customer, amount
FROM orders
WHERE MONTH(order_date) = 2

-- Quarterly analysis
SELECT COUNT(*) AS q1_orders,
       SUM(amount) AS q1_revenue
FROM orders
WHERE MONTH(order_date) <= 3
```

**Rules:**
- Date functions work with standard date formats (YYYY-MM-DD, etc.)
- Can be used in SELECT, WHERE, and GROUP BY clauses
- Returns NULL for invalid dates
- Date parsing uses PHP's DateTime class

**Supported Date Formats:**
- `YYYY-MM-DD` (2024-01-15)
- `YYYY/MM/DD` (2024/01/15)
- `DD-MM-YYYY` (15-01-2024)
- Any format supported by PHP's `DateTime` constructor

## Limitations

### Not Yet Supported

The following SQL features are not currently implemented:

**Aggregation:**
- `GROUP BY` - Grouping rows
- `HAVING` - Filter grouped results
- `COUNT()`, `SUM()`, `AVG()`, `MIN()`, `MAX()` - Aggregate functions
- `DISTINCT` - Remove duplicates

**Sorting & Limiting:**
- `ORDER BY` - Sort results
- `LIMIT` - Limit number of results
- `OFFSET` - Skip results

**Advanced Filtering:**
- `IN (value1, value2, ...)` - Match multiple values
- `LIKE` - Pattern matching
- `BETWEEN` - Range queries
- Complex nested WHERE conditions

**Data Modification:**
- `INSERT` - Add rows
- `UPDATE` - Modify rows
- `DELETE` - Remove rows

**Advanced Joins:**
- `FULL OUTER JOIN` - Full outer join
- Self-joins
- Non-equality joins (>, <, etc.)

**Other:**
- Subqueries
- `UNION` - Combine queries
- Window functions
- CTEs (Common Table Expressions)

### Workarounds

Many of these features can be implemented in PHP after querying:

```php
$cql = new CQL();
$results = $cql->query($query);

// Sorting
usort($results, fn($a, $b) => $a['age'] <=> $b['age']);

// Limiting
$limited = array_slice($results, 0, 10);

// Distinct
$unique = array_unique($results, SORT_REGULAR);

// Aggregation
$total = array_sum(array_column($results, 'amount'));
$average = $total / count($results);

// Grouping
$grouped = [];
foreach ($results as $row) {
    $grouped[$row['category']][] = $row;
}
```

## Complete Examples

### Example 1: Basic Filtering

```php
<?php

use CQL\CQL;

// users.csv:
// id,name,age,status
// 1,Alice,30,active
// 2,Bob,25,inactive
// 3,Charlie,35,active

$cql = new CQL();

$results = $cql->execute("
    DEFINE 'users.csv' AS users WITH HEADERS
    SELECT name, age
    FROM users
    WHERE age >= 30 AND status = 'active'
");

// Output:
// {"name":"Alice","age":"30"}
// {"name":"Charlie","age":"35"}
```

### Example 2: Joining Multiple Files

```php
<?php

use CQL\CQL;

// users.csv:
// id,name
// 1,Alice
// 2,Bob

// orders.csv:
// order_id,user_id,product,amount
// 101,1,Laptop,1200
// 102,1,Mouse,25
// 103,2,Keyboard,75

$cql = new CQL();

$results = $cql->execute("
    DEFINE 'users.csv' AS users WITH HEADERS
    DEFINE 'orders.csv' AS orders WITH HEADERS
    SELECT users.name, orders.product, orders.amount
    FROM users
    JOIN orders ON users.id = orders.user_id
    WHERE orders.amount > 50
");

// Output:
// {"users.name":"Alice","orders.product":"Laptop","orders.amount":"1200"}
// {"users.name":"Bob","orders.product":"Keyboard","orders.amount":"75"}
```

### Example 3: Column Aliasing

```php
<?php

use CQL\CQL;

$cql = new CQL();

$results = $cql->execute("
    DEFINE 'employees.csv' AS emp WITH HEADERS
    SELECT emp.first_name AS name, emp.salary AS pay
    FROM emp
    WHERE emp.department = 'Engineering'
");

// Output uses aliased column names:
// {"name":"John","pay":"95000"}
// {"name":"Jane","pay":"105000"}
```

### Example 4: Mathematical Expressions

```php
<?php

use CQL\CQL;

// products.csv:
// name,price,quantity
// Widget,10.50,100
// Gadget,25.00,50
// Doohickey,5.75,200

$cql = new CQL();

$results = $cql->execute("
    DEFINE 'products.csv' AS products WITH HEADERS
    SELECT name, price, quantity
    FROM products
    WHERE price * quantity > 1000
");

// Output:
// {"name":"Gadget","price":"25.00","quantity":"50"}
// {"name":"Doohickey","price":"5.75","quantity":"200"}
```

### Example 5: Streaming Mode for Large Files

```php
<?php

use CQL\CQL;

// For large CSV files (>50MB), use streaming mode to reduce memory usage

// Explicit streaming
$cql = CQL::streaming();
$results = $cql->execute("
    DEFINE 'large_dataset.csv' AS data WITH HEADERS
    SELECT id, name, value
    FROM data
    WHERE value > 1000
");

// Automatic mode (recommended) - decides based on file size
$cql = CQL::auto();
$results = $cql->execute($query);

// Custom threshold
$cql = CQL::auto(10 * 1024 * 1024); // 10MB threshold
$results = $cql->execute($query);
```

## Architecture

CQL Core is built with a clean, modular architecture consisting of three main components:

### 1. Lexer (Tokenization)
Breaks down query strings into tokens (keywords, identifiers, operators, etc.)

### 2. Parser (Syntax Analysis)
Converts tokens into an Abstract Syntax Tree (AST) representing the query structure

### 3. Interpreter (Execution)
Executes the parsed query against CSV data sources and returns results

### 4. Data Layer
- **CSVDataSource** - Handles CSV file loading with streaming support
- **Collection** - Functional data manipulation with filter, map, and iteration

All of these components are accessed through the simple `CQL` facade class, which handles the entire pipeline automatically.

## Exception Handling

CQL Core provides specific exceptions for different error scenarios:

| Exception | Purpose |
|-----------|---------|
| `DataSourceException` | CSV file errors (not found, permissions, format issues) |
| `InterpreterException` | Execution errors (undefined aliases, ambiguous columns) |
| `LexerException` | Tokenization errors |
| `ParserException` | Parsing errors (unexpected tokens, malformed syntax) |
| `SyntaxException` | Query syntax errors (invalid keywords, structure) |

```php
use CQL\CQL;
use CQL\Exceptions\{
    DataSourceException,
    InterpreterException,
    SyntaxException
};

try {
    $cql = new CQL();
    $results = $cql->execute($query);
} catch (DataSourceException $e) {
    // Handle file not found, permission errors, etc.
    echo "Data source error: " . $e->getMessage();
} catch (SyntaxException $e) {
    // Handle invalid query syntax
    echo "Syntax error: " . $e->getMessage();
} catch (InterpreterException $e) {
    // Handle execution errors
    echo "Execution error: " . $e->getMessage();
}
```

## Advanced Usage

### Custom Operators

Extend the operator system by implementing operator interfaces:

```php
use CQL\Engine\Operators\BaseComparisonOperator;
use CQL\Engine\Operators\Contracts\ComparisonOperatorInterface;

class LikeOperator extends BaseComparisonOperator implements ComparisonOperatorInterface
{
    public static function symbols(): array
    {
        return ['LIKE'];
    }

    public static function evaluate(mixed $left, mixed $right): bool
    {
        $pattern = str_replace('%', '.*', preg_quote($right, '/'));
        return (bool) preg_match("/^{$pattern}$/i", $left);
    }
}

// Register the operator
use CQL\Engine\Operators\Registry\OperatorRegistry;
OperatorRegistry::register(LikeOperator::class);
```

### Working with Results

Results are returned as a `Collection` object with helpful methods:

```php
$cql = new CQL();
$results = $cql->execute($query);

// Get result count
$count = $results->count();

// Convert to array
$array = $results->toArray();

// Get first result
$first = $results->first();

// Chain operations
$filtered = $results
    ->filter(fn($row) => $row['age'] > 30)
    ->map(fn($row) => ['name' => strtoupper($row['name'])]);

// Iterate
foreach ($results as $row) {
    // Process each row
}

// Or use convenience methods directly
$array = $cql->query($query);   // Returns array
$first = $cql->first($query);   // Returns first row
$count = $cql->count($query);   // Returns count
```

## Performance Considerations

### Memory Management

CQL offers three modes for handling CSV files:

**1. Normal Mode** - Explicitly loads entire CSV into memory
- **Best for**: Small to medium files (<50MB)
- **Usage**: `CQL::normal()`
- **Pros**: Faster query execution, supports complex operations
- **Cons**: High memory usage for large files

**2. Streaming Mode** - Explicitly processes rows one at a time
- **Best for**: Large files (>50MB)
- **Usage**: `CQL::streaming()`
- **Pros**: Minimal memory footprint, handles files of any size
- **Cons**: Slightly slower for small files, still loads results into memory

**3. Automatic Mode** - Automatically selects based on file size
- **Best for**: Unknown file sizes, dynamic workloads (recommended)
- **Usage**: `new CQL()` or `CQL::auto()`
- **Usage**: `new Interpreter($ast, streaming: null)` or `new Interpreter($ast)`
- **Default threshold**: 50 MB (configurable)
- **Pros**: No manual decision needed, optimal for most cases
- **Cons**: Requires file size check

**Mode Selection Examples:**

```php
use CQL\CQL;

// Explicit normal mode (always load into memory)
$cql = CQL::normal();

// Explicit streaming mode (always stream)
$cql = CQL::streaming();

// Automatic mode with default 50MB threshold (recommended)
$cql = new CQL();
// or
$cql = CQL::auto();

// Automatic mode with custom 10MB threshold
$cql = CQL::auto(10 * 1024 * 1024);

// Configuration via constructor
$cql = new CQL([
    'streaming' => true,
    'autoStreamingThreshold' => 20 * 1024 * 1024
]);
```

**Performance Comparison:**

| File Size | Mode | Memory Usage | Speed |
|-----------|------|--------------|-------|
| 1000 rows (~22KB) | Normal | ~500 KB | Fast |
| 1000 rows (~22KB) | Streaming | ~0 KB | Very Fast |
| 100,000 rows (~2MB) | Normal | ~50 MB | Fast |
| 100,000 rows (~2MB) | Streaming | ~5 MB | Fast |
| 1,000,000 rows (~20MB) | Normal | ~500 MB | Slow |
| 1,000,000 rows (~20MB) | Streaming | ~50 MB | Medium |

### Other Performance Tips

- **Joins**: Join operations create in-memory indexes. Multiple joins on large datasets may consume significant memory even in streaming mode.
- **Filtering**: WHERE clauses are applied during data loading in streaming mode, reducing memory usage.
- **File Format**: Ensure CSV files are well-formed to avoid parsing errors.
- **Caching**: Consider caching parsed ASTs for frequently-executed queries.

## Limitations

- **No Aggregations**: Currently no support for GROUP BY, COUNT, SUM, AVG, etc.
- **No Sorting**: ORDER BY is not yet implemented
- **No Limits**: LIMIT and OFFSET are not yet implemented
- **Single WHERE Condition**: Complex WHERE clauses with multiple conditions are limited
- **No Subqueries**: Nested queries are not supported
- **No INSERT/UPDATE/DELETE**: Read-only operations only

## Roadmap

Future features under consideration:

- [ ] Aggregation functions (COUNT, SUM, AVG, MIN, MAX)
- [ ] GROUP BY and HAVING clauses
- [ ] ORDER BY with ASC/DESC
- [ ] LIMIT and OFFSET
- [ ] Complex WHERE conditions with nested logic
- [ ] Subqueries
- [ ] UNION operations
- [ ] DISTINCT keyword
- [ ] Additional operators (LIKE, BETWEEN, IN with arrays)
- [ ] Write operations (INSERT, UPDATE, DELETE)
- [ ] JSON and other data format support

## Testing

Run the test suite:

```bash
composer test
```

Run static analysis:

```bash
vendor/bin/phpstan analyze
```

Run code style checks:

```bash
vendor/bin/phpcs --standard=.phpcs.xml src
```

## Contributing

Contributions are welcome! Please:

1. Fork the repository
2. Create a feature branch
3. Write tests for new functionality
4. Ensure all tests pass
5. Submit a pull request

## License

MIT License - see LICENSE file for details

## Credits

Developed by the Absorbing team.

## Support

- **Issues**: [GitHub Issues](https://github.com/Absorbing/cql-core/issues)
- **Discussions**: [GitHub Discussions](https://github.com/Absorbing/cql-core/discussions)
