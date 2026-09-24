<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\BetaReaderAccess\Application\Handler;

use LectoresBeta\Reading\BetaReaderAccess\Application\Event\UserBlocked;
use LectoresBeta\Reading\BetaReaderAccess\Domain\Entity\BetaReaderAccess;
use LectoresBeta\Reading\BetaReaderAccess\Domain\Event\BetaReaderAccessRevoked;
use LectoresBeta\Reading\BetaReaderAccess\Domain\Repository\BetaReaderAccessRepository;
use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\ReaderId;
use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

/**
 * Bloquear a alguien le quita el acceso a tus obras (`FEAT-COM-034`
 * `RN-B1`).
 *
 * **Esto lo decide `Reading`, no `Community`.** Allí se publica que dos
 * personas han dejado de hablarse; qué significa eso para una obra inédita se
 * decide aquí, que es donde se sabe qué es un acceso de lector beta. Si
 * `Community` revocase accesos, tendría que aprender un modelo que no es
 * suyo.
 *
 * Se revocan **todos** los accesos vivos de esa persona a obras del
 * bloqueador, vinieran de donde vinieran: solicitud aceptada, invitación o
 * corrección empezada. Un bloqueo no distingue por dónde se entró.
 *
 * Lo que esto se lleva por delante está dicho de frente en la ficha: **quien
 * estuviera corrigiendo pierde ese trabajo y no cobra**, porque nunca llegó a
 * entregar. Es la única situación del sistema en la que alguien pierde
 * trabajo real por una decisión ajena, y se asume a conciencia — el derecho a
 * bloquear pesa más que el caso raro de quien lo use de mala fe. Su borrador
 * se conserva, porque es texto suyo.
 *
 * Lo ya entregado **no se toca** (`RN-B4`): el autor lo pagó y el lector lo
 * ganó. El bloqueo corta el futuro, no reescribe el pasado.
 *
 * Desbloquear no lo deshace. Recuperar el acceso es volver a concederlo, que
 * es una decisión del autor y no un efecto secundario.
 */
final readonly class RevokeAccessOnBlock
{
    public function __construct(
        private BetaReaderAccessRepository $accesses,
        private TransactionalSession $session,
        private EventPublisher $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(UserBlocked $event): void
    {
        try {
            $blockedReader = ReaderId::fromString($event->blockedId);
        } catch (InvalidValue) {
            return;
        }

        $revoked = array_values(array_filter(
            $this->accesses->liveOfReader($blockedReader),
            static fn (BetaReaderAccess $access): bool => $access->authorId()->value() === $event->blockerId,
        ));

        if ([] === $revoked) {
            return;
        }

        $now = $this->clock->now();

        $this->session->execute(function () use ($revoked, $now): void {
            foreach ($revoked as $access) {
                $access->revoke($now);
                $this->accesses->save($access);
            }
        });

        foreach ($revoked as $access) {
            $this->events->publish(new BetaReaderAccessRevoked(
                EventId::generate(),
                $access->id(),
                $access->workId(),
                $blockedReader,
                $now,
            ));
        }
    }
}
