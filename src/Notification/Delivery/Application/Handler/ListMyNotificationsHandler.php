<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Application\Handler;

use LectoresBeta\Notification\Delivery\Application\DTO\NotificationPage;
use LectoresBeta\Notification\Delivery\Application\DTO\NotificationView;
use LectoresBeta\Notification\Delivery\Application\Query\ListMyNotifications;
use LectoresBeta\Notification\Delivery\Domain\Repository\NotificationRepository;
use LectoresBeta\Notification\Delivery\Domain\ValueObject\RecipientId;
use LectoresBeta\Shared\Domain\Exception\InvalidCursor;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Pagination\Cursor;
use LectoresBeta\Shared\Domain\Pagination\PageSize;

/**
 * Leer la bandeja (`FEAT-NOT-009`).
 *
 * Solo la propia: el destinatario sale de la sesión y no de la petición, así
 * que no hay forma de pedir la de otra persona.
 *
 * No consulta a nadie para pintar cada fila. El nombre y el título ya están
 * en el `payload` desde que el aviso se creó (`FEAT-NOT-001` `RN-5`), que es
 * exactamente lo que esta lectura evita: una bandeja de veinte avisos serían
 * cuarenta peticiones más a otros contextos.
 */
final readonly class ListMyNotificationsHandler
{
    public function __construct(private NotificationRepository $notifications)
    {
    }

    public function __invoke(ListMyNotifications $query): NotificationPage
    {
        try {
            $recipient = RecipientId::fromString($query->userId);
        } catch (InvalidValue) {
            return new NotificationPage([], null);
        }

        $limit = PageSize::of($query->limit);
        $after = null === $query->cursor || '' === $query->cursor
            ? null
            : Cursor::decode($query->cursor) ?? throw InvalidCursor::create();

        $found = $this->notifications->inboxOf($recipient, $query->unreadOnly, $after, $limit);
        $rows = \array_slice($found, 0, $limit);
        $last = end($rows);

        return new NotificationPage(
            array_map(
                static fn ($notification): NotificationView => new NotificationView(
                    $notification->id()->value(),
                    $notification->kind(),
                    $notification->payload(),
                    $notification->createdAt(),
                    $notification->readAt(),
                ),
                $rows,
            ),
            \count($found) > $limit && false !== $last
                ? Cursor::of($last->createdAt(), $last->id()->value())->encode()
                : null,
        );
    }
}
