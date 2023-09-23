<?php

/** @noinspection PhpExpressionResultUnusedInspection */
declare(strict_types=1);

namespace Tests\dataproviders;

use function App\Functional\partition;
use function PHPStan\Testing\assertType;

/**
 * @var array<string> $list
 * @var array<string, int> $map
 * @var non-empty-array<string, int> $nonEmptyArray
 * @var callable(int, string): bool $evenNumbersPredicate
 * @var callable(string): bool $charactersPredicate
 */

// Keys are not kept for lists
[$successStrings, $failedStrings] = partition($list, $charactersPredicate);
assertType('array<string>', $successStrings);
assertType('array<string>', $failedStrings);

// Keys are kept for maps
[$evenNumbers, $oddNumbers] = partition($map, $evenNumbersPredicate);
assertType('array<string, int>', $evenNumbers);
assertType('array<string, int>', $oddNumbers);

// Even for non-empty arrays, we cannot know if either of the partition'ed array will not be empty
[$nonEmptyEvenNumbers, $nonEmptyOddNumbers] = partition($nonEmptyArray, $evenNumbersPredicate);
assertType('array<string, int>', $nonEmptyEvenNumbers);
assertType('array<string, int>', $nonEmptyOddNumbers);
