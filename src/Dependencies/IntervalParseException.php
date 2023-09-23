<?php

declare(strict_types=1);

namespace App\Dependencies;

use Brick\DateTime\Parser\DateTimeParseException;
use Throwable;

final class IntervalParseException extends DateTimeParseException
{
    public static function notAnInterval(string $textToParse): self
    {
        return new self('Text cannot be parsed to an interval: ' . $textToParse);
    }

    public static function uniqueDuration(string $textToParse): self
    {
        return new self('Text cannot be parsed to a Duration/Duration format: ' . $textToParse);
    }

    public static function durationIncompatibleWithInfinity(string $textToParse): self
    {
        return new self('Text cannot be parsed to a Period/- or -/Duration format: ' . $textToParse);
    }

    public static function localTimeInterval(string $textToParse, ?Throwable $throwable = null): self
    {
        return new self('Text cannot be parsed to a LocalTime/Duration format: ' . $textToParse, 0, $throwable);
    }
}
