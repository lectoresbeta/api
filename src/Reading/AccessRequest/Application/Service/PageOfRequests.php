<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\AccessRequest\Application\Service;

use LectoresBeta\Reading\AccessRequest\Application\DTO\AccessRequestPage;
use LectoresBeta\Reading\AccessRequest\Application\DTO\AccessRequestView;
use LectoresBeta\Reading\AccessRequest\Domain\Entity\AccessRequest;
use LectoresBeta\Reading\AccessRequest\Domain\Enum\AccessRequestStatus;
use LectoresBeta\Reading\AccessRequest\Domain\Exception\AccessRequestRefused;
use LectoresBeta\Shared\Domain\Exception\InvalidCursor;
use LectoresBeta\Shared\Domain\Pagination\Cursor;
use LectoresBeta\Shared\Domain\Pagination\PageSize;
use LectoresBeta\Work\Manuscript\Application\Contract\WorkAccessBrief;
use LectoresBeta\Work\Manuscript\Application\Contract\WorkAccessBriefs;

/**
 * Lo que las dos bandejas hacen igual (`FEAT-RDG-002`, `FEAT-RDG-003`).
 *
 * Una solicitud es un objeto visto desde dos extremos, así que paginarla,
 * filtrarla por estado y ponerle el título de su obra es el mismo trabajo a
 * los dos lados. Escribirlo dos veces sería dos sitios donde equivocarse con
 * el cursor.
 *
 * Los títulos se piden **de una vez para toda la página**: una llamada por
 * fila sería un N+1 escondido detrás de un contrato.
 */
final readonly class PageOfRequests
{
    public function __construct(private WorkAccessBriefs $works)
    {
    }

    public function size(?int $requested): int
    {
        return PageSize::of($requested);
    }

    /**
     * Sin filtro se enseña **lo pendiente**, que es lo que alguien viene a
     * mirar. Un estado que no existe se rechaza en lugar de ignorarse: servir
     * otra lista dejaría al que pregunta sin forma de notarlo.
     */
    public function status(?string $status): ?AccessRequestStatus
    {
        if (null === $status) {
            return AccessRequestStatus::PENDING;
        }

        if ('ALL' === strtoupper($status)) {
            return null;
        }

        return AccessRequestStatus::tryFrom(strtoupper($status))
            ?? throw AccessRequestRefused::unknownStatusFilter();
    }

    public function after(?string $cursor): ?Cursor
    {
        if (null === $cursor || '' === $cursor) {
            return null;
        }

        return Cursor::decode($cursor) ?? throw InvalidCursor::create();
    }

    /**
     * @param list<AccessRequest> $found una fila de más que la página, que es
     *                                   cómo se sabe si hay siguiente sin
     *                                   contar nada
     */
    public function of(array $found, int $limit): AccessRequestPage
    {
        $requests = \array_slice($found, 0, $limit);
        $last = end($requests);

        $titles = $this->works->ofWorks(array_values(array_unique(array_map(
            static fn (AccessRequest $request): string => $request->workId()->value(),
            $requests,
        ))));

        return new AccessRequestPage(
            array_map(
                static fn (AccessRequest $request): AccessRequestView => new AccessRequestView(
                    $request->id()->value(),
                    $request->workId()->value(),
                    self::titleOf($titles, $request->workId()->value()),
                    $request->requesterId()->value(),
                    $request->status()->value,
                    $request->message(),
                    $request->requestedAt(),
                    $request->resolvedAt(),
                ),
                $requests,
            ),
            \count($found) > $limit && false !== $last
                ? Cursor::of($last->requestedAt(), $last->id()->value())->encode()
                : null,
        );
    }

    /**
     * @param array<string, WorkAccessBrief> $titles
     */
    private static function titleOf(array $titles, string $workId): ?string
    {
        return $titles[$workId]->title ?? null;
    }
}
