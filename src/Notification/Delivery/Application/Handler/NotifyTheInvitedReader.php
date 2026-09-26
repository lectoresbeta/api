<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Application\Handler;

use LectoresBeta\Notification\Delivery\Application\Event\BetaReaderInvited;
use LectoresBeta\Notification\Delivery\Application\Service\Notify;
use LectoresBeta\Notification\Delivery\Domain\Enum\NotificationKind;

/**
 * «Te han ofrecido una obra» (`FEAT-NOT-001`).
 *
 * Es el aviso más necesario de los tres caminos de acceso: el invitado **no
 * sabía nada**. Sin él, una invitación se queda esperando una respuesta que
 * nadie sabe que tiene que dar.
 */
final readonly class NotifyTheInvitedReader
{
    public function __construct(private Notify $notify)
    {
    }

    public function __invoke(BetaReaderInvited $event): void
    {
        $this->notify->deliver(
            $event->readerId,
            NotificationKind::BETA_READER_INVITATION,
            $event->eventId(),
            [
                ...$this->notify->actor($event->authorId),
                ...$this->notify->work($event->workId),
                'invitationId' => $event->invitationId,
            ],
            actorId: $event->authorId,
        );
    }
}
