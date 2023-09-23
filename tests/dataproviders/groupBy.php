<?php

/** @noinspection PhpExpressionResultUnusedInspection */
declare(strict_types=1);

namespace Tests\dataproviders;

use App\Dependencies\Percentage;
use App\Dependencies\Timeline;
use function App\Functional\groupBy;
use function PHPStan\Testing\assertType;

/**
 * @var callable(): void $doNothing
 * @var list<array{id: int, name: string, work: Timeline<Percentage>}> $items
 * @var callable(array{id: int, name: string, work: Timeline<Percentage>}): string $byName
 * @var callable(array{id: int, name: string, work: Timeline<Percentage>}): int $byId
 * @var callable(array{id: int, name: string, work: Timeline<Percentage>}): float $byWork (this is not supposed to be allowed)
 */

/** @phpstan-ignore-next-line */
assertType('array<list<*NEVER*>>', groupBy([], $doNothing));

assertType(
    'array<string, list<array{id: int, name: string, work: Gammadia\Collections\Timeline\Timeline<Gammadia\Common\Math\Percentage>}>>',
    groupBy($items, $byName),
);
assertType(
    'array<int, list<array{id: int, name: string, work: Gammadia\Collections\Timeline\Timeline<Gammadia\Common\Math\Percentage>}>>',
    groupBy($items, $byId),
);

// Float is converted to int, precision is lost and so is the key type
assertType(
    'array<list<array{id: int, name: string, work: Gammadia\Collections\Timeline\Timeline<Gammadia\Common\Math\Percentage>}>>',
    /** @phpstan-ignore-next-line */
    groupBy($items, $byWork),
);
