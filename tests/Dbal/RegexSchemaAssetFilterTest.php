<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests\Dbal;

use Doctrine\Bundle\DoctrineBundle\Dbal\RegexSchemaAssetFilter;
use PHPUnit\Framework\TestCase;

class RegexSchemaAssetFilterTest extends TestCase
{
    public function testShouldIncludeAsset(): void
    {
        $filter = new RegexSchemaAssetFilter('~^(?!t_)~');

        $this->assertTrue($filter('do_not_t_ignore_me'));
        $this->assertFalse($filter('t_ignore_me'));
    }
}
