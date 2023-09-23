<?php

/** @noinspection PhpExpressionResultUnusedInspection */
declare(strict_types=1);

namespace Tests\dataproviders;

use function App\Functional\chunk;
use function PHPStan\Testing\assertType;

/**
 * @var list<int> $items
 * @var array{id: int, name: string, work: float} $resource
 * @var array<string, array{id: int, name: string, work: float}> $resourceList Indexed by name
 */
assertType('list<list<*NEVER*>>', chunk([], 2));
assertType('list<list<int>>', chunk($items, 2));

assertType('list<list<float|int|string>>', chunk($resource, 2));
assertType('list<array<string, float|int|string>>', chunk($resource, 2, preserveKeys: true));

// Preserve keys makes no difference for a list of maps
assertType('list<list<array{id: int, name: string, work: float}>>', chunk($resourceList, 5));
assertType('list<array<string, array{id: int, name: string, work: float}>>', chunk($resourceList, 5, preserveKeys: true));

/** @phpstan-ignore-next-line A size of zero is invalid */
assertType('list<list<int>>', chunk($items, 0));
