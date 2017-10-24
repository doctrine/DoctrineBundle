<?php
/*
 * This file is part of the Doctrine Bundle
 *
 * The code was originally distributed inside the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 * (c) Doctrine Project, Benjamin Eberlei <kontakt@beberlei.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
*/

namespace Doctrine\Bundle\DoctrineBundle\Tests\Twig;

use Doctrine\Bundle\DoctrineBundle\Repository\RepositoryTrait;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;

class RepositoryTraitTest extends TestCase
{
    public function testBasicTraitFunctionality()
    {
        $em = $this->getMockBuilder(EntityManagerInterface::class)->getMock();
        $repo = $this->getMockBuilder(EntityRepository::class)->disableOriginalConstructor()->getMock();

        $em->expects($this->once())
            ->method('getRepository')
            ->with('App\Entity\CoolStuff')
            ->will($this->returnValue($repo));

        $qb = $this->getMockBuilder(QueryBuilder::class)->disableOriginalConstructor()->getMock();
        $repo->expects($this->once())
            ->method('createQueryBuilder')
            ->with('cs')
            ->willReturn($qb);

        $stubRepo = new StubRepository($em);
        $this->assertSame($qb, $stubRepo->createQueryBuilder('cs'));
    }
}

class StubRepository
{
    use RepositoryTrait;

    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    protected function getEntityManager()
    {
        return $this->em;
    }

    protected function getClassName()
    {
        return 'App\Entity\CoolStuff';
    }
}