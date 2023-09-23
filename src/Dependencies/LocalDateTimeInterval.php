<?php

declare(strict_types=1);

namespace App\Dependencies;

use Brick\DateTime\Duration;
use Brick\DateTime\Instant;
use Brick\DateTime\LocalDate;
use Brick\DateTime\LocalDateTime;
use Brick\DateTime\LocalTime;
use Brick\DateTime\Period;
use Brick\DateTime\Year;
use Brick\DateTime\YearMonth;
use Brick\DateTime\YearWeek;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
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
final class LocalDateTimeInterval implements JsonSerializable, Stringable
{
    private function __construct(
        private ?LocalDateTime $start,
        private ?LocalDateTime $end,
        bool $validateStartAfterEnd = false,
    ) {
        /**
         * Using Assert here would have a huge performance cost because of the {@see LocalDateTime::__toString()} calls.
         */
        if ($validateStartAfterEnd && null !== $start && null !== $end && $start->isAfter($end)) {
            throw new InvalidArgumentException(sprintf('Start after end: %s / %s', $start, $end));
        }
    }

    public function __toString(): string
    {
        return sprintf('%s/%s', $this->start ?? InfinityStyle::SYMBOL, $this->end ?? InfinityStyle::SYMBOL);
    }

    public function jsonSerialize(): string
    {
        return (string) $this;
    }

    /**
     * Creates a finite half-open interval between given time points (inclusive start, exclusive end).
     */
    public static function between(?LocalDateTime $start, ?LocalDateTime $end): self
    {
        return new self($start, $end, validateStartAfterEnd: true);
    }

    /**
     * Creates an empty interval at the given timepoint.
     */
    public static function empty(LocalDateTime $timepoint): self
    {
        return new self($timepoint, $timepoint);
    }

    /**
     * Creates an infinite half-open interval since given start (inclusive).
     */
    public static function since(LocalDateTime $timepoint): self
    {
        return new self($timepoint, null);
    }

    /**
     * Creates an infinite open interval until given end (exclusive).
     */
    public static function until(LocalDateTime $timepoint): self
    {
        return new self(null, $timepoint);
    }

    /**
     * Creates an infinite interval.
     */
    public static function forever(): self
    {
        return new self(null, null);
    }

    public static function day(LocalDate|LocalDateTime $input): self
    {
        $startOfDay = $input instanceof LocalDateTime
            ? $input->withTime(LocalTime::min())
            : $input->atTime(LocalTime::min());

        return new self($startOfDay, $startOfDay->plusDays(1));
    }

    /**
     * If the type of the input argument is known at call-site, usage of the following explicit methods is preferred :
     * - {@see self::day()} for LocalDate
     * - {@see self::empty()} for LocalDateTime
     *
     * @return ($temporal is null ? null : self)
     */
    public static function cast(null|string|self|LocalDate|LocalDateTime|LocalDateInterval|YearWeek|YearMonth|Year $temporal): ?self
    {
        if (null === $temporal) {
            return null;
        }
        if (is_string($temporal)) {
            return self::parse($temporal);
        }
        if ($temporal instanceof self) {
            return $temporal;
        }

        if ($temporal instanceof LocalDateInterval) {
            // Not sure why but the following line is required even though the cache is initialized after LocalDateInterval's class definition
            /** @phpstan-ignore-next-line https://github.com/phpstan/phpstan/issues/8390 */
            LocalDateInterval::$cache ??= new WeakMap();

            /** @var self */
            return LocalDateInterval::$cache[$temporal] ??= new self(
                start: $temporal->getStart()?->atTime(LocalTime::min()),
                end: $temporal->getEnd()?->atTime(LocalTime::min())->plusDays(1),
            );
        }

        return match (true) {
            $temporal instanceof LocalDate => self::day($temporal),
            $temporal instanceof LocalDateTime => self::empty($temporal),
            $temporal instanceof YearWeek => new self(
                start: $temporal->getFirstDay()->atTime(LocalTime::min()),
                end: $temporal->getLastDay()->atTime(LocalTime::min())->plusDays(1),
            ),
            $temporal instanceof YearMonth => new self(
                start: $temporal->getFirstDay()->atTime(LocalTime::min()),
                end: $temporal->getLastDay()->atTime(LocalTime::min())->plusDays(1),
            ),
            // This matches type Year, which is the only remaining one at this point
            default => new self(
                start: $temporal->atMonth(1)->getFirstDay()->atTime(LocalTime::min()),
                end: $temporal->atMonth(12)->getLastDay()->atTime(LocalTime::min())->plusDays(1),
            ),
        };
    }

