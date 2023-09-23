<?php

/** @noinspection PhpExpressionResultUnusedInspection */
declare(strict_types=1);

namespace Tests\dataproviders;

use function App\Functional\reverse;
use function PHPStan\Testing\assertType;

assertType('array{}', reverse([]));
assertType('array{1337, null, 42}', reverse([42, null, 1337]));
assertType('array{test3: 1337, test2: null, test1: 42}', reverse(['test1' => 42, 'test2' => null, 'test3' => 1337]));
assertType("array{test3: 1337, test2: 'test 2', 0: 42}", reverse([42, 'test2' => 'test 2', 'test3' => 1337]));
