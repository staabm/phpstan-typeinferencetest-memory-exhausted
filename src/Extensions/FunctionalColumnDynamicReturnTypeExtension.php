<?php

declare(strict_types=1);

namespace App\Extensions;

use PHPStan\Reflection\FunctionReflection;
use PHPStan\Type\Php\ArrayColumnFunctionReturnTypeExtension;

final class FunctionalColumnDynamicReturnTypeExtension extends ArrayColumnFunctionReturnTypeExtension
{
    public function isFunctionSupported(FunctionReflection $functionReflection): bool
    {
        return 'functional\column' === strtolower($functionReflection->getName());
    }
}
