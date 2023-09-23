<?php

declare(strict_types=1);

namespace App\Extensions;

use PHPStan\Reflection\FunctionReflection;
use PHPStan\Type\Php\ArrayKeyFirstDynamicReturnTypeExtension;

final class FunctionalFirstKeyDynamicReturnTypeExtension extends ArrayKeyFirstDynamicReturnTypeExtension
{
    public function isFunctionSupported(FunctionReflection $functionReflection): bool
    {
        return 'functional\firstkey' === strtolower($functionReflection->getName());
    }
}
