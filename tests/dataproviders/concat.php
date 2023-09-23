<?php

/** @noinspection PhpExpressionResultUnusedInspection */
declare(strict_types=1);

namespace Tests\dataproviders;

use App\Dependencies\Percentage;
use App\Dependencies\Timeline;
use function App\Functional\concat;
use function PHPStan\Testing\assertType;

function do6() {
    /**
     * Keys are never specified as they always are subtypes of array-key, which is array's default key type (no kidding!)
     *
     * @var Timeline<Percentage>[] $timelines
     * @var array<string, string> $arrayWithKeys
     * @var string[] $arrayWithoutKeys
     */
    assertType('array<Gammadia\Collections\Timeline\Timeline<Gammadia\Common\Math\Percentage>|string>', concat($timelines, $arrayWithKeys));
    assertType('array<Gammadia\Collections\Timeline\Timeline<Gammadia\Common\Math\Percentage>|string>', concat($timelines, $arrayWithoutKeys));

}
