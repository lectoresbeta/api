<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Messaging\Application\DTO;

use LectoresBeta\User\Account\Application\Contract\DirectoryEntry;

/**
 * Una fila de la lista de conversaciones (`FEAT-COM-012` `RN-3`).
 *
 * La otra persona viaja **resuelta** —nombre, `@usuario`, avatar— porque una
 * fila con un identificador no se puede pintar, y pedirlos de uno en uno
 * sería una consulta por fila.
 *
 * `lastMessage` es un extracto, no el mensaje entero: la lista enseña una
 * línea, y mandar cuatro mil caracteres por fila para recortarlos en el
 * cliente es mandar una conversación entera por una lista.
 */
final readonly class ConversationRow
{
    public function __construct(
        public string $conversationId,
        public ?DirectoryEntry $other,
        public ?string $lastMessage,
        public ?\DateTimeImmutable $lastMessageAt,
        public bool $lastMessageIsMine,
        public int $unreadCount,
    ) {
    }
}
