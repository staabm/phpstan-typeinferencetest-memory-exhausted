<?php

declare(strict_types=1);

namespace App\Dependencies;

final class Predicate
{
    /**
     * @return callable(mixed): bool
     */
    public static function notNull(): callable
    {
        return static fn (mixed $value): bool => null !== $value;
    }
}
