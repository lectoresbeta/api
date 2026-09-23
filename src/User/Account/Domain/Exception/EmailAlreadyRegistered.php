<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Domain\Exception;

/**
 * Deliberately carries no email address in its message: the API response must
 * not let anyone find out whether a given address has an account
 * (`FEAT-USR-001` `RN-14`).
 */
final class EmailAlreadyRegistered extends \DomainException
{
    public static function create(): self
    {
        return new self('That email address cannot be used to register.');
    }
}
