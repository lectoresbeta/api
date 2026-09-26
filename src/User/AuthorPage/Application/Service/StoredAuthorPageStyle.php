<?php

declare(strict_types=1);

namespace LectoresBeta\User\AuthorPage\Application\Service;

use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\AuthorPage\Domain\Entity\AuthorPageStyle;
use LectoresBeta\User\AuthorPage\Domain\Repository\AuthorPageStyleRepository;

/**
 * El estilo de alguien, creándolo si nunca lo tocó (`FEAT-USR-016` `RN-3`).
 *
 * Lo necesitan leer, guardar y **pintar el perfil público**, y las tres
 * copias serían tres sitios donde recordar que una fila ausente significa
 * `CLASSIC` y `SLATE` y no un hueco.
 */
final readonly class StoredAuthorPageStyle
{
    public function __construct(
        private AuthorPageStyleRepository $styles,
        private Clock $clock,
    ) {
    }

    public function of(UserId $userId): AuthorPageStyle
    {
        return $this->styles->ofUser($userId) ?? new AuthorPageStyle($userId, $this->clock->now());
    }
}
