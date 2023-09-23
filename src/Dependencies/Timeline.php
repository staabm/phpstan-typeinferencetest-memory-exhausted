<?php

declare(strict_types=1);

namespace App\Dependencies;

use Brick\DateTime\LocalDate;
use Brick\DateTime\LocalDateTime;
use Countable;
use Generator;
use IteratorAggregate;
use Traversable;
use UnexpectedValueException;
use Webmozart\Assert\Assert;
use function App\Functional\collect;
use function App\Functional\column;
use function App\Functional\concat;
use function App\Functional\filter;
use function App\Functional\first;
use function App\Functional\init;
use function App\Functional\last;
use function App\Functional\map;
use function App\Functional\reduce;
use function App\Functional\some;
use function App\Functional\sort;
use function App\Functional\sreduce;
use function App\Functional\unique;
use function App\Functional\values;
use function App\Functional\window;
use function App\Dependencies\equals;

/**
 * A timeline of a time-varying values for a single concept.
 *
 * This is basically a collection of non-overlapping time ranges, each representing
 * a continuous/non-empty time range during which the value of the concept does not vary.
 * Each change of value is associated with a new non-empty time range in the collection.
 *
 * The value might be undefined for some duration in the timeline, in which case it
 * is assumed to be null. While a timeline can safely store and manipulate null values,
 * some functions might not provide a way to distinguish between an explicit null value
 * and the absence of value. Purposely storing nulls in a timeline is discouraged.
 *
 * @see \Gammadia\Collections\Test\Unit\Timeline\TimelineTest
 *
 * @template T
 * @implements \IteratorAggregate<LocalDateTimeInterval, T>
 */
final class Timeline implements IteratorAggregate, Countable
{
    /**
     * We assume that items are correctly sorted and without overlapping/empty time range
     * (this is enforced by {@see Timeline::doAdd()}).
     *
     * @param list<array{0: LocalDateTimeInterval, 1: T}> $items
     */
    private function __construct(
        private array $items,
    ) {
    }

    /**
     * @return self<never>
     */
    public static function empty(): self
    {
        /** @var self<never> */
        return new self([]);
    }

    /**
     * @template U
     *
     * @param U $value
     *
     * @return self<U>
     */
    public static function constant(mixed $value): self
    {
        return new self([[LocalDateTimeInterval::forever(), $value]]);
    }

    /**
     * @template U
     *
     * @param U $value
     *
     * @return self<U>
     */
    public static function with(LocalDate|LocalDateInterval|LocalDateTimeInterval $range, mixed $value): self
    {
        $timeline = self::empty();
        $timeline->doAdd(LocalDateTimeInterval::cast($range), $value);

        return $timeline;
    }

    /**
     * Alias for add() that simplifies adding values able to provide a LocalDateTimeInterval
     *
     * @template K
     * @template U
     * @template V
     *
     * @param iterable<U> $values An iterable of mixed values, or an iterable of time ranges (will be used as values too)
     * @param (callable(U, K): (LocalDate|LocalDateInterval|LocalDateTimeInterval)|callable(U, K): iterable<LocalDate|LocalDateInterval|LocalDateTimeInterval>|callable(U, K): iterable<LocalDate|LocalDateInterval|LocalDateTimeInterval, V>|callable(U, K): V)|null $callable
     *
     * @return self<mixed> {@see TimelineImportDynamicReturnTypeExtension}
     */
    public static function import(iterable $values, ?callable $callable = null): self
    {
        $timeline = self::empty();
        $originalValuesTimeline = self::empty();

        /** @var LocalDate|LocalDateInterval|LocalDateTimeInterval $range */
        foreach (self::assembleValues($values, $callable) as $range => [$value, $originalValue]) {
            $timeRange = LocalDateTimeInterval::cast($range);

            try {
                $timeline->doAdd($timeRange, $value);
                $originalValuesTimeline->doAdd($timeRange, $originalValue);
            } catch (TimelineRangeConflictException $exception) {
                throw new TimelineImportConflictException(
                    $timeRange,
                    $originalValue,
                    $originalValuesTimeline->keep($range),
                    $exception,
                );
            }
        }

        return $timeline;
    }

