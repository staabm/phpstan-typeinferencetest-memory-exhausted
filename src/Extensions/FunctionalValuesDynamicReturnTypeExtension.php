<?php

declare(strict_types=1);

namespace App\Extensions;

use PHPStan\Reflection\FunctionReflection;
use PHPStan\Type\Php\ArrayValuesFunctionDynamicReturnTypeExtension;

final class FunctionalValuesDynamicReturnTypeExtension extends ArrayValuesFunctionDynamicReturnTypeExtension
{
    public function isFunctionSupported(FunctionReflection $functionReflection): bool
    {
        return 'functional\values' === strtolower($functionReflection->getName());
    }
}
