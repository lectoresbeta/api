<?php

declare(strict_types=1);

namespace LectoresBeta\User\Privacy\Domain\Repository;

use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Privacy\Domain\Entity\UserPrivacySettings;
use LectoresBeta\User\Privacy\Domain\Enum\PrivacyAudience;

interface UserPrivacySettingsRepository
{
    public function save(UserPrivacySettings $settings): void;

    /**
     * `null` when the account has no row, which should not happen: one is
     * written when the account is created (`RN-4`). Whoever asks decides what
     * to do, and the answer is **never** «then everything is allowed» — it is
     * the explicit defaults, which is a different sentence even when it gives
     * the same result today.
     */
    public function ofUser(UserId $userId): ?UserPrivacySettings;

    /**
     * Cómo de abierto está el perfil de cada uno de estos, en una consulta.
     *
     * Existe para el buscador de personas, que filtra una página entera:
     * preguntar uno a uno sería una consulta por fila. Quien no tenga fila
     * **no aparece en el resultado**, y quien pregunta aplica el valor por
     * defecto explícito.
     *
     * @param list<string> $userIds
     *
     * @return array<string, PrivacyAudience>
     */
    public function profileVisibilityOf(array $userIds): array;
}
