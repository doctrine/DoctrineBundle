<?php

namespace Doctrine\Bundle\DoctrineBundle\Mapping;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @deprecated since 1.11 and will be removed in 2.0. Use ContainerEntityListenerResolver instead.
 */
class ContainerAwareEntityListenerResolver extends ContainerEntityListenerResolver
{
    public function __construct(ContainerInterface $container)
    {
        @trigger_error(sprintf('The class "%s" is deprecated since 1.11 and will be removed in 2.0 Use "%s" instead.', self::class, 'Doctrine\Bundle\DoctrineBundle\Mapping\ContainerEntityListenerResolver'), E_USER_DEPRECATED);
        parent::__construct($container);
    }
}
