<?php

/** @noinspection PhpExpressionResultUnusedInspection */
declare(strict_types=1);

namespace Tests\dataproviders;

use App\Dependencies\Percentage;
use App\Dependencies\Timeline;
use function App\Functional\flip;
use function PHPStan\Testing\assertType;

assertType('array{}', flip([]));
assertType('array{42: 0, 1337: 1}', flip([42, 1337]));
assertType('array{42: 0, hello there: 1}', flip([42, 'hello there']));
assertType("array{42: 'foobar', 1337: 'hello there'}", flip(['foobar' => 42, 'hello there' => 1337]));

/*
 * PHP produces a warning for the following scenarios (and PHPStan an inspection), as only array-key is
 * accepted as values for flip() (not even null)
 */
/** @phpstan-ignore-next-line */
flip([42, null, 1337]);

/** @phpstan-ignore-next-line */
flip([0 => Timeline::constant(Percentage::of(80.0))]);

/** @phpstan-ignore-next-line */
flip([0 => 42, 1 => Timeline::constant(Percentage::of(80.0))]);
