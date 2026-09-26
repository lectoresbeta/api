<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Unit\Credits;

use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

final class ImmediateSession implements TransactionalSession
{
    public function execute(callable $operation): mixed
    {
        return $operation();
    }
}
