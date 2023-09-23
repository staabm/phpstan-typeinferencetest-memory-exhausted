<?php

/** @noinspection PhpExpressionResultUnusedInspection */
declare(strict_types=1);

namespace Tests\dataproviders;

use App\Dependencies\Percentage;
use App\Dependencies\Timeline;
use function App\Functional\combine;
use function PHPStan\Testing\assertType;

/**
 * @var list<string> $keys
 * @var list<Timeline<Percentage>> $values
 */
assertType('array<string, Gammadia\Collections\Timeline\Timeline<Gammadia\Common\Math\Percentage>>', combine($keys, $values));

/**
 * If the two arrays have different lengths, it throws an Exception, which means it never returns
 *
 * @var array{0: string, 1: string, 2: string} $keys
 * @var array{0: Timeline<Percentage>} $values
 */
assertType('*NEVER*', combine($keys, $values));
