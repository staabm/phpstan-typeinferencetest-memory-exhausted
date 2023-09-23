<?php

declare(strict_types=1);

namespace App\Extensions;

use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Type\Php\ArrayMapFunctionReturnTypeExtension;
use PHPStan\Type\Type;
use function App\Functional\reverse;

final class FunctionalMapDynamicReturnTypeExtension extends ArrayMapFunctionReturnTypeExtension
{
    public function isFunctionSupported(FunctionReflection $functionReflection): bool
    {
        return 'functional\map' === strtolower($functionReflection->getName());
    }

    /**
     * Our implementation as reversed arguments (array first, callable second) compared to array_map
     */
    public function getTypeFromFunctionCall(FunctionReflection $functionReflection, FuncCall $functionCall, Scope $scope): Type
    {
        $reversedFunctionCall = new FuncCall($functionCall->name, reverse($functionCall->getArgs()));

        return parent::getTypeFromFunctionCall($functionReflection, $reversedFunctionCall, $scope);
    }
}
