<?php

/** @noinspection PhpExpressionResultUnusedInspection */
declare(strict_types=1);

namespace Tests\dataproviders;

use function App\Functional\contains;
use function PHPStan\Testing\assertType;

function doFoo7() {

    /**
     * @var array<int, string|null> $nullableList
     */
    if (!contains($nullableList, value: null)) {
        assertType('array<int, string>', $nullableList);
    }

}
