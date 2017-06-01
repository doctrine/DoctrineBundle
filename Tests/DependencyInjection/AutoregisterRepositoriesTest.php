<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection;


use Prophecy\Argument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\RepositoryAliasPass;
use Doctrine\Common\Persistence\Mapping\Driver\MappingDriver;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\HttpKernel\Kernel;


class AutoregisterRepositoriesTest extends \PHPUnit_Framework_TestCase
{
    public function testSingleEmNoRegisteredRepositories()
    {
        $containerBuilder = $this->prophesize(ContainerBuilder::class);
        $metadataDriver = $this->prophesize(MappingDriver::class);
        $parameterBag = $this->prophesize(ParameterBag::class);
        $definition = $this->prophesize(Definition::class);

        $this->prepPropheciesForOneEmAndClassA($containerBuilder, $metadataDriver, $parameterBag);

        $containerBuilder->getDefinitions()->willReturn([]);
        $containerBuilder->has(ClassARepository::class)->willReturn(false);
        $containerBuilder->register(ClassARepository::class, ClassARepository::class)->shouldBeCalled()->willReturn($definition);
        $definition->setFactory(Argument::any())->shouldBeCalled()->willReturn($definition);
        $definition->setArguments(['ClassA', 'default'])->shouldBeCalled()->willReturn($definition);
        $definition->setPublic(false)->shouldBeCalled()->willReturn($definition);

        if (method_exists(Definition::class, 'setShared')) {
            $definition->setShared(false)->shouldBeCalled()->willReturn($definition);
        } else {
            $definition->setScope('prototype')->shouldBeCalled()->willReturn($definition);
        }


        $pass = new RepositoryAliasPass();
        $pass->process($containerBuilder->reveal());
    }

    public function testSingleEmRepoAlreadyRegisteredWithClassName()
    {
        $containerBuilder = $this->prophesize(ContainerBuilder::class);
        $metadataDriver = $this->prophesize(MappingDriver::class);
        $parameterBag = $this->prophesize(ParameterBag::class);

        $this->prepPropheciesForOneEmAndClassA($containerBuilder, $metadataDriver, $parameterBag);

        $containerBuilder->getDefinitions()->willReturn([]);
        $containerBuilder->has(ClassARepository::class)->willReturn(true);

        $pass = new RepositoryAliasPass();
        $pass->process($containerBuilder->reveal());
    }

    public function testSingleEmRepoAlreadyRegisteredVanillaServiceOnSymfony3()
    {
        if (Kernel::MAJOR_VERSION !== 3) {
            $this->markTestSkipped("This test is only run with Symfony 3");
        }

        $pass = new RepositoryAliasPass();

        $containerBuilder = $this->prophesize(ContainerBuilder::class);
        $metadataDriver = $this->prophesize(MappingDriver::class);
        $parameterBag = $this->prophesize(ParameterBag::class);
        $previousDefinition = $this->prophesize(Definition::class);

        $this->prepPropheciesForOneEmAndClassA($containerBuilder, $metadataDriver, $parameterBag);

        $containerBuilder->getDefinitions()->willReturn([
            'app.class_a_repo' => $previousDefinition,
        ]);
        $previousDefinition->getClass()->willReturn(ClassARepository::class);
        $containerBuilder->has(ClassARepository::class)->willReturn(false);
        if (method_exists(ContainerBuilder::class, 'log')) {
            $containerBuilder->log($pass, Argument::any())->shouldBeCalled();
        }

        $pass->process($containerBuilder->reveal());
    }

    public function testMultipleEms()
    {
        $pass = new RepositoryAliasPass();

        $containerBuilder = $this->prophesize(ContainerBuilder::class);
        $metadataDriver1 = $this->prophesize(MappingDriver::class);
        $metadataDriver2 = $this->prophesize(MappingDriver::class);
        $parameterBag = $this->prophesize(ParameterBag::class);

        $containerBuilder->hasParameter('doctrine.entity_managers')->willReturn(true);
        $containerBuilder->getParameter('doctrine.entity_managers')->willReturn([
            'default' => 'doctrine.default',
            'secondary' => 'doctrine.secondary',
        ]);
        $containerBuilder->has('doctrine.orm.default_metadata_driver')->willReturn(true);
        $containerBuilder->get('doctrine.orm.default_metadata_driver')->willReturn($metadataDriver1);
        $containerBuilder->has('doctrine.orm.secondary_metadata_driver')->willReturn(true);
        $containerBuilder->get('doctrine.orm.secondary_metadata_driver')->willReturn($metadataDriver1);
        $containerBuilder->getParameterBag()->willReturn($parameterBag);

        $parameterBag->resolveValue(Argument::any())->will(function($args) {
            return $args[0];
        });

        $metadataDriver1->getAllClassNames()->willReturn([
            'ClassA',
        ]);
        $metadataDriver1->loadMetadataForClass('ClassA', Argument::which('getName', 'ClassA'))
            ->will(function($args) {
                $args[1]->customRepositoryClassName = ClassARepository::class;
            });

        $metadataDriver2->getAllClassNames()->willReturn([
            'ClassA',
        ]);
        $metadataDriver2->loadMetadataForClass('ClassA', Argument::which('getName', 'ClassA'))
            ->will(function($args) {
                $args[1]->customRepositoryClassName = ClassARepository::class;
            });

        $containerBuilder->getDefinitions()->willReturn([]);
        $containerBuilder->has(ClassARepository::class)->willReturn(false);
        if (method_exists(ContainerBuilder::class, 'log')) {
            $containerBuilder->log($pass, Argument::any())->shouldBeCalled();
        }

        $pass->process($containerBuilder->reveal());
    }

    protected function prepPropheciesForOneEmAndClassA($containerBuilder, $metadataDriver, $parameterBag)
    {
        $containerBuilder->hasParameter('doctrine.entity_managers')->willReturn(true);
        $containerBuilder->getParameter('doctrine.entity_managers')->willReturn([
            'default' => 'doctrine.default',
        ]);
        $containerBuilder->has('doctrine.orm.default_metadata_driver')->willReturn(true);
        $containerBuilder->get('doctrine.orm.default_metadata_driver')->willReturn($metadataDriver);
        $containerBuilder->getParameterBag()->willReturn($parameterBag);

        $parameterBag->resolveValue(Argument::any())->will(function($args) {
            return $args[0];
        });

        $metadataDriver->getAllClassNames()->willReturn([
            'ClassA',
        ]);
        $metadataDriver->loadMetadataForClass('ClassA', Argument::which('getName', 'ClassA'))
            ->will(function($args) {
                $args[1]->customRepositoryClassName = ClassARepository::class;
            });
    }
}


class ClassAlphaRepository {}
class ClassARepository extends ClassAlphaRepository {}
