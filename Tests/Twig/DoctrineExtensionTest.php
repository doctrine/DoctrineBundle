<?php
/*
 * This file is part of the Doctrine Bundle
 *
 * The code was originally distributed inside the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 * (c) Doctrine Project, Benjamin Eberlei <kontakt@beberlei.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
*/

namespace Doctrine\Bundle\DoctrineBundle\Tests\Twig;

use Doctrine\Bundle\DoctrineBundle\Twig\DoctrineExtension;

class DoctrineExtensionTest extends \PHPUnit_Framework_TestCase
{
    public function testReplaceQueryParametersWithPostgresCasting()
    {
        $extension = new DoctrineExtension();
        $query = 'a=? OR (1)::string OR b=?';
        $parameters = array(1, 2);

        $result = $extension->replaceQueryParameters($query, $parameters);
        $this->assertEquals('a=1 OR (1)::string OR b=2', $result);
    }

    public function testReplaceQueryParametersWithStartingIndexAtOne()
    {
        $extension = new DoctrineExtension();
        $query = 'a=? OR b=?';
        $parameters = array(
            1 => 1,
            2 => 2
        );

        $result = $extension->replaceQueryParameters($query, $parameters);
        $this->assertEquals('a=1 OR b=2', $result);
    }

    public function testReplaceQueryParameters()
    {
        $extension = new DoctrineExtension();
        $query = 'a=? OR b=?';
        $parameters = array(
            1,
            2
        );

        $result = $extension->replaceQueryParameters($query, $parameters);
        $this->assertEquals('a=1 OR b=2', $result);
    }

    public function testReplaceQueryParametersWithNamedIndex()
    {
        $extension = new DoctrineExtension();
        $query = 'a=:a OR b=:b';
        $parameters = array(
            'a' => 1,
            'b' => 2
        );

        $result = $extension->replaceQueryParameters($query, $parameters);
        $this->assertEquals('a=1 OR b=2', $result);
    }

    public function testEscapeBinaryParameter()
    {
        $binaryString = pack('H*', '9d40b8c1417f42d099af4782ec4b20b6');
        $this->assertEquals('0x9D40B8C1417F42D099AF4782EC4B20B6', DoctrineExtension::escapeFunction($binaryString));
    }

    public function testEscapeStringParameter()
    {
        $this->assertEquals("'test string'", DoctrineExtension::escapeFunction('test string'));
    }

    public function testEscapeArrayParameter()
    {
        $this->assertEquals("1, NULL, 'test', foo", DoctrineExtension::escapeFunction(array(1, null, 'test', new DummyClass('foo'))));
    }

    public function testEscapeObjectParameter()
    {
        $object = new DummyClass('bar');
        $this->assertEquals('bar', DoctrineExtension::escapeFunction($object));
    }

    public function testEscapeNullParameter()
    {
        $this->assertEquals('NULL', DoctrineExtension::escapeFunction(null));
    }

    public function testEscapeBooleanParameter()
    {
        $this->assertEquals('1', DoctrineExtension::escapeFunction(true));
    }
}

class DummyClass
{
    protected $str;

    public function __construct($str)
    {
        $this->str = $str;
    }

    public function __toString()
    {
        return $this->str;
    }
}