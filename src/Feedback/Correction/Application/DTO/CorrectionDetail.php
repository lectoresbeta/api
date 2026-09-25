<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Application\DTO;

/**
 * Una corrección entregada, entera (`FEAT-FBK-004`).
 *
 * `answers` viene vacío cuando la corrección está retenida por descubierto
 * ([`FEAT-CRD-018`](../../../../../docs/features/credits/FEAT-CRD-018-negative-balance.md)):
 * el autor ve **que existe y de quién es**, no lo que dice. Ocultarla entera
 * le haría creer que nadie le ha corregido, que es peor y además falso.
 *
 * Los enunciados son los de **la versión que se respondió**, no los de ahora:
 * el autor puede haber reescrito el cuestionario mientras el lector escribía,
 * y una respuesta bajo una pregunta que ya no existe es una respuesta sin
 * pregunta.
 */
final readonly class CorrectionDetail
{
    /**
     * @param list<AnsweredQuestionView> $answers
     */
    public function __construct(
        public string $correctionId,
        public string $workId,
        public string $chapterId,
        public ?string $chapterTitle,
        public ?string $readerId,
        public ?string $authorLabel,
        public string $origin,
        public string $visibility,
        public int $questionnaireVersion,
        public string $submittedAt,
        /**
         * Si su destinatario la ha abierto. **Nulo para quien la escribió**:
         * saberlo sería una confirmación de lectura entre dos personas que no
         * han elegido tener una conversación (`F-14`).
         */
        public ?bool $read,
        public ?bool $helpful,
        /** Lo que contestó el autor, si contestó. La ven las dos partes. */
        public ?string $reply,
        public array $answers,
    ) {
    }
}
