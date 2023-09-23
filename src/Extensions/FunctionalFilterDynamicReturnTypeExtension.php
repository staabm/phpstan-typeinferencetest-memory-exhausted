<?php

declare(strict_types=1);

namespace App\Extensions;

use PHPStan\Reflection\FunctionReflection;
use PHPStan\Type\Php\ArrayFilterFunctionReturnTypeReturnTypeExtension;

final class FunctionalFilterDynamicReturnTypeExtension extends ArrayFilterFunctionReturnTypeReturnTypeExtension
{
    public function isFunctionSupported(FunctionReflection $functionReflection): bool
    {
        return 'functional\filter' === strtolower($functionReflection->getName());
    }
}
