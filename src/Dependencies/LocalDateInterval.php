<?php

declare(strict_types=1);

namespace App\Dependencies;

use Brick\DateTime\Duration;
use Brick\DateTime\LocalDate;
use Brick\DateTime\LocalDateTime;
use Brick\DateTime\Period;
use Brick\DateTime\Year;
use Brick\DateTime\YearMonth;
use Brick\DateTime\YearWeek;
use Countable;
use InvalidArgumentException;
use IteratorAggregate;
use JsonSerializable;
use RuntimeException;
use Stringable;
use Traversable;
use WeakMap;
use Webmozart\Assert\Assert;
use function App\Functional\contains;
use function App\Functional\filter;
use function App\Functional\map;
use function App\Functional\sort;

/**
 * @implements IteratorAggregate<int, LocalDate>
 */
final class LocalDateInterval implements IteratorAggregate, JsonSerializable, Countable, Stringable
{
    /**
     * @var WeakMap<self, LocalDateTimeInterval>
     * @internal
     *
     * This property is initialized at the end of this file (outside the class)
     */
    public static WeakMap $cache;

    private function __construct(
        private ?LocalDate $start,
        private ?LocalDate $end,
        bool $validateStartAfterEnd = false,
    ) {
        /**
         * Using Assert here would have a huge performance cost because of the {@see LocalDate::__toString()} calls.
         */
        if ($validateStartAfterEnd && null !== $start && null !== $end && $start->isAfter($end)) {
            throw new InvalidArgumentException(sprintf('Start after end: %s / %s', $start, $end));
        }
    }

    public function __toString(): string
    {
        return sprintf(
            '%s/%s',
            $this->hasInfiniteStart() ? InfinityStyle::SYMBOL : $this->start,
            $this->hasInfiniteEnd() ? InfinityStyle::SYMBOL : $this->end,
        );
    }

    public function jsonSerialize(): string
    {
        return (string) $this;
    }

    /**
     * Creates a closed interval between given dates.
     */
    public static function between(?LocalDate $start, ?LocalDate $end): self
    {
        return new self($start, $end, validateStartAfterEnd: true);
    }

    /**
     * Creates an infinite interval since given start date.
     */
    public static function since(LocalDate $date): self
    {
        return new self($date, null);
    }

    /**
     * Creates an infinite interval until given end date.
     */
    public static function until(LocalDate $date): self
    {
        return new self(null, $date);
    }

    /**
     * Creates a closed interval including only given date.
     */
    public static function day(LocalDate|LocalDateTime $input): self
    {
        $date = $input instanceof LocalDateTime ? $input->getDate() : $input;

        return new self($date, $date);
    }

    /**
     * Creates an infinite interval.
     */
    public static function forever(): self
    {
        return new self(null, null);
    }

    /**
     * Creates an interval that contains (encompasses) every provided intervals
     *
     * Returns new timestamp interval or null if the input is empty
     */
    public static function containerOf(null|self|LocalDate|LocalDateTime|LocalDateTimeInterval|YearWeek|YearMonth|Year ...$temporals): ?self
    {
        $starts = $ends = [];
        foreach ($temporals as $temporal) {
            switch (true) {
                case null === $temporal:
                    continue 2;
                case $temporal instanceof LocalDate:
                    $starts[] = $temporal;
                    $ends[] = $temporal;
                    break;
                case $temporal instanceof LocalDateTime:
                    $starts[] = $temporal->getDate();
                    $ends[] = $temporal->getDate();
                    break;
                case $temporal instanceof LocalDateTimeInterval:
                    $starts[] = $temporal->getStart()?->getDate();
                    $ends[] = $temporal->toFullDays()->getEnd()?->getDate()->minusDays(1);
                    break;
                case $temporal instanceof YearWeek:
                    $starts[] = $temporal->getFirstDay();
                    $ends[] = $temporal->getLastDay();
                    break;
                case $temporal instanceof YearMonth:
                    $starts[] = $temporal->getFirstDay();
                    $ends[] = $temporal->getLastDay();
                    break;
                case $temporal instanceof Year:
                    $starts[] = $temporal->atMonth(1)->getFirstDay();
                    $ends[] = $temporal->atMonth(12)->getLastDay();
                    break;
                default:
                    $starts[] = $temporal->start;
                    $ends[] = $temporal->end;
                    break;
            }
        }

        return match (count($starts)) {
            0 => null,
            1 => new self($starts[0], $ends[0]),
            default => new self(
                start: contains($starts, value: null) ? null : LocalDate::minOf(...$starts),
                end: contains($ends, value: null) ? null : LocalDate::maxOf(...$ends),
            ),
        };
    }

