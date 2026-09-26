<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Subscription\Application\Contract;

/**
 * Los dos números de la cabecera del perfil (`FEAT-USR-028`).
 *
 * **Cuentan todo lo que hay, sin filtrar por privacidad.** Es deliberado y
 * tiene una consecuencia que conviene conocer: si alguien con el perfil
 * cerrado te sigue, tu contador dice 12 y tu lista de seguidores enseña 11
 * (`FEAT-COM-027` `RN-3`). Filtrar la cifra costaría resolver la visibilidad
 * de cada seguidor para pintar un número, y además la haría distinta para
 * cada visitante, que es peor: el contador dejaría de ser un dato de esa
 * persona para ser un dato de quien mira.
 */
final readonly class SubscriptionCount
{
    public function __construct(
        public int $following,
        public int $followers,
    ) {
    }
}
