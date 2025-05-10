<?php

namespace CQL\Engine\Modifiers\Contracts;

interface ModifierInterface
{
    /**
     * Apply the modifier to the given data.
     *
     * @param mixed $data The data to modify.
     * @return mixed The modified data.
     */
    public function apply(mixed $data): mixed;

    /**
     * Get the name of the modifier.
     *
     * @return string The name of the modifier.
     */
    public function getName(): string;
}