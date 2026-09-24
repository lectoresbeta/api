<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\AccessInvitation\Application\Service;

use LectoresBeta\Reading\AccessInvitation\Application\DTO\InvitationPage;
use LectoresBeta\Reading\AccessInvitation\Application\DTO\InvitationView;
use LectoresBeta\Reading\AccessInvitation\Domain\Entity\AccessInvitation;
use LectoresBeta\Reading\AccessInvitation\Domain\Enum\AccessInvitationStatus;
use LectoresBeta\Reading\AccessInvitation\Domain\Exception\InvitationRefused;
use LectoresBeta\Shared\Domain\Exception\InvalidCursor;
use LectoresBeta\Shared\Domain\Pagination\Cursor;
use LectoresBeta\Shared\Domain\Pagination\PageSize;
use LectoresBeta\Work\Manuscript\Application\Contract\WorkAccessBrief;
use LectoresBeta\Work\Manuscript\Application\Contract\WorkAccessBriefs;

/**
 * Lo que las dos bandejas de invitaciones hacen igual (`FEAT-RDG-004`,
 * `FEAT-RDG-005`).
 *
 * A diferencia de su gemela del lado de las solicitudes, esta pone en cada
 * fila **sinopsis y clasificación de contenido**, no solo el título: quien
 * decide si acepta no ha podido ojear la obra, y eso es lo único que le
 * avisa de lo que va a leer.
 *
 * Los datos de las obras se piden de una vez para toda la página.
 */
final readonly class PageOfInvitations
{
    public function __construct(private WorkAccessBriefs $works)
    {
    }

    public function size(?int $requested): int
    {
        return PageSize::of($requested);
    }

    public function status(?string $status): ?AccessInvitationStatus
    {
        if (null === $status) {
            return AccessInvitationStatus::PENDING;
        }

        if ('ALL' === strtoupper($status)) {
            return null;
        }

        return AccessInvitationStatus::tryFrom(strtoupper($status))
            ?? throw InvitationRefused::unknownStatusFilter();
    }

    public function after(?string $cursor): ?Cursor
    {
        if (null === $cursor || '' === $cursor) {
            return null;
        }

        return Cursor::decode($cursor) ?? throw InvalidCursor::create();
    }

    /**
     * @param list<AccessInvitation> $found una fila de más que la página
     */
    public function of(array $found, int $limit): InvitationPage
    {
        $invitations = \array_slice($found, 0, $limit);
        $last = end($invitations);

        $briefs = $this->works->ofWorks(array_values(array_unique(array_map(
            static fn (AccessInvitation $invitation): string => $invitation->workId()->value(),
            $invitations,
        ))));

        return new InvitationPage(
            array_map(
                static fn (AccessInvitation $invitation): InvitationView => self::view(
                    $invitation,
                    $briefs[$invitation->workId()->value()] ?? null,
                ),
                $invitations,
            ),
            \count($found) > $limit && false !== $last
                ? Cursor::of($last->createdAt(), $last->id()->value())->encode()
                : null,
        );
    }

    /**
     * Una obra que ha dejado de existir deja la fila sin título y sin
     * advertencias, y **marcada como para adultos**: ante la duda, el aviso
     * más prudente es el que no promete que algo es inofensivo.
     */
    private static function view(AccessInvitation $invitation, ?WorkAccessBrief $work): InvitationView
    {
        return new InvitationView(
            $invitation->id()->value(),
            $invitation->workId()->value(),
            $work?->title,
            $work?->synopsis,
            null === $work || $work->adultsOnly,
            null === $work ? [] : $work->contentWarnings,
            $invitation->authorId()->value(),
            $invitation->inviteeId()->value(),
            $invitation->status()->value,
            $invitation->message(),
            $invitation->createdAt(),
            $invitation->resolvedAt(),
        );
    }
}
