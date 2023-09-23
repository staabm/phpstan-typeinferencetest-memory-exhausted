<?php

/** @noinspection PhpExpressionResultUnusedInspection */
declare(strict_types=1);

namespace Tests\dataproviders;

use App\Dependencies\Percentage;
use App\Dependencies\Timeline;
use function App\Functional\fillWith;
use function PHPStan\Testing\assertType;

/**
 * @var callable(): void $doNothing
 */
assertType('list<*NEVER*>', fillWith([], 0, 0, $doNothing));

assertType('array<int>', fillWith([1, 2, 3, 4], 1, 0, static fn (): string => '42'));
assertType('list<int>', fillWith([], 0, 2, static fn (): int => 42));
assertType('list<int>', fillWith([], 0, mt_rand(0, 3), static fn (): int => 42));
assertType('array<int|string>', fillWith([1, 2, 3, 4], 1, 2, static fn (): string => '42'));

assertType(
    'list<Gammadia\Collections\Timeline\Timeline<Gammadia\Common\Math\Percentage>>',
    fillWith([], 0, 1, static fn (): Timeline => Timeline::constant(Percentage::of(80))),
);
