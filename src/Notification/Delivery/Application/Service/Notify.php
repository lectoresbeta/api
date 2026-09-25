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
use LectoresBeta\User\Preferences\Application\Contract\NotificationChoices;
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
 *   Una cuenta eliminada no recibe nada, y reintentar no la va a resucitar;
 * - **lo que su dueño ha silenciado no se entrega**
 *   ([`FEAT-USR-039`](../../../../../docs/features/user/FEAT-USR-039-notification-preferences.md)
 *   `RN-1`). Se pregunta **aquí y no al publicar el hecho**: el emisor no
 *   conoce las preferencias de nadie, y entre el hecho y la entrega pueden
 *   haber cambiado.
 *
 * Los mensajes **operativos** no preguntan nada (`RN-3`). La activación, el
 * restablecimiento de contraseña y los avisos de seguridad no son
 * notificaciones, y si el interruptor general los alcanzara dejaría a alguien
 * sin poder recuperar su cuenta. La clasificación vive en el propio catálogo
 * de tipos, que no admite un tipo sin clasificar.
 *
 * Los nombres y los títulos se piden a `User` y a `Work` por sus contratos
 * publicados. Que esto sea legítimo y la consulta desde `CheckAuthorAudience`
 * no lo fuera tiene una razón concreta: **un consumidor de eventos no es un
 * contrato respondiendo**, así que no hay nadie esperando al otro lado.
 */
final readonly class Notify
{
    private const PLATFORM = 'PLATFORM';

    private const EMAIL = 'EMAIL';

    public function __construct(
        private NotificationRepository $notifications,
        private ProfileCards $profiles,
        private WorkAccessBriefs $works,
        private NotificationChoices $choices,
        private EmailTheNotification $email,
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

        // La fila de ese hecho, si ya existe. **No basta con saber que
        // existe**: un reintento necesita saber qué se hizo con ella, y en
        // particular si el correo llegó a salir (`FEAT-NOT-002` `RN-3`).
        $notification = $this->notifications->ofSource($recipient, $kind, $sourceEventId);

        if (null === $notification) {
            $notification = new Notification(
                NotificationId::generate(),
                $recipient,
                $kind,
                $this->clock->now(),
                $payload,
                $sourceEventId,
                // Silenciada no significa inexistente: la fila es el registro
                // de qué se hizo con este hecho, y hace falta aunque no se
                // enseñe, para poder anotar el correo si sí lo quiere.
                $this->wants($recipientId, $kind, self::PLATFORM),
            );

            $this->session->execute(function () use ($notification): void {
                $this->notifications->save($notification);
            });
        }

        if ($kind->reachesInbox() && $this->wants($recipientId, $kind, self::EMAIL)) {
            $this->email->of($notification);
        }
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

    /**
     * Si esa persona quiere ese aviso por ese canal.
     *
     * Los **operativos no preguntan** (`FEAT-NOT-003` `RN-3`): la activación,
     * el restablecimiento de contraseña y los avisos de seguridad no son
     * notificaciones, y si el interruptor general los alcanzara dejaría a
     * alguien sin poder recuperar su cuenta.
     */
    private function wants(string $recipientId, NotificationKind $kind, string $channel): bool
    {
        return $kind->isOperational() || $this->choices->allows($recipientId, $kind->value, $channel);
    }
}
