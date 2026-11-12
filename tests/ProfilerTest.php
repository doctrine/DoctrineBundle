<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Tests;

use Doctrine\Bundle\DoctrineBundle\DataCollector\DoctrineDataCollector;
use Doctrine\Bundle\DoctrineBundle\DataCollector\HydrationProfiler;
use Doctrine\Bundle\DoctrineBundle\Twig\DoctrineExtension;
use Doctrine\ORM\HydrationObserver;
use Doctrine\ORM\Internal\Hydration\ScalarHydrator;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Symfony\Bridge\Doctrine\Middleware\Debug\DebugDataHolder;
use Symfony\Bridge\Doctrine\Middleware\Debug\Query;
use Symfony\Bridge\Twig\Extension\CodeExtension as CodeExtensionLegacy;
use Symfony\Bridge\Twig\Extension\HttpKernelExtension;
use Symfony\Bridge\Twig\Extension\HttpKernelRuntime;
use Symfony\Bridge\Twig\Extension\RoutingExtension;
use Symfony\Bundle\WebProfilerBundle\Profiler\CodeExtension;
use Symfony\Bundle\WebProfilerBundle\Twig\WebProfilerExtension;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\RequestDataCollector;
use Symfony\Component\HttpKernel\Fragment\FragmentHandler;
use Symfony\Component\HttpKernel\Profiler\Profile;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\Runtime\EscaperRuntime;
use Twig\RuntimeLoader\FactoryRuntimeLoader;

use function class_exists;
use function html_entity_decode;
use function interface_exists;
use function preg_match;
use function preg_quote;
use function str_contains;
use function str_replace;

class ProfilerTest extends BaseTestCase
{
    private DebugDataHolder $debugDataHolder;
    private Environment $twig;

    public function setUp(): void
    {
        $this->debugDataHolder = new DebugDataHolder();

        $twigLoaderFilesystem = new FilesystemLoader(__DIR__ . '/../templates/Collector');
        $twigLoaderFilesystem->addPath(__DIR__ . '/../vendor/symfony/web-profiler-bundle/Resources/views', 'WebProfiler');
        $this->twig = new Environment($twigLoaderFilesystem, ['debug' => true, 'strict_variables' => true]);

        $fragmentHandler = $this->createStub(FragmentHandler::class);
        $fragmentHandler->method('render')->willReturn('');

        $kernelRuntime = new HttpKernelRuntime($fragmentHandler);

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('');

        if (class_exists(CodeExtension::class)) {
            $this->twig->addExtension(new CodeExtension('', '', ''));
        } elseif (class_exists(CodeExtensionLegacy::class)) {
            /* @phpstan-ignore argument.type (available in symfony < 7) */
            $this->twig->addExtension(new CodeExtensionLegacy('', '', ''));
        }

        $this->twig->addExtension(new RoutingExtension($urlGenerator));
        $this->twig->addExtension(new HttpKernelExtension());
        $this->twig->addExtension(new WebProfilerExtension());
        $this->twig->addExtension(new DoctrineExtension());

        $this->twig->addRuntimeLoader(new FactoryRuntimeLoader([
            HttpKernelRuntime::class => static fn () => $kernelRuntime,
            EscaperRuntime::class => static fn () => new EscaperRuntime(),
        ]));
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function testRender(bool $withHydration): void
    {
        if ($withHydration && ! interface_exists(HydrationObserver::class)) {
            self::markTestSkipped('This test requires the ORM hydration observer');
        }

        $hydrationProfiler = null;

        if ($withHydration) {
            $hydrationProfiler = new HydrationProfiler();
            $hydrationProfiler->start(ScalarHydrator::class, []);
            $hydrationProfiler->end(0);
        }

        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getConnectionNames')->willReturn([]);
        $registry->method('getManagerNames')->willReturn([]);
        $registry->method('getManagers')->willReturn([]);
        $collector = new DoctrineDataCollector($registry, true, $this->debugDataHolder, hydrationProfiler: $hydrationProfiler);

        $this->debugDataHolder->addQuery('foo', new Query('SELECT * FROM foo WHERE bar IN (?, ?) AND "" >= ""'));

        $collector->collect($request = new Request(['group' => '0']), new Response());

        $profile = new Profile('foo');
        $profile->setMethod('GET');
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $requestDataCollector = new RequestDataCollector($requestStack);
        $requestDataCollector->collect($request, new Response());
        $requestDataCollector->lateCollect();

        $profile->addCollector($requestDataCollector);

        $output = $this->twig->render('db.html.twig', [
            'request' => $request,
            'token' => 'foo',
            'page' => 'foo',
            'profile' => $profile,
            'collector' => $collector,
            'queries' => $this->debugDataHolder->getData(),
            'profiler_markup_version' => 3,
            'profile_type' => 'request',
        ]);

        $expectedEscapedSql = 'SELECT&#x0A;&#x20;&#x20;&#x2A;&#x0A;FROM&#x0A;&#x20;&#x20;foo&#x0A;WHERE&#x0A;&#x20;&#x20;bar&#x20;IN&#x20;&#x28;&#x3F;,&#x20;&#x3F;&#x29;&#x0A;&#x20;&#x20;AND&#x20;&quot;&quot;&#x20;&gt;&#x3D;&#x20;&quot;&quot;';
        $this->assertSame(
            "SELECT\n  *\nFROM\n  foo\nWHERE\n  bar IN (?, ?)\n  AND \"\" >= \"\"",
            html_entity_decode($expectedEscapedSql),
        );

        $this->assertStringContainsString($expectedEscapedSql, $output);

        $this->assertSame(1, preg_match('/' . str_replace(
            ' ',
            '.*',
            preg_quote('SELECT * FROM foo WHERE bar IN ( ? , ? )'),
        ) . '/', $output));

        $this->assertStringContainsString('Managed entities', $output);
        $this->assertStringContainsString('No managed entities.', $output);
        $this->assertSame($withHydration, str_contains($output, 'Hydration time'));
        $this->assertSame($withHydration, str_contains($output, ScalarHydrator::class));
    }
}
