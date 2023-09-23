<?php

/** @noinspection PhpExpressionResultUnusedInspection */
declare(strict_types=1);

namespace Tests\dataproviders;

use App\Dependencies\LocalDateTimeInterval;
use App\Dependencies\Percentage;
use App\Dependencies\Timeline;
use Brick\DateTime\LocalDate;
use Brick\DateTime\LocalDateTime;
use function App\Functional\filter;
use function PHPStan\Testing\assertType;
use const ARRAY_FILTER_USE_BOTH;
use const ARRAY_FILTER_USE_KEY;

// Without callable
assertType('array{}', filter([]));
assertType('array{0: 42, 2: 1337}', filter([42, null, 1337]));
assertType('array{test1: 42, test3: 1337}', filter(['test1' => 42, 'test2' => null, 'test3' => 1337]));
assertType(
    'array{Gammadia\Collections\Timeline\Timeline<Gammadia\Common\Math\Percentage>}',
    filter([Timeline::constant(Percentage::of(80.0)), null]),
);

// With callable (the exact key cannot be determined by PHPStan's extension)
assertType(
    'array{1: 42, 2: null, 3: 1337, 4: 0}',
    filter([false, 42, null, 1337, 0], static fn (mixed $value): bool => false !== $value),
);
assertType(
    'array{0?: Gammadia\Collections\Timeline\Timeline<Gammadia\Common\Math\Percentage>, 1?: Gammadia\Collections\Timeline\Timeline<Gammadia\Common\Math\Percentage>}',
    filter(
        [
            Timeline::constant(Percentage::of(40.0)),
            Timeline::with(LocalDateTimeInterval::day(LocalDate::parse('2022-04-24')), Percentage::of(80.0)),
        ],
        static fn (Timeline $timeline): bool
            => null !== $timeline->valueAt(LocalDateTime::parse('2022-04-25T09:40')),
    ),
);

/*
 * Testing that wrong combination of callable arguments' types and modes does trigger inspections
 */

/** @phpstan-ignore-next-line This one does fail, as the value + key is asked and the key + value is used */
filter([false, true, false], static fn (int $key, bool $value): bool => 0 === $key % 2 && $value, mode: ARRAY_FILTER_USE_BOTH);

/** @phpstan-ignore-next-line This one does fail, as the value + key is asked but only the key is used */
filter([false, true, false], static fn (int $key): bool => 0 === $key % 2, mode: ARRAY_FILTER_USE_BOTH);

/** @phpstan-ignore-next-line This one does fail, as the value is asked but the key is used */
filter([false, true, false], static fn (int $key): bool => 0 === $key % 2, mode: 0);

/** @phpstan-ignore-next-line This one does fail, as the key is asked but the value is used */
filter([true, false], static fn (bool $value): bool => $value, mode: ARRAY_FILTER_USE_KEY);

// More complex example fails too
filter(
    [
        Timeline::constant(Percentage::of(40.0)),
        Timeline::with(LocalDateTimeInterval::day(LocalDate::parse('2022-04-24')), Percentage::of(80.0)),
    ],
    /** @phpstan-ignore-next-line */
    static fn (Timeline $timeline): bool
        => null !== $timeline->valueAt(LocalDateTime::parse('2022-04-25T09:40')),
    mode: ARRAY_FILTER_USE_KEY,
);
