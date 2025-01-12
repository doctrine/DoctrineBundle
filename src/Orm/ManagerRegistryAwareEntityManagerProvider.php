<?php

namespace Doctrine\Bundle\DoctrineBundle\Orm;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Console\EntityManagerProvider;
use Doctrine\Persistence\ManagerRegistry;
use RuntimeException;

use function get_debug_type;
use function sprintf;

final class ManagerRegistryAwareEntityManagerProvider implements EntityManagerProvider
{
    public function __construct(
        private readonly ManagerRegistry $managerRegistry,
    ) {
    }

    public function getDefaultManager(): EntityManagerInterface
    {
        return $this->getManager($this->managerRegistry->getDefaultManagerName());
    }

    public function getManager(string $name): EntityManagerInterface
    {
        $em = $this->managerRegistry->getManager($name);

        if ($em instanceof EntityManagerInterface) {
            return $em;
        }

        throw new RuntimeException(
            sprintf(
                'Only managers of type "%s" are supported. Instance of "%s given.',
                EntityManagerInterface::class,
                get_debug_type($em),
            ),
        );
    }
}
