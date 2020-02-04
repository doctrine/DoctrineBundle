UPGRADE FROM 1.11 to 1.12
=========================

Deprecation of DoctrineCacheBundle
----------------------------------

With DoctrineCacheBundle [being deprecated](https://github.com/doctrine/DoctrineCacheBundle/issues/156),
configuring caches through it has been deprecated. If you are using anything
other than the `pool` or `id` cache types, please update your configuration to
either use symfony/cache through the `pool` type or configure your cache
services manually and use the `service` type.

Service aliases
---------------

 * Deprecated the `Symfony\Bridge\Doctrine\RegistryInterface` and `Doctrine\Bundle\DoctrineBundle\Registry` service alias, use `Doctrine\Common\Persistence\ManagerRegistry` instead.
 * Deprecated the `Doctrine\Common\Persistence\ObjectManager` service alias, use `Doctrine\ORM\EntityManagerInterface` instead.

UnitOfWork cleared between each request
---------------------------------------
If all of these are true:
* You call `Symfony\Bundle\FrameworkBundle\Client::disableReboot()` in your test case
* Trigger multiple HTTP requests (via `Symfony\Bundle\FrameworkBundle\Client::request()` etc.) within your test case
* Your test case relies on Doctrine ORM keeping references to old entities between requests (this is most obvious when calling `Doctrine\Persistence\ObjectManager::refresh`)

Your test case will fail since `DoctrineBundle` 1.12.3, as identity map is now cleared between each request 
to better simulate real requests and avoid memory leaks. You have two options to solve this:

1. Change your test cases with new behaviour in mind. In a lot of cases this just means to replace `ObjectManager::refresh($entity)` with `$entity = ObjectManager::find($entity->getId())`. This is the recommended solution.
2. Write a compiler pass which restores old behaviour, e.g. by adding the following to your `Kernel` class:
```php
protected function build(\Symfony\Component\DependencyInjection\ContainerBuilder $container)
{
    parent::build($container);

    if ($this->environment === 'test') {
        $container->addCompilerPass(new class implements \Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface {
            public function process(\Symfony\Component\DependencyInjection\ContainerBuilder $container)
            {
                $container->getDefinition('doctrine')->clearTag('kernel.reset');
            }
        }, \Symfony\Component\DependencyInjection\Compiler\PassConfig::TYPE_BEFORE_OPTIMIZATION, 1);
    }
}
```
