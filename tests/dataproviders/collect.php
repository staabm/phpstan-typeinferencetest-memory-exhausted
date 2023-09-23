<?php

/** @noinspection PhpExpressionResultUnusedInspection */
declare(strict_types=1);

namespace Tests\dataproviders;

use App\Dependencies\Percentage;
use App\Dependencies\Timeline;
use Brick\DateTime\LocalDateTime;
use function App\Functional\collect;
use function PHPStan\Testing\assertType;

/** @var array<int|string, int> $items */
assertType('list<int>', collect($items, static function (int $v): iterable {
    yield $v * 2;
}));
assertType('list<int|string>', collect($items, static function (int $v): iterable {
    yield $v * 2;
    yield (string) $v;
}));
assertType('list<int>', collect($items, static function () use ($items): iterable {
    yield from $items;
}));
assertType('list<int>', collect($items, static fn (int $v): iterable => yield $v * 2));

/**
 * @var Timeline<Percentage>[] $timelines
 */

/** @todo Subtype is lost... find a way to fix that */
assertType(
    /* 'array<Gammadia\Collections\Timeline\Timeline<Gammadia\Common\Math\Percentage>>' */
    'list<Gammadia\Collections\Timeline\Timeline>',
    collect(
        $timelines,
        static function (Timeline $timeline): iterable {
            if (null !== $timeline->valueAt(LocalDateTime::parse('2022-04-25T12:00'))) {
                yield $timeline;
            }
        },
    ),
);