    /**
     * Creates an interval that contains (encompasses) every provided intervals
     *
     * Returns new timestamp interval or null if the input is empty
     */
    public static function containerOf(null|self|LocalDate|LocalDateTime|LocalDateInterval|YearWeek|YearMonth|Year ...$temporals): ?self
    {
        $starts = $ends = [];
        foreach ($temporals as $temporal) {
            switch (true) {
                case null === $temporal:
                    continue 2;
                case $temporal instanceof LocalDate:
                    $start = $temporal->atTime(LocalTime::min());
                    $starts[] = $start;
                    $ends[] = $start->plusDays(1);
                    break;
                case $temporal instanceof LocalDateTime:
                    $starts[] = $temporal;
                    $ends[] = $temporal;
                    break;
                case $temporal instanceof LocalDateInterval:
                    $starts[] = $temporal->getStart()?->atTime(LocalTime::min());
                    $ends[] = $temporal->getEnd()?->atTime(LocalTime::min())->plusDays(1);
                    break;
                case $temporal instanceof YearWeek:
                    $starts[] = $temporal->getFirstDay()->atTime(LocalTime::min());
                    $ends[] = $temporal->getLastDay()->atTime(LocalTime::min())->plusDays(1);
                    break;
                case $temporal instanceof YearMonth:
                    $starts[] = $temporal->getFirstDay()->atTime(LocalTime::min());
                    $ends[] = $temporal->getLastDay()->atTime(LocalTime::min())->plusDays(1);
                    break;
                case $temporal instanceof Year:
                    $starts[] = $temporal->atMonth(1)->getFirstDay()->atTime(LocalTime::min());
                    $ends[] = $temporal->atMonth(12)->getLastDay()->atTime(LocalTime::min())->plusDays(1);
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
                start: contains($starts, value: null) ? null : LocalDateTime::minOf(...$starts),
                end: contains($ends, value: null) ? null : LocalDateTime::maxOf(...$ends),
            ),
        };
    }

    /**
     * @todo Remove once a PHPStan extension has been written and containerOf() does not need Asserts anymore
     */
    public static function unsafeContainerOf(null|self|LocalDate|LocalDateTime|LocalDateInterval|YearWeek|YearMonth|Year ...$temporals): self
    {
        $container = self::containerOf(...$temporals);
        Assert::notNull($container, sprintf('You cannot give an empty array to %s.', __METHOD__));

        return $container;
    }

    /**
     * @return list<self>
     */
    public static function disjointContainersOf(null|self|LocalDate|LocalDateTime|LocalDateInterval|YearWeek|YearMonth|Year ...$temporals): array
    {
        $temporals = filter($temporals);
        if ([] === $temporals) {
            return [];
        }

        $timeRanges = sort(
            map($temporals, static fn (self|LocalDate|LocalDateTime|LocalDateInterval|YearWeek|YearMonth|Year $temporal): self
                => self::unsafeContainerOf($temporal),
            ),
            static fn (self $a, self $b): int => $a->compareTo($b),
        );

        /** @var array{start: LocalDateTime|null, end: LocalDateTime|null}|null $nextContainer */
        $nextContainer = null;
        $containers = [];
        foreach ($timeRanges as $timeRange) {
            if (null === $nextContainer) {
                $nextContainer = ['start' => $timeRange->start, 'end' => $timeRange->end];
            } elseif (null !== $nextContainer['end']
                && null !== $timeRange->start
                && $nextContainer['end']->isBefore($timeRange->start)
            ) {
                $containers[] = new self($nextContainer['start'], $nextContainer['end']);
                $nextContainer = ['start' => $timeRange->start, 'end' => $timeRange->end];
            } elseif (null === $nextContainer['end'] || null === $timeRange->end) {
                $containers[] = new self($nextContainer['start'], null);

                return $containers;
            } elseif ($timeRange->end->isAfter($nextContainer['end'])) {
                $nextContainer['end'] = $timeRange->end;
            }
        }

        Assert::notNull($nextContainer);

        $containers[] = new self($nextContainer['start'], $nextContainer['end']);

        return $containers;
    }

