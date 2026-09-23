<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Domain\Exception;

final class UserNotFound extends \DomainException
{
    public static function withId(string $userId): self
    {
        return new self(\sprintf('There is no user with id %s.', $userId));
    }

    public static function withUsername(string $username): self
    {
        return new self(\sprintf('There is no user with username @%s.', $username));
    }
}
