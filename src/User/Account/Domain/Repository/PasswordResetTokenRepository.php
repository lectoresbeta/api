<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Domain\Repository;

use LectoresBeta\User\Account\Domain\Entity\PasswordResetToken;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;

interface PasswordResetTokenRepository
{
    public function save(PasswordResetToken $token): void;

    /**
     * Se busca por hash y nunca por el token: el valor en claro existe solo
     * dentro del correo (`FEAT-USR-007` `RN-5`).
     */
    public function ofTokenHash(string $tokenHash): ?PasswordResetToken;

    /**
     * @return list<PasswordResetToken>
     */
    public function liveTokensOf(UserId $userId): array;
}
