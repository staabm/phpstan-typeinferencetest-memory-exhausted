<?php

/** @noinspection PhpExpressionResultUnusedInspection */
declare(strict_types=1);

namespace Tests\dataproviders;

use App\Dependencies\Percentage;
use App\Dependencies\Snowflake;
use App\Dependencies\Timeline;
use Brick\DateTime\LocalDate;
use function App\Functional\map;
use function PHPStan\Testing\assertType;

/**
 * @var callable(): void $doNothing
 * @var list<int> $list
 * @var array{
 *     id: int|numeric-string,
 *     snowflake: Snowflake,
 *     name: string,
 *     work: Timeline<Percentage>,
 *     salary: callable(float): string
 * }[] $items
 */
assertType('array{}', map([], $doNothing));
assertType('array{int, int}', map([1, 2], static fn (int $i): int => $i * 2));
assertType('list<int>', map($list, static fn (int $i): int => $i * 2));
assertType('list<void>', map($list, $doNothing));
assertType(
    'array{test1: string, test2: string}',
    map(['test1' => 'test 1', 'test2' => 'test 2'], static fn (string $value, string $key): string => $key),
);
assertType(
    'array<non-falsy-string>',
    map($items, static fn (array $item): string => sprintf('#%s %s', $item['id'], $item['name'])),
);

/** @todo Here map() loses the subtype, while array_map() does not! It would be really nice to find a way to fix this */
assertType(
    /* 'array<Gammadia\Collections\Timeline\Timeline<Gammadia\Common\Math\Percentage>>' */
    'array<Gammadia\Collections\Timeline\Timeline>',
    map($items, static fn (array $item): Timeline => $item['work']->keep(LocalDate::parse('2022-04-25'))),
);
assertType(
    'array<Gammadia\Collections\Timeline\Timeline<Gammadia\Common\Math\Percentage>>',
    array_map(static fn (array $item): Timeline => $item['work']->keep(LocalDate::parse('2022-04-25')), $items),
);

// Yet storing the callback in a variable changes the type ! Why ?!
$callback = static fn (array $item): Timeline => $item['work']->keep(LocalDate::parse('2022-04-25'));
assertType(
    /* 'array<Gammadia\Collections\Timeline\Timeline<Gammadia\Common\Math\Percentage>>' */
    'array<Gammadia\Collections\Timeline\Timeline>',
    array_map($callback, $items),
);
