<?php

/** @noinspection PhpExpressionResultUnusedInspection */
declare(strict_types=1);

namespace Tests\dataproviders;

use function App\Functional\keys;
use function PHPStan\Testing\assertType;

assertType('array{}', keys([]));
assertType('array{0, 1, 2}', keys([42, null, 1337]));
assertType("array{0, 'test2', 'test3'}", keys([42, 'test2' => 'test2', 'test3' => 1337]));
assertType("array{'test1', 'test2', 'test3'}", keys(['test1' => 42, 'test2' => null, 'test3' => 1337]));
