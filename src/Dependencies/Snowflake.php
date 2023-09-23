<?php

declare(strict_types=1);

namespace App\Dependencies;

use BadMethodCallException;
use JsonSerializable;
use Serializable;
use Stringable;

final class Snowflake implements Serializable, JsonSerializable, Stringable
{
    public const EPOCH = 1_546_300_800_000 /* 1 January 2019 */;

    private static ?RandomSnowflakeFactory $factory = null;

    private function __construct(
        private int $value,
    ) {
    }

    /**
     * @return numeric-string
     */
    public function __toString(): string
    {
        return $this->toString();
    }

    public static function create(): self
    {
        return self::getFactory()->generate();
    }

    public static function cast(mixed $snowflake): self
    {
        if ($snowflake instanceof self) {
            return $snowflake;
        }

        if (!(is_numeric($snowflake) && (int)$snowflake == $snowflake && (int)$snowflake >= 0)) {
            throw new InvalidSnowflakeException($snowflake);
        }

        return new self((int)$snowflake);
    }

    public function value(): int
    {
        return $this->value;
    }

    public function timestamp(): int
    {
        return (int)floor((($this->value >> 22) + self::EPOCH) / 1000);
    }

    public function equals(?self $other): bool
    {
        return $this->value === $other?->value;
    }

    public function compareTo(self $other): int
    {
        return $this->value <=> $other->value;
    }

    /**
     * @return numeric-string
     */
    public function toString(): string
    {
        return (string)$this->value;
    }

    public function serialize(): string
    {
        throw new BadMethodCallException('This method is only here to please the serializable interface.');
    }

    /**
     * @return array{0: int}
     */
    public function __serialize(): array
    {
        return [$this->value];
    }

    /**
     * Removing this method can mess with the snowflakes already serialized in a cache somewhere in the infra.
     * So we need to check everywhere before deleting it.
     *
     * @param numeric-string $data
     */
    public function unserialize(string $data): void
    {
        $this->value = (int)$data;
    }

    /**
     * @param array{0: int} $data
     */
    public function __unserialize(array $data): void
    {
        [$this->value] = $data;
    }

    /**
     * @return numeric-string
     */
    public function jsonSerialize(): string
    {
        return $this->toString();
    }

    private static function getFactory(): RandomSnowflakeFactory
    {
        if (null === self::$factory) {
            self::$factory = new RandomSnowflakeFactory();
        }

        return self::$factory;
    }
}
