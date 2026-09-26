<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Application\Contract;

/**
 * El contrato que permite enseñar una obra fuera de `Work` (`FEAT-COM-028`).
 *
 * **Pregunta, nunca manda** ([`decision:0014`](../../../../../docs/decisions/0014-published-contracts-between-contexts.md)):
 * nada de aquí publica, archiva ni abre una obra.
 *
 * Solo responde por **obras visibles para cualquiera**. Una obra en borrador,
 * archivada o bloqueada por moderación simplemente no está en la respuesta, y
 * eso es lo que hace que una publicación deje de anunciarla en el momento en
 * que deja de existir para los demás (`FEAT-COM-028` `RN-4`). Filtrarlo aquí
 * y no en quien pregunta es lo que impide que la regla más peligrosa del
 * backend acabe escrita dos veces.
 *
 * Es síncrono y no una proyección alimentada por eventos, y la razón es
 * justamente esa: una tarjeta que se actualiza con el retraso de una cola
 * seguiría anunciando durante un rato una obra que un moderador acaba de
 * bloquear.
 */
interface WorkCards
{
    /**
     * Las que existen y son visibles, indexadas por identificador.
     *
     * Solo la versión por lotes, a propósito: esto se pregunta desde una
     * página de muro, y un contrato que invita a un N+1 acaba teniendo uno.
     * Lo que no existe, o no es visible, está **ausente**.
     *
     * @param list<string> $workIds
     *
     * @return array<string, WorkCard>
     */
    public function ofWorks(array $workIds): array;
}
