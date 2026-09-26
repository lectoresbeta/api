<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Domain\ValueObject;

use LectoresBeta\Shared\Domain\ValueObject\Uuid;

/**
 * The identity of a person on the platform.
 *
 * It survives everything: changing the username, changing the email, even
 * deleting the account. Corrections and credit movements keep pointing at it
 * once the account is anonymised, which is why identity can never be the
 * email or the username.
 */
final readonly class UserId extends Uuid
{
}
