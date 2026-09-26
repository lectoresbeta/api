<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Application\Handler;

use LectoresBeta\Notification\Delivery\Application\Event\AccessRequested;
use LectoresBeta\Notification\Delivery\Application\Service\Notify;
use LectoresBeta\Notification\Delivery\Domain\Enum\NotificationKind;

/**
 * «Alguien quiere leer tu obra» (`FEAT-NOT-001`).
 *
 * Para el autor, que es quien tiene que resolverlo.
 */
final readonly class NotifyTheAuthorOfAnAccessRequest
{
    public function __construct(private Notify $notify)
    {
    }

    public function __invoke(AccessRequested $event): void
    {
        $this->notify->deliver(
            $event->authorId,
            NotificationKind::ACCESS_REQUESTED,
            $event->eventId(),
            [
                ...$this->notify->actor($event->readerId),
                ...$this->notify->work($event->workId),
                'accessRequestId' => $event->accessRequestId,
            ],
            actorId: $event->readerId,
        );
    }
}
