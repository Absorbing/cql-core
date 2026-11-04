<?php

namespace CQL;

use CQL\Data\Support\Collection;
use CQL\Exceptions\ParameterException;
use CQL\Parser\Nodes\Contracts\StatementNodeInterface;
use CQL\Parser\Nodes\ParameterNode;
use CQL\Parser\Parser;
use CQL\Lexer\Tokenizer;

/** A parsed query reusable with a fresh set of values on every execution. */
final class PreparedQuery
{
    /**
     * Compile without opening sources or executing a statement.
     * @param CQL $connection Owning connection.
     * @param string $query Statement template.
     * @return self
     */
    public static function fromQuery(CQL $connection, string $query): self
    {
        $parser = new Parser((new Tokenizer($query))->tokenize(), allowMissingDefines: true);
        return new self($connection, $parser->parse(), $parser->getParameters());
    }

    /**
     * @param CQL $connection Owning execution context.
     * @param StatementNodeInterface $statement Parsed statement.
     * @param array<string|int, ParameterNode> $parameters Expected parameters.
     */
    public function __construct(
        private readonly CQL $connection,
        private readonly StatementNodeInterface $statement,
        private readonly array $parameters,
    ) {
    }

    /**
     * @param array<string|int, mixed> $values Bindings for this execution.
     * @return Collection<array-key, mixed>
     */
    public function execute(array $values = []): Collection
    {
        return $this->connection->executeParsed($this->statement, $this->bind($values));
    }

    /**
     * @param array<string|int, mixed> $values Bindings for this execution.
     * @return array<array-key, mixed>
     */
    public function query(array $values = []): array
    {
        return $this->execute($values)->toArray();
    }

    /**
     * @param array<string|int, mixed> $values Bindings for this execution.
     * @return int Number of affected rows.
     */
    public function statement(array $values = []): int
    {
        return $this->connection->writeParsed($this->statement, $this->bind($values));
    }

    /**
     * @param array<string|int, mixed> $values Bindings for this execution.
     * @return QueryResult
     */
    public function run(array $values = []): QueryResult
    {
        return $this->connection->runParsed($this->statement, $this->bind($values));
    }

    /**
     * @param array<string|int, mixed> $values Supplied bindings.
     * @return array<string|int, string|int|float|bool|null>
     */
    private function bind(array $values): array
    {
        $bound = [];
        if ($this->parameters !== [] && is_int(array_key_first($this->parameters)) && !array_is_list($values)) {
            throw new ParameterException('Positional bindings must be a zero-based list');
        }
        foreach ($values as $key => $value) {
            $key = is_string($key) ? ltrim($key, ':') : $key;
            if (!array_key_exists($key, $this->parameters)) {
                throw new ParameterException("Unexpected parameter '{$key}'");
            }
            if (array_key_exists($key, $bound)) {
                throw new ParameterException("Parameter '{$key}' was supplied twice");
            }
            $bound[$key] = $this->finiteValue($key, $value);
        }
        foreach ($this->parameters as $key => $parameter) {
            if (!array_key_exists($key, $bound)) {
                throw new ParameterException("Missing parameter '{$key}'", position: $parameter->position);
            }
        }
        return $bound;
    }

    /**
     * @param string|int $key Normalised binding name.
     * @param mixed $value Supplied value.
     * @return string|int|float|bool|null
     */
    private function finiteValue(string|int $key, mixed $value): string|int|float|bool|null
    {
        if (!(is_scalar($value) || $value === null) || (is_float($value) && !is_finite($value))) {
            throw new ParameterException("Parameter '{$key}' must be a finite scalar or null", position: $this->parameters[$key]->position);
        }
        return $value;
    }
}
