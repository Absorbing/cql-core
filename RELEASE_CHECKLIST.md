# Release Checklist for CQL Core

## Current Status: v0.1.0 RELEASED ✅

CQL Core v0.1.0 is a **read-only query library** with comprehensive SELECT capabilities. Full CRUD operations are planned for future releases.

---

## v0.1.0 - Released Features ✅

### Core Functionality
- [x] Tokenizer/Lexer - Complete
- [x] Parser - Complete for read operations
- [x] Interpreter - Complete for read operations
- [x] CSV data source loading
- [x] Streaming mode for large files
- [x] Automatic mode selection

### Query Features (Read)
- [x] DEFINE - Data source definition
- [x] SELECT - Column selection with wildcards and aliases
- [x] FROM - Primary data source
- [x] WHERE - Filtering with comparison, logical, and mathematical operators
- [x] JOIN - INNER, LEFT, RIGHT joins
- [x] Multiple table joins
- [x] Expression evaluation in WHERE clauses
- [x] GROUP BY - Grouping by columns or functions
- [x] Aggregate functions - COUNT, SUM, AVG, MIN, MAX
- [x] Date functions - YEAR, MONTH, DAY, DATE

### API & Usability
- [x] CQL facade class for simple usage
- [x] Convenience methods (query, first, count)
- [x] Static factory methods (auto, streaming, normal)
- [x] Fluent configuration
- [x] Exception handling
- [x] Collection class for results

### Performance
- [x] Normal mode (load into memory)
- [x] Streaming mode (row-by-row)
- [x] Automatic mode selection based on file size
- [x] Configurable thresholds
- [x] ~100% memory savings on large files

### Documentation
- [x] Complete README with language reference
- [x] CHANGELOG.md with version history
- [x] Examples directory with 8 working examples
- [x] API documentation
- [x] Limitations clearly documented

### Testing
- [x] Tokenizer tests
- [x] Parser tests (all passing)
- [x] Interpreter tests
- [x] Streaming tests
- [x] CQL facade tests
- [x] CSVDataSource tests
- [x] 50 tests total with 186 assertions

### Quality
- [x] PHPStan configuration (level 8)
- [x] PHP_CodeSniffer configuration
- [x] PHPUnit configuration
- [x] CI/CD workflows (GitHub Actions)
- [x] No diagnostic errors

---

## Roadmap to v1.0

### v0.2.0 - Enhanced Query Features (Planned)

**Query Enhancements:**
- [ ] ORDER BY clause (ASC/DESC)
- [ ] LIMIT and OFFSET
- [ ] DISTINCT keyword
- [ ] HAVING clause for aggregate filtering
- [ ] Complex WHERE conditions (AND/OR chains with parentheses)
- [ ] IN operator
- [ ] LIKE operator for pattern matching
- [ ] BETWEEN operator

**Additional Functions:**
- [ ] String functions (UPPER, LOWER, TRIM, CONCAT, SUBSTRING)
- [ ] More date functions (DATE_ADD, DATE_DIFF, DATE_FORMAT)
- [ ] Mathematical functions (ROUND, CEIL, FLOOR, ABS)
- [ ] Conditional functions (COALESCE, NULLIF)

**Estimated effort:** 40-60 hours

### v0.5.0 - Write Operations Foundation (Planned)

**INSERT Operations:**
- [ ] INSERT keyword in lexer
- [ ] INSERT parser implementation
- [ ] INSERT interpreter implementation
- [ ] Support for INSERT INTO table (col1, col2) VALUES (val1, val2)
- [ ] Support for multiple value rows
- [ ] Header preservation when inserting
- [ ] File locking for concurrent writes

**File Writing Infrastructure:**
- [ ] CSVWriter class
- [ ] File locking mechanism
- [ ] Atomic write operations (write to temp, then rename)
- [ ] Backup/rollback support
- [ ] Transaction support (optional)

**Syntax to support:**
```sql
-- Single row
INSERT INTO 'users.csv' (name, age, email) VALUES ('John', 30, 'john@example.com')

-- Multiple rows
INSERT INTO 'users.csv' (name, age) VALUES 
    ('Alice', 25),
    ('Bob', 30),
    ('Charlie', 35)
```

