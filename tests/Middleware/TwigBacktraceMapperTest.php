<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Tests\Middleware;

use Doctrine\Bundle\DoctrineBundle\Middleware\TwigBacktraceMapper;
use PHPUnit\Framework\TestCase;
use ReflectionObject;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

use function array_key_first;
use function class_exists;

final class TwigBacktraceMapperTest extends TestCase
{
    public function testMapsACompiledTemplateFrameToItsSource(): void
    {
        if (! class_exists(Environment::class)) {
            self::markTestSkipped('twig/twig is not installed.');
        }

        $twig         = new Environment(new FilesystemLoader(__DIR__ . '/Fixtures'), ['debug' => true]);
        $template     = $twig->load('lazy_load.html.twig')->unwrap($twig);
        $debugInfo    = $template->getDebugInfo();
        $compiledLine = array_key_first($debugInfo);
        $compiledFile = (new ReflectionObject($template))->getFileName();

        self::assertIsInt($compiledLine);
        self::assertIsString($compiledFile);

        $mapped = (new TwigBacktraceMapper())->map([
            ['class' => 'Doctrine\ORM\PersistentCollection', 'function' => 'count', 'file' => $compiledFile, 'line' => $compiledLine],
            ['class' => $template::class, 'function' => 'doDisplay', 'file' => '/vendor/twig/twig/src/Template.php', 'line' => 388, 'object' => $template],
            ['file' => '/app/src/Controller.php', 'line' => 20, 'function' => 'index'],
        ]);

        self::assertSame(
            [
                ['class' => 'Doctrine\ORM\PersistentCollection', 'function' => 'count', 'file' => __DIR__ . '/Fixtures/lazy_load.html.twig', 'line' => $debugInfo[$compiledLine]],
                ['class' => $template::class, 'function' => 'doDisplay', 'file' => '/vendor/twig/twig/src/Template.php', 'line' => 388],
                ['file' => '/app/src/Controller.php', 'line' => 20, 'function' => 'index'],
            ],
            $mapped,
        );
    }

    public function testLeavesATraceWithoutTemplateAlone(): void
    {
        $trace = [
            ['file' => '/app/src/Controller.php', 'line' => 20, 'function' => 'index'],
        ];

        self::assertSame($trace, (new TwigBacktraceMapper())->map($trace));
    }
}
