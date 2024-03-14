<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection;

use DirectoryIterator;
use DOMDocument;
use PHPUnit\Framework\TestCase;

use function substr;

class XMLSchemaTest extends TestCase
{
    /** @return list<array{0: string}> */
    public static function dataValidateSchemaFiles(): array
    {
        $schemaFiles = [];
        $di          = new DirectoryIterator(__DIR__ . '/Fixtures/config/xml');
        foreach ($di as $element) {
            if (! $element->isFile() || substr($element->getFilename(), -4) !== '.xml') {
                continue;
            }

            $schemaFiles[] = [$element->getPathname()];
        }

        return $schemaFiles;
    }

    /** @dataProvider dataValidateSchemaFiles */
    public function testValidateSchema(string $file): void
    {
        $found = false;
        $dom   = new DOMDocument('1.0', 'UTF-8');
        $dom->load($file);

        $xmlns = 'http://symfony.com/schema/dic/doctrine';

        $dbalElements = $dom->getElementsByTagNameNS($xmlns, 'dbal');
        if ($dbalElements->length) {
            $dbalDom    = new DOMDocument('1.0', 'UTF-8');
            $dbalNode   = $dbalDom->importNode($dbalElements->item(0));
            $configNode = $dbalDom->createElementNS($xmlns, 'config');
            $configNode->appendChild($dbalNode);
            $dbalDom->appendChild($configNode);

            $ret = $dbalDom->schemaValidate(__DIR__ . '/../../config/schema/doctrine-1.0.xsd');
            $this->assertTrue($ret, 'DoctrineBundle Dependency Injection XMLSchema did not validate this XML instance.');
            $found = true;
        }

        $ormElements = $dom->getElementsByTagNameNS($xmlns, 'orm');
        if ($ormElements->length) {
            $ormDom     = new DOMDocument('1.0', 'UTF-8');
            $ormNode    = $ormDom->importNode($ormElements->item(0));
            $configNode = $ormDom->createElementNS($xmlns, 'config');
            $configNode->appendChild($ormNode);
            $ormDom->appendChild($configNode);

            $ret = $ormDom->schemaValidate(__DIR__ . '/../../config/schema/doctrine-1.0.xsd');
            $this->assertTrue($ret, 'DoctrineBundle Dependency Injection XMLSchema did not validate this XML instance.');
            $found = true;
        }

        $this->assertTrue($found, 'Neither <doctrine:orm> nor <doctrine:dbal> elements found in given XML. Are namespaces configured correctly?');
    }
}
