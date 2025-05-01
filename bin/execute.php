<?php

require_once __DIR__ . '/../vendor/autoload.php';

use CQL\Lexer\Tokenizer;
use CQL\Parser\Parser;
use CQL\Engine\Interpreter;
use CQL\Engine\Operators\Registry\OperatorRegistry;

if ($argc < 2) {
    echo "Usage: php bin/execute.php \"DEFINE 'users.csv' AS data WITH HEADERS COLUMNS (id, name, age, gender) SELECT id, name, age FROM data WHERE age >= 18;\"";
}

$query = implode(' ', array_slice($argv, 1));

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
} catch (\Throwable $e) {
    echo $e->getMessage() . PHP_EOL;
    exit(1);
}