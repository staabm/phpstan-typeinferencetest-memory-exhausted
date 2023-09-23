<?php

/** @noinspection PhpExpressionResultUnusedInspection */
declare(strict_types=1);

namespace Tests\dataproviders;

use App\Dependencies\Percentage;
use App\Dependencies\Timeline;
use function App\Functional\first;
use function PHPStan\Testing\assertType;

/**
 * @var array<never, never> $emptyList
 * @var array{} $emptyMap
 * @var array<int, string> $unknownList
 * @var array{id?: int, name?: string} $unknownMap
 * @var non-empty-array<int, Timeline<Percentage>> $nonEmptyList
 * @var array{work: Timeline<Percentage>} $nonEmptyMap
 */
assertType('null', first([]));
assertType('null', first($emptyList));
assertType('null', first($emptyMap));

assertType('string|null', first($unknownList));
assertType('int|string|null', first($unknownMap));

assertType('Gammadia\Collections\Timeline\Timeline<Gammadia\Common\Math\Percentage>', first($nonEmptyList));
assertType('Gammadia\Collections\Timeline\Timeline<Gammadia\Common\Math\Percentage>', first($nonEmptyMap));

if (!empty($unknownList)) {
    assertType('string', first($unknownList));
}
