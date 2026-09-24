<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Application\Contract;

/**
 * Las tarjetas de estas personas, **sin filtrar por privacidad**
 * ([`decision:0014`](../../../../../docs/decisions/0014-published-contracts-between-contexts.md)).
 *
 * Es el hermano peligroso de `VisibleProfiles`, y existe por un caso
 * concreto: **la lista de a quién has bloqueado** (`FEAT-COM-034`). Si se
 * filtrara por privacidad, bloquear a alguien que después cierra su perfil lo
 * haría desaparecer de esa lista, y el bloqueo sería irreversible en la
 * práctica — no se puede deshacer lo que no se puede encontrar.
 *
 * **Solo se usa cuando quien pregunta ya sabe quiénes son.** En cualquier
 * otro sitio —una lista de seguidores, un buscador, un muro— va
 * `VisibleProfiles`, que es el que respeta lo que cada persona decidió
 * enseñar. Si algún día esto aparece en una pantalla donde alguien descubre
 * gente, está mal usado.
 *
 * Una cuenta eliminada tampoco sale: no queda nada que enseñar.
 */
interface ProfileCards
{
    /**
     * @param list<string> $userIds
     *
     * @return array<string, DirectoryEntry> indexado por `userId`
     */
    public function of(array $userIds): array;
}
