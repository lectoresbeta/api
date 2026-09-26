<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Application\Command;

/**
 * Following the link from the activation email (`FEAT-USR-020`).
 *
 * The token is the whole authorisation: no session is needed, because the
 * person may well open the email on a different device from the one they
 * signed up on.
 */
final readonly class ActivateAccount
{
    public function __construct(public string $token)
    {
    }
}
