<?php

declare(strict_types=1);

namespace App\Dependencies;

use InvalidArgumentException;
use JsonSerializable;

/**
 * A percentage in the form of xxx.yy% (like 50.12% or 100.0%). Precision is not limited.
 */
final class Percentage implements JsonSerializable
{
    private const ZERO = 0.0;
    private const TOTAL = 100.0;

    private function __construct(
        private float $percentage,
    ) {
    }

    public static function zero(): self
    {
        return new self(self::ZERO);
    }

    public static function total(): self
    {
        return new self(self::TOTAL);
    }

    public static function cast(mixed $value): self
    {
        return match (true) {
            $value instanceof self => $value,
            is_numeric($value) => new self((float)$value),
            default => throw new InvalidArgumentException('Expected a numeric'),
        };
    }

    public static function of(float $percentage): self
    {
        return new self($percentage);
    }

    public static function ofFactor(float $factor): self
    {
        return new self($factor * self::TOTAL);
    }

    public static function ofRatio(float $a, float $b): self
    {
        return new self($a / $b * self::TOTAL);
    }

    public function multiplyBy(int|float|self $other): self
    {
        return new self($this->percentage * match (true) {
                $other instanceof self => $other->factor(),
                default => $other,
            });
    }

    public function minus(self $other): self
    {
        return new self($this->percentage - $other->percentage);
    }

    public function plus(self $other): self
    {
        return new self($this->percentage + $other->percentage);
    }

    public function absolute(): self
    {
        return new self(abs($this->percentage));
    }

    public function equals(?self $other): bool
    {
        return $this->percentage === $other?->percentage;
    }

    public function compareTo(self $other): int
    {
        return $this->percentage <=> $other->percentage;
    }

    public function isZero(): bool
    {
        return self::ZERO === $this->percentage;
    }

    public function isPositive(): bool
    {
        return 0 < $this->percentage;
    }

    public function isPositiveOrZero(): bool
    {
        return 0 <= $this->percentage;
    }

    public function isNegative(): bool
    {
        return 0 > $this->percentage;
    }

    public function isNegativeOrZero(): bool
    {
        return 0 >= $this->percentage;
    }

    public function isTotal(): bool
    {
        return self::TOTAL === $this->percentage;
    }

    public function value(): float
    {
        return $this->percentage;
    }

    public function factor(): float
    {
        return $this->percentage / self::TOTAL;
    }

    public function jsonSerialize(): float
    {
        return $this->percentage;
    }
}
