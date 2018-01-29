<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;

class TestDatetimeFunction extends FunctionNode
{
    public function getSql(SqlWalker $sqlWalker)
    {
        return '';
    }

    public function parse(Parser $parser)
    {
        return '';
    }
}
