<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Application\Contract;

/**
 * Everything another context needs to send somebody their activation link.
 *
 * Deliberately not the `User` aggregate, nor anything that leads to it: a
 * published contract hands over **data**, and the consumer that receives an
 * entity ends up reaching through it.
 *
 * `token` is the plain, single-use secret. It is safe here and would not be
 * safe in an integration event: this value is handed over in process and
 * dies with the request, whereas a message on RabbitMQ is persisted, retried
 * and parked in a failure queue that people read.
 */
final readonly class ActivationLink
{
    public function __construct(
        public string $email,
        public string $username,
        public string $token,
        public \DateTimeImmutable $expiresAt,
    ) {
    }
}
