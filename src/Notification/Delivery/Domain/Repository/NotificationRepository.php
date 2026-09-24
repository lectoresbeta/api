<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Domain\Repository;

use LectoresBeta\Notification\Delivery\Domain\Entity\Notification;
use LectoresBeta\Notification\Delivery\Domain\Enum\NotificationKind;
use LectoresBeta\Notification\Delivery\Domain\ValueObject\NotificationId;
use LectoresBeta\Notification\Delivery\Domain\ValueObject\RecipientId;
use LectoresBeta\Shared\Domain\Pagination\Cursor;

interface NotificationRepository
{
    public function save(Notification $notification): void;

    /**
     * Whether this exact notice already exists for this recipient and this
     * originating fact.
     *
     * It is the idempotency of `FEAT-NOT-008` `RN-2`, and the same triple is
     * a unique constraint in the database: the check saves the work, the
     * constraint is what actually guarantees it when two workers race.
     */
    public function existsFor(RecipientId $recipientId, NotificationKind $kind, string $sourceEventId): bool;

    public function ofId(NotificationId $id): ?Notification;

    /**
     * La bandeja: lo más reciente primero, con una fila de más para saber si
     * hay página siguiente (`FEAT-NOT-009` `RN-1`).
     *
     * @return list<Notification>
     */
    public function inboxOf(RecipientId $recipientId, bool $unreadOnly, ?Cursor $after, int $limit): array;

    public function countUnread(RecipientId $recipientId): int;

    /**
     * Marca de una vez todo lo que quede sin leer (`RN-5`).
     *
     * En una sola sentencia y no fila a fila: quien lleva meses sin entrar
     * puede tener cientos, y cargarlos todos para marcarlos sería pagar la
     * bandeja entera por vaciar un contador.
     */
    public function markAllRead(RecipientId $recipientId, \DateTimeImmutable $now): void;
}
