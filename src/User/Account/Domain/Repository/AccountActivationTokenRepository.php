<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Domain\Repository;

use LectoresBeta\User\Account\Domain\Entity\AccountActivationToken;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;

interface AccountActivationTokenRepository
{
    public function save(AccountActivationToken $token): void;

    /**
     * Looked up by hash, never by the token itself: the plain value exists
     * only inside the email (`FEAT-USR-020`).
     */
    public function ofTokenHash(string $tokenHash): ?AccountActivationToken;

    /**
     * @return list<AccountActivationToken>
     */
    public function liveTokensOf(UserId $userId): array;
}
