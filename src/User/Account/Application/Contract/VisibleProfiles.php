<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Application\Contract;

/**
 * Las tarjetas de perfil que **esta persona puede ver**, de entre estas
 * ([`decision:0014`](../../../../../docs/decisions/0014-published-contracts-between-contexts.md)).
 *
 * Fíjate en cómo está preguntado. No es «dame estos perfiles» —que obligaría
 * a quien llama a acordarse de filtrarlos— sino «dame los que esta persona
 * puede ver». La diferencia es la que separa una regla que se cumple siempre
 * de una que se cumple mientras nadie la olvide, y aquí lo olvidado sería que
 * alguien con el perfil cerrado apareciera en la lista de otro
 * (`FEAT-COM-027` `RN-3`).
 *
 * Qué hace visible a una persona es de `User` y de nadie más: sus ajustes de
 * privacidad, quién la sigue y si su cuenta existe. Quien pregunta no tiene
 * por qué aprender nada de eso.
 *
 * **Por lotes, nunca de una en una.** Existe para pintar una página entera de
 * una lista, y una llamada por fila sería un N+1 escondido detrás de un
 * contrato.
 */
interface VisibleProfiles
{
    /**
     * @param list<string> $userIds
     *
     * @return array<string, DirectoryEntry> indexado por `userId`; **quien no
     *                                       sea visible sencillamente no
     *                                       está**, que es lo mismo que
     *                                       responde el perfil
     */
    public function visibleTo(?string $viewerId, array $userIds): array;
}
