<?php

/** @noinspection PhpExpressionResultUnusedInspection */
declare(strict_types=1);

namespace Tests\dataproviders;

use App\Dependencies\Percentage;
use App\Dependencies\Snowflake;
use App\Dependencies\Timeline;
use Brick\DateTime\LocalDateTime;
use function App\Functional\reduce;
use function PHPStan\Testing\assertType;

/**
 * @var callable(): void $doNothing
 */
assertType('null', reduce([], $doNothing));
assertType('array{}', reduce([], $doNothing, initial: []));
assertType("''", reduce([], static fn (string $carry, string $value): string => $carry . $value, initial: ''));

// Without keys
assertType('int', reduce([1, 2], static fn (int $carry, int $value): int => $carry + $value, initial: 0));
assertType('bool', reduce([false, true, false], static fn (bool $carry, bool $value): bool => $carry ?: $value, initial: false));

// With keys
assertType('int', reduce([1 => '1', 2 => '2'], static fn (int $carry, string $value, int $key): int => $carry + $key, initial: 0));
assertType('string', reduce(['test1' => 'test 1', 'test2' => 'test 2'], static fn (string $carry, string $value, string $key): string => $carry . $key, initial: ''));

/**
 * @var array{
 *     id: int|numeric-string,
 *     snowflake: Snowflake,
 *     name: string,
 *     work: Timeline<Percentage>,
 *     salary: callable(Percentage): Percentage
 * }[] $items
 */
assertType('Gammadia\Common\Math\Percentage|null', reduce(
    $items,
    static fn (?Percentage $carry, array $item): ?Percentage
        => $carry?->plus($item['salary']($item['work']->valueAt(LocalDateTime::parse('2022-04-25T00:00')) ?? Percentage::zero())),
));
assertType('Gammadia\Common\Math\Percentage', reduce(
    $items,
    static fn (Percentage $carry, array $item): Percentage
        => $carry->plus($item['salary']($item['work']->valueAt(LocalDateTime::parse('2022-04-25T00:00')) ?? Percentage::zero())),
    initial: Percentage::zero(),
));
