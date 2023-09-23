<?php

/** @noinspection PhpExpressionResultUnusedInspection */
declare(strict_types=1);

namespace Tests\dataproviders;

use function App\Functional\firstKey;
use function PHPStan\Testing\assertType;

/**
 * @var array<never, never> $emptyList
 * @var array{} $emptyMap
 * @var array<int, string> $unknownList
 * @var array{id?: int, name?: string} $unknownMap
 * @var non-empty-array<int, string> $nonEmptyList
 * @var array{work: float} $nonEmptyMap
 */
assertType('null', firstKey([]));
assertType('null', firstKey($emptyList));
assertType('null', firstKey($emptyMap));

assertType('int|null', firstKey($unknownList));
assertType("'id'|'name'|null", firstKey($unknownMap));

assertType('int', firstKey($nonEmptyList));
assertType("'work'", firstKey($nonEmptyMap));
