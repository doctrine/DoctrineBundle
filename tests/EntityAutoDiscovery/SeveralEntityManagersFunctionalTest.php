<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Tests\EntityAutoDiscovery;

use Doctrine\Bundle\DoctrineBundle\Tests\EntityAutoDiscovery\Fixtures\SeveralEntityManagersKernel;
use Doctrine\Bundle\DoctrineBundle\Tests\EntityAutoDiscovery\Fixtures\Tenant\Project;
use Doctrine\Bundle\DoctrineBundle\Tests\EntityAutoDiscovery\Fixtures\UnmappedUser;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

use function assert;
use function interface_exists;

/**
 * A mapping-less entity manager listed first must not claim the entities
 * mapped by another entity manager.
 */
class SeveralEntityManagersFunctionalTest extends TestCase
{
    public function testEntityIsResolvedToTheEntityManagerMappingIt(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('This test requires ORM');
        }

        $kernel = new SeveralEntityManagersKernel();
        $kernel->boot();

        try {
            $registry = $kernel->getContainer()->get('doctrine');

            $tenant = $registry->getManager('tenant');
            self::assertSame($tenant, $registry->getManagerForClass(Project::class));
            self::assertNull($registry->getManagerForClass(UnmappedUser::class));

            // A raw CREATE TABLE avoids the Schema::createTable() deprecation of DBAL 4,
            // see EntityValueResolverFunctionalTest.
            $connection = $registry->getConnection('tenant');
            assert($connection instanceof Connection);
            $connection->executeStatement('CREATE TABLE projects (id INTEGER NOT NULL, name VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
            $connection->insert('projects', ['id' => 1, 'name' => 'apollo']);

            self::assertSame('apollo', $registry->getRepository(Project::class)->find(1)?->name);
        } finally {
            $kernel->shutdown();
        }
    }
}