    /**
     * @template U
     *
     * @param TimeIndexedCollection<U> $collection
     *
     * @return self<list<U>>
     */
    public static function fromTimeIndexedCollection(
        TimeIndexedCollection $collection,
        null|LocalDate|LocalDateInterval|LocalDateTimeInterval $range = null,
    ): self {
        /** @var list<self<U>> $timelines */
        $timelines = sreduce(
            $collection->stream($range),
            static function (array $timelines, mixed $value, LocalDateTimeInterval $timeRange): array {
                for ($i = 0; true; ++$i) {
                    try {
                        $timelines[$i] ??= self::empty();
                        $timelines[$i]->doAdd($timeRange, $value);

                        break;
                    } catch (TimelineRangeConflictException) {
                        Assert::notNull($timelines[$i] ?? null, 'Adding a single value in an empty timeline cannot fail.');
                    }
                }

                return $timelines;
            },
            initial: [],
        );

        /** @var self<list<U>> $timeline */
        $timeline = self::zipAll(...$timelines)->map(static fn (array $zipped): array
            => values(filter($zipped, Predicate::notNull())),
        );

        return null !== $range ? $timeline->keep($range) : $timeline;
    }

    /**
     * Alias for `Timeline::zipAll()->map()` with intermediate array unpacking. Argument is a list of Timeline
     * objects, then a callable. The arguments in the callable must all be nullable (as there might be no value
     * for a given time frame).
     *
     * @return self<mixed>
     */
    public static function merge(mixed ...$arguments): self
    {
        $callable = last($arguments);
        if (is_callable($callable)) {
            $timelines = init($arguments);
        } else {
            $timelines = $arguments;
            $callable = static fn (...$values) => first(filter($values, Predicate::notNull()));
        }

        Assert::allIsInstanceOf($timelines, self::class);
        Assert::isCallable($callable);

        return self::zipAll(...$timelines)
            ->map(static function (array $values, LocalDateTimeInterval $timeRange) use ($callable): mixed {
                // Add the range as the last parameter to map
                $values[] = $timeRange;

                return $callable(...$values);
            });
    }

    /**
     * Return a new timeline with values being an array containing all timeline values for each possible boundary.
     *
     * @template U
     *
     * @param self<U> ...$timelines
     *
     * @return self<list<U|null>>
     */
    public static function zipAll(self ...$timelines): self
    {
        $hasInfiniteStart = false;
        $starts = collect($timelines, function (self $timeline) use (&$hasInfiniteStart): Generator {
            yield from collect($timeline->items, static function (array $item) use (&$hasInfiniteStart): Generator {
                /**
                 * @var LocalDateTimeInterval $timeRange
                 */
                [$timeRange] = $item;
                $start = $timeRange->getStart();
                if (null === $start) {
                    $hasInfiniteStart = true;
                } else {
                    yield $start;
                }
            });
        });

        $hasInfiniteEnd = false;
        $ends = collect($timelines, function (self $timeline) use (&$hasInfiniteEnd): Generator {
            yield from collect($timeline->items, static function (array $item) use (&$hasInfiniteEnd): Generator {
                /**
                 * @var LocalDateTimeInterval $timeRange
                 */
                [$timeRange] = $item;
                $end = $timeRange->getEnd();
                if (null === $end) {
                    $hasInfiniteEnd = true;
                } else {
                    yield $end;
                }
            });
        });

        $boundaries = concat(
            $hasInfiniteStart ? [null] : [],
            sort(
                unique(concat($starts, $ends), static fn (LocalDateTime $boundary): string => (string) $boundary),
                static fn (LocalDateTime $a, LocalDateTime $b): int => $a->compareTo($b),
            ),
            $hasInfiniteEnd ? [null] : [],
        );
        if ([] === $boundaries) {
            return self::empty();
        }

        $items = values(map(
            map(window($boundaries, 2), static fn (array $window): LocalDateTimeInterval
                => LocalDateTimeInterval::between(...$window),
            ),
            static fn (LocalDateTimeInterval $timeRange): array
                => [$timeRange, map($timelines, static fn (self $timeline) => $timeline->soleValueIn($timeRange))],
        ));

        // Remove slices of the timeline without any matching items in all other timelines
        return (new self($items))->filter(static fn (array $zipped): bool
            => some($zipped, Predicate::notNull()),
        );
    }

