<?php

/*
 * This file is part of the Doctrine Bundle
 *
 * (c) Doctrine Project, Benjamin Eberlei <kontakt@beberlei.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Doctrine\Bundle\DoctrineBundle\Tests\Query;

use Doctrine\Bundle\DoctrineBundle\Query\ContainerFilterFactory;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

class ContainerFilterFactoryTest extends \PHPUnit_Framework_TestCase
{
    public function testCreateFilter()
    {
        $configuration = new Configuration();
        $configuration->addFilter('oldSkoolFilter', 'Doctrine\Bundle\DoctrineBundle\Tests\Query\MockFilter');
        $configuration->addFilter('containerFilter', 'my.service');

        $em = $this->getMockBuilder('Doctrine\ORM\EntityManager')->disableOriginalConstructor()->getMock();
        $em->expects($this->any())->method('getConfiguration')->will($this->returnValue($configuration));

        $container = $this->getMock('Symfony\Component\DependencyInjection\ContainerInterface');
        $containerFilter = new MockContainerFilter($em);
        $container->expects($this->once())->method('get')->will($this->returnValue($containerFilter));
        $factory = new ContainerFilterFactory($container);

        $filter = $factory->createFilter($em, 'oldSkoolFilter');
        $this->assertInstanceOf('Doctrine\Bundle\DoctrineBundle\Tests\Query\MockFilter', $filter);

        $filter = $factory->createFilter($em, 'containerFilter');
        $this->assertEquals($containerFilter, $filter);
    }
}

class MockFilter extends SQLFilter
{
    public function addFilterConstraint(ClassMetadata $targetEntity, $targetTableAlias)
    {
    }
}

class MockContainerFilter extends SQLFilter
{
  public function addFilterConstraint(ClassMetadata $targetEntity, $targetTableAlias)
  {
  }
}