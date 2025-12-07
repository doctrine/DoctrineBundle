<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Twig;

use Doctrine\SqlFormatter\HtmlHighlighter;
use Doctrine\SqlFormatter\NullHighlighter;
use Doctrine\SqlFormatter\SqlFormatter;
use Stringable;
use Symfony\Component\VarDumper\Cloner\Data;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

use function addslashes;
use function array_filter;
use function array_key_exists;
use function array_keys;
use function array_values;
use function assert;
use function bin2hex;
use function count;
use function implode;
use function is_array;
use function is_bool;
use function is_string;
use function preg_match;
use function preg_replace_callback;
use function strtoupper;
use function substr;

/**
 * This class contains the needed functions in order to do the query highlighting
 *
 * @internal
 */
class DoctrineExtension extends AbstractExtension
{
    private SqlFormatter $sqlFormatter;

    /**
     * Define our functions
     *
     * @return TwigFilter[]
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('doctrine_prettify_sql', [$this, 'prettifySql'], ['is_safe' => ['html']]),
            new TwigFilter('doctrine_format_sql', [$this, 'formatSql'], ['is_safe' => ['html']]),
            new TwigFilter('doctrine_replace_query_parameters', [$this, 'replaceQueryParameters']),
        ];
    }

    /**
     * Escape parameters of a SQL query
     * DON'T USE THIS FUNCTION OUTSIDE ITS INTENDED SCOPE
     *
     * @internal
     */
    public static function escapeFunction(mixed $parameter): string|int|float
    {
        $result = $parameter;

        switch (true) {
            // Check if result is non-unicode string using PCRE_UTF8 modifier
            case is_string($result) && ! preg_match('//u', $result):
                $result = '0x' . strtoupper(bin2hex($result));
                break;

            case is_string($result):
                $result = "'" . addslashes($result) . "'";
                break;

            case is_array($result):
                foreach ($result as &$value) {
                    $value = static::escapeFunction($value);
                }

                $result = implode(', ', $result) ?: 'NULL';
                break;

            case $result instanceof Stringable:
                $result = addslashes((string) $result);
                break;

            case $result === null:
                $result = 'NULL';
                break;

            case is_bool($result):
                $result = $result ? '1' : '0';
                break;
        }

        return $result;
    }

    /**
     * Return a query with the parameters replaced
     *
     * @param array<array-key, mixed>|Data $parameters
     */
    public function replaceQueryParameters(string $query, array|Data $parameters): string
    {
        if ($parameters instanceof Data) {
            $parameters = $parameters->getValue(true);
            assert(is_array($parameters));
        }

        $keys = array_keys($parameters);

        if (count(array_filter($keys, 'is_int')) === count($keys)) {
            $parameters = array_values($parameters);
        }

        $i = 0;

        return preg_replace_callback(
            '/(?<!\?)\?(?!\?)|(?<!:)(:[a-z0-9_]+)/i',
            static function (array $matches) use ($parameters, &$i): string {
                $key = substr($matches[0], 1);

                if (! array_key_exists($i, $parameters) && ! array_key_exists($key, $parameters)) {
                    return $matches[0];
                }

                $value = array_key_exists($i, $parameters) ? $parameters[$i] : $parameters[$key];

                $i++;

                return (string) DoctrineExtension::escapeFunction($value);
            },
            $query,
        );
    }

    public function prettifySql(string $sql): string
    {
        $this->setUpSqlFormatter();

        return $this->sqlFormatter->highlight($sql);
    }

    public function formatSql(string $sql, bool $highlight): string
    {
        $this->setUpSqlFormatter($highlight);

        return $this->sqlFormatter->format($sql);
    }

    private function setUpSqlFormatter(bool $highlight = true): void
    {
        $this->sqlFormatter = new SqlFormatter($highlight ? new HtmlHighlighter([
            HtmlHighlighter::HIGHLIGHT_PRE            => 'class="highlight highlight-sql"',
            HtmlHighlighter::HIGHLIGHT_QUOTE          => 'class="string"',
            HtmlHighlighter::HIGHLIGHT_BACKTICK_QUOTE => 'class="string"',
            HtmlHighlighter::HIGHLIGHT_RESERVED       => 'class="keyword"',
            HtmlHighlighter::HIGHLIGHT_BOUNDARY       => 'class="symbol"',
            HtmlHighlighter::HIGHLIGHT_NUMBER         => 'class="number"',
            HtmlHighlighter::HIGHLIGHT_WORD           => 'class="word"',
            HtmlHighlighter::HIGHLIGHT_ERROR          => 'class="error"',
            HtmlHighlighter::HIGHLIGHT_COMMENT        => 'class="comment"',
            HtmlHighlighter::HIGHLIGHT_VARIABLE       => 'class="variable"',
        ]) : new NullHighlighter());
    }
}