    /**
     * Converts this instance to a timestamp interval with
     * dates from midnight to midnight.
     */
    public function toFullDays(): self
    {
        return new self(
            start: $this->start?->withTime(LocalTime::min()),
            end: null === $this->end ? null : (!$this->isEmpty() && $this->end->getTime()->isEqualTo(LocalTime::min())
                ? $this->end
                : $this->end->plusDays(1)->withTime(LocalTime::min())
            ),
        );
    }

    public function isFullDays(): bool
    {
        return $this->equals($this->toFullDays());
    }

    /**
     * Returns the nullable start time point.
     */
    public function getStart(): ?LocalDateTime
    {
        return $this->start;
    }

    /**
     * Returns the nullable end time point.
     */
    public function getEnd(): ?LocalDateTime
    {
        return $this->end;
    }

    /**
     * Yields the start time point if not null.
     */
    public function getFiniteStart(): LocalDateTime
    {
        return $this->start ?? throw new RuntimeException(sprintf('The interval "%s" does not have a finite start.', $this));
    }

    /**
     * Yields the end time point if not null.
     */
    public function getFiniteEnd(): LocalDateTime
    {
        return $this->end ?? throw new RuntimeException(sprintf('The interval "%s" does not have a finite end.', $this));
    }

    /**
     * Yields a copy of this interval with given start time.
     */
    public function withStart(?LocalDateTime $timepoint): self
    {
        return new self($timepoint, $this->end, validateStartAfterEnd: true);
    }

    /**
     * Yields a copy of this interval with given end time.
     */
    public function withEnd(?LocalDateTime $timepoint): self
    {
        return new self($this->start, $timepoint, validateStartAfterEnd: true);
    }

    /**
     * Interpretes given ISO-conforming text as interval.
     *
     * @return ($text is null ? null : self)
     */
    public static function parse(?string $text): ?self
    {
        if (null === $text) {
            return null;
        }
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
            $ldt1 = null;
        } elseif ($startsWithPeriod) {
            $ldt2 = LocalDateTime::parse($endStr);
            $ldt1 = str_contains($startStr, 'T')
                ? $ldt2->minusDuration(Duration::parse($startStr))
                : $ldt2->minusPeriod(Period::parse($startStr));

            return new self($ldt1, $ldt2, validateStartAfterEnd: true);
        } else {
            $ldt1 = LocalDateTime::parse($startStr);
        }

        // END
        if ($endsWithInfinity) {
            $ldt2 = null;
        } elseif ($endsWithPeriod) {
            if (null === $ldt1) {
                throw new RuntimeException('Cannot process end period without start.');
            }
            $ldt2 = str_contains($endStr, 'T')
                ? $ldt1->plusDuration(Duration::parse($endStr))
                : $ldt1->plusPeriod(Period::parse($endStr));
        } else {
            $ldt2 = LocalDateTime::parse($endStr);
        }

