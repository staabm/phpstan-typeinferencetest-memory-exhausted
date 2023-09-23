<?php

/** @noinspection PhpExpressionResultUnusedInspection */
declare(strict_types=1);

namespace Tests\dataproviders;

use App\Dependencies\Percentage;
use App\Dependencies\Timeline;
use function App\Functional\fill;
use function PHPStan\Testing\assertType;

assertType('array{}', fill(0, 0, value: 'toto'));
assertType('array{42, 42}', fill(0, 2, value: 42));
assertType('array{2: 42, 3: 42}', fill(2, 2, value: 42));
assertType('list<null>', fill(0, mt_rand(0, 3), value: null));
assertType(
    'array{Gammadia\Collections\Timeline\Timeline<Gammadia\Common\Math\Percentage>}',
    fill(0, 1, value: Timeline::constant(Percentage::of(80))),
);
