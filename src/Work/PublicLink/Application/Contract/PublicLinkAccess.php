<?php

declare(strict_types=1);

namespace LectoresBeta\Work\PublicLink\Application\Contract;

/**
 * El contrato publicado de `Work` para quien recoge correcciones anónimas
 * ([`decision:0014`](../../../../../docs/decisions/0014-published-contracts-between-contexts.md)).
 *
 * **Pregunta, no manda.** No crea correcciones, no gasta cupo y no revoca
 * nada: dice qué abre un token y quien llama decide.
 *
 * Quién lleva la cuenta de las correcciones gastadas es `Feedback`, que es
 * quien las tiene. Aquí se publica el tope; contarlas también en este
 * contexto sería una segunda copia del número, y una de las dos se quedaría
 * vieja.
 */
interface PublicLinkAccess
{
    /**
     * @param string      $token     el token en claro, tal y como llegó en la URL
     * @param string|null $chapterId el capítulo que se quiere corregir, si ya se sabe cuál
     */
    public function opening(string $token, ?string $chapterId = null): ?PublicLinkOpening;
}
