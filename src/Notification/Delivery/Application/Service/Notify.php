<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Application\Service;

use LectoresBeta\Notification\Delivery\Domain\Entity\Notification;
use LectoresBeta\Notification\Delivery\Domain\Enum\NotificationKind;
use LectoresBeta\Notification\Delivery\Domain\Repository\NotificationRepository;
use LectoresBeta\Notification\Delivery\Domain\ValueObject\NotificationId;
use LectoresBeta\Notification\Delivery\Domain\ValueObject\RecipientId;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\User\Account\Application\Contract\DirectoryEntry;
use LectoresBeta\User\Account\Application\Contract\ProfileCards;
use LectoresBeta\Work\Manuscript\Application\Contract\WorkAccessBriefs;

/**
 * Convertir un hecho en un aviso (`FEAT-NOT-001`).
 *
 * Lo comparten todos los consumidores porque todos hacen lo mismo: comprobar
 * que ese aviso no existe ya, ponerle nombre a lo que pasó y guardarlo.
 * Escrito una vez, no seis.
 *
 * **Tres reglas viven aquí y no en quien llama**, que es lo que las hace
 * cumplirse siempre:
 *
 * - **idempotencia** (`RN-2`): el mismo hecho reentregado por RabbitMQ no
 *   produce dos avisos. La comprobación ahorra el trabajo; el índice único
 *   sobre `(destinatario, tipo, evento)` es lo que de verdad lo garantiza
 *   cuando dos consumidores corren a la vez;
 * - **nadie se avisa a sí mismo** (`RN-3`). Quien provoca el hecho ya sabe lo
 *   que ha hecho, y un aviso propio es ruido que enseña a ignorar la campana;
 * - **destinatario que no se puede resolver, aviso que no se crea** (`RN-6`).
 *   Una cuenta eliminada no recibe nada, y reintentar no la va a resucitar.
 *
 * Los nombres y los títulos se piden a `User` y a `Work` por sus contratos
 * publicados. Que esto sea legítimo y la consulta desde `CheckAuthorAudience`
 * no lo fuera tiene una razón concreta: **un consumidor de eventos no es un
 * contrato respondiendo**, así que no hay nadie esperando al otro lado.
 */
final readonly class Notify
{
    public function __construct(
        private NotificationRepository $notifications,
        private ProfileCards $profiles,
        private WorkAccessBriefs $works,
        private TransactionalSession $session,
        private Clock $clock,
    ) {
    }

    /**
     * @param array<string, scalar|null> $payload lo que la frase necesita, y
     *                                            nada más: **nunca** texto de
     *                                            una obra o de una corrección
     */
    public function deliver(
        string $recipientId,
        NotificationKind $kind,
        string $sourceEventId,
        array $payload = [],
        ?string $actorId = null,
    ): void {
        try {
            $recipient = RecipientId::fromString($recipientId);
        } catch (InvalidValue) {
            return;
        }

        if (null !== $actorId && $actorId === $recipientId) {
            return;
        }

        if ($this->notifications->existsFor($recipient, $kind, $sourceEventId)) {
            return;
        }

        $notification = new Notification(
            NotificationId::generate(),
            $recipient,
            $kind,
            $this->clock->now(),
            $payload,
            $sourceEventId,
        );

        $this->session->execute(function () use ($notification): void {
            $this->notifications->save($notification);
        });
    }

    /**
     * Cómo se llama quien provoca el aviso, para que la frase se pueda
     * escribir sin una petición más por fila.
     *
     * Es una **instantánea** (`RN-5`): si esa persona cambia de nombre
     * después, el aviso antiguo conserva el de entonces, que es lo correcto
     * — describe algo que pasó.
     *
     * @return array<string, scalar|null>
     */
    public function actor(string $userId): array
    {
        $card = $this->profiles->of([$userId])[$userId] ?? null;

        return $card instanceof DirectoryEntry
            ? ['actorId' => $userId, 'actorName' => $card->name, 'actorUsername' => $card->username]
            : ['actorId' => $userId];
    }

    /**
     * El título de la obra, por el mismo motivo y con la misma advertencia.
     *
     * @return array<string, scalar|null>
     */
    public function work(string $workId): array
    {
        return ['workId' => $workId, 'workTitle' => $this->works->ofWork($workId)?->title];
    }
}
