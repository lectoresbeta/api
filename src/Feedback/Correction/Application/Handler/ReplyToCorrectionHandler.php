<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Application\Handler;

use LectoresBeta\Feedback\Correction\Application\Command\ReplyToCorrection;
use LectoresBeta\Feedback\Correction\Application\Service\AnswerableCorrection;
use LectoresBeta\Feedback\Correction\Domain\Entity\CorrectionReply;
use LectoresBeta\Feedback\Correction\Domain\Event\FeedbackReplied;
use LectoresBeta\Feedback\Correction\Domain\Exception\EmptyReply;
use LectoresBeta\Feedback\Correction\Domain\Repository\CorrectionReplyRepository;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\AuthorId;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\CorrectionReplyId;
use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

/**
 * El autor contesta a una corrección (`FEAT-FBK-005`).
 *
 * **Una respuesta, no una conversación.** Quien corrigió no replica: poner a
 * autor y corrector a discutir sobre un texto es crear el conflicto que la
 * moderación existe para resolver. Quien discrepe tiene dos salidas mejores
 * —no valorarla, o reclamarla si es fraudulenta— y el único hilo de ida y
 * vuelta de la plataforma es el del moderador con cada parte por separado.
 *
 * Volver a enviarla **sustituye** la que había, y solo la primera avisa
 * (`RN-2`): recibir tres notificaciones porque alguien corrige sus erratas
 * es ruido.
 */
final readonly class ReplyToCorrectionHandler
{
    private const MAX_LENGTH = 4000;

    public function __construct(
        private AnswerableCorrection $answerable,
        private CorrectionReplyRepository $replies,
        private EventPublisher $events,
        private TransactionalSession $session,
        private Clock $clock,
    ) {
    }

    public function __invoke(ReplyToCorrection $command): void
    {
        $correction = $this->answerable->ownedBy($command->correctionId, $command->authorId);

        $body = mb_substr(trim($command->body), 0, self::MAX_LENGTH);

        if ('' === $body) {
            throw EmptyReply::create();
        }

        $now = $this->clock->now();
        $existing = $this->replies->ofCorrection($correction->id());

        $this->session->execute(function () use ($existing, $correction, $command, $body, $now): void {
            if (null !== $existing) {
                $existing->edit($body, $now);
                $this->replies->save($existing);

                return;
            }

            $this->replies->save(new CorrectionReply(
                CorrectionReplyId::generate(),
                $correction->id(),
                AuthorId::fromString($command->authorId),
                $body,
                $now,
            ));
        });

        if (null !== $existing) {
            return;
        }

        $readerId = $correction->readerId();

        if (null === $readerId) {
            return;
        }

        // Sin el texto, como ningún contenido en esta plataforma: una cola
        // que persiste, reintenta y aparca mensajes no es sitio para lo que
        // dos personas se dicen.
        $this->events->publish(new FeedbackReplied(
            EventId::generate(),
            $correction->id(),
            $correction->chapterId(),
            $correction->workId(),
            $readerId,
            $now,
        ));
    }
}
