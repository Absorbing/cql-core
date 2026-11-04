<?php

namespace Data;

use ArrayIterator;
use CQL\Data\Support\Collection;
use Generator;
use PHPUnit\Framework\TestCase;

class CollectionTest extends TestCase
{
    public function test_flat_map_consumes_arrays_iterators_and_generators_in_order(): void
    {
        $items = ['array' => 1, 'iterator' => 2, 'generator' => 3, 'empty' => 4];
        $visited = [];
        $generator = static function (int $value): Generator {
            yield 'same' => "generator:{$value}:first";
            yield 'same' => "generator:{$value}:second";
        };

        $collection = new Collection($items);
        $result = $collection->flatMap(
            static function (int $value, string $key) use (&$visited, $generator): iterable {
                $visited[$key] = $value;
                return match ($key) {
                    'array' => [10 => "array:{$value}"],
                    'iterator' => new ArrayIterator(['value' => "iterator:{$value}"]),
                    'generator' => $generator($value),
                    default => [],
                };
            }
        );

        $this->assertSame($items, $visited);
        $this->assertSame(
            ['array:1', 'iterator:2', 'generator:3:first', 'generator:3:second'],
            $result->toArray()
        );
        $this->assertSame($items, $collection->toArray());
    }
}
