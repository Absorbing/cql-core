# Changelog

All notable changes to CQL Core will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.0] - 2025-02-01

### Added
- **Core Query Engine**: SQL-like query language for CSV files
- **DEFINE Statement**: Define CSV data sources with header detection
- **SELECT Statement**: Column selection with wildcards and aliasing
- **FROM Statement**: Specify primary data source
- **WHERE Clause**: Filter rows with comparison and logical operators
- **JOIN Support**: INNER, LEFT, and RIGHT joins between CSV files
- **Mathematical Expressions**: Support for +, -, *, /, %, ^ operators in WHERE
- **Aggregate Functions**: COUNT, SUM, AVG, MIN, MAX
- **Date Functions**: YEAR, MONTH, DAY, DATE for date extraction and formatting
- **GROUP BY Clause**: Group rows by columns or date functions
- **Streaming Mode**: Process large files (>50MB) with minimal memory usage
  - Automatic mode selection based on file size
  - Explicit streaming control
  - Configurable thresholds
- **CQL Facade**: Simple, user-friendly API
  - `execute()` - Execute query and return Collection
  - `query()` - Execute and return array
  - `first()` - Get first result
  - `count()` - Get result count
  - Static factories: `CQL::auto()`, `CQL::streaming()`, `CQL::normal()`
- **Column Aliasing**: Rename columns with AS keyword
- **Wildcard Selection**: Select all columns with * or prefix.*
- **Comprehensive Examples**: 8 working examples covering all features
- **Full Test Suite**: 50 tests with 186 assertions
- **Complete Documentation**: README with language reference and examples

### Features by Category

#### Query Language
- SQL-like syntax familiar to developers
- Support for multiple data sources in single query
- Expression evaluation in WHERE clauses
- Function calls in SELECT, WHERE, and GROUP BY

#### Data Processing
- CSV header detection (WITH/WITHOUT HEADERS)
- Custom column naming
- Automatic alias generation from filenames
- Namespaced column names in JOIN results

#### Performance
- Streaming mode for large files
- Automatic mode selection (50MB default threshold)
- Memory-efficient row-by-row processing
- ~100% memory savings on large files

#### Developer Experience
- Type-safe implementation with PHP 8.4+ features
- Readonly classes and enums
- Comprehensive error messages
- Modular operator system
- Extensible architecture

### Examples Included
1. `simple_facade.php` - CQL Facade API
2. `basic_query.php` - Basic queries and filtering
3. `join_example.php` - Multi-table joins
4. `expressions_example.php` - Mathematical expressions
5. `streaming_example.php` - Streaming mode performance
6. `mode_selection_example.php` - Streaming mode control
7. `real_world_sales_analysis.php` - Business intelligence workflow
8. `aggregate_functions_example.php` - Aggregate functions
9. `date_functions_example.php` - Date functions

### Known Limitations
- No CRUD operations (INSERT, UPDATE, DELETE) - read-only
- Single WHERE condition (no complex AND/OR chains)
- No HAVING clause for filtering aggregates
- No ORDER BY, LIMIT, OFFSET
- No DISTINCT
- No subqueries
- No UNION/INTERSECT/EXCEPT
- No window functions
- No CASE statements

### Technical Details
- **PHP Version**: 8.4+
- **Dependencies**: None (dev dependencies only)
- **License**: MIT
- **Test Coverage**: Core functionality fully tested

### Documentation
- Complete README with language reference
- RELEASE_CHECKLIST.md for future development roadmap
- Examples README with patterns and tips

## [Unreleased]

### Planned for v0.2.0
- ORDER BY clause
- LIMIT and OFFSET
- DISTINCT keyword
- HAVING clause for aggregate filtering
- Complex WHERE conditions (AND/OR chains)
- Additional string functions (UPPER, LOWER, TRIM, etc.)
- Additional date functions (DATE_ADD, DATE_DIFF, etc.)

### Planned for v0.5.0
- INSERT statement
- File writing infrastructure
- Transaction support

### Planned for v1.0.0
- UPDATE statement
- DELETE statement
- Full CRUD operations
- Production-ready for write operations

---

[0.1.0]: https://github.com/Absorbing/cql-core/releases/tag/v0.1.0
