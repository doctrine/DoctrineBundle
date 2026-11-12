<?php

declare(strict_types=1);

/** This is a blank command serving for generating symfony's var/ folder, needed for symfony phpstan extension */

use Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection\Fixtures\TestKernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;

require __DIR__ . '/../vendor/autoload.php';

new Application(new TestKernel(projectDir: __DIR__ . '/../'))->run();
