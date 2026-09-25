<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Application\Handler;

use LectoresBeta\Feedback\Correction\Application\Command\DeleteCorrectionReply;
use LectoresBeta\Feedback\Correction\Application\Service\AnswerableCorrection;
use LectoresBeta\Feedback\Correction\Domain\Repository\CorrectionReplyRepository;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

/**
 * Retirar la respuesta (`FEAT-FBK-005` `RN-6`).
 *
 * Lo que se dijo deja de estar, y no se conserva un historial visible de lo
 * dicho y retirado: un rastro de «aquí había algo» invita a imaginar lo peor.
 *
 * **No se avisa a quien corrigió.** Recibir «te han quitado la respuesta» es
 * peor que no enterarse (`F-18`).
 */
final readonly class DeleteCorrectionReplyHandler
{
    public function __construct(
        private AnswerableCorrection $answerable,
        private CorrectionReplyRepository $replies,
        private TransactionalSession $session,
    ) {
    }

    public function __invoke(DeleteCorrectionReply $command): void
    {
        $correction = $this->answerable->ownedBy($command->correctionId, $command->authorId, needsAWriter: false);
        $reply = $this->replies->ofCorrection($correction->id());

        if (null === $reply) {
            return;
        }

        $this->session->execute(function () use ($reply): void {
            $this->replies->remove($reply);
        });
    }
}
