<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection\Fixtures;

use LogicException;
use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;

class TestExpressionLanguageProvider implements ExpressionFunctionProviderInterface
{
    public function __construct(private readonly string $title = 'Hello world')
    {
    }

    /** @return ExpressionFunction[] */
    public function getFunctions(): array
    {
        return [
            new ExpressionFunction(
                'current_title',
                static function (): string {
                    throw new LogicException('The "current_title" function cannot be compiled.');
                },
                fn (): string => $this->title,
            ),
        ];
    }
}
