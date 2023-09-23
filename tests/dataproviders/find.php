<?php

/** @noinspection PhpExpressionResultUnusedInspection */
declare(strict_types=1);

namespace Tests\dataproviders;

use App\Dependencies\Percentage;
use App\Dependencies\Timeline;
use function App\Functional\find;
use function PHPStan\Testing\assertType;

/**
 * @var callable(mixed): bool $callback
 * @var array<never, never> $emptyList
 * @var array{} $emptyMap
 * @var array<int, string> $unknownList
 * @var array{id?: int, name?: string} $unknownMap
 * @var non-empty-array<int, Timeline<Percentage>> $nonEmptyList
 * @var array{work: Timeline<Percentage>} $nonEmptyMap
 */
assertType('null', find([], $callback));
assertType('null', find($emptyList, $callback));
assertType('null', find($emptyMap, $callback));

assertType('string|null', find($unknownList, $callback));
assertType('int|string|null', find($unknownMap, $callback));

assertType('Gammadia\Collections\Timeline\Timeline<Gammadia\Common\Math\Percentage>|null', find($nonEmptyList, $callback));
assertType('Gammadia\Collections\Timeline\Timeline<Gammadia\Common\Math\Percentage>|null', find($nonEmptyMap, $callback));
