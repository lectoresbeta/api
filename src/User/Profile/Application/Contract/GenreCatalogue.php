<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Application\Contract;

/**
 * El catálogo de temáticas, como contrato publicado
 * ([`decision:0014`](../../../../../docs/decisions/0014-published-contracts-between-contexts.md)).
 *
 * `User` lo posee porque nació para el onboarding —qué le gusta leer a cada
 * persona ([`FEAT-USR-023`](../../../../../docs/features/user/FEAT-USR-023-onboarding-select-genres.md))—
 * y una obra apunta a los mismos códigos. Que la lista viva en un solo sitio
 * es lo que hace que «Ficción» signifique lo mismo en las dos pantallas.
 *
 * **Pregunta al revés de como parecería natural**: qué códigos *no* existen,
 * en vez de cuáles sí. Es la respuesta que hace falta —un código desconocido
 * se rechaza **nombrándolo**, nunca se descarta en silencio— y de paso evita
 * entregar el catálogo entero para validar tres valores.
 *
 * Responde solo por el catálogo **vigente**: una temática retirada deja de
 * poder asignarse, y las obras que ya la tienen siguen apuntando a ella,
 * porque retirar no borra.
 */
interface GenreCatalogue
{
    /**
     * @param list<string> $codes
     *
     * @return list<string> los que no están en el catálogo vigente
     */
    public function unknownAmong(array $codes): array;
}
