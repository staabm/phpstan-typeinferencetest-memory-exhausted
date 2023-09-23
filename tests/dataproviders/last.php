<?php

/** @noinspection PhpExpressionResultUnusedInspection */
declare(strict_types=1);

namespace Tests\dataproviders;

use App\Dependencies\Percentage;
use App\Dependencies\Timeline;
use function App\Functional\last;
use function PHPStan\Testing\assertType;

/**
 * @var array<never, never> $emptyList
 * @var array{} $emptyMap
 * @var array<int, string> $unknownList
 * @var array{id?: int, name?: string} $unknownMap
 * @var non-empty-array<int, Timeline<Percentage>> $nonEmptyList
 * @var array{work: Timeline<Percentage>} $nonEmptyMap
 */
assertType('null', last([]));
assertType('null', last($emptyList));
assertType('null', last($emptyMap));

assertType('string|null', last($unknownList));
assertType('int|string|null', last($unknownMap));

assertType('Gammadia\Collections\Timeline\Timeline<Gammadia\Common\Math\Percentage>', last($nonEmptyList));
assertType('Gammadia\Collections\Timeline\Timeline<Gammadia\Common\Math\Percentage>', last($nonEmptyMap));