        return new self($ldt1, $ldt2, validateStartAfterEnd: true);
    }

    /**
     * Moves this interval along the POSIX-axis by the given duration or period.
     */
    public function move(Duration|Period $input): self
    {
        return $input instanceof Period
            ? new self($this->start?->plusPeriod($input), $this->end?->plusPeriod($input))
            : new self($this->start?->plusDuration($input), $this->end?->plusDuration($input));
    }

    /**
     * Return the duration of this interval.
     */
    public function getDuration(): Duration
    {
        if (null === $this->start || null === $this->end) {
            throw new RuntimeException('Returning the duration with infinite boundary is not possible.');
        }

        return Duration::between(
            startInclusive: $this->getUTCInstant($this->start),
            endExclusive: $this->getUTCInstant($this->end),
        );
    }

    /**
     * Iterates through every moments which are the result of adding the given duration or period
     * to the start until the end of this interval is reached.
     *
     * @return Traversable<LocalDateTime>
     */
    public function iterate(Duration|Period $input): Traversable
    {
        if (null === $this->start || null === $this->end) {
            throw new RuntimeException('Iterate is not supported for infinite intervals.');
        }

        for (
            $start = $this->start;
            $start->isBefore($this->end);
        ) {
            yield $start;

            $start = $input instanceof Period
                ? $start->plusPeriod($input)
                : $start->plusDuration($input);
        }
    }

    /**
     * Returns slices of this interval.
     *
     * Each slice is at most as long as the given period or duration. The last slice might be shorter.
     *
     * @return Traversable<self>
     */
    public function slice(Duration|Period $input): Traversable
    {
        foreach ($this->iterate($input) as $start) {
            $end = $input instanceof Period
                ? $start->plusPeriod($input)
                : $start->plusDuration($input);

            $end = null !== $this->end
                ? LocalDateTime::minOf($end, $this->end)
                : $end;

            yield new self($start, $end);
        }
    }

    /**
     * Determines if this interval is empty. An interval is empty when the "end" is equal to the "start" boundary.
     */
    public function isEmpty(): bool
    {
        return null !== $this->start
            && null !== $this->end
            && $this->start->isEqualTo($this->end);
    }

    /**
     * ALLEN-relation: Does this interval precede the other one such that
     * there is a gap between?
     */
    public function precedes(self|LocalDate|LocalDateTime|LocalDateInterval|YearWeek|YearMonth|Year $temporal): bool
    {
        $other = self::cast($temporal);

        return null !== $this->end && null !== $other->start && $this->end->isBefore($other->start);
    }

    /**
     * ALLEN-relation: Equivalent to $other->precedes($this).
     */
    public function precededBy(self|LocalDate|LocalDateTime|LocalDateInterval|YearWeek|YearMonth|Year $temporal): bool
    {
        return self::cast($temporal)->precedes($this);
    }

    /**
     * ALLEN-relation: Does this interval precede the other one such that
     * there is no gap between?
     */
    public function meets(self|LocalDate|LocalDateTime|LocalDateInterval|YearWeek|YearMonth|Year $temporal): bool
    {
        $other = self::cast($temporal);

        return null !== $this->end && null !== $other->start && $this->end->isEqualTo($other->start)
            && (null === $this->start || $this->start->isBefore($other->start));
    }

    /**
     * ALLEN-relation: Equivalent to $other->meets($this).
     */
    public function metBy(self|LocalDate|LocalDateTime|LocalDateInterval|YearWeek|YearMonth|Year $temporal): bool
    {
        return self::cast($temporal)->meets($this);
    }

    /**
     * ALLEN-relation: Does this interval finish the other one such that
     * both end time points are equal and the start of this interval is after
     * the start of the other one?
     */
    public function finishes(self|LocalDate|LocalDateTime|LocalDateInterval|YearWeek|YearMonth|Year $temporal): bool
    {
        $other = self::cast($temporal);

        return null !== $this->start && (null === $other->start || $this->start->isAfter($other->start))
            && (null === $this->end
                ? null === $other->end
                : null !== $other->end && $this->end->isEqualTo($other->end) && !$this->start->isEqualTo($this->end)
            );
    }

    /**
     * ALLEN-relation: Equivalent to $other->finishes($this).
     */
    public function finishedBy(self|LocalDate|LocalDateTime|LocalDateInterval|YearWeek|YearMonth|Year $temporal): bool
    {
        return self::cast($temporal)->finishes($this);
    }

    /**
     * ALLEN-relation: Does this interval start the other one such that both
     * start time points are equal and the end of this interval is before the
     * end of the other one?
     */
    public function starts(self|LocalDate|LocalDateTime|LocalDateInterval|YearWeek|YearMonth|Year $temporal): bool
    {
        $other = self::cast($temporal);

        return null !== $this->end && (null === $other->end || $this->end->isBefore($other->end))
            && (null === $this->start
                ? null === $other->start
                : null !== $other->start && $this->start->isEqualTo($other->start)
            );
    }

    /**
     * ALLEN-relation: Equivalent to $other->starts($this).
     */
    public function startedBy(self|LocalDate|LocalDateTime|LocalDateInterval|YearWeek|YearMonth|Year $temporal): bool
    {
        return self::cast($temporal)->starts($this);
    }

    /**
     * ALLEN-relation ("contains"): Does this interval enclose the other one such that
     * this start is before the start of the other one and this end is after
     * the end of the other one?
     */
    public function encloses(self|LocalDate|LocalDateTime|LocalDateInterval|YearWeek|YearMonth|Year $temporal): bool
    {
        $other = self::cast($temporal);

        return null !== $other->start && (null === $this->start || $this->start->isBefore($other->start))
            && null !== $other->end && (null === $this->end || $this->end->isAfter($other->end));
    }

    /**
     * ALLEN-relation ("during"): Equivalent to $other->encloses($this).
     */
    public function enclosedBy(self|LocalDate|LocalDateTime|LocalDateInterval|YearWeek|YearMonth|Year $temporal): bool
    {
        return self::cast($temporal)->encloses($this);
    }

    /**
     * ALLEN-relation: Does this interval overlaps the other one such that
     * the start of this interval is before the start of the other one and
     * the end of this interval is after the start of the other one but still
     * before the end of the other one?
     */
    public function overlaps(self|LocalDate|LocalDateTime|LocalDateInterval|YearWeek|YearMonth|Year $temporal): bool
    {
        $other = self::cast($temporal);

        return null !== $other->start && (null === $this->start || $this->start->isBefore($other->start))
            && null !== $this->end && (null === $other->end || $this->end->isBefore($other->end))
            && $this->end->isAfter($other->start);
    }

    /**
     * ALLEN-relation: Equivalent to $other->overlaps($this).
     */
    public function overlappedBy(self|LocalDate|LocalDateTime|LocalDateInterval|YearWeek|YearMonth|Year $temporal): bool
    {
        return self::cast($temporal)->overlaps($this);
    }

    /**
     * ALLEN-relation: Find out which Allen relation applies
     *
     * @return Relation::*
     */
    public function relationWith(self|LocalDate|LocalDateTime|LocalDateInterval|YearWeek|YearMonth|Year $temporal): int
    {
        $other = self::cast($temporal);

        return match (null === $this->start ? (null === $other->start ? 0 : -1) : (null === $other->start ? 1 : $this->start->compareTo($other->start))) {
            -1 => match (null === $this->end || null === $other->start ? 1 : $this->end->compareTo($other->start)) {
                -1 => Relation::PRECEDES,
                0 => Relation::MEETS,
                1 => match (null === $this->end ? (null === $other->end ? 0 : 1) : (null === $other->end ? -1 : $this->end->compareTo($other->end))) {
                    -1 => Relation::OVERLAPS,
                    0 => Relation::FINISHED_BY,
                    1 => Relation::ENCLOSES,
                },
            },
            0 => match (null === $this->end ? (null === $other->end ? 0 : 1) : (null === $other->end ? -1 : $this->end->compareTo($other->end))) {
                -1 => Relation::STARTS,
                0 => Relation::EQUALS,
                1 => Relation::STARTED_BY,
            },
            1 => match (null === $this->start || null === $other->end ? -1 : $this->start->compareTo($other->end)) {
                -1 => match (null === $this->end ? (null === $other->end ? 0 : 1) : (null === $other->end ? -1 : $this->end->compareTo($other->end))) {
                    -1 => Relation::ENCLOSED_BY,
                    0 => Relation::FINISHES,
                    1 => Relation::OVERLAPPED_BY,
                },
                0 => Relation::MET_BY,
                1 => Relation::PRECEDED_BY,
            },
        };
    }

    /**
     * Is the finite end of this interval before or equal to the given interval's start?
     */
    public function isBefore(self|LocalDate|LocalDateTime|LocalDateInterval|YearWeek|YearMonth|Year $temporal): bool
    {
        if (null === $this->end) {
            return false;
        }

        return $this->precedes($temporal) || $this->meets($temporal);
    }

    /**
     * Is the finite start of this interval after or equal to the given interval's end?
     */
    public function isAfter(self|LocalDate|LocalDateTime|LocalDateInterval|YearWeek|YearMonth|Year $temporal): bool
    {
        if (null === $this->start) {
            return false;
        }

        return $this->precededBy($temporal) || $this->metBy($temporal);
    }

    /**
     * Queries whether an interval contains another interval.
     * One interval contains another if it stays within its bounds.
     * An empty interval never contains anything.
     */
    public function contains(self|LocalDate|LocalDateTime|LocalDateInterval|YearWeek|YearMonth|Year $temporal): bool
    {
        if ($this->isEmpty()) {
            return false;
        }

        return (bool) ($this->relationWith($temporal) & (Relation::ENCLOSES | Relation::EQUALS | Relation::STARTED_BY | Relation::FINISHED_BY));
    }

    /**
     * Queries whether an interval intersects another interval.
     * An interval intersects if its neither before nor after the other.
     *
     * This method is commutative (A intersects B if and only if B intersects A).
     */
    public function intersects(self|LocalDate|LocalDateTime|LocalDateInterval|YearWeek|YearMonth|Year $temporal): bool
    {
        return (bool) ($this->relationWith($temporal) & ~(Relation::PRECEDES | Relation::PRECEDED_BY | Relation::MEETS | Relation::MET_BY));
    }

    /**
     * Obtains the intersection of this interval and other one if present.
     *
     * Returns a wrapper around the found intersection (which can be empty) or null.
     */
    public function findIntersection(null|self|LocalDate|LocalDateTime|LocalDateInterval|YearWeek|YearMonth|Year $temporal): ?self
    {
        if (null === $temporal) {
            return null;
        }

        if (!$this->intersects($temporal)) {
            return null;
        }

        $other = self::cast($temporal);
        if (null === $this->start && null === $other->start) {
            $start = null;
        } elseif (null === $this->start) {
            $start = $other->start;
        } elseif (null === $other->start) {
            $start = $this->start;
        } else {
            $start = LocalDateTime::maxOf($this->start, $other->start);
        }

        if (null === $this->end && null === $other->end) {
            $end = null;
        } elseif (null === $this->end) {
            $end = $other->end;
        } elseif (null === $other->end) {
            $end = $this->end;
        } else {
            $end = LocalDateTime::minOf($this->end, $other->end);
        }

        return new self($start, $end);
    }

    /**
     * @return list<self>
     */
    public function subtract(null|self|LocalDate|LocalDateTime|LocalDateInterval|YearWeek|YearMonth|Year $temporal): array
    {
        if (null === $temporal) {
            return [$this];
        }

        $temporal = self::cast($temporal);

        return match ($this->relationWith($temporal)) {
            Relation::EQUALS, Relation::STARTS, Relation::FINISHES, Relation::ENCLOSED_BY
                => [],
            Relation::PRECEDES, Relation::PRECEDED_BY, Relation::MEETS, Relation::MET_BY
                => [$this],
            Relation::OVERLAPPED_BY, Relation::STARTED_BY
                => [$this->withStart($temporal->getEnd())],
            Relation::OVERLAPS, Relation::FINISHED_BY
                => [$this->withEnd($temporal->getStart())],
            Relation::ENCLOSES
                => [$this->withEnd($temporal->getStart()), $this->withStart($temporal->getEnd())],
        };
    }

    /**
     * Compares the boundaries (start and end) of this and the other interval.
     */
    public function equals(null|self|LocalDate|LocalDateTime|LocalDateInterval|YearWeek|YearMonth|Year $temporal): bool
    {
        if (null === $temporal) {
            return false;
        }

        $other = self::cast($temporal);

        return (null === $this->start ? null === $other->start : (null !== $other->start && $this->start->isEqualTo($other->start)))
            && (null === $this->end ? null === $other->end : (null !== $other->end && $this->end->isEqualTo($other->end)));
    }

    /**
     * @return -1|0|1
     */
    public function compareTo(self|LocalDate|LocalDateTime|LocalDateInterval|YearWeek|YearMonth|Year $temporal): int
    {
        $other = self::cast($temporal);

        return (null === $this->start ? (null === $other->start ? 0 : -1) : (null === $other->start ? 1 : $this->start->compareTo($other->start)))
            ?: (null === $this->end ? (null === $other->end ? 0 : 1) : (null === $other->end ? -1 : $this->end->compareTo($other->end)));
    }

    /**
     * Determines if this interval has finite boundaries.
     */
    public function isFinite(): bool
    {
        return null !== $this->start && null !== $this->end;
    }

    /**
     * Determines if this interval has infinite start boundary.
     */
    public function hasInfiniteStart(): bool
    {
        return null === $this->start;
    }

    /**
     * Determines if this interval has infinite end boundary.
     */
    public function hasInfiniteEnd(): bool
    {
        return null === $this->end;
    }

    private function getUTCInstant(LocalDateTime $timepoint): Instant
    {
        static $utc;
        $utc ??= new DateTimeZone('UTC');

        return Instant::of(
            epochSecond: (new DateTimeImmutable((string) $timepoint->withNano(0), $utc))->getTimestamp(),
            nanoAdjustment: $timepoint->getNano(),
        );
    }
}
