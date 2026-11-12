<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Tests\ArgumentResolver\Fixtures;

use LogicException;
use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;

class TitleExpressionLanguageProvider implements ExpressionFunctionProviderInterface
{
    /** @return ExpressionFunction[] */
    public function getFunctions(): array
    {
        return [
            new ExpressionFunction(
                'current_title',
                static function (): string {
                    throw new LogicException('The "current_title" function cannot be compiled.');
                },
                static fn (): string => 'Hello world',
            ),
        ];
    }
}
