<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Messaging\Domain\Repository;

use LectoresBeta\Community\Messaging\Domain\Entity\DirectMessage;
use LectoresBeta\Community\Messaging\Domain\ValueObject\ConversationId;
use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Shared\Domain\Pagination\Cursor;

interface DirectMessageRepository
{
    public function save(DirectMessage $message): void;

    /**
     * Los mensajes de una conversación, **del más reciente hacia atrás**.
     *
     * @return list<DirectMessage> con una fila de más, que es cómo se sabe si
     *                             hay página siguiente
     */
    public function of(ConversationId $conversationId, ?Cursor $after, int $limit): array;

    public function lastOf(ConversationId $conversationId): ?DirectMessage;

    /**
     * Cuántos le quedan sin leer a esa persona en esa conversación.
     *
     * **Lo recibido, nunca lo enviado** (`FEAT-COM-012` `RN-5`): marcar como
     * leído lo propio no significa nada.
     */
    public function unreadFor(ConversationId $conversationId, MemberId $member): int;

    /**
     * Cuántos sin leer en cada una de estas, de golpe.
     *
     * Una consulta por fila sería una consulta por conversación para pintar
     * una lista.
     *
     * @param list<string> $conversationIds
     *
     * @return array<string, int>
     */
    public function unreadAmong(array $conversationIds, MemberId $member): array;

    /**
     * Marca lo recibido como leído. Idempotente: no mueve la fecha de lo que
     * ya estaba leído.
     */
    public function markReadFor(ConversationId $conversationId, MemberId $member, \DateTimeImmutable $now): void;

    /**
     * El último mensaje de cada una, para pintar la lista sin una consulta
     * por fila.
     *
     * @param list<string> $conversationIds
     *
     * @return array<string, DirectMessage>
     */
    public function lastAmong(array $conversationIds): array;
}
