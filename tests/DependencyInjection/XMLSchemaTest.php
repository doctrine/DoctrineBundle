<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection;

use DirectoryIterator;
use DOMDocument;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function basename;
use function substr;

class XMLSchemaTest extends TestCase
{
    /** @return array<string, array{0: string}> */
    public static function dataValidateSchemaFiles(): array
    {
        $schemaFiles = [];
        $di          = new DirectoryIterator(__DIR__ . '/Fixtures/config/xml');
        foreach ($di as $element) {
            if (! $element->isFile() || substr($element->getFilename(), -4) !== '.xml') {
                continue;
            }

            $schemaFiles[basename($element->getPathname())] = [$element->getPathname()];
        }

        return $schemaFiles;
    }

    #[DataProvider('dataValidateSchemaFiles')]
    public function testValidateSchema(string $file): void
    {
        $found = false;
        $dom   = new DOMDocument('1.0', 'UTF-8');
        $dom->load($file);

        $xmlns = 'http://symfony.com/schema/dic/doctrine';

        $dbalElements = $dom->getElementsByTagNameNS($xmlns, 'dbal');
        if ($dbalElements->length) {
            $dbalDom     = new DOMDocument('1.0', 'UTF-8');
            $dbalElement = $dbalElements->item(0);
            if ($dbalElement !== null) {
                $dbalNode   = $dbalDom->importNode($dbalElement);
                $configNode = $dbalDom->createElementNS($xmlns, 'config');
                $configNode->appendChild($dbalNode);
                $dbalDom->appendChild($configNode);

                $ret = $dbalDom->schemaValidate(__DIR__ . '/../../config/schema/doctrine-1.0.xsd');
                $this->assertTrue($ret, 'DoctrineBundle Dependency Injection XMLSchema did not validate this XML instance.');
                $found = true;
            }
        }

        $ormElements = $dom->getElementsByTagNameNS($xmlns, 'orm');
        if ($ormElements->length) {
            $ormDom     = new DOMDocument('1.0', 'UTF-8');
            $ormElement = $ormElements->item(0);
            if ($ormElement !== null) {
                $ormNode    = $ormDom->importNode($ormElement);
                $configNode = $ormDom->createElementNS($xmlns, 'config');
                $configNode->appendChild($ormNode);
                $ormDom->appendChild($configNode);

                $ret = $ormDom->schemaValidate(__DIR__ . '/../../config/schema/doctrine-1.0.xsd');
                $this->assertTrue($ret, 'DoctrineBundle Dependency Injection XMLSchema did not validate this XML instance.');
                $found = true;
            }
        }

        $this->assertTrue($found, 'Neither <doctrine:orm> nor <doctrine:dbal> elements found in given XML. Are namespaces configured correctly?');
    }
}
