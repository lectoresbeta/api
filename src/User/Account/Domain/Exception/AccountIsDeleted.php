<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Domain\Exception;

final class AccountIsDeleted extends \DomainException
{
    public static function forUser(string $userId): self
    {
        return new self(\sprintf('The account %s is deleted and cannot change.', $userId));
    }
}
