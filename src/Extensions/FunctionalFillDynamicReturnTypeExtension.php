<?php

declare(strict_types=1);

namespace App\Extensions;

use PHPStan\Reflection\FunctionReflection;
use PHPStan\Type\Php\ArrayFillFunctionReturnTypeExtension;

final class FunctionalFillDynamicReturnTypeExtension extends ArrayFillFunctionReturnTypeExtension
{
    public function isFunctionSupported(FunctionReflection $functionReflection): bool
    {
        return 'functional\fill' === strtolower($functionReflection->getName());
    }
}
