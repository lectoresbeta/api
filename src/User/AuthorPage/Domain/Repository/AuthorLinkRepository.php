<?php

declare(strict_types=1);

namespace LectoresBeta\User\AuthorPage\Domain\Repository;

use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\AuthorPage\Domain\Entity\AuthorLink;

interface AuthorLinkRepository
{
    /**
     * Deja las referencias de esta persona **exactamente** en estas.
     *
     * No hay «añadir una»: lo que el formulario manda es la lista como su
     * dueño la ve en pantalla, igual que con las temáticas de una obra.
     * Reconciliar diferencias daría el mismo resultado con más código y una
     * forma más de equivocarse.
     *
     * @param list<AuthorLink> $links
     */
    public function replaceAllOf(UserId $userId, array $links): void;

    /**
     * @return list<AuthorLink>
     */
    public function of(UserId $userId): array;

    /**
     * Las de varias personas a la vez, para pintar una lista de perfiles sin
     * una consulta por tarjeta.
     *
     * @param list<string> $userIds
     *
     * @return array<string, list<AuthorLink>>
     */
    public function ofUsers(array $userIds): array;
}
