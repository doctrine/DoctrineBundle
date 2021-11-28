<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests;

use Doctrine\Bundle\DoctrineBundle\DataCollector\DoctrineDataCollector;
use Doctrine\Bundle\DoctrineBundle\Twig\DoctrineExtension;
use Doctrine\DBAL\Logging\DebugStack;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Symfony\Bridge\Twig\Extension\CodeExtension;
use Symfony\Bridge\Twig\Extension\HttpKernelExtension;
use Symfony\Bridge\Twig\Extension\HttpKernelRuntime;
use Symfony\Bridge\Twig\Extension\RoutingExtension;
use Symfony\Bundle\WebProfilerBundle\Twig\WebProfilerExtension;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Fragment\FragmentHandler;
use Symfony\Component\HttpKernel\Profiler\Profile;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\RuntimeLoader\RuntimeLoaderInterface;

use function html_entity_decode;
use function preg_match;
use function preg_quote;
use function str_replace;

class ProfilerTest extends BaseTestCase
{
    /** @var DebugStack */
    private $logger;

    /** @var Environment */
    private $twig;

    /** @var DoctrineDataCollector */
    private $collector;

    public function setUp(): void
    {
        $this->logger = new DebugStack();
        $registry     = $this->getMockBuilder(ManagerRegistry::class)->getMock();
        $registry->method('getConnectionNames')->willReturn([]);
        $registry->method('getManagerNames')->willReturn([]);
        $registry->method('getManagers')->willReturn([]);
        $this->collector = new DoctrineDataCollector($registry);
        $this->collector->addLogger('foo', $this->logger);

        $twigLoaderFilesystem = new FilesystemLoader(__DIR__ . '/../Resources/views/Collector');
        $twigLoaderFilesystem->addPath(__DIR__ . '/../vendor/symfony/web-profiler-bundle/Resources/views', 'WebProfiler');
        $this->twig = new Environment($twigLoaderFilesystem, ['debug' => true, 'strict_variables' => true]);

        $fragmentHandler = $this->getMockBuilder(FragmentHandler::class);
        $fragmentHandler->disableOriginalConstructor();
        $fragmentHandler = $fragmentHandler->getMock();
        $fragmentHandler->method('render')->willReturn('');

        $kernelRuntime = new HttpKernelRuntime($fragmentHandler);

        $urlGenerator = $this->getMockBuilder(UrlGeneratorInterface::class)->getMock();
        $urlGenerator->method('generate')->willReturn('');

        $this->twig->addExtension(new CodeExtension('', '', ''));
        $this->twig->addExtension(new RoutingExtension($urlGenerator));
        $this->twig->addExtension(new HttpKernelExtension());
        /**
         * @psalm-suppress InternalClass
         * @psalm-suppress InternalMethod
         */
        $this->twig->addExtension(new WebProfilerExtension());
        $this->twig->addExtension(new DoctrineExtension());

        $loader = $this->getMockBuilder(RuntimeLoaderInterface::class)->getMock();
        $loader->method('load')->willReturn($kernelRuntime);
        $this->twig->addRuntimeLoader($loader);
    }

    public function testRender(): void
    {
        $this->logger->queries = [
            [
                'sql' => 'SELECT * FROM foo WHERE bar IN (?, ?) AND "" >= ""',
                'params' => ['foo', 'bar'],
                'types' => null,
                'executionMS' => 1,
            ],
        ];

        $this->collector->collect($request = new Request(['group' => '0']), new Response());

        $profile = new Profile('foo');
        $profile->setMethod('GET');

        $output = $this->twig->render('db.html.twig', [
            'request' => $request,
            'token' => 'foo',
            'page' => 'foo',
            'profile' => $profile,
            'collector' => $this->collector,
            'queries' => $this->logger->queries,
        ]);

        $expectedEscapedSql = 'SELECT&#x0A;&#x20;&#x20;&#x2A;&#x0A;FROM&#x0A;&#x20;&#x20;foo&#x0A;WHERE&#x0A;&#x20;&#x20;bar&#x20;IN&#x20;&#x28;&#x3F;,&#x20;&#x3F;&#x29;&#x0A;&#x20;&#x20;AND&#x20;&quot;&quot;&#x20;&gt;&#x3D;&#x20;&quot;&quot;';
        $this->assertSame(
            "SELECT\n  *\nFROM\n  foo\nWHERE\n  bar IN (?, ?)\n  AND \"\" >= \"\"",
            html_entity_decode($expectedEscapedSql)
        );

        $this->assertStringContainsString($expectedEscapedSql, $output);

        $this->assertSame(1, preg_match('/' . str_replace(
            ' ',
            '.*',
            preg_quote('SELECT * FROM foo WHERE bar IN ( ? , ? )')
        ) . '/', $output));
    }
}
