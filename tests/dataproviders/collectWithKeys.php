<?php

/** @noinspection PhpExpressionResultUnusedInspection */
declare(strict_types=1);

namespace Tests\dataproviders;

use App\Dependencies\Percentage;
use App\Dependencies\Timeline;
use Brick\DateTime\LocalDateTime;
use function App\Functional\collectWithKeys;
use function PHPStan\Testing\assertType;

function doFoo2() {
    /** @var array<int|string, int> $items */
    assertType('array<int, int>', collectWithKeys($items, static function (int $v): iterable {
        yield $v => $v * 2;
    }));
    assertType('array<string, int>', collectWithKeys($items, static function (int $v): iterable {
        yield (string) $v => $v * 2;
    }));
    assertType('array<int|string, int>', collectWithKeys($items, static function (int $v, int|string $k): iterable {
        yield $k => $v * 2;
    }));
    assertType('array<int, int|string>', collectWithKeys($items, static function (int $v): iterable {
        yield $v => $v * 2;
        yield (string) $v;
    }));
    assertType('array<int|string, int>', collectWithKeys($items, static function () use ($items): iterable {
        yield from $items;
    }));
    assertType('array<string, int>', collectWithKeys($items, static fn (int $v): iterable => yield (string) $v => $v * 2));

    /**
     * @var Timeline<Percentage>[] $timelines
     */

    /** @todo Subtype is lost... find a way to fix that */
    assertType(
    /* 'array<string, Gammadia\Collections\Timeline\Timeline<Gammadia\Common\Math\Percentage>>' */
        'array<string, Gammadia\Collections\Timeline\Timeline>',
        collectWithKeys(
            $timelines,
            static function (Timeline $timeline): iterable {
                $timepoint = LocalDateTime::parse('2022-04-25T12:00');
                if (null !== $timeline->valueAt($timepoint)) {
                    yield (string) $timepoint => $timeline;
                }
            },
        ),
    );

}