    /**
     * @todo Remove once a PHPStan extension has been written and containerOf() does not need Asserts anymore
     */
    public static function unsafeContainerOf(null|self|LocalDate|LocalDateTime|LocalDateTimeInterval|YearWeek|YearMonth|Year ...$temporals): self
    {
        $container = self::containerOf(...$temporals);
        Assert::notNull($container, sprintf('You cannot give an empty array to %s.', __METHOD__));

        return $container;
    }

    /**
     * @return list<self>
     */
    public static function disjointContainersOf(null|self|LocalDate|LocalDateTime|LocalDateTimeInterval|YearWeek|YearMonth|Year ...$temporals): array
    {
        $temporals = filter($temporals);
        if ([] === $temporals) {
            return [];
        }

        $dateRanges = sort(
            map($temporals, static fn (self|LocalDate|LocalDateTime|LocalDateTimeInterval|YearWeek|YearMonth|Year $temporal): self
                => self::unsafeContainerOf($temporal),
            ),
            static fn (self $a, self $b): int => $a->compareTo($b),
        );

        /** @var array{start: LocalDate|null, end: LocalDate|null}|null $nextContainer */
        $nextContainer = null;
        $containers = [];
        foreach ($dateRanges as $dateRange) {
            if (null === $nextContainer) {
                $nextContainer = ['start' => $dateRange->start, 'end' => $dateRange->end];
            } elseif (null !== $nextContainer['end']
                && null !== $dateRange->start
                && $nextContainer['end']->isBefore($dateRange->start->minusDays(1))
            ) {
                $containers[] = new self($nextContainer['start'], $nextContainer['end']);
                $nextContainer = ['start' => $dateRange->start, 'end' => $dateRange->end];
            } elseif (null === $nextContainer['end'] || null === $dateRange->end) {
                $containers[] = new self($nextContainer['start'], null);

                return $containers;
            } elseif ($dateRange->end->isAfter($nextContainer['end'])) {
                $nextContainer['end'] = $dateRange->end;
            }
        }

        Assert::notNull($nextContainer);

        $containers[] = new self($nextContainer['start'], $nextContainer['end']);

        return $containers;
    }

    public function toFullWeeks(): self
    {
        return new self(
            start: $this->start?->minusDays($this->start->getDayOfWeek()->getValue() - 1),
            end: $this->end?->plusDays(7 - $this->end->getDayOfWeek()->getValue()),
        );
    }

    public function getStart(): ?LocalDate
    {
        return $this->start;
    }

    public function getEnd(): ?LocalDate
    {
        return $this->end;
    }

    public function getFiniteStart(): LocalDate
    {
        if (null === $this->start) {
            throw new RuntimeException(sprintf('The interval "%s" does not have a finite start.', $this));
        }

        return $this->start;
    }

    public function getFiniteEnd(): LocalDate
    {
        if (null === $this->end) {
            throw new RuntimeException(sprintf('The interval "%s" does not have a finite end.', $this));
        }

        return $this->end;
    }

    /**
     * Yields a copy of this interval with given start time.
     */
    public function withStart(?LocalDate $start): self
    {
        return new self($start, $this->end, validateStartAfterEnd: true);
    }

    /**
     * Yields a copy of this interval with given end time.
     */
    public function withEnd(?LocalDate $end): self
    {
        return new self($this->start, $end, validateStartAfterEnd: true);
    }

