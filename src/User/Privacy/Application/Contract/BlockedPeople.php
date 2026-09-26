<?php

declare(strict_types=1);

namespace LectoresBeta\User\Privacy\Application\Contract;

/**
 * Con quién tiene un bloqueo esta persona, en cualquiera de los dos sentidos
 * ([`decision:0014`](../../../../../docs/decisions/0014-published-contracts-between-contexts.md)).
 *
 * **Sin dirección, y eso es la regla, no una simplificación**: un bloqueo
 * esconde a los dos lados (`FEAT-COM-034`). Quien pregunta no tiene que
 * averiguar quién bloqueó a quién, ni podría — el sentido es dato de `User` y
 * saberlo no cambia lo que hay que hacer con la lista.
 *
 * Responde **la lista entera**, no un filtro sobre unos candidatos, porque
 * quien la necesita la usa para excluir dentro de una consulta y todavía no
 * sabe a quién va a encontrar. Es una lista corta por naturaleza: bloquear es
 * excepcional.
 */
interface BlockedPeople
{
    /**
     * @return list<string> los identificadores con los que hay bloqueo; vacía
     *                      para quien no ha bloqueado ni ha sido bloqueado
     */
    public function blockedWith(string $userId): array;
}
