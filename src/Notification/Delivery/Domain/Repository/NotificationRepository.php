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

    /**
     * La fila de ese hecho para esa persona, si ya existe.
     *
     * Hermana de `existsFor()` y no sustituta suya: aquella responde a «¿hay
     * que crearla?» y esta a «¿qué se hizo ya con ella?». La diferencia
     * importa desde `FEAT-NOT-002`, donde un reintento necesita saber si el
     * correo llegó a salir y no solo si el aviso existe.
     */
    public function ofSource(RecipientId $recipientId, NotificationKind $kind, string $sourceEventId): ?Notification;

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
     * Retira el aviso de una corrección que su destinatario acaba de abrir
     * (`FEAT-FBK-004` `RN-7`).
     *
     * Un centro de notificaciones que sigue marcando como nuevo algo que ya
     * se ha leído deja de significar nada en una semana, y entonces la gente
     * deja de mirarlo.
     */
    public function markReadByCorrection(RecipientId $recipientId, string $correctionId, \DateTimeImmutable $now): void;

    /**
     * Marca de una vez todo lo que quede sin leer (`RN-5`).
     *
     * En una sola sentencia y no fila a fila: quien lleva meses sin entrar
     * puede tener cientos, y cargarlos todos para marcarlos sería pagar la
     * bandeja entera por vaciar un contador.
     */
    public function markAllRead(RecipientId $recipientId, \DateTimeImmutable $now): void;
}
