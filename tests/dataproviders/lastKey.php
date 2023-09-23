<?php

/** @noinspection PhpExpressionResultUnusedInspection */
declare(strict_types=1);

namespace Tests\dataproviders;

use function App\Functional\lastKey;
use function PHPStan\Testing\assertType;

/**
 * @var array<never, never> $emptyList
 * @var array{} $emptyMap
 * @var array<int, string> $unknownList
 * @var array{id?: int, name?: string} $unknownMap
 * @var non-empty-array<int, string> $nonEmptyList
 * @var array{work: float} $nonEmptyMap
 */
assertType('null', lastKey([]));
assertType('null', lastKey($emptyList));
assertType('null', lastKey($emptyMap));

assertType('int|null', lastKey($unknownList));
assertType("'id'|'name'|null", lastKey($unknownMap));

assertType('int', lastKey($nonEmptyList));
assertType("'work'", lastKey($nonEmptyMap));
