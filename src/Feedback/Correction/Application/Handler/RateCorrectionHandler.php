<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Application\Handler;

use LectoresBeta\Feedback\Correction\Application\Command\RateCorrection;
use LectoresBeta\Feedback\Correction\Application\Service\AnswerableCorrection;
use LectoresBeta\Feedback\Correction\Domain\Event\FeedbackRatedPositively;
use LectoresBeta\Feedback\Correction\Domain\Repository\CorrectionRepository;
use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

/**
 * El autor marca una corrección como útil o no útil (`FEAT-FBK-006`).
 *
 * **Binaria, y resuelve `F-5`.** Una escala fina invita a puntuar a la baja
 * por desacuerdo literario, y eso es justo lo que no queremos medir: la
 * pregunta es «¿te ha servido?», no «¿cuánto te ha gustado lo que dice?».
 *
 * **No paga.** Hubo un diseño en el que valorar positivamente abonaba cinco
 * créditos; [`decision:0006`](../../../../../docs/decisions/0006-credit-system.md)
 * lo sustituyó por la propina, que el autor decide. La diferencia no es de
 * importe: un abono automático convierte el pulgar en un botón de dinero, y a
 * partir de ahí deja de significar «me ha servido».
 *
 * Se puede cambiar las veces que haga falta, pero **solo la primera positiva
 * anuncia nada** (`RN-7`): avisar a alguien de que su corrección ha dejado de
 * ser útil es una crueldad sin función.
 */
final readonly class RateCorrectionHandler
{
    public function __construct(
        private AnswerableCorrection $answerable,
        private CorrectionRepository $corrections,
        private EventPublisher $events,
        private TransactionalSession $session,
        private Clock $clock,
    ) {
    }

    public function __invoke(RateCorrection $command): void
    {
        // Una corrección por enlace público **sí** se valora: no hay a quién
        // avisar, y al autor le sirve igual para ordenar lo que ha recibido.
        $correction = $this->answerable->ownedBy($command->correctionId, $command->authorId, needsAWriter: false);

        if ($command->helpful === $correction->helpful()) {
            return;
        }

        $announces = $command->helpful && null === $correction->helpful();
        $now = $this->clock->now();

        $this->session->execute(function () use ($correction, $command, $now): void {
            $correction->rate($command->helpful, $now);
            $this->corrections->save($correction);
        });

        $readerId = $correction->readerId();

        if (!$announces || null === $readerId) {
            return;
        }

        $this->events->publish(new FeedbackRatedPositively(
            EventId::generate(),
            $correction->id(),
            $correction->chapterId(),
            $correction->workId(),
            $readerId,
            $now,
        ));
    }
}
