<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Infrastructure\Http;

use LectoresBeta\Notification\Delivery\Application\DTO\NotificationPage;
use LectoresBeta\Notification\Delivery\Application\DTO\NotificationView;
use Symfony\Component\HttpFoundation\Request;

/**
 * La forma de la bandeja en HTTP (`FEAT-NOT-009`).
 *
 * Cada fila lleva `kind` y `payload`, **y ninguna frase**: el texto lo compone
 * el cliente. Así se traduce sin desplegar el backend y se cambia la redacción
 * sin migrar nada de lo ya guardado.
 *
 * `payload` viaja tal cual se guardó, que es una instantánea de cuando el
 * hecho ocurrió (`FEAT-NOT-001` `RN-5`). Un aviso describe algo que pasó, así
 * que conserva el nombre y el título de entonces aunque hoy sean otros.
 */
final readonly class InboxBody
{
    /**
     * @return array<string, mixed>
     */
    public static function of(NotificationPage $page): array
    {
        return [
            'data' => array_map(
                static fn (NotificationView $notification): array => [
                    'notificationId' => $notification->notificationId,
                    'kind' => $notification->kind->value,
                    'payload' => (object) $notification->payload,
                    'read' => null !== $notification->readAt,
                    'readAt' => $notification->readAt?->format(\DATE_ATOM),
                    'createdAt' => $notification->createdAt->format(\DATE_ATOM),
                ],
                $page->notifications,
            ),
            'pageInfo' => [
                'nextCursor' => $page->nextCursor,
                'hasNextPage' => null !== $page->nextCursor,
            ],
        ];
    }

    /**
     * Por defecto la bandeja entera, no solo lo pendiente.
     *
     * Es lo contrario de lo que enseña la pantalla, y a propósito: el filtro
     * es una decisión de quien pinta, y un endpoint que oculta por omisión
     * hace que «mis avisos» signifique dos cosas distintas.
     */
    public static function unreadOnly(Request $request): bool
    {
        return $request->query->getBoolean('unreadOnly');
    }

    public static function cursor(Request $request): ?string
    {
        $value = $request->query->get('cursor');

        return \is_string($value) && '' !== $value ? $value : null;
    }

    public static function limit(Request $request): ?int
    {
        return $request->query->has('limit') ? $request->query->getInt('limit') : null;
    }
}
