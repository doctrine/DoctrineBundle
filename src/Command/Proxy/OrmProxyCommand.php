<?php

namespace Doctrine\Bundle\DoctrineBundle\Command\Proxy;

use Doctrine\ORM\Tools\Console\EntityManagerProvider;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function trigger_deprecation;

/**
 * @internal
 * @deprecated
 */
trait OrmProxyCommand
{
    private ?EntityManagerProvider $entityManagerProvider;

    public function __construct(?EntityManagerProvider $entityManagerProvider = null)
    {
        parent::__construct($entityManagerProvider);

        $this->entityManagerProvider = $entityManagerProvider;

        trigger_deprecation(
            'doctrine/doctrine-bundle',
            '2.8',
            'Class "%s" is deprecated. Use "%s" instead.',
            self::class,
            parent::class,
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (! $this->entityManagerProvider) {
            DoctrineCommandHelper::setApplicationEntityManager($this->getApplication(), $input->getOption('em'));
        }

        return parent::execute($input, $output);
    }
}
