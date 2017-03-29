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

namespace Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Config\FileLocator;

class YamlDoctrineExtensionTest extends AbstractDoctrineExtensionTest
{
    protected function loadFromFile(ContainerBuilder $container, $file)
    {
        $loadYaml = new YamlFileLoader($container, new FileLocator(__DIR__.'/Fixtures/config/yml'));
        $loadYaml->load($file.'.yml');
    }
}
