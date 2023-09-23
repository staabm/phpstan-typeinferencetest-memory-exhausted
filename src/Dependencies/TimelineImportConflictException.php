<?php

declare(strict_types=1);

namespace App\Dependencies;


/**
 * @template T
 */
final class TimelineImportConflictException extends TimelineRangeConflictException
{
    /**
     * @param T $value
     * @param Timeline<T> $conflictingValuesTimeline
     */
    public function __construct(
        private LocalDateTimeInterval $timeRange,
        private mixed $value,
        private Timeline $conflictingValuesTimeline,
        TimelineRangeConflictException $previous,
    ) {
        parent::__construct($previous->conflictingTimeRange(), $previous->existingTimeRange(), $previous);
    }

    public function timeRange(): LocalDateTimeInterval
    {
        return $this->timeRange;
    }

    /**
     * @return T
     */
    public function value(): mixed
    {
        return $this->value;
    }

    /**
     * @return Timeline<T>
     */
    public function conflictingValuesTimeline(): Timeline
    {
        return $this->conflictingValuesTimeline;
    }
}
