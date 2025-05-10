<?php

namespace CQL\Parser\Enums;

use InvalidArgumentException;

enum JoinType: string
{
    case INNER = 'INNER';
    case LEFT = 'LEFT';
    case RIGHT = 'RIGHT';
    case FULL = 'FULL'; // optional for now

    /**
     * Returns the join type based on the given keyword.
     *
     * @param string|null $keyword
     * @return self
     * @throws InvalidArgumentException
     */
    public static function fromKeyword(?string $keyword): self
    {
        return match (strtoupper($keyword ?? '')) {
            'LEFT' => self::LEFT,
            'RIGHT' => self::RIGHT,
            'FULL' => self::FULL,
            'INNER', '' => self::INNER,
            default => throw new InvalidArgumentException("Invalid join type '$keyword'")
        };
    }
}