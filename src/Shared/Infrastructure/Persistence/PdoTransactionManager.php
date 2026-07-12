<?php

declare(strict_types=1);

namespace Gamad\Core\Shared\Infrastructure\Persistence;

use Gamad\Core\Shared\Application\TransactionManager;
use PDO;
use Throwable;

final readonly class PdoTransactionManager implements TransactionManager
{
    public function __construct(private PDO $connection)
    {
    }

    public function transactional(callable $operation): mixed
    {
        if ($this->connection->inTransaction()) {
            return $operation();
        }

        $this->connection->beginTransaction();

        try {
            $result = $operation();
            $this->connection->commit();

            return $result;
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            throw $exception;
        }
    }
}
