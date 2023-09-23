<?php

/** @noinspection PhpExpressionResultUnusedInspection */
declare(strict_types=1);

namespace Tests\dataproviders;

use function App\Functional\values;
use function PHPStan\Testing\assertType;

assertType('array{}', values([]));
assertType('array{42, null, 1337}', values([42, null, 1337]));
assertType('array{42, null, 1337}', values(['test1' => 42, 'test2' => null, 'test3' => 1337]));
assertType("array{42, 'test 2', 1337}", values([42, 'test2' => 'test 2', 'test3' => 1337]));
