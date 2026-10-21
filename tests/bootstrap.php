<?php

declare(strict_types=1);

use Doctrine\Deprecations\Deprecation;

require dirname(__DIR__) . '/vendor/autoload.php';

// DBAL 4.6 deprecates the static type registry that this branch still relies on
// to register types for ORM versions that do not support a type provider.
Deprecation::ignoreDeprecations('https://github.com/doctrine/dbal/pull/7342');