    /**
     * @template U
     *
     * @param U $value
     *
     * @return self<T|U>
     */
    public function fillBlanks(LocalDate|LocalDateInterval|LocalDateTimeInterval $range, mixed $value): self
    {
        $timeRange = LocalDateTimeInterval::cast($range);
        if ($timeRange->isEmpty()) {
            return $this;
        }

        /** @var self<T|U> */
        return $this
            ->keep($timeRange)
            ->zip(self::with($timeRange, $value))
            ->filter(fn (array $values, LocalDateTimeInterval $sliceRange): bool => [] === $this->doKeep($sliceRange))
            ->reduce(static function (self $carry, array $values, LocalDateTimeInterval $timeRange): self {
                [, $value] = $values;

                $carry->doAdd($timeRange, $value);

                return $carry;
            }, initial: $this);
    }

    public function hasBlanks(LocalDate|LocalDateInterval|LocalDateTimeInterval $range): bool
    {
        $timeRanges = LocalDateTimeInterval::disjointContainersOf(...column($this->doKeep($range), column: 0));
        $hasNoBlanks = 1 === count($timeRanges) && first($timeRanges)->equals($range);

        return !$hasNoBlanks;
    }

    /**
     * Alias for Timeline::zipAll($this, ...$others).
     *
     * @template U
     *
     * @param self<U> ...$others
     *
     * @return self<list<T|U|null>>
     */
    public function zip(self ...$others): self
    {
        /** @phpstan-ignore-next-line {@see https://github.com/phpstan/phpstan/issues/8777} */
        return self::zipAll($this, ...$others);
    }

    /**
     * Returns a new timeline with a new value for the given range.
     *
     * @template U
     *
     * @param U $value
     *
     * @return self<T|U>
     *
     * @throws TimelineRangeConflictException If the range overlaps an already defined range in this timeline
     */
    public function add(LocalDate|LocalDateInterval|LocalDateTimeInterval $range, mixed $value): self
    {
        $timeRange = LocalDateTimeInterval::cast($range);
        if ($timeRange->isEmpty()) {
            return $this;
        }

        // Respect immutability
        $timeline = new self($this->items);
        $timeline->doAdd($timeRange, $value);

        return $timeline;
    }

    /**
     * @return T|null
     */
    public function valueAt(LocalDateTime $timepoint): mixed
    {
        $items = $this->items;

        /** @noinspection SuspiciousLoopInspection use of the "comma operator" is a rare syntax only for `for()` */
        for (
            $start = 0, $end = count($items);
            $i = $start + (int) (($end - $start) / 2), $i < $end;
        ) {
            /**
             * @var LocalDateTimeInterval $itemTimeRange
             * @var T $value
             */
            [$itemTimeRange, $value] = $items[$i];
            if ($itemTimeRange->contains($timepoint)) {
                return $value;
            } elseif ($itemTimeRange->isBefore($timepoint)) {
                $start = $i + 1;
            } else {
                $end = $i;
            }
        }

        return null;
    }

    /**
     * Returns a timeline without any ranges outside the given range.
     *
     * If some ranges in this timeline cross the given range boundaries, they will be truncated.
     *
     * @return self<T>
     */
    public function keep(LocalDate|LocalDateInterval|LocalDateTimeInterval $range): self
    {
        return new self($this->doKeep($range));
    }

