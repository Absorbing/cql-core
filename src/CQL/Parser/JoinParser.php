<?php

namespace CQL\Parser;

use CQL\Parser\Nodes\FromNode;
use CQL\Parser\Nodes\JoinNode;
use CQL\Parser\Enums\JoinType;

/** @internal Grammar implementation shared by Parser. */
final class JoinParser
{
    /**
     * @param ParseContext $context Shared cursor and binding state.
     */
    public function __construct(private readonly ParseContext $context)
    {
    }
    /**
     * Parse the FROM statement.
     *
     * JoinType is a pure enum factory.
     * @SuppressWarnings("PHPMD.StaticAccess")
     * @return FromNode
     */
    public function parseFrom(): FromNode
    {
        $this->context->expect('KEYWORD', 'FROM');
        $base = $this->context->expect('IDENTIFIER')->value;

        $joins = [];

        while (true) {
            $type = null;

            if ($this->context->match('KEYWORD', 'LEFT') || $this->context->match('KEYWORD', 'RIGHT') || $this->context->match(
                    'KEYWORD',
                    'INNER'
                )) {
                $type = strtoupper($this->context->advance()->value);
            }

            if (!$this->context->match('KEYWORD', 'JOIN')) {
                if ($type !== null) {
                    $this->context->expect('KEYWORD', 'JOIN');
                }
                break;
            }

            $this->context->advance(); // consume JOIN

            $right = $this->context->expect('IDENTIFIER')->value;
            $this->context->expect('KEYWORD', 'ON');

            $leftToken = $this->context->expect('IDENTIFIER');
            $this->context->expect('DOT');
            $leftField = $this->context->expect('IDENTIFIER')->value;

            $this->context->expect('COMPARISON_OPERATOR', '=');

            $this->context->expect('IDENTIFIER');
            $this->context->expect('DOT');
            $rightField = $this->context->expect('IDENTIFIER')->value;

            $joins[] = new JoinNode(
                rightAlias: $right,
                leftAlias: $leftToken->value,
                leftKey: $leftField,
                rightKey: $rightField,
                type: JoinType::fromKeyword($type)
            );
        }

        return new FromNode($base, $joins);
    }
}
