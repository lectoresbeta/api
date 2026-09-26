<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Messaging\Infrastructure\Http;

use LectoresBeta\Community\Messaging\Application\DTO\ConversationRow;
use LectoresBeta\Community\Messaging\Application\DTO\MessageRow;
use Symfony\Component\HttpFoundation\Request;

/**
 * La forma de las dos listas de «Mensajes» en HTTP (`FEAT-COM-012`).
 *
 * Está aquí y no en cada controlador porque las dos comparten el sobre
 * —`data` y `pageInfo`— y porque lo que se deja fuera importa: la lista de
 * conversaciones lleva **un extracto** del último mensaje, nunca el mensaje
 * entero. El cuerpo completo solo viaja al abrir el hilo.
 */
final readonly class MessagingBody
{
    /**
     * @param list<ConversationRow> $rows
     *
     * @return array<string, mixed>
     */
    public static function conversations(array $rows, ?string $nextCursor): array
    {
        return self::page(array_map(
            static fn (ConversationRow $row): array => [
                'conversationId' => $row->conversationId,
                // Nulo cuando la otra parte es una cuenta eliminada: el hilo
                // sigue, porque borrarlo reescribiría una conversación que su
                // dueño sí tuvo (`RN-8`).
                'other' => null === $row->other ? null : [
                    'userId' => $row->other->userId,
                    'username' => $row->other->username,
                    'name' => $row->other->name,
                    'avatarUrl' => $row->other->avatarUrl,
                ],
                'lastMessage' => $row->lastMessage,
                'lastMessageAt' => $row->lastMessageAt?->format(\DATE_ATOM),
                'lastMessageIsMine' => $row->lastMessageIsMine,
                'unreadCount' => $row->unreadCount,
            ],
            $rows,
        ), $nextCursor);
    }

    /**
     * @param list<MessageRow> $rows
     *
     * @return array<string, mixed>
     */
    public static function messages(array $rows, ?string $nextCursor): array
    {
        return self::page(array_map(
            static fn (MessageRow $row): array => [
                'messageId' => $row->messageId,
                'senderId' => $row->senderId,
                'mine' => $row->mine,
                'body' => $row->body,
                'sentAt' => $row->sentAt->format(\DATE_ATOM),
                'read' => $row->read,
            ],
            $rows,
        ), $nextCursor);
    }

    public static function cursor(Request $request): ?string
    {
        $value = $request->query->get('cursor');

        return \is_string($value) && '' !== $value ? $value : null;
    }

    public static function limit(Request $request): ?int
    {
        return $request->query->has('limit') ? $request->query->getInt('limit') : null;
    }

    /**
     * @param list<array<string, mixed>> $data
     *
     * @return array<string, mixed>
     */
    private static function page(array $data, ?string $nextCursor): array
    {
        return [
            'data' => $data,
            'pageInfo' => [
                'nextCursor' => $nextCursor,
                'hasNextPage' => null !== $nextCursor,
            ],
        ];
    }
}
