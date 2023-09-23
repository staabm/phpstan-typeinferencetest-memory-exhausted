<?php

/** @noinspection PhpExpressionResultUnusedInspection */
declare(strict_types=1);

namespace Tests\dataproviders;

use function App\Functional\pairs;
use function PHPStan\Testing\assertType;

/**
 * @var list<int> $list
 * @var array{id: int, name: string} $map
 * @var array{0: int, 1: string} $tuple
 * @var list<string> $listWithNumericKeys
 * @var array<string, string> $arrayWithStringKeys
 */
assertType('list<array{*NEVER*, *NEVER*}>', pairs([]));
assertType('list<array{*NEVER*, *NEVER*}>', pairs([], [], []));
assertType('list<array{int, int}>', pairs($list));
assertType('list<array{int, string}>', pairs($listWithNumericKeys));
assertType('list<array{string, string}>', pairs($arrayWithStringKeys));
assertType('list<array{string, int|string}>', pairs($map));
assertType('list<array{int, int|string}>', pairs($tuple));
