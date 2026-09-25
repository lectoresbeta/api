<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Chapter\Application\Contract;

/**
 * `Work` responde si esta persona puede leer este capítulo
 * ([`decision:0014`](../../../../../docs/decisions/0014-published-contracts-between-contexts.md)).
 *
 * **Pregunta, nunca ordena.** No concede acceso, no abre nada y no cambia
 * ninguna modalidad.
 *
 * Existe para `FEAT-COM-036`: `Community` deja comentar y apoyar bajo el
 * texto de un capítulo, y necesita saber quién puede verlo. La alternativa
 * era que reconstruyera la regla, y la regla de lectura es la más peligrosa
 * del backend — escrita dos veces, tarde o temprano una de las dos se queda
 * atrás.
 *
 * **Los dos booleanos los trae quien pregunta**, y es lo que mantiene la
 * regla 4 de
 * [`decision:0015`](../../../../../docs/decisions/0015-work-and-reading-ask-each-other.md):
 * si este contrato preguntara por su cuenta a `Reading` si alguien es lector
 * beta y a `User` si tiene edad, estaría llamando a otros contratos mientras
 * responde, que es justo lo que separa un ciclo de referencias de uno de
 * llamadas. Quien pregunta ya los tiene —los pide como los pide
 * `EligibleCorrectionBrief`— y aquí solo se combinan.
 *
 * Hermano de `CorrectionBriefs` y distinto a propósito: aquel responde «qué
 * se pregunta en este capítulo y si se puede corregir», y arrastra el
 * cuestionario, que es **texto del autor**. Para decidir sobre un «me gusta»
 * no hace falta nada de eso.
 */
interface ReadableChapters
{
    /**
     * En qué obra está el capítulo.
     *
     * Hace falta para poder preguntarle a `Reading` por el acceso de lector
     * beta, que se concede **por obra** y no por capítulo. Es lo único que
     * revela —que un capítulo pertenece a una obra, sin decir cuál ni si se
     * puede ver—, y evita que quien pregunta tenga que guardar esa relación
     * por su cuenta: una copia así envejece, y con ella el control de acceso.
     */
    public function workOf(string $chapterId): ?string;

    /**
     * `null` si el capítulo no existe. No existir y no poder verse se
     * distinguen aquí porque quien pregunta responde lo mismo a los dos, y
     * es mejor que esa decisión sea suya y explícita.
     */
    public function of(
        string $chapterId,
        string $readerId,
        bool $readerIsOfAge,
        bool $isBetaReader,
    ): ?ChapterAccess;
}
