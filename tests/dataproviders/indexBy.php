<?php

/** @noinspection PhpExpressionResultUnusedInspection */
declare(strict_types=1);

namespace Tests\dataproviders;

use App\Dependencies\Percentage;
use App\Dependencies\Timeline;
use function App\Functional\indexBy;
use function PHPStan\Testing\assertType;

/**
 * @var callable(): void $doNothing
 * @var callable(): int $byId
 * @var callable(): string $byName
 * @var callable(): float $byWork
 * @var array{id: int, name: string, work: Timeline<Percentage>}[] $items
 */

/** @phpstan-ignore-next-line */
assertType('array<*NEVER*>', indexBy([], $doNothing));
assertType('array<int, *NEVER*>', indexBy([], $byId));

assertType(
    'array<int, array{id: int, name: string, work: Gammadia\Collections\Timeline\Timeline<Gammadia\Common\Math\Percentage>}>',
    indexBy($items, $byId),
);
assertType(
    'array<string, array{id: int, name: string, work: Gammadia\Collections\Timeline\Timeline<Gammadia\Common\Math\Percentage>}>',
    indexBy($items, $byName),
);

// Float is converted to int, precision is lost and so is the key type
assertType(
    'array<array{id: int, name: string, work: Gammadia\Collections\Timeline\Timeline<Gammadia\Common\Math\Percentage>}>',
    /** @phpstan-ignore-next-line */
    indexBy($items, $byWork),
);
