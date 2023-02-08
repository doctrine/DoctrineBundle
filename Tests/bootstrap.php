<?php

use Doctrine\Deprecations\Deprecation;

require_once 'vendor/autoload.php';

if (class_exists(Deprecation::class)) {
    Deprecation::enableWithTriggerError();
}
