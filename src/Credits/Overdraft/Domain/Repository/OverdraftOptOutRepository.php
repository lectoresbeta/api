<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Overdraft\Domain\Repository;

use LectoresBeta\Credits\Account\Domain\ValueObject\UserId;
use LectoresBeta\Credits\Overdraft\Domain\Entity\OverdraftOptOut;

interface OverdraftOptOutRepository
{
    public function ofUser(UserId $userId): ?OverdraftOptOut;

    public function save(OverdraftOptOut $optOut): void;

    public function remove(OverdraftOptOut $optOut): void;
}
