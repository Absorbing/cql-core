<?php

require_once __DIR__ . '/../vendor/autoload.php';

use CQL\Lexer\Tokenizer;

if ($argc < 2) {
    echo "Usage: php bin/tokenize.php \"SELECT * FROM users WHERE age >= 18;\"\n";
    exit(1);
}

$query = implode(' ', array_slice($argv, 1));

$tokenizer = new Tokenizer($query);
$tokens = $tokenizer->tokenize();

foreach ($tokens as $token) {
    echo sprintf(
        "[%s] '%s' at position %d\n",
        $token->type,
        $token->value,
        $token->position
    );
}