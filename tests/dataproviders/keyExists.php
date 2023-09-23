<?php

/** @noinspection PhpExpressionResultUnusedInspection */
declare(strict_types=1);

namespace Tests\dataproviders;

use function App\Functional\keyExists;
use function PHPStan\Testing\assertType;

/**
 * @var array<0|1|2, mixed> $list
 * @var array{baz: int} $map
 */

// Accessing a key that exists does not trigger an error
$value = $list[2];

/** @phpstan-ignore-next-line Accessing a key that we are not sure exists in a list should trigger an inspection */
$value = $list[4];
/** @phpstan-ignore-next-line Asking if a key exist when it knows it does not should trigger an inspection */
if (keyExists($list, key: 4)) {
    // But then accessing the key once we've "proved" it does exist does not trigger an inspection
    $value = $list[4];
}

// Accessing a key that exists does not trigger an error
$value = $map['baz'];

/** @phpstan-ignore-next-line Accessing a key that does not exist on the map should trigger an inspection */
$value = $map['bar'];
/** @phpstan-ignore-next-line Asking if a key exist when it knows it does not should trigger an inspection */
if (keyExists($map, key: 'bar')) {
    // But then accessing the key once we've "proved" it does exist does not trigger an inspection
    $value = $map['bar'];
}

// PHPStan wants an assert in each file
assertType('true', true);
