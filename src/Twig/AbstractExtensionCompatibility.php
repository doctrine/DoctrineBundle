<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Twig;

use ReflectionMethod;
use Twig\Extension\AbstractExtension;

if ((new ReflectionMethod(AbstractExtension::class, 'getFilters'))->hasReturnType()) {
    /** @internal */
    trait AbstractExtensionCompatibility
    {
        public function getFilters(): array
        {
            return $this->doGetFilters();
        }
    }
} else {
    /** @internal */
    trait AbstractExtensionCompatibility
    {
        public function getFilters()
        {
            return $this->doGetFilters();
        }
    }
}
