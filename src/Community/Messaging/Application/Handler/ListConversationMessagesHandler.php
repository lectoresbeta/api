<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Messaging\Application\Handler;

use LectoresBeta\Community\Messaging\Application\DTO\MessageRow;
use LectoresBeta\Community\Messaging\Application\Query\ListConversationMessages;
use LectoresBeta\Community\Messaging\Application\Service\MyConversation;
use LectoresBeta\Community\Messaging\Domain\Entity\DirectMessage;
use LectoresBeta\Community\Messaging\Domain\Repository\DirectMessageRepository;
use LectoresBeta\Shared\Domain\Pagination\Cursor;
use LectoresBeta\Shared\Domain\Pagination\PageSize;

/**
 * Los mensajes de una conversación (`FEAT-COM-012` `RN-4`).
 *
 * **Del más reciente hacia atrás**, que es como se lee una conversación: lo
 * que hace falta al abrirla es lo último, no lo primero.
 */
final readonly class ListConversationMessagesHandler
{
    public function __construct(
        private MyConversation $mine,
        private DirectMessageRepository $messages,
    ) {
    }

    /**
     * @return array{rows: list<MessageRow>, nextCursor: ?string}
     */
    public function __invoke(ListConversationMessages $query): array
    {
        $conversation = $this->mine->of($query->memberId, $query->conversationId);
        $limit = PageSize::of($query->limit);

        $found = $this->messages->of(
            $conversation->id(),
            null === $query->cursor || '' === $query->cursor ? null : Cursor::decode($query->cursor),
            $limit,
        );

        $page = \array_slice($found, 0, $limit);
        $last = end($page);

        return [
            'rows' => array_map(
                static fn (DirectMessage $message): MessageRow => new MessageRow(
                    $message->id()->value(),
                    $message->senderId()->value(),
                    $message->senderId()->value() === $query->memberId,
                    $message->body(),
                    $message->sentAt(),
                    $message->isRead(),
                ),
                $page,
            ),
            'nextCursor' => \count($found) > $limit && false !== $last
                ? Cursor::of($last->sentAt(), $last->id()->value())->encode()
                : null,
        ];
    }
}
