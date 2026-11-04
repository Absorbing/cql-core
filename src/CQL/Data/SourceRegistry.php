<?php

namespace CQL\Data;

use Closure;
use CQL\Data\Contracts\DataSourceInterface;
use CQL\Data\Enums\CSVHeaderMode;
use CQL\Exceptions\DataSourceException;
use CQL\Exceptions\InterpreterException;
use CQL\Parser\Nodes\DefineNode;
use CQL\Parser\Nodes\LiteralNode;

/** Source definitions belong to one CQL instance, never to global state. */
final class SourceRegistry
{
    /** @var array<string, Closure(?bool, int): SourceHandle> */
    private array $factories = [];

    /**
     * @param string $alias Source name.
     * @param callable(): DataSourceInterface $factory Factory returning a fresh source with unqualified row keys.
     * @return void
     */
    public function register(string $alias, callable $factory): void
    {
        $this->validateAlias($alias);
        $this->factories[$alias] = static function () use ($factory, $alias): SourceHandle {
            /** @var mixed $source Validate third-party factories at runtime. */
            $source = $factory();
            if (!$source instanceof DataSourceInterface) {
                throw new DataSourceException("Factory for '{$alias}' must return a DataSourceInterface", context: ['alias' => $alias]);
            }
            return new SourceHandle($source, $alias);
        };
    }

    /**
     * @param string $alias Source name.
     * @param string $path Literal filesystem path, without SQL quoting.
     * @param bool $headers Whether the first CSV row contains headers.
     * @param string $delimiter CSV delimiter.
     * @return void
     */
    public function registerCsv(string $alias, string $path, bool $headers = true, string $delimiter = ','): void
    {
        $this->validateAlias($alias);
        if (strlen($delimiter) !== 1) {
            throw new DataSourceException('CSV delimiter must be one byte');
        }
        $this->factories[$alias] = fn(?bool $streaming, int $threshold): SourceHandle => $this->csv(
            $alias, $path, $headers ? CSVHeaderMode::WITH_HEADERS : CSVHeaderMode::WITHOUT_HEADERS,
            $delimiter, $streaming, $threshold,
        );
    }

    /**
     * @param string $alias Source name.
     * @return void
     */
    public function unregister(string $alias): void
    {
        unset($this->factories[$alias]);
    }

    /**
     * @param string $alias Source name.
     * @param array<DefineNode> $defines Statement-local definitions.
     * @param bool|null $streaming Requested mode.
     * @param int $threshold Automatic streaming threshold.
     * @return SourceHandle
     */
    public function resolve(string $alias, array $defines, ?bool $streaming, int $threshold): SourceHandle
    {
        $inline = [];
        foreach ($defines as $define) {
            if (isset($inline[$define->alias])) {
                throw new DataSourceException("Duplicate DEFINE alias '{$define->alias}'", context: ['alias' => $define->alias]);
            }
            $inline[$define->alias] = $define;
        }
        if (isset($inline[$alias])) {
            $define = $inline[$alias];
            return $this->csv($alias, LiteralNode::decode($define->path), $define->hasHeaders, ',', $streaming, $threshold);
        }
        if (!isset($this->factories[$alias])) {
            throw new InterpreterException("Undefined data source alias '{$alias}'", context: ['alias' => $alias]);
        }
        return ($this->factories[$alias])($streaming, $threshold);
    }

    /**
     * @param string $alias Source name.
     * @return void
     */
    private function validateAlias(string $alias): void
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $alias)) {
            throw new DataSourceException("Invalid source alias '{$alias}'");
        }
    }

    /**
     * @param string $alias Source name.
     * @param string $path Literal path.
     * @param CSVHeaderMode $headers Header mode.
     * @param string $delimiter CSV delimiter.
     * @param bool|null $streaming Requested mode.
     * @param int $threshold Automatic streaming threshold.
     * @return SourceHandle
     */
    private function csv(string $alias, string $path, CSVHeaderMode $headers, string $delimiter, ?bool $streaming, int $threshold): SourceHandle
    {
        clearstatcache(true, $path);
        $streaming ??= is_file($path) && filesize($path) > $threshold;
        // Quote once for the legacy CSVDataSource constructor, which accepts SQL paths.
        $quoted = "'" . str_replace("'", "''", $path) . "'";
        return new SourceHandle(new CSVDataSource($quoted, $headers, $delimiter, $alias, $streaming), $alias, true);
    }
}
