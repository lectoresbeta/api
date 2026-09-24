<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\AccessRequest\Infrastructure\Http;

use LectoresBeta\Reading\AccessRequest\Application\DTO\AccessRequestPage;
use LectoresBeta\Reading\AccessRequest\Application\DTO\AccessRequestView;
use Symfony\Component\HttpFoundation\Request;

/**
 * La forma de una bandeja de solicitudes en HTTP.
 *
 * Las dos listas —la del lector y la del autor— devuelven **la misma
 * estructura**, porque una solicitud es un objeto visto desde dos extremos.
 * Lo que cambia es qué campo mira cada uno, y eso es cosa de la pantalla.
 *
 * `limit` se lee como `null` cuando no viene, y no como cero: quien decide
 * cuál es el tamaño por defecto es `PageSize`, no el borde HTTP.
 */
final readonly class AccessRequestBody
{
    /**
     * @return array<string, mixed>
     */
    public static function of(AccessRequestPage $page): array
    {
        return [
            'requests' => array_map(
                static fn (AccessRequestView $request): array => [
                    'requestId' => $request->requestId,
                    'workId' => $request->workId,
                    'workTitle' => $request->workTitle,
                    'readerId' => $request->readerId,
                    'status' => $request->status,
                    'message' => $request->message,
                    'requestedAt' => $request->requestedAt->format(\DATE_ATOM),
                    'resolvedAt' => $request->resolvedAt?->format(\DATE_ATOM),
                ],
                $page->requests,
            ),
            'pageInfo' => [
                'nextCursor' => $page->nextCursor,
                'hasNextPage' => null !== $page->nextCursor,
            ],
        ];
    }

    public static function optional(Request $request, string $parameter): ?string
    {
        $value = $request->query->get($parameter);

        return \is_string($value) && '' !== $value ? $value : null;
    }

    public static function limit(Request $request): ?int
    {
        return $request->query->has('limit') ? $request->query->getInt('limit') : null;
    }
}
