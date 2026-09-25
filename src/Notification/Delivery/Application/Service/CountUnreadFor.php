<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Application\Service;

use LectoresBeta\Notification\Delivery\Application\Contract\UnreadNotificationCount;
use LectoresBeta\Notification\Delivery\Domain\Repository\NotificationRepository;
use LectoresBeta\Notification\Delivery\Domain\ValueObject\RecipientId;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;

/**
 * El lado de `Notification` del contrato.
 *
 * Un identificador que no es un identificador cuenta cero en vez de estallar:
 * quien pregunta está pintando una campana, y un menú lateral que no se
 * dibuja por eso sería una avería mucho mayor que un punto de menos.
 */
final readonly class CountUnreadFor implements UnreadNotificationCount
{
    public function __construct(private NotificationRepository $notifications)
    {
    }

    public function forRecipient(string $userId): int
    {
        try {
            return $this->notifications->countUnread(RecipientId::fromString($userId));
        } catch (InvalidValue) {
            return 0;
        }
    }
}
