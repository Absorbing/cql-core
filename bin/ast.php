<?php

require_once __DIR__ . '/../vendor/autoload.php';

use CQL\Lexer\Tokenizer;
use CQL\Parser\Parser;
use CQL\Support\Debug\PrettyPrinter;

/// Outputs the AST of a CQL query

$query = "DEFINE 'users.csv' AS data WITH HEADERS COLUMNS (id, name, age) SELECT id, name FROM data WHERE age >= 18;";

if ($argc < 2) {
    echo "Usage: php bin/ast.php \"$query\"\n";
    exit(1);
}

$tokenizer = new Tokenizer($query);
$tokens = $tokenizer->tokenize();
$parser = new Parser($tokens);
$ast = $parser->parse();

echo PrettyPrinter::print($ast);