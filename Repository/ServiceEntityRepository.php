<?php

namespace Doctrine\Bundle\DoctrineBundle\Repository;

use Doctrine\ORM\EntityRepository;

use function property_exists;

if (property_exists(EntityRepository::class, '_entityName')) {
    // ORM 2
    class_alias(ServiceEntityRepositoryOrm2::class, 'ServiceEntityRepository');
} else {
    // ORM 3
    class_alias(ServiceEntityRepositoryOrm3::class, 'ServiceEntityRepository');
}
