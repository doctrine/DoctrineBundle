<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Middleware;

use ReflectionObject;
use Twig\Template;

use function array_key_exists;
use function class_exists;

/**
 * Replaces the frames of a backtrace that point to a compiled Twig template
 * with the source template and its line.
 *
 * Twig compiles each template to a PHP class in the cache directory. Without
 * this mapping, the query backtrace in the profiler links to that generated
 * file, where the line has no meaning for the developer.
 *
 * @internal
 */
final class TwigBacktraceMapper
{
    /**
     * @param list<array{class?: string, function?: string, file?: string, line?: int, object?: object}> $trace
     *
     * @return list<array{class?: string, function?: string, file?: string, line?: int, object?: object}>
     */
    public function map(array $trace): array
    {
        $templates = class_exists(Template::class) ? $this->compiledTemplates($trace) : [];

        foreach ($trace as $index => $frame) {
            // The objects are only needed for the mapping and must not end up
            // in the profiler data, where they would be serialized.
            unset($trace[$index]['object']);

            $file = $frame['file'] ?? null;
            if ($templates === [] || $file === null || ! array_key_exists($file, $templates)) {
                continue;
            }

            $template = $templates[$file];
            $line     = $this->templateLine($template, $frame['line'] ?? 0);
            if ($line === null) {
                continue;
            }

            $source                = $template->getSourceContext();
            $trace[$index]['file'] = $source->getPath() ?: $source->getName();
            $trace[$index]['line'] = $line;
        }

        return $trace;
    }

    /**
     * @param list<array{object?: object}> $trace
     *
     * @return array<string, Template>
     */
    private function compiledTemplates(array $trace): array
    {
        $templates = [];

        foreach ($trace as $frame) {
            $object = $frame['object'] ?? null;
            if (! $object instanceof Template) {
                continue;
            }

            $file = (new ReflectionObject($object))->getFileName();
            if ($file === false) {
                continue;
            }

            $templates[$file] = $object;
        }

        return $templates;
    }

    /**
     * Returns the template line matching a compiled line. Twig stores the
     * compiled to template line map in the compiled class, and reads it the
     * same way when it reports an error.
     */
    private function templateLine(Template $template, int $compiledLine): int|null
    {
        foreach ($template->getDebugInfo() as $codeLine => $templateLine) {
            if ($codeLine <= $compiledLine) {
                return $templateLine;
            }
        }

        return null;
    }
}
