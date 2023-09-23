<?php

/** @noinspection PhpExpressionResultUnusedInspection */
declare(strict_types=1);

namespace Tests\dataproviders;

use App\Dependencies\Percentage;
use App\Dependencies\Snowflake;
use App\Dependencies\Timeline;
use function App\Functional\column;
use function PHPStan\Testing\assertType;

function doFoo8() {
    /**
     * @var array{
     *     id: int|numeric-string,
     *     snowflake: Snowflake,
     *     name: string,
     *     work: Timeline<Percentage>,
     *     salary: callable(float): string
     * }[] $items
     */
    assertType('list<int|numeric-string>', column($items, 'id'));
    assertType('list<Gammadia\Snowflake\Snowflake>', column($items, 'snowflake'));
    assertType('list<string>', column($items, 'name'));
    assertType('list<Gammadia\Collections\Timeline\Timeline<Gammadia\Common\Math\Percentage>>', column($items, 'work'));
    assertType('list<callable(float): string>', column($items, 'salary'));

    /** @var array{string, string}[] $items */
    assertType('list<string>', column($items, 1));
}


