<?php

declare(strict_types=1);

namespace App\Extensions;

use PHPStan\Reflection\FunctionReflection;
use PHPStan\Type\Php\ArrayFlipFunctionReturnTypeExtension;

final class FunctionalFlipDynamicReturnTypeExtension extends ArrayFlipFunctionReturnTypeExtension
{
    public function isFunctionSupported(FunctionReflection $functionReflection): bool
    {
        return 'functional\flip' === strtolower($functionReflection->getName());
    }
}
