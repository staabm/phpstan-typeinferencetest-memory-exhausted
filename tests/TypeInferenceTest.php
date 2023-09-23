<?php

declare(strict_types=1);

namespace Tests;

use PHPStan\Testing\TypeInferenceTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Finder\Finder;

final class TypeInferenceTest extends TypeInferenceTestCase
{
    /**
     * @return iterable<mixed>
     */
    public static function dataAsserts(): iterable
    {
        foreach ((new Finder())->in(__DIR__ . '/dataproviders')->files()->name('*.php') as $file) {
            yield from self::gatherAssertTypes($file->getRealPath());
        }
    }

    #[DataProvider('dataAsserts')]
    public function testAsserts(string $assertType, string $file, mixed ...$args): void
    {
        $this->assertFileAsserts($assertType, $file, ...$args);
    }

    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/../phpstan-extensions.neon'];
    }
}