    /**
     * @return list<array{0: LocalDateTimeInterval, 1: T}>
     */
    private function doKeep(null|LocalDate|LocalDateInterval|LocalDateTimeInterval $range): array
    {
        if (null === $range) {
            return $this->items;
        }

        $timeRange = LocalDateTimeInterval::cast($range);
        if ($timeRange->isEmpty()) {
            return [];
        }

        $items = $this->items;

        $min = $timeRange->getStart();
        if (null !== $min) {
            /** @noinspection SuspiciousLoopInspection use of the "comma operator" is a rare syntax only for `for()` */
            for (
                $start = 0, $end = count($items);
                $i = $start + (int) (($end - $start) / 2), $i < $end;
            ) {
                /**
                 * @var LocalDateTimeInterval $itemTimeRange
                 */
                [$itemTimeRange] = $items[$i];
                if ($itemTimeRange->contains($min)) {
                    $items[$i][0] = $itemTimeRange->withStart($min);
                    break;
                } elseif ($itemTimeRange->isBefore($min)) {
                    $start = $i + 1;
                } else {
                    $end = $i;
                }
            }

            $items = array_slice($items, $i);
        }

        // Get the inclusive end of the range
        $max = $timeRange->getEnd()?->minusNanos(1);
        if (null !== $max) {
            /** @noinspection SuspiciousLoopInspection use of the "comma operator" is a rare syntax only for `for()` */
            for (
                $start = 0, $end = count($items);
                $i = $start + (int) (($end - $start) / 2), $i < $end;
            ) {
                /**
                 * @var LocalDateTimeInterval $itemTimeRange
                 */
                [$itemTimeRange] = $items[$i];
                if ($itemTimeRange->contains($max)) {
                    $items[$i][0] = $itemTimeRange->withEnd($timeRange->getFiniteEnd());
                    ++$i;
                    break;
                } elseif ($itemTimeRange->isBefore($max)) {
                    $start = $i + 1;
                } else {
                    $end = $i;
                }
            }

            $items = array_slice($items, 0, $i);
        }

        /** @var list<array{0: LocalDateTimeInterval, 1: T}> */
        return $items;
    }

    /**
     * Applies the given function to every ranges of value in this timeline.
     *
     * @template U
     *
     * @param callable(T, LocalDateTimeInterval): U $fn
     *
     * @return self<U>
     */
    public function map(callable $fn): self
    {
        return new self(values(map($this->items, static function (array $item) use ($fn): array {
            [$timeRange, $value] = $item;

            return [$timeRange, $fn($value, $timeRange)];
        })));
    }

    /**
     * Filters the timeline, keeping only ranges of value for which the predicate returns true.
     *
     * @param (callable(T, LocalDateTimeInterval): bool)|null $predicate
     *
     * @return self<T>
     */
    public function filter(?callable $predicate = null): self
    {
        /** @var self<T> */
        return new self(values(filter($this->items, static function (array $item) use ($predicate): bool {
            [$timeRange, $value] = $item;

            return null !== $predicate ? $predicate($value, $timeRange) : (bool) $value;
        })));
    }

    /**
     * Reduces this interval by invoking the reducer with every range of values in this timeline.
     *
     * @template U
     * @template V
     *
     * @param callable(U|V, T, LocalDateTimeInterval): V $reducer
     * @param U $initial
     *
     * @return U|V
     */
    public function reduce(callable $reducer, mixed $initial = null): mixed
    {
        return reduce($this->items, static function (mixed $carry, array $item) use ($reducer): mixed {
            /**
             * @var U|V $carry
             * @var T $value
             * @var LocalDateTimeInterval $timeRange
             */
            [$timeRange, $value] = $item;

            return $reducer($carry, $value, $timeRange);
        }, $initial);
    }

    /**
     * @return list<LocalDateTimeInterval>
     */
    public function timeRanges(null|LocalDate|LocalDateInterval|LocalDateTimeInterval $range = null): array
    {
        return column($this->doKeep($range), column: 0);
    }

    /**
     * @return list<T>
     */
    public function values(null|LocalDate|LocalDateInterval|LocalDateTimeInterval $range = null): array
    {
        return column($this->doKeep($range), column: 1);
    }

    /**
     * Simplifies the timeline such that there is no two item with meeting ranges and equal values,
     * merging adjacent items if necessary. How equality is compared can be customized by passing a callable.
     *
     * @param (callable(T, T): bool)|null $equals
     *
     * @return self<T>
     */
    public function simplify(?callable $equals = null): self
    {
        /** @var list<array{0: LocalDateTimeInterval, 1: T}> $values */
        $values = $this->reduce(
            static function (array $items, mixed $value, LocalDateTimeInterval $timeRange) use ($equals): array {
                /** @var array{0: ?LocalDateTimeInterval, 1: T} $lastItem */
                $lastItem = last($items);
                [$lastRange, $lastValue] = $lastItem;

                if (null !== $lastRange && $lastRange->meets($timeRange) &&
                    (null === $equals ? equals($value, $lastValue) : $equals($value, $lastValue))
                ) {
                    array_splice($items, -1, 1, [[
                        LocalDateTimeInterval::between($lastRange->getStart(), $timeRange->getEnd()),
                        $value,
                    ]]);
                } else {
                    $items[] = [$timeRange, $value];
                }

                return $items;
            },
            initial: [],
        );

        /** @var self<T> */
        return new self($values);
    }

