<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Messaging\Application\Handler;

use LectoresBeta\Community\Messaging\Application\Command\MarkConversationRead;
use LectoresBeta\Community\Messaging\Application\Service\MyConversation;
use LectoresBeta\Community\Messaging\Domain\Repository\DirectMessageRepository;
use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

/**
 * Marcar una conversación como leída (`FEAT-COM-012` `RN-5`, `RN-6`).
 *
 * **Lo recibido, nunca lo enviado.** Marcar como leído lo propio no significa
 * nada, y contarlo haría que el contador de quien escribe bajara al abrir su
 * propio hilo.
 *
 * Idempotente y sin mover la fecha de lo ya leído: la pantalla marca al abrir
 * y también con un gesto, así que la segunda vez llega sola.
 */
final readonly class MarkConversationReadHandler
{
    public function __construct(
        private MyConversation $mine,
        private DirectMessageRepository $messages,
        private TransactionalSession $session,
        private Clock $clock,
    ) {
    }

    public function __invoke(MarkConversationRead $command): int
    {
        $conversation = $this->mine->of($command->memberId, $command->conversationId);
        $member = MemberId::fromString($command->memberId);

        $this->session->execute(function () use ($conversation, $member): void {
            $this->messages->markReadFor($conversation->id(), $member, $this->clock->now());
        });

        return $this->messages->unreadFor($conversation->id(), $member);
    }
}
