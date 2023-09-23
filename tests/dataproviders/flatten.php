<?php

/** @noinspection PhpExpressionResultUnusedInspection */
declare(strict_types=1);

namespace Tests\dataproviders;

use App\Dependencies\Percentage;
use App\Dependencies\Timeline;
use function App\Functional\flatten;
use function PHPStan\Testing\assertType;

/**
 * All keys are lost.
 *
 * @var list<array{work: Timeline<Percentage>}> $timelines
 * @var array<string, list<array<string, mixed>>> $listsGroupedByString
 * @var array<string, array<string, array<string, mixed>>> $arraysGroupedByStrings
 * @var string[][] $arrayWithoutKeys
 * @var array{id: int, name: string, hobbies: string[]} $map
 */
assertType('list<Gammadia\Collections\Timeline\Timeline<Gammadia\Common\Math\Percentage>>', flatten($timelines));
assertType('list<array<string, mixed>>', flatten($listsGroupedByString));
assertType('list<array<string, mixed>>', flatten($arraysGroupedByStrings));
assertType('list<string>', flatten($arrayWithoutKeys));

/** @todo This should yield an *ERROR* or at least return "never" as it throws an exception */
assertType(
    /* 'list<int, never>' */
    'list<string>',
    /** @phpstan-ignore-next-line */
    flatten($map),
);
