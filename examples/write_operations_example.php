<?php

require __DIR__ . '/../vendor/autoload.php';

use CQL\CQL;

/**
 * Write Operations Example
 *
 * Demonstrates INSERT, UPDATE, and DELETE statements added in v0.2.0.
 */

// Set up a scratch CSV so the example is repeatable
$file = __DIR__ . '/write_example.csv';
file_put_contents($file, "id,name,age\n1,Alice,30\n2,Bob,25\n3,Carol,17\n");

$cql = new CQL();
$define = "DEFINE '{$file}' AS users WITH HEADERS ";

echo "=== Initial data ===\n";
print_r($cql->query($define . "SELECT * FROM users"));

// INSERT with an explicit column list (multiple tuples supported)
$affected = $cql->statement(
    $define . "INSERT INTO users (id, name, age) VALUES (4, 'Dave', 41), (5, 'Eve', 29)"
);
echo "\nInserted {$affected} row(s)\n";

// INSERT positionally - values must match the file's column order
$affected = $cql->statement($define . "INSERT INTO users VALUES (6, 'Frank', 52)");
echo "Inserted {$affected} row(s) positionally\n";

// UPDATE with an expression - assignments run through the expression engine
$affected = $cql->statement(
    $define . "UPDATE users SET age = age + 1 WHERE name = 'Alice'"
);
echo "Updated {$affected} row(s) (Alice had a birthday)\n";

// UPDATE with multiple assignments
$affected = $cql->statement(
    $define . "UPDATE users SET name = 'Robert', age = 26 WHERE id = 2"
);
echo "Updated {$affected} row(s) (Bob prefers Robert)\n";

// DELETE with a WHERE clause
$affected = $cql->statement($define . "DELETE FROM users WHERE age < 18");
echo "Deleted {$affected} row(s) (minors removed)\n";

// execute() also accepts write statements and returns a Collection
$result = $cql->query($define . "INSERT INTO users (id, name, age) VALUES (7, 'Heidi', 33)");
echo "\nexecute() on a write returns: " . json_encode($result) . "\n";

echo "\n=== Final data ===\n";
print_r($cql->query($define . "SELECT * FROM users"));

// Clean up
unlink($file);