    /**
     * Interpretes given ISO-conforming text as interval.
     */
    public static function parse(string $text): self
    {
        if (!str_contains($text, '/')) {
            throw IntervalParseException::notAnInterval($text);
        }

        [$startStr, $endStr] = explode('/', trim($text), 2);

        $startsWithPeriod = str_starts_with($startStr, 'P');
        $startsWithInfinity = InfinityStyle::SYMBOL === $startStr;

        $endsWithPeriod = str_starts_with($endStr, 'P');
        $endsWithInfinity = InfinityStyle::SYMBOL === $endStr;

        if ($startsWithPeriod && $endsWithPeriod) {
            throw IntervalParseException::uniqueDuration($text);
        }

        if (($startsWithPeriod && $endsWithInfinity) || ($startsWithInfinity && $endsWithPeriod)) {
            throw IntervalParseException::durationIncompatibleWithInfinity($text);
        }

        // START
        if ($startsWithInfinity) {
            $ld1 = null;
        } elseif ($startsWithPeriod) {
            $ld2 = LocalDate::parse($endStr);
            $ld1 = $ld2->minusPeriod(Period::parse($startStr));

            return new self($ld1, $ld2, validateStartAfterEnd: true);
        } else {
            $ld1 = LocalDate::parse($startStr);
        }

        // END
        if ($endsWithInfinity) {
            $ld2 = null;
        } elseif ($endsWithPeriod) {
            if (null === $ld1) {
                throw new RuntimeException('Cannot process end period without start.');
            }
            $ld2 = $ld1->plusPeriod(Period::parse($endStr));
        } else {
            $ld2 = LocalDate::parse($endStr);
        }

        return new self($ld1, $ld2, validateStartAfterEnd: true);
    }

    /**
     * Moves this interval along the time axis by given units.
     */
    public function move(Period $period): self
    {
        return new self($this->start?->plusPeriod($period), $this->end?->plusPeriod($period));
    }

    /**
     * Return the duration of this interval.
     */
    public function getDuration(): Duration
    {
        return (self::$cache[$this] ??= LocalDateTimeInterval::cast($this))->getDuration();
    }

    /**
     * Yields the length of this interval in given calendrical units.
     */
    public function getPeriod(): Period
    {
        if (null === $this->start || null === $this->end) {
            throw new RuntimeException('An infinite interval has no finite duration.');
        }

        return Period::between($this->start, $this->end->plusDays(1));
    }

    /**
     * Obtains a stream iterating over every calendar date which is the result of addition of given duration
     * to start until the end of this interval is reached.
     *
     * @return Traversable<LocalDate>
     */
    public function iterate(?Period $period = null): Traversable
    {
        if (null === $this->start || null === $this->end) {
            throw new RuntimeException('Iterate is not supported for infinite interval.');
        }

        for (
            $start = $this->start;
            $start->isBeforeOrEqualTo($this->end);
            $start = (null !== $period ? $start->plusPeriod($period) : $start->plusDays(1))
        ) {
            yield $start;
        }
    }

    /**
     * Obtains a stream iterating over every calendar date between given interval boundaries.
     *
     * @return Traversable<LocalDate>
     */
    public function getIterator(): Traversable
    {
        return $this->iterate();
    }

    /**
     * @return int<1, max>
     */
    public function count(): int
    {
        if (null === $this->start || null === $this->end) {
            throw new RuntimeException('Count is not supported for infinite interval.');
        }

        $count = $this->end->toEpochDay() - $this->start->toEpochDay() + 1;
        Assert::positiveInteger($count, 'The number of days of a date range must be 1 or more.');

        return $count;
    }

    /**
     * Returns slices of this interval.
     *
     * Each slice is at most as long as the given period or duration. The last slice might be shorter.
     *
     * @return Traversable<self>
     */
    public function slice(Period $input): Traversable
    {
        foreach ($this->iterate($input) as $start) {
            $end = $start->plusPeriod($input)->minusDays(1);

            yield new self($start, null !== $this->end ? LocalDate::minOf($end, $this->end) : $end);
        }
    }

    /**
     * Is the finite end of this interval before the given interval's start?
     */
    public function isBefore(self|LocalDate|LocalDateTime|LocalDateTimeInterval|YearWeek|YearMonth|Year $temporal): bool
    {
        return (self::$cache[$this] ??= LocalDateTimeInterval::cast($this))->isBefore($temporal);
    }

