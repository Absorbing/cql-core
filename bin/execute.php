<?php

require_once __DIR__ . '/../vendor/autoload.php';

use CQL\Lexer\Tokenizer;
use CQL\Parser\Parser;
use CQL\Engine\Interpreter;
use CQL\Engine\Operators\Registry\OperatorRegistry;

$query = "DEFINE 'users.csv' AS data WITH HEADERS COLUMNS (id, name, age, gender) SELECT id, name, age FROM data WHERE age >= 18;";
echo "Current working directory: " . getcwd() . PHP_EOL;
echo "Looking for file: users.csv" . PHP_EOL;

if (!file_exists('users.csv')) {
    echo "File not found: users.csv" . PHP_EOL;
    exit(1);
} else {
    echo "File found: users.csv" . PHP_EOL;
}

try {
    OperatorRegistry::initialize();

    $tokenizer = new Tokenizer($query);
    $tokens = $tokenizer->tokenize();

    $parser = new Parser($tokens);
    $ast = $parser->parse();

    $interpreter = new Interpreter($ast);
    $result = $interpreter->execute();

    foreach ($result->toArray() as $row) {
        echo json_encode($row, JSON_PRETTY_PRINT) . PHP_EOL;
    }

    exit(1);
} catch (\Throwable $e) {
    echo $e->getMessage() . PHP_EOL;
    exit(1);
}