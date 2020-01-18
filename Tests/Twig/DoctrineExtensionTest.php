<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests\Twig;

use Doctrine\Bundle\DoctrineBundle\Twig\DoctrineExtension;
use PHPUnit\Framework\TestCase;

class DoctrineExtensionTest extends TestCase
{
    public function testReplaceQueryParametersWithPostgresCasting() : void
    {
        $extension  = new DoctrineExtension();
        $query      = 'a=? OR (1)::string OR b=?';
        $parameters = [1, 2];

        $result = $extension->replaceQueryParameters($query, $parameters);
        $this->assertEquals('a=1 OR (1)::string OR b=2', $result);
    }

    public function testReplaceQueryParametersWithStartingIndexAtOne() : void
    {
        $extension  = new DoctrineExtension();
        $query      = 'a=? OR b=?';
        $parameters = [
            1 => 1,
            2 => 2,
        ];

        $result = $extension->replaceQueryParameters($query, $parameters);
        $this->assertEquals('a=1 OR b=2', $result);
    }

    public function testReplaceQueryParameters() : void
    {
        $extension  = new DoctrineExtension();
        $query      = 'a=? OR b=?';
        $parameters = [
            1,
            2,
        ];

        $result = $extension->replaceQueryParameters($query, $parameters);
        $this->assertEquals('a=1 OR b=2', $result);
    }

    public function testReplaceQueryParametersWithNamedIndex() : void
    {
        $extension  = new DoctrineExtension();
        $query      = 'a=:a OR b=:b';
        $parameters = [
            'a' => 1,
            'b' => 2,
        ];

        $result = $extension->replaceQueryParameters($query, $parameters);
        $this->assertEquals('a=1 OR b=2', $result);
    }

    public function testEscapeBinaryParameter() : void
    {
        $binaryString = pack('H*', '9d40b8c1417f42d099af4782ec4b20b6');
        $this->assertEquals('0x9D40B8C1417F42D099AF4782EC4B20B6', DoctrineExtension::escapeFunction($binaryString));
    }

    public function testEscapeStringParameter() : void
    {
        $this->assertEquals("'test string'", DoctrineExtension::escapeFunction('test string'));
    }

    public function testEscapeArrayParameter() : void
    {
        $this->assertEquals("1, NULL, 'test', foo", DoctrineExtension::escapeFunction([1, null, 'test', new DummyClass('foo')]));
    }

    public function testEscapeObjectParameter() : void
    {
        $object = new DummyClass('bar');
        $this->assertEquals('bar', DoctrineExtension::escapeFunction($object));
    }

    public function testEscapeNullParameter() : void
    {
        $this->assertEquals('NULL', DoctrineExtension::escapeFunction(null));
    }

    public function testEscapeBooleanParameter() : void
    {
        $this->assertEquals('1', DoctrineExtension::escapeFunction(true));
    }
}

class DummyClass
{
    /** @var string */
    protected $str;

    public function __construct(string $str)
    {
        $this->str = $str;
    }

    public function __toString() : string
    {
        return $this->str;
    }
}
