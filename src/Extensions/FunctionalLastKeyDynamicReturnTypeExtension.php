<?php

declare(strict_types=1);

namespace App\Extensions;

use PHPStan\Reflection\FunctionReflection;
use PHPStan\Type\Php\ArrayKeyLastDynamicReturnTypeExtension;

final class FunctionalLastKeyDynamicReturnTypeExtension extends ArrayKeyLastDynamicReturnTypeExtension
{
    public function isFunctionSupported(FunctionReflection $functionReflection): bool
    {
        return 'functional\lastkey' === strtolower($functionReflection->getName());
    }
}
