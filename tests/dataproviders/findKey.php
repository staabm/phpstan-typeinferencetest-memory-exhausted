<?php

/** @noinspection PhpExpressionResultUnusedInspection */
declare(strict_types=1);

namespace Tests\dataproviders;

use function App\Functional\findKey;
use function PHPStan\Testing\assertType;

/**
 * @todo Fix the typing as it is completely broken... {@see https://phpstan.org/r/d979d850-c552-4598-bb72-50ee7cf7d4eb}
 *
 * @var callable(mixed): bool $callback
 * @var array<never, never> $emptyList
 * @var array{} $emptyMap
 * @var array<int, string> $unknownList
 * @var array{id?: int, name?: string} $unknownMap
 * @var non-empty-array<int, string> $nonEmptyList
 * @var array{work: float} $nonEmptyMap
 */
assertType('null', findKey([], $callback));

// This does not work as expected...
assertType(
    /* 'null' */
    'int|string|null',
    findKey($emptyList, $callback),
);
assertType('null', findKey($emptyMap, $callback));

// This is not great, as it loses the "int" type
assertType(
    /* 'int|null' */
    'int|string|null',
    findKey($unknownList, $callback),
);

// This does not work as expected...
assertType(
    /* "'id'|'name'|null" */
    'int|string|null',
    findKey($unknownMap, $callback),
);

// This does not work as expected...
assertType(
    /* 'int|null' */
    'int|string|null',
    findKey($nonEmptyList, $callback),
);

// This does not work as expected...
assertType(
    /* "'work'|null" */
    'int|string|null',
    findKey($nonEmptyMap, $callback),
);
