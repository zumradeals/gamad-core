<?php

declare(strict_types=1);

namespace Gamad\Core\Tests\Support;

use Gamad\Core\Shared\Application\TransactionManager;

final readonly class SynchronousTransactionManager implements TransactionManager
{
    public function transactional(callable $operation): mixed
    {
        return $operation();
    }
}
