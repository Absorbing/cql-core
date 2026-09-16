# Changelog

All notable changes to CQL Core will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.2.0] - 2026-07-23

### Added
- **Full WHERE condition grammar**: WHERE clauses are now condition trees
  with standard SQL precedence (OR < AND < NOT < predicates)
  - **AND / OR**: combine any number of conditions, with parentheses for
    explicit grouping: `WHERE (a = 1 OR b = 2) AND c > 3`
  - **NOT**: negate any condition or group: `WHERE NOT (x = 1 OR y = 2)`
  - **IN / NOT IN**: value list membership with loose comparison:
    `WHERE age IN (25, 30)`, `WHERE city NOT IN ('Bristol', 'Bath')`
  - **EXISTS**: presence test - true when a column's value is neither
    null (missing) nor an empty string: `WHERE EXISTS email`,
    `WHERE NOT EXISTS email` ('0' counts as existing)
  - **Bare expressions**: `WHERE active` evaluates truthiness
  - Applies identically to SELECT, UPDATE, and DELETE via the shared
    evaluation trait
  - Parenthesised expressions still work as predicates: `(a + b) > 5`
- **Strict statement termination**: trailing tokens after a complete
  statement now raise a SyntaxException. Previously
  `WHERE a = 1 AND b = 2` parsed the first condition and silently
  discarded the rest
- **New AST node**: `UnaryConditionNode` (NOT / EXISTS); `WhereNode` now
  holds a condition tree root instead of a single `ConditionNode`
- **Write Operations (DML)**: CQL is no longer read-only
  - **INSERT Statement**: `INSERT INTO alias [(columns)] VALUES (...), (...)`
    - Explicit column lists (missing columns written as empty strings)
    - Positional inserts matching the file's header order
    - Multiple VALUES tuples in a single statement
    - Inserting into an empty file writes the header line automatically
  - **UPDATE Statement**: `UPDATE alias SET col = expr [, ...] [WHERE condition]`
    - Full expression support in assignments (e.g. `SET age = age + 1`)
    - Multiple assignments per statement
  - **DELETE Statement**: `DELETE FROM alias [WHERE condition]`
    - Omitting WHERE removes all rows (header line is preserved)
- **Atomic Writes**: UPDATE and DELETE rewrite files via a temporary file
  and rename, so a failure part-way through never corrupts the source
- **Streaming Writes**: UPDATE and DELETE stream row-by-row on large files,
  honouring the same automatic/explicit streaming modes as queries
- **`CQL::statement()`**: Execute a write statement and return the affected
  row count (`execute()` also accepts writes, returning
  `[['affected_rows' => n]]`)
- **`WritableDataSourceInterface`**: New data source contract with
  `appendRows()`, `rewriteFrom()` (generator-friendly), and `getHeaders()`,
  implemented by `CSVDataSource` with `flock`-guarded appends
- **New AST Nodes**: `InsertNode`, `UpdateNode`, `DeleteNode`,
  `AssignmentNode`, and a shared `StatementNodeInterface` contract
  (implemented by `QueryNode`); `Parser::parse()` now returns
  `StatementNodeInterface`
- **`Engine\Writer`**: Write executor mirroring the Interpreter's streaming
  behaviour; shared expression logic extracted into the
  `Engine\Concerns\EvaluatesExpressions` trait used by both engines
- **Boolean literals** accepted in expressions (`VALUES (TRUE)`)
- 37 new tests (87 total, 260 assertions)

### Fixed
- **String literals in WHERE clauses matched nothing**: quoted strings kept
  their quotes from the tokenizer and were treated as unresolvable column
  references, so `WHERE name = 'Alice'` always returned 0 rows. Quoted
  operands now resolve as string literals
- **Tokenizer mangled words starting with "IN"**: the `IN` comparison
  operator pattern had no word boundaries, so `INSERT` tokenized as
  `IN` + `SERT` and identifiers like `index` as `IN` + `dex`. Word-based
  operators are now anchored with `\b`
- **`Parser::isOperator()` always returned true**: it compared
  `tryResolve()`'s `|false` return against `null`
- **`InOperator::evaluate()` and `ExistsOperator::evaluate()` were
  stubs**: they returned constant `false` / `true` respectively. Both are
  now implemented and registered (along with a new `NotInOperator`)
- **`bin/ast.php` ignored its argument**: it always parsed its hardcoded
  example query
- **`OrOperator` registered under the wrong symbol**: its `symbols()`
  returned `['NOT']` (copy-paste error), so `OR` was never resolvable and
  the registration overwrote `NotOperator` - a WHERE clause using `NOT` as
  a binary operator silently evaluated `left || right` instead. `OR` now
  resolves correctly, and a registry test guards against future symbol
  collisions

### Changed
- **Operator contract hierarchy redesigned** (BREAKING for anyone extending
  the operator system):
  - `ResolvableOperatorInterface` is now the true base contract, declaring
    `symbols()` and `precedence()` (precedence applies to every operator
    regardless of arity)
  - New `BinaryOperatorInterface` declares `evaluate($left, $right)` and is
    extended by `ComparisonOperatorInterface`, `LogicalOperatorInterface`,
    `MathOperatorInterface`, and `ExpressionOperatorInterface`
  - `UnaryOperatorInterface` (NOT, EXISTS) extends the base directly with
    its own `evaluate($value)` and `operandPosition()`
  - `OperatorRegistry` gains `resolveBinary()` and `resolveUnary()` which
    throw a clear `InvalidArgumentException` on arity mismatch; the engine
    now uses `resolveBinary()` wherever it evaluates left/right operands
- `CSVDataSource` now records headers during in-memory loads (previously
  only streaming loads populated them), exposed via `getHeaders()`
- `PrettyPrinter::print()` accepts any `StatementNodeInterface` (falls back
  to a structural dump for write statements)

### Removed
- `OperatorInterface` - replaced by `BinaryOperatorInterface`. No concrete
  operator implemented it directly (all go through the specific
  sub-interfaces), so only code type-hinting `OperatorInterface` itself is
  affected

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