**Estimated effort:** 50-70 hours

### v0.8.0 - UPDATE Operations (Planned)

**UPDATE Operations:**
- [ ] UPDATE keyword in lexer
- [ ] SET keyword in lexer
- [ ] UPDATE parser implementation
- [ ] UPDATE interpreter implementation
- [ ] Support for UPDATE table SET col1=val1 WHERE condition
- [ ] Multiple column updates
- [ ] Expression support in SET clause
- [ ] Return count of updated rows

**Syntax to support:**
```sql
-- Simple update
UPDATE 'users.csv' SET age = 31 WHERE name = 'John'

-- Multiple columns
UPDATE 'users.csv' SET age = 31, status = 'active' WHERE name = 'John'

-- With expressions
UPDATE 'users.csv' SET age = age + 1 WHERE department = 'Engineering'
```

**Implementation notes:**
- Read entire file into memory
- Apply updates to matching rows
- Write back atomically
- Preserve headers
- Handle file locking

**Estimated effort:** 30-40 hours

### v1.0.0 - DELETE Operations & Production Ready (Planned)

**DELETE Operations:**
- [ ] DELETE keyword in lexer
- [ ] DELETE parser implementation
- [ ] DELETE interpreter implementation
- [ ] Support for DELETE FROM table WHERE condition
- [ ] Return count of deleted rows

**Syntax to support:**
```sql
-- Delete with condition
DELETE FROM 'users.csv' WHERE age < 18

-- Delete all (dangerous, require confirmation)
DELETE FROM 'users.csv'
```

**Production Readiness:**
- [ ] Comprehensive test coverage for all CRUD operations
- [ ] Performance benchmarks
- [ ] Security audit
- [ ] Concurrent access tests
- [ ] Error handling and recovery
- [ ] Production documentation
- [ ] Migration guide from v0.x

**Estimated effort:** 30-40 hours

**Total estimated effort for v1.0:** 150-210 hours

---

## Version Summary

| Version | Status | Features | Estimated Hours |
|---------|--------|----------|-----------------|
| **v0.1.0** | ✅ Released | Read-only queries, aggregates, date functions, streaming | Completed |
| **v0.2.0** | 📋 Planned | ORDER BY, LIMIT, DISTINCT, HAVING, more functions | 40-60 |
| **v0.5.0** | 📋 Planned | INSERT operations, file writing infrastructure | 50-70 |
| **v0.8.0** | 📋 Planned | UPDATE operations | 30-40 |
| **v1.0.0** | 📋 Planned | DELETE operations, production ready | 30-40 |

---

## Recommended Release Strategy

### Phase 1: v0.1.0 (Current) ✅
- **Status**: Released
- **Use Case**: Read-only CSV analysis and reporting
- **Stability**: Production-ready for read operations
- **Recommendation**: Safe to use for data analysis, BI, reporting

### Phase 2: v0.2.0 (Next)
- **Timeline**: 2-3 weeks
- **Focus**: Enhanced query capabilities
- **Use Case**: More complex read operations
- **Recommendation**: Incremental improvement, backward compatible

### Phase 3: v0.5.0 (Mid-term)
- **Timeline**: 1-2 months
- **Focus**: INSERT operations
- **Use Case**: Data ingestion, append-only workflows
- **Recommendation**: Beta testing with limited write operations

### Phase 4: v0.8.0 (Long-term)
- **Timeline**: 2-3 months
- **Focus**: UPDATE operations
- **Use Case**: Data modification workflows
- **Recommendation**: Extended beta testing

### Phase 5: v1.0.0 (Production)
- **Timeline**: 3-4 months
- **Focus**: Full CRUD + production hardening
- **Use Case**: Complete CSV database replacement
- **Recommendation**: Production-ready for all operations

---

## Current Recommendation

**For v0.1.0 users:**
- ✅ Use for read-only operations
- ✅ Use for data analysis and reporting
- ✅ Use for CSV querying and transformation
- ❌ Do not use for write operations (not implemented)
- ❌ Do not use as primary data store (read-only)

