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

        $result = $extension->replaceQueryParameters($query, $parameters, false);
        $this->assertEquals('a=1 OR (1)::string OR b=2', $result);
    }
}
