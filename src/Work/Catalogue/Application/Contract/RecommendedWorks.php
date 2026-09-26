<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Catalogue\Application\Contract;

/**
 * Qué obras merece la pena poner delante de alguien, y en qué orden
 * (`FEAT-COM-017`).
 *
 * **Pregunta, nunca manda** ([`decision:0014`](../../../../../docs/decisions/0014-published-contracts-between-contexts.md)).
 *
 * Existe para que el carrusel de la Home no tenga que volver a decidir qué es
 * recomendable. Esa decisión ya está tomada y es delicada: cinco puertas de
 * visibilidad, la clasificación por edad, lo que el lector ha pedido no ver,
 * y la fórmula de reparto de
 * [`decision:0008`](../../../../../docs/decisions/0008-catalogue-ordering.md).
 * Rehacerla en otro contexto la dejaría escrita dos veces, y la copia que se
 * queda atrás es la que enseña obra inédita a quien no debe.
 *
 * **Quien pregunta trae la edad y lo que el lector excluye**, igual que en
 * `ReadableChapters`. No es comodidad: si esta implementación le preguntara a
 * `User`, un contrato estaría llamando al contrato de otro contexto mientras
 * responde, que es justo lo que `decision:0015` prohíbe para que las
 * referencias cruzadas no se conviertan en una cadena de llamadas.
 *
 * Lo que este contrato **no** hace es saber qué le gusta a nadie. Los géneros
 * los trae quien pregunta, porque el gusto de una persona es de `Community` y
 * de `User`, no de `Work`.
 */
interface RecommendedWorks
{
    /**
     * Las mejores `limit` obras de esos géneros para esa persona, ordenadas
     * por relevancia.
     *
     * Con la lista de géneros vacía responde sobre **todo el catálogo**, que
     * es el segundo tramo de la cadena de relleno: es preferible enseñar algo
     * reciente que no encaje a dejar la Home vacía.
     *
     * Nunca incluye obras de quien pregunta: nadie corrige lo suyo.
     *
     * @param list<string> $genres           en `O`: cualquiera de ellos, no todos
     * @param list<string> $excludedWarnings lo que esta persona ha pedido no ver
     * @param list<string> $blockedAuthorIds con quién tiene un bloqueo, en los
     *                                       dos sentidos
     *                                       ([`FEAT-WRK-012`](../../../../../docs/features/work/FEAT-WRK-012-browse-catalogue.md)
     *                                       `RN-8`). Lo trae quien pregunta
     *                                       por lo mismo que la edad: `Work`
     *                                       no sabe quién ha bloqueado a quién
     *
     * @return list<RecommendedWork>
     */
    public function forReader(
        string $readerId,
        array $genres,
        bool $readerIsOfAge,
        array $excludedWarnings,
        array $blockedAuthorIds,
        int $limit,
    ): array;
}