    /**
     * Is the finite start of this interval after the given interval's end?
     */
    public function isAfter(self|LocalDate|LocalDateTime|LocalDateTimeInterval|YearWeek|YearMonth|Year $temporal): bool
    {
        return (self::$cache[$this] ??= LocalDateTimeInterval::cast($this))->isAfter($temporal);
    }

    /**
     * ALLEN-relation: Does this interval precede the other one such that
     * there is a gap between?
     */
    public function precedes(self|LocalDate|LocalDateTime|LocalDateTimeInterval|YearWeek|YearMonth|Year $temporal): bool
    {
        return (self::$cache[$this] ??= LocalDateTimeInterval::cast($this))->precedes($temporal);
    }

    public function precededBy(self|LocalDate|LocalDateTime|LocalDateTimeInterval|YearWeek|YearMonth|Year $temporal): bool
    {
        return (self::$cache[$this] ??= LocalDateTimeInterval::cast($this))->precededBy($temporal);
    }

    /**
     * ALLEN-relation: Does this interval precede the other one such that
     * there is no gap between?
     */
    public function meets(self|LocalDate|LocalDateTime|LocalDateTimeInterval|YearWeek|YearMonth|Year $temporal): bool
    {
        return (self::$cache[$this] ??= LocalDateTimeInterval::cast($this))->meets($temporal);
    }

    public function metBy(self|LocalDate|LocalDateTime|LocalDateTimeInterval|YearWeek|YearMonth|Year $temporal): bool
    {
        return (self::$cache[$this] ??= LocalDateTimeInterval::cast($this))->metBy($temporal);
    }

    /**
     * ALLEN-relation: Does this interval finish the other one such that
     * both end time points are equal and the start of this interval is after
     * the start of the other one?
     */
    public function finishes(self|LocalDate|LocalDateTime|LocalDateTimeInterval|YearWeek|YearMonth|Year $temporal): bool
    {
        return (self::$cache[$this] ??= LocalDateTimeInterval::cast($this))->finishes($temporal);
    }

    public function finishedBy(self|LocalDate|LocalDateTime|LocalDateTimeInterval|YearWeek|YearMonth|Year $temporal): bool
    {
        return (self::$cache[$this] ??= LocalDateTimeInterval::cast($this))->finishedBy($temporal);
    }

    /**
     * ALLEN-relation: Does this interval start the other one such that both
     * start time points are equal and the end of this interval is before the
     * end of the other one?
     */
    public function starts(self|LocalDate|LocalDateTime|LocalDateTimeInterval|YearWeek|YearMonth|Year $temporal): bool
    {
        return (self::$cache[$this] ??= LocalDateTimeInterval::cast($this))->starts($temporal);
    }

    public function startedBy(self|LocalDate|LocalDateTime|LocalDateTimeInterval|YearWeek|YearMonth|Year $temporal): bool
    {
        return (self::$cache[$this] ??= LocalDateTimeInterval::cast($this))->startedBy($temporal);
    }

    /**
     * ALLEN-relation: Does this interval enclose the other one such that
     * this start is before the start of the other one and this end is after
     * the end of the other one?
     */
    public function encloses(self|LocalDate|LocalDateTime|LocalDateTimeInterval|YearWeek|YearMonth|Year $temporal): bool
    {
        return (self::$cache[$this] ??= LocalDateTimeInterval::cast($this))->encloses($temporal);
    }

    public function enclosedBy(self|LocalDate|LocalDateTime|LocalDateTimeInterval|YearWeek|YearMonth|Year $temporal): bool
    {
        return (self::$cache[$this] ??= LocalDateTimeInterval::cast($this))->enclosedBy($temporal);
    }

    /**
     * ALLEN-relation: Does this interval overlaps the other one such that
     * the start of this interval is still before the start of the other
     * one?
     */
    public function overlaps(self|LocalDate|LocalDateTime|LocalDateTimeInterval|YearWeek|YearMonth|Year $temporal): bool
    {
        return (self::$cache[$this] ??= LocalDateTimeInterval::cast($this))->overlaps($temporal);
    }

