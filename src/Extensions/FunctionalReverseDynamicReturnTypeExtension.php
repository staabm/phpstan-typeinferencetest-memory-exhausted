<?php

declare(strict_types=1);

namespace App\Extensions;

use PHPStan\Reflection\FunctionReflection;
use PHPStan\Type\Php\ArrayReverseFunctionReturnTypeExtension;

final class FunctionalReverseDynamicReturnTypeExtension extends ArrayReverseFunctionReturnTypeExtension
{
    public function isFunctionSupported(FunctionReflection $functionReflection): bool
    {
        return 'functional\reverse' === strtolower($functionReflection->getName());
    }
}
