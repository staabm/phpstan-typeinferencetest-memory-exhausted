<?php

/** @noinspection PhpExpressionResultUnusedInspection */
declare(strict_types=1);

namespace Tests\dataproviders;

use function App\Functional\sort;
use function PHPStan\Testing\assertType;
use const SORT_NATURAL;

/**
 * @var list<int> $list
 * @var array<string, int> $map
 * @var callable(int, int): int $comparator
 */
assertType('list<int>', sort($list));
assertType('list<int>', sort($list, preserveKeys: true));
assertType('list<int>', sort($list, flags: SORT_NATURAL));
assertType('list<int>', sort($list, $comparator));

assertType('list<int>', sort($map));
assertType('array<string, int>', sort($map, preserveKeys: true));
assertType('list<int>', sort($map, flags: SORT_NATURAL));
assertType('list<int>', sort($map, $comparator));
