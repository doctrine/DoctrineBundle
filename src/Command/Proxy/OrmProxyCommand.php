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
    public function __construct(
        private readonly EntityManagerProvider|null $entityManagerProvider = null,
    ) {
        parent::__construct($entityManagerProvider);

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
            /* @phpstan-ignore argument.type (ORM < 3 specific) */
            DoctrineCommandHelper::setApplicationEntityManager($this->getApplication(), $input->getOption('em'));
        }

        return parent::execute($input, $output);
    }
}
