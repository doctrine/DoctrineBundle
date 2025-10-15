<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Tests\Polyfill;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;

use function method_exists;

/**
 * Necessary until support for Symfony < 7.4 is dropped.
 */
final class SymfonyApp extends Application
{
    public function addCommand(callable|Command $command): Command|null
    {
        /** @phpstan-ignore function.alreadyNarrowedType */
        if (method_exists(parent::class, 'addCommand')) {
            return parent::addCommand($command);
        }

        return $this->add($command);
    }

    /** Needs to exist to pass this test:
     * https://github.com/symfony/console/blob/525f69f6840d00b195ffeaea6c3ba1ec6cd87476/Application.php#L1356
     */
    public function add(Command $command): Command
    {
        return parent::add($command);
    }
}
