<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Middleware\NPlusOne;

use Doctrine\Bundle\DoctrineBundle\Middleware\NPlusOneGuard;
use Doctrine\DBAL\Driver\Middleware\AbstractStatementMiddleware;
use Doctrine\DBAL\Driver\Result as ResultInterface;
use Doctrine\DBAL\Driver\Statement as StatementInterface;
use Doctrine\DBAL\ParameterType;

/** @internal */
final class Statement extends AbstractStatementMiddleware
{
    /** @var array<int|string, mixed> */
    private array $params = [];

    public function __construct(
        StatementInterface $statement,
        private readonly NPlusOneGuard $guard,
        private readonly string $connectionName,
        private readonly string $sql,
    ) {
        parent::__construct($statement);
    }

    public function bindValue(int|string $param, mixed $value, ParameterType $type): void
    {
        $this->params[$param] = $value;

        parent::bindValue($param, $value, $type);
    }

    public function execute(): ResultInterface
    {
        $this->guard->track($this->connectionName, $this->sql, $this->params);

        return parent::execute();
    }
}