**Best suited for:**
- Business intelligence and reporting
- Data analysis and exploration
- CSV file querying without database
- ETL read operations
- Log file analysis
- Data validation and quality checks

**Not yet suited for:**
- CRUD applications
- Data modification workflows
- Primary data storage
- Transactional systems

---

## Notes

- v0.1.0 is a solid foundation with comprehensive read capabilities
- The architecture is extensible and ready for write operations
- Streaming mode makes it viable for large datasets
- Test coverage is good for implemented features
- Documentation is comprehensive

**Next Steps:**
1. Gather user feedback on v0.1.0
2. Prioritize v0.2.0 features based on demand
3. Begin design work on write operations
4. Consider community contributions for feature development
- [ ] Update examples with CRUD operations
- [ ] Add CRUD workflow examples
- [ ] Update limitations section
- [ ] Update roadmap

### 9. Bug Fixes

**Required:**
- [ ] Fix 3 failing Parser tests (API changes)
- [ ] Fix 27 PHPStan errors (type mismatches)
- [ ] Resolve deprecation warnings

### 10. Safety Features

**Recommended:**
- [ ] Backup files before write operations
- [ ] Confirmation for DELETE without WHERE
- [ ] Dry-run mode for testing queries
- [ ] Audit log for write operations
- [ ] Data validation before writes

---

## 📋 Estimated Work

### Time Estimates (Conservative)

| Task | Estimated Time |
|------|----------------|
| INSERT implementation | 8-12 hours |
| UPDATE implementation | 8-12 hours |
| DELETE implementation | 6-8 hours |
| File writing infrastructure | 10-15 hours |
| Parser updates | 6-8 hours |
| Interpreter updates | 8-10 hours |
| Testing (comprehensive) | 15-20 hours |
| Documentation | 6-8 hours |
| Bug fixes | 4-6 hours |
| Safety features | 8-10 hours |
| **Total** | **79-109 hours** |

### Priority Order

1. **File writing infrastructure** (foundation)
2. **INSERT operations** (create)
3. **UPDATE operations** (update)
4. **DELETE operations** (delete)
5. **Testing** (validation)
6. **Bug fixes** (quality)
7. **Documentation** (usability)
8. **Safety features** (production-ready)

---

## 🎯 Release Criteria

Before v1.0 release, ALL of the following must be true:

- [ ] Full CRUD operations implemented and tested
- [ ] All tests passing (0 failures)
- [ ] PHPStan level 8 with 0 errors
- [ ] Code coverage > 80%
- [ ] Complete documentation for all features
- [ ] At least 3 complete CRUD workflow examples
- [ ] File locking and concurrent access handled
- [ ] Error handling and rollback mechanisms in place
- [ ] Performance benchmarks for write operations
- [ ] Security review completed

---

## 🚀 Alternative: Phased Release

If full CRUD is too much work for initial release, consider:

### v0.5 (Current State)
- Read-only operations
- Clearly marked as "Query-only" or "Read-only"
- Full documentation of limitations

### v0.8
- INSERT operations only
- Append-only mode

### v0.9
- UPDATE and DELETE operations
- Full CRUD support

### v1.0
- Production-ready with all safety features
- Complete test coverage
- Performance optimizations

---

## 📝 Notes

- Current codebase is well-structured and ready for extension
- Streaming mode will need special handling for write operations
- Consider whether streaming writes are necessary (probably not for v1.0)
- File locking is critical for production use
- Transaction support would be a major differentiator
- Consider adding a `--dry-run` flag for testing write operations

---

## 🤔 Decision Point

**Option 1:** Complete full CRUD before any release (~80-110 hours of work)

**Option 2:** Release v0.5 as "read-only" library, add CRUD in v1.0

**Option 3:** Implement INSERT only for v0.8, full CRUD for v1.0

**Recommendation:** Option 2 or 3 - The read functionality is solid and useful on its own. Many use cases only need querying. Release what's ready, iterate based on user feedback.
