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

namespace Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection;

class XMLSchemaTest extends \PHPUnit_Framework_TestCase
{
    static public function dataValidateSchemaFiles()
    {
        $schemaFiles = array();
        $di = new \DirectoryIterator(__DIR__."/Fixtures/config/xml");
        foreach ($di as $element) {
            if ($element->isFile() && substr($element->getFilename(), -4) === ".xml") {
                $schemaFiles[] = array($element->getPathname());
            }
        }

        return $schemaFiles;
    }

    /**
     * @dataProvider dataValidateSchemaFiles
     */
    public function testValidateSchema($file)
    {
        $found = false;
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->load($file);

        $xmlns = "http://symfony.com/schema/dic/doctrine";

        $dbalElements = $dom->getElementsByTagNameNS($xmlns, 'dbal');
        if ($dbalElements->length) {
            $dbalDom = new \DOMDocument('1.0', 'UTF-8');
            $dbalNode = $dbalDom->importNode($dbalElements->item(0));
            $configNode = $dbalDom->createElementNS($xmlns, 'config');
            $configNode->appendChild($dbalNode);
            $dbalDom->appendChild($configNode);

            $ret = $dbalDom->schemaValidate(__DIR__."/../../Resources/config/schema/doctrine-1.0.xsd");
            $this->assertTrue($ret, "DoctrineBundle Dependency Injection XMLSchema did not validate this XML instance.");
            $found = true;
        }

        $ormElements = $dom->getElementsByTagNameNS($xmlns, 'orm');
        if ($ormElements->length) {
            $ormDom = new \DOMDocument('1.0', 'UTF-8');
            $ormNode = $ormDom->importNode($ormElements->item(0));
            $configNode = $ormDom->createElementNS($xmlns, 'config');
            $configNode->appendChild($ormNode);
            $ormDom->appendChild($configNode);

            $ret = $ormDom->schemaValidate(__DIR__."/../../Resources/config/schema/doctrine-1.0.xsd");
            $this->assertTrue($ret, "DoctrineBundle Dependency Injection XMLSchema did not validate this XML instance.");
            $found = true;
        }

        $this->assertTrue($found, "Neither <doctrine:orm> nor <doctrine:dbal> elements found in given XML. Are namespaces configured correctly?");
    }
}
