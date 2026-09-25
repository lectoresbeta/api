<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Questionnaire\Application\Contract;

/**
 * Qué preguntaba el autor **en la versión a la que alguien respondió**
 * (`FEAT-FBK-004`).
 *
 * `CorrectionBriefs` responde lo que se pregunta **ahora**, que es lo que
 * necesita quien va a corregir. Esto responde lo que se preguntaba
 * **entonces**, que es lo que necesita quien lee una corrección entregada
 * hace un mes.
 *
 * Son dos preguntas distintas y por eso son dos contratos: el autor puede
 * haber reescrito el cuestionario mientras el lector escribía, y una
 * respuesta bajo un enunciado que ya no existe es una respuesta sin
 * pregunta.
 *
 * Los cuestionarios se versionan y **las versiones antiguas se conservan**
 * ([`FEAT-WRK-014`](../../../../../docs/features/work/FEAT-WRK-014-configure-questionnaire.md)
 * `RN-4`), así que esto no obliga a guardar nada nuevo: solo a poder leerlo.
 */
interface AnsweredQuestionnaires
{
    /**
     * @return list<AnsweredQuestion> vacío si esa versión ya no existe
     */
    public function questionsOf(string $workId, int $version): array;
}
