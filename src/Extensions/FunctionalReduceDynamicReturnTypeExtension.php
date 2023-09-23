<?php

declare(strict_types=1);

namespace App\Extensions;

use PHPStan\Reflection\FunctionReflection;
use PHPStan\Type\Php\ArrayReduceFunctionReturnTypeExtension;

final class FunctionalReduceDynamicReturnTypeExtension extends ArrayReduceFunctionReturnTypeExtension
{
    public function isFunctionSupported(FunctionReflection $functionReflection): bool
    {
        return 'functional\reduce' === strtolower($functionReflection->getName());
    }
}
