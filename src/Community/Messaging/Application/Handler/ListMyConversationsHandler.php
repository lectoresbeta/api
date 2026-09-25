<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Messaging\Application\Handler;

use LectoresBeta\Community\Messaging\Application\DTO\ConversationRow;
use LectoresBeta\Community\Messaging\Application\Query\ListMyConversations;
use LectoresBeta\Community\Messaging\Domain\Entity\Conversation;
use LectoresBeta\Community\Messaging\Domain\Repository\ConversationRepository;
use LectoresBeta\Community\Messaging\Domain\Repository\DirectMessageRepository;
use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Community\Relationship\Domain\Repository\UserBlockRepository;
use LectoresBeta\Shared\Domain\Pagination\Cursor;
use LectoresBeta\Shared\Domain\Pagination\PageSize;
use LectoresBeta\User\Account\Application\Contract\ProfileCards;

/**
 * La sección «Mensajes» (`FEAT-COM-012`).
 *
 * Tres cosas se piden **de golpe y no por fila**: el último mensaje de cada
 * conversación, cuántos quedan sin leer, y quién es la otra parte. Una
 * consulta por fila sería una consulta por conversación para pintar una
 * lista, que es exactamente cómo una pantalla se vuelve lenta el día que
 * empieza a usarse.
 *
 * Con quien hay bloqueo **no aparece**, en ninguno de los dos sentidos, y
 * **no se borra**: el bloqueo se puede deshacer y el hilo vuelve entero
 * (`RN-7`).
 */
final readonly class ListMyConversationsHandler
{
    /**
     * Lo que se enseña de un mensaje en la lista. Mandar cuatro mil
     * caracteres por fila para recortarlos en el cliente es mandar una
     * conversación entera por una lista.
     */
    private const EXCERPT = 140;

    public function __construct(
        private ConversationRepository $conversations,
        private DirectMessageRepository $messages,
        private UserBlockRepository $blocks,
        private ProfileCards $profiles,
    ) {
    }

    /**
     * @return array{rows: list<ConversationRow>, nextCursor: ?string}
     */
    public function __invoke(ListMyConversations $query): array
    {
        $member = MemberId::fromString($query->memberId);
        $limit = PageSize::of($query->limit);

        // `involving` ya resuelve las dos direcciones: quién bloqueó a quién
        // aquí da igual, lo que se decide es si estas dos personas se hablan.
        $hidden = $this->blocks->involving($member);

        $found = $this->conversations->of(
            $member,
            $hidden,
            null === $query->cursor || '' === $query->cursor ? null : Cursor::decode($query->cursor),
            $limit,
        );

        $page = \array_slice($found, 0, $limit);
        $tail = end($page);
        $ids = array_map(static fn (Conversation $one): string => $one->id()->value(), $page);

        $last = $this->messages->lastAmong($ids);
        $unread = $this->messages->unreadAmong($ids, $member);
        $others = $this->profiles->of(array_values(array_unique(array_map(
            static fn (Conversation $one): string => $one->otherThan($member)->value(),
            $page,
        ))));

        $rows = [];

        foreach ($page as $conversation) {
            $id = $conversation->id()->value();
            $message = $last[$id] ?? null;

            $rows[] = new ConversationRow(
                $id,
                // Nula cuando la otra parte es una cuenta eliminada: el hilo
                // sigue existiendo, porque borrarlo sería reescribir la mitad
                // de una conversación que su dueño sí tuvo (`RN-8`).
                $others[$conversation->otherThan($member)->value()] ?? null,
                null === $message ? null : mb_substr($message->body(), 0, self::EXCERPT),
                $message?->sentAt(),
                null !== $message && $message->senderId()->value() === $member->value(),
                $unread[$id] ?? 0,
            );
        }

        return [
            'rows' => $rows,
            'nextCursor' => \count($found) > $limit && false !== $tail && null !== $tail->lastMessageId()
                ? Cursor::of($tail->lastMessageAt(), $tail->lastMessageId()->value())->encode()
                : null,
        ];
    }
}
