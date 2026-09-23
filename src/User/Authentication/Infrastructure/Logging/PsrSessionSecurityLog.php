<?php

declare(strict_types=1);

namespace LectoresBeta\User\Authentication\Infrastructure\Logging;

use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Authentication\Application\Port\SessionSecurityLog;
use Psr\Log\LoggerInterface;

final readonly class PsrSessionSecurityLog implements SessionSecurityLog
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    /**
     * A warning and not an informational line: this is worth waking somebody
     * for, and the level is the decision of the adapter — the use case only
     * knows the fact.
     */
    public function refreshTokenReused(UserId $userId, int $revokedSessions): void
    {
        $this->logger->warning('A revoked refresh token was presented again; every session of the user was revoked.', [
            'userId' => $userId->value(),
            'revokedSessions' => $revokedSessions,
        ]);
    }
}
