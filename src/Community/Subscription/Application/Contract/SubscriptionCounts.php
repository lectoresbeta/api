<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Subscription\Application\Contract;

/**
 * Cuánta gente sigue esta persona y cuánta la sigue
 * ([`decision:0014`](../../../../../docs/decisions/0014-published-contracts-between-contexts.md)).
 *
 * El primer contrato que publica `Community`, y existe para los contadores de
 * la cabecera del perfil (`FEAT-USR-028`). **Dos cifras, nunca las listas**:
 * quien pinta un contador no necesita saber quién está detrás, y entregarlo
 * haría de la privacidad de cada seguidor un problema de quien pregunta —
 * cuando las listas sí tienen su endpoint, con sus reglas (`FEAT-COM-027`).
 *
 * Las dos juntas y no una por llamada: se enseñan juntas, y separarlas serían
 * dos viajes para pintar dos números que van pegados.
 */
interface SubscriptionCounts
{
    public function of(string $userId): SubscriptionCount;
}
