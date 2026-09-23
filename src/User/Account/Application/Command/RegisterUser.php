<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Application\Command;

/**
 * The intent behind `POST /auth/register`, already free of HTTP
 * (`FEAT-USR-001`).
 *
 * The plain password travels as a string and stops here: the handler hands it
 * to the hasher and nothing keeps it. It is not a value object on purpose —
 * an object would be passed around, and something that is passed around ends
 * up in a log.
 */
final readonly class RegisterUser
{
    public function __construct(
        public string $email,
        public string $plainPassword,
        public ?string $acceptedTermsVersion,
        public ?string $acceptedPrivacyVersion,
        public ?string $invitationToken = null,
        public ?string $ipAddress = null,
    ) {
    }
}
