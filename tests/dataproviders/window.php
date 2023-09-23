<?php

/** @noinspection PhpExpressionResultUnusedInspection */
declare(strict_types=1);

namespace Tests\dataproviders;

use function App\Functional\window;
use function PHPStan\Testing\assertType;

/** @phpstan-ignore-next-line */
assertType('list<list<*NEVER*>>', window([], 0));
assertType('list<list<*NEVER*>>', window([], 1));

// This would need a custom extension to work
assertType(
    /* 'array<list<*NEVER*>>' */
    'list<list<int>>',
    window([1, 2], 4),
);

/** @var array{first: int, second: int, third: int} $items */
assertType('list<array<string, int>>', window($items, 2));

/** @var array{42: string, 1337: string} $items */
assertType('list<list<string>>', window($items, 2));
