<?php

declare(strict_types=1);

namespace LectoresBeta\User\Preferences\Domain\Repository;

use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Preferences\Domain\Enum\ContentWarning;

/**
 * Qué ha decidido no ver cada persona (`FEAT-USR-043`).
 *
 * Solo se guardan las exclusiones: por defecto no se filtra nada (`RN-3`), así
 * que **no tener ninguna fila es una respuesta**, no un dato que falte.
 *
 * Se sustituye la lista entera en vez de añadir y quitar de una en una porque
 * es lo que significa el `PUT` de la pantalla: esto es lo que no quiero ver, y
 * lo que no esté aquí ya no cuenta.
 */
interface ContentPreferencesRepository
{
    /**
     * @return list<ContentWarning>
     */
    public function excludedBy(UserId $userId): array;

    /**
     * @param list<ContentWarning> $warnings
     */
    public function replace(UserId $userId, array $warnings, \DateTimeImmutable $now): void;
}
