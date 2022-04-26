<?php

namespace Doctrine\Bundle\DoctrineBundle\Command\Proxy;

use Doctrine\ORM\Tools\Console\EntityManagerProvider;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/** @internal */
trait OrmProxyCommand
{
    /** @var EntityManagerProvider|null */
    private $entityManagerProvider;

    public function __construct(?EntityManagerProvider $entityManagerProvider = null)
    {
        parent::__construct($entityManagerProvider);
        $this->entityManagerProvider = $entityManagerProvider;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (! $this->entityManagerProvider) {
            DoctrineCommandHelper::setApplicationEntityManager($this->getApplication(), $input->getOption('em'));
        }

        return parent::execute($input, $output);
    }
}
