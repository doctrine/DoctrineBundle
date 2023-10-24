<?php

use Doctrine\Deprecations\Deprecation;

require_once __DIR__ . '/../vendor-bin/phpunit/autoload.php';

Deprecation::enableWithTriggerError();
