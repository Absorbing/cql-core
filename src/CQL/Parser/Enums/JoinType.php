<?php

namespace CQL\Parser\Enums;

enum JoinType: string
{
    case INNER = 'INNER';
    case LEFT = 'LEFT';
    case RIGHT = 'RIGHT';
    case FULL = 'FULL'; // optional for now

    public static function fromKeyword(?string $keyword): self
    {
        return match (strtoupper($keyword ?? '')) {
            'LEFT' => self::LEFT,
            'RIGHT' => self::RIGHT,
            'FULL' => self::FULL,
            'INNER', '' => self::INNER,
            default => throw new \InvalidArgumentException("Invalid join type '$keyword'")
        };
    }
}