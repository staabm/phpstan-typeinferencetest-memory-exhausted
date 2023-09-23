<?php

declare(strict_types=1);

namespace App\Dependencies;

use function is_object;

function equals(mixed $a, mixed $b): bool
{
    if (is_object($a) && is_object($b) && $a::class === $b::class) {
        return match (true) {
            // Gammadia
            method_exists($a, 'equals') => $a->equals($b),
            // Brick DateTime
            method_exists($a, 'isEqualTo') => $a->isEqualTo($b),
            // Carbon
            method_exists($a, 'equalTo') => $a->equalTo($b),
            // Symfony string
            method_exists($a, 'equalsTo') => $a->equalsTo($b),
            default => $a === $b,
        };
    }

    return $a === $b;
}
