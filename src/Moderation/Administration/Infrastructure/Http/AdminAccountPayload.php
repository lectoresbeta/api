<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Administration\Infrastructure\Http;

use LectoresBeta\User\Account\Application\Contract\AdministrableAccount;

/**
 * La forma de una cuenta en el backoffice, en un sitio y no en dos
 * controladores.
 */
final class AdminAccountPayload
{
    /**
     * @return array<string, scalar|null>
     */
    public static function of(AdministrableAccount $account): array
    {
        return [
            'userId' => $account->userId,
            'username' => $account->username,
            'name' => $account->name,
            'email' => $account->email,
            'status' => $account->status,
            'registeredAt' => $account->registeredAt,
            'activatedAt' => $account->activatedAt,
            'lastSignedInAt' => $account->lastSignedInAt,
            'restrictedUntil' => $account->restrictedUntil,
        ];
    }
}
