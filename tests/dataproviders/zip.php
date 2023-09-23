<?php

/** @noinspection PhpExpressionResultUnusedInspection */
declare(strict_types=1);

namespace Tests\dataproviders;

use function App\Functional\zip;
use function PHPStan\Testing\assertType;

assertType('array<int, array{*NEVER*, *NEVER*}>', zip([], []));
assertType('non-empty-array<int, array{1|2, 3|4}>', zip([1, 2], [3, 4]));
assertType('non-empty-array<int, array{1|2, 3|4, 5}>', zip([1, 2], [3, 4], [5]));
