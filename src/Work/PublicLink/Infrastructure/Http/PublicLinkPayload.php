<?php

declare(strict_types=1);

namespace LectoresBeta\Work\PublicLink\Infrastructure\Http;

use LectoresBeta\Work\PublicLink\Application\DTO\PublicLinkView;

/**
 * La forma de un enlace en la API, en un sitio y no en dos controladores
 * (`FEAT-WRK-010`).
 *
 * Lo que garantiza es lo que **no** sale: el token no está aquí, así que no
 * puede escaparse por el listado (`RN-2`).
 */
final class PublicLinkPayload
{
    /**
     * @return array<string, scalar|null>
     */
    public static function of(PublicLinkView $link): array
    {
        return [
            'publicLinkId' => $link->publicLinkId,
            'label' => $link->label,
            'maxCorrections' => $link->maxCorrections,
            'createdAt' => $link->createdAt,
            'expiresAt' => $link->expiresAt,
            'revokedAt' => $link->revokedAt,
            'usable' => $link->usable,
        ];
    }
}
