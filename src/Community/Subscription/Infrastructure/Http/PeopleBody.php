<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Subscription\Infrastructure\Http;

use LectoresBeta\Community\Subscription\Application\DTO\SubscriptionPage;
use LectoresBeta\User\Account\Application\Contract\DirectoryEntry;
use Symfony\Component\HttpFoundation\Request;

/**
 * La forma de una lista de personas en HTTP, la misma en las dos
 * (`FEAT-COM-027`).
 *
 * Cada fila es **una tarjeta de perfil**, no un identificador: una lista de
 * UUID obligaría al cliente a una petición por fila para poder pintarla.
 *
 * `hasNextPage` sale del cursor y **no de cuántas filas llegaron**. La
 * diferencia importa aquí más que en otras listas: el filtro de privacidad se
 * aplica después de paginar, así que una página puede venir corta, o incluso
 * vacía, y seguir teniendo siguiente.
 */
final readonly class PeopleBody
{
    /**
     * @return array<string, mixed>
     */
    public static function of(SubscriptionPage $page): array
    {
        return [
            'data' => array_map(
                static function (array $person): array {
                    /** @var DirectoryEntry $profile */
                    $profile = $person['profile'];

                    return [
                        'userId' => $profile->userId,
                        'username' => $profile->username,
                        'name' => $profile->name,
                        'avatarUrl' => $profile->avatarUrl,
                        'subscribedAt' => $person['subscribedAt']->format(\DATE_ATOM),
                    ];
                },
                $page->people,
            ),
            'pageInfo' => [
                'nextCursor' => $page->nextCursor,
                'hasNextPage' => null !== $page->nextCursor,
            ],
        ];
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
