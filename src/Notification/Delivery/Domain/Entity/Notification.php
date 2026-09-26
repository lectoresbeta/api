<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Domain\Entity;

use LectoresBeta\Notification\Delivery\Domain\Enum\NotificationKind;
use LectoresBeta\Notification\Delivery\Domain\ValueObject\NotificationId;
use LectoresBeta\Notification\Delivery\Domain\ValueObject\RecipientId;

/**
 * One notice for one person.
 *
 * `sourceEventId` is what makes delivery idempotent (`RN-3`): the same
 * integration event, delivered twice by RabbitMQ, must not produce two
 * identical notices. The unique index on (recipient, kind, source event) is
 * where that is actually enforced.
 *
 * `payload` carries what the template needs to build a sentence and a link —
 * names, titles, counts. **Never the content of a work, a correction or a
 * message** (`RN-4`): unpublished writing does not leave the platform by
 * email.
 */
class Notification
{
    private string $id;

    private string $recipientId;

    private NotificationKind $kind;

    /** @var array<string, scalar|null> */
    private array $payload = [];

    private ?string $sourceEventId = null;

    private \DateTimeImmutable $createdAt;

    private ?\DateTimeImmutable $readAt = null;

    /**
     * Si este aviso se enseña en la campana (`FEAT-NOT-003` `RN-1`).
     *
     * Existe porque los dos canales se apagan por separado: alguien puede
     * querer el correo y no la campana. Con un solo canal bastaba con **no
     * crear la fila**; con dos, la fila deja de ser «lo que se le enseña» y
     * pasa a ser el registro de qué se hizo con ese hecho, que es lo único
     * que permite anotar que el correo salió.
     */
    private bool $inbox = true;

    /**
     * Cuándo salió por correo, si salió (`FEAT-NOT-002` `RN-2`, `RN-3`).
     *
     * **Distingue «ya se mandó» de «existe el aviso»**, y esa distinción es
     * lo que hace correcto el reintento: si el proveedor falla, la fila ya
     * está, y sin esta fecha el reintento la encontraría, se daría por hecho
     * y dejaría a alguien sin su correo para siempre.
     */
    private ?\DateTimeImmutable $emailedAt = null;

    /**
     * @param array<string, scalar|null> $payload
     */
    public function __construct(
        NotificationId $id,
        RecipientId $recipientId,
        NotificationKind $kind,
        \DateTimeImmutable $now,
        array $payload = [],
        ?string $sourceEventId = null,
        bool $inbox = true,
    ) {
        $this->id = $id->value();
        $this->recipientId = $recipientId->value();
        $this->kind = $kind;
        $this->createdAt = $now;
        $this->payload = $payload;
        $this->sourceEventId = $sourceEventId;
        $this->inbox = $inbox;
    }

    public function id(): NotificationId
    {
        return NotificationId::fromString($this->id);
    }

    public function recipientId(): RecipientId
    {
        return RecipientId::fromString($this->recipientId);
    }

    public function kind(): NotificationKind
    {
        return $this->kind;
    }

    /**
     * @return array<string, scalar|null>
     */
    public function payload(): array
    {
        return $this->payload;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function readAt(): ?\DateTimeImmutable
    {
        return $this->readAt;
    }

    public function isFor(RecipientId $recipientId): bool
    {
        return $this->recipientId === $recipientId->value();
    }

    public function isRead(): bool
    {
        return null !== $this->readAt;
    }

    public function isInInbox(): bool
    {
        return $this->inbox;
    }

    public function wasEmailed(): bool
    {
        return null !== $this->emailedAt;
    }

    /**
     * Salió por correo. Idempotente por lo mismo que `markRead()`: la segunda
     * vez llega sola, y mover la fecha convertiría un registro en una
     * suposición.
     */
    public function markEmailed(\DateTimeImmutable $now): void
    {
        $this->emailedAt ??= $now;
    }

    /**
     * Marcar lo ya leído no mueve la fecha (`FEAT-NOT-009` `RN-4`). La
     * idempotencia no es un detalle de implementación: la pantalla marca al
     * abrir y también con un gesto, así que la segunda vez llega sola.
     */
    public function markRead(\DateTimeImmutable $now): void
    {
        $this->readAt ??= $now;
    }
}
