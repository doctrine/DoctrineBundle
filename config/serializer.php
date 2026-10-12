<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Bridge\Doctrine\Serializer\Normalizer\CollectionDenormalizer;

return static function (ContainerConfigurator $container): void {
    $container->services()

        ->set('doctrine.orm.collection_denormalizer', CollectionDenormalizer::class)
            ->tag('serializer.normalizer');
};
