<?php

declare(strict_types=1);

namespace LectoresBeta\Work\PublicLink\Application\Handler;

use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Work\Manuscript\Domain\Repository\WorkRepository;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\AuthorId;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId;
use LectoresBeta\Work\PublicLink\Application\DTO\PublicLinkView;
use LectoresBeta\Work\PublicLink\Application\Query\ListPublicLinks;
use LectoresBeta\Work\PublicLink\Application\Service\DescribePublicLink;
use LectoresBeta\Work\PublicLink\Domain\Exception\PublicLinkRefused;
use LectoresBeta\Work\PublicLink\Domain\Repository\PublicLinkRepository;

/**
 * Los enlaces vivos y muertos de una obra, para su autor (`FEAT-WRK-010`).
 *
 * Se devuelven también los revocados y los caducados: el autor necesita saber
 * qué repartió, no solo qué sigue abierto.
 */
final readonly class ListPublicLinksHandler
{
    public function __construct(
        private PublicLinkRepository $links,
        private WorkRepository $works,
        private DescribePublicLink $describe,
        private Clock $clock,
    ) {
    }

    /**
     * @return list<PublicLinkView>
     */
    public function __invoke(ListPublicLinks $query): array
    {
        try {
            $work = $this->works->ofId(WorkId::fromString($query->workId));
            $author = AuthorId::fromString($query->authorId);
        } catch (InvalidValue) {
            throw PublicLinkRefused::workNotYours();
        }

        if (null === $work || !$work->authorId()->equals($author)) {
            throw PublicLinkRefused::workNotYours();
        }

        $now = $this->clock->now();

        return array_map(
            fn ($link): PublicLinkView => $this->describe->one($link, $now),
            $this->links->ofWork($work->id()),
        );
    }
}
