<?php

declare(strict_types=1);

namespace App\Extensions;

use PHPStan\Reflection\FunctionReflection;
use PHPStan\Type\Php\ArrayKeysFunctionDynamicReturnTypeExtension;

final class FunctionalKeysDynamicReturnTypeExtension extends ArrayKeysFunctionDynamicReturnTypeExtension
{
    public function isFunctionSupported(FunctionReflection $functionReflection): bool
    {
        return 'functional\keys' === strtolower($functionReflection->getName());
    }
}
