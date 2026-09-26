<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\AccessInvitation\Infrastructure\Http;

use LectoresBeta\Reading\AccessInvitation\Application\DTO\InvitationPage;
use LectoresBeta\Reading\AccessInvitation\Application\DTO\InvitationView;
use Symfony\Component\HttpFoundation\Request;

/**
 * La forma de una bandeja de invitaciones en HTTP.
 *
 * Las dos listas devuelven la misma estructura, porque una invitación es un
 * objeto visto desde dos extremos. Lo que cambia es qué mira cada uno: el
 * autor, a quién ofreció; el invitado, qué le ofrecieron.
 */
final readonly class InvitationBody
{
    /**
     * @return array<string, mixed>
     */
    public static function of(InvitationPage $page): array
    {
        return [
            'invitations' => array_map(
                static fn (InvitationView $invitation): array => [
                    'invitationId' => $invitation->invitationId,
                    'workId' => $invitation->workId,
                    'workTitle' => $invitation->workTitle,
                    'workSynopsis' => $invitation->workSynopsis,
                    'adultsOnly' => $invitation->adultsOnly,
                    'contentWarnings' => $invitation->contentWarnings,
                    'authorId' => $invitation->authorId,
                    'readerId' => $invitation->readerId,
                    'status' => $invitation->status,
                    'message' => $invitation->message,
                    'invitedAt' => $invitation->invitedAt->format(\DATE_ATOM),
                    'resolvedAt' => $invitation->resolvedAt?->format(\DATE_ATOM),
                ],
                $page->invitations,
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