    /**
     * Returns a iterator over every ranges of values in this timeline.
     * Keys are the time ranges of each item.
     *
     * Beware: as the time range object is not a PHP array-key, calling `sarray($timeline)` will result in a TypeError.
     *
     * @return Traversable<LocalDateTimeInterval, T>
     */
    public function getIterator(): Traversable
    {
        /**
         * @var LocalDateTimeInterval $timeRange
         * @var T $value
         */
        foreach ($this->items as [$timeRange, $value]) {
            yield $timeRange => $value;
        }
    }

    public function count(): int
    {
        return count($this->items);
    }

    /**
     * @template K
     * @template U
     * @template V
     *
     * @param iterable<U> $values
     * @param (callable(U, K): (LocalDate|LocalDateInterval|LocalDateTimeInterval)|callable(U, K): iterable<LocalDate|LocalDateInterval|LocalDateTimeInterval>|callable(U, K): V|callable(U, K): iterable<LocalDate|LocalDateInterval|LocalDateTimeInterval, V>)|null $callable
     *
     * @return iterable<LocalDate|LocalDateInterval|LocalDateTimeInterval, array{0: mixed, 1: mixed}>
     */
    private static function assembleValues(iterable $values, ?callable $callable): iterable
    {
        foreach ($values as $valueKey => $value) {
            if (null === $callable) {
                /** @var LocalDate|LocalDateInterval|LocalDateTimeInterval $value */
                yield $value => [$value, $value];
                continue;
            }

            /**
             * @var LocalDate|LocalDateInterval|LocalDateTimeInterval|iterable<LocalDate|LocalDateInterval|LocalDateTimeInterval>|iterable<LocalDate|LocalDateInterval|LocalDateTimeInterval, V> $result
             */
            $result = $callable($value, $valueKey);

            foreach (is_array($result) || $result instanceof Generator ? $result : [$result] as $timeRange => $newValue) {
                // The user returned an array of ranges or yielded only ranges, we need to reverse the arguments
                if (!is_object($timeRange) && is_object($newValue)) {
                    $timeRange = $newValue;
                    $newValue = $value;
                }

                /** @var LocalDate|LocalDateInterval|LocalDateTimeInterval $timeRange */
                yield $timeRange => [$newValue, $value];
            }
        }
    }

    /**
     * @return T|null
     */
    public function soleValueIn(LocalDate|LocalDateInterval|LocalDateTimeInterval $range): mixed
    {
        $items = $this->doKeep($range);
        if ([] === $items) {
            return null;
        }
        if (1 !== count($items)) {
            throw new UnexpectedValueException('Found more than one value in this time range.');
        }

        [$timeRange, $value] = first($items);
        if (!$timeRange->equals($range)) {
            throw new UnexpectedValueException('Found one value in this time range, but it does not cover the entire range.');
        }

        return $value;
    }

    /**
     * This method improves performances of imports by allowing to add an item to the timeline in a mutable way.
     *
     * @template U
     *
     * @param U $value
     *
     * @phpstan-self-out self<T|U>
     *
     * @throws TimelineRangeConflictException If the range overlaps an already defined range in this timeline
     */
    private function doAdd(LocalDateTimeInterval $timeRange, mixed $value): void
    {
        // A timeline does not support storing elements with an empty time range
        if ($timeRange->isEmpty()) {
            return;
        }

        /** @noinspection SuspiciousLoopInspection use of the "comma operator" is a rare syntax only for `for()` */
        for (
            $start = 0, $end = count($this->items);
            $i = $start + (int) (($end - $start) / 2), $i < $end;
        ) {
            /**
             * @var LocalDateTimeInterval $itemTimeRange
             */
            [$itemTimeRange] = $this->items[$i];
            if ($itemTimeRange->intersects($timeRange)) {
                throw new TimelineRangeConflictException($timeRange, $itemTimeRange);
            }

            if ($itemTimeRange->isBefore($timeRange)) {
                // If $items[$i] is before the range, we drop the lower half of indices
                $start = $i + 1;
            } else {
                // If $items[$i] is after the range, we instead drop the higher half of indices
                $end = $i;
            }
        }

        array_splice($this->items, $i, 0, [[$timeRange, $value]]);
    }
}