    public function overlappedBy(self|LocalDate|LocalDateTime|LocalDateTimeInterval|YearWeek|YearMonth|Year $temporal): bool
    {
        return (self::$cache[$this] ??= LocalDateTimeInterval::cast($this))->overlappedBy($temporal);
    }

    /**
     * Queries whether an interval contains another interval.
     * One interval contains another if it stays within its bounds.
     */
    public function contains(self|LocalDate|LocalDateTime|LocalDateTimeInterval|YearWeek|YearMonth|Year $temporal): bool
    {
        return (self::$cache[$this] ??= LocalDateTimeInterval::cast($this))->contains($temporal);
    }

    /**
     * Queries if this interval intersects the other one such that there is at least one common time point.
     */
    public function intersects(self|LocalDate|LocalDateTime|LocalDateTimeInterval|YearWeek|YearMonth|Year $temporal): bool
    {
        return (self::$cache[$this] ??= LocalDateTimeInterval::cast($this))->intersects($temporal);
    }

    /**
     * Obtains the intersection of this interval and other one if present.
     */
    public function findIntersection(null|self|LocalDate|YearWeek|YearMonth|Year $other): ?self
    {
        if (null === $other) {
            return null;
        }
        if (!$other instanceof self) {
            $other = self::unsafeContainerOf($other);
        }

        if ($this->intersects($other)) {
            if (null === $this->start || null === $other->start) {
                $start = $this->start ?? $other->start;
            } else {
                $start = LocalDate::maxOf($this->start, $other->start);
            }

            if (null === $this->end || null === $other->end) {
                $end = $this->end ?? $other->end;
            } else {
                $end = LocalDate::minOf($this->end, $other->end);
            }

            return new self($start, $end);
        }

        return null;
    }

    /**
     * Compares the boundaries (start and end) of this and the other interval.
     */
    public function equals(null|self|LocalDate|LocalDateTime|LocalDateTimeInterval|YearWeek|YearMonth|Year $temporal): bool
    {
        return (self::$cache[$this] ??= LocalDateTimeInterval::cast($this))->equals($temporal);
    }

    /**
     * @return list<self>
     */
    public function subtract(null|self|LocalDate|LocalDateTime|LocalDateTimeInterval|YearWeek|YearMonth|Year $temporal): array
    {
        if (null === $temporal) {
            return [$this];
        }

        $temporal = LocalDateTimeInterval::cast($temporal);
        $relation = (self::$cache[$this] ??= LocalDateTimeInterval::cast($this))->relationWith($temporal);

        return match ($relation) {
            Relation::EQUALS, Relation::STARTS, Relation::FINISHES, Relation::ENCLOSED_BY
                => [],
            Relation::PRECEDES, Relation::PRECEDED_BY, Relation::MEETS, Relation::MET_BY
                => [$this],
            Relation::OVERLAPPED_BY, Relation::STARTED_BY
                => [$this->withStart($temporal->getEnd()?->getDate())],
            Relation::OVERLAPS, Relation::FINISHED_BY
                => [$this->withEnd($temporal->getStart()?->getDate()->minusDays(1))],
            Relation::ENCLOSES
                => [$this->withEnd($temporal->getStart()?->getDate()->minusDays(1)), $this->withStart($temporal->getEnd()?->getDate())],
        };
    }

    public function compareTo(self|LocalDate|LocalDateTime|LocalDateTimeInterval|YearWeek|YearMonth|Year $temporal): int
    {
        return (self::$cache[$this] ??= LocalDateTimeInterval::cast($this))->compareTo($temporal);
    }

    public function isFinite(): bool
    {
        return null !== $this->start && null !== $this->end;
    }

    public function hasInfiniteStart(): bool
    {
        return null === $this->start;
    }

    public function hasInfiniteEnd(): bool
    {
        return null === $this->end;
    }
}

/** @phpstan-ignore-next-line https://github.com/phpstan/phpstan/issues/8390 */
LocalDateInterval::$cache = new WeakMap();
