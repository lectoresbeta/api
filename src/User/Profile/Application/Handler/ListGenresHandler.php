<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Application\Handler;

use LectoresBeta\User\Profile\Application\DTO\GenreView;
use LectoresBeta\User\Profile\Application\Query\ListGenres;
use LectoresBeta\User\Profile\Domain\Entity\Genre;
use LectoresBeta\User\Profile\Domain\Repository\GenreRepository;

/**
 * The catalogue is **served, not hard-coded in the client**
 * (`FEAT-USR-023` `RN-5`).
 *
 * The same list feeds the onboarding, the work search, the author search and
 * the ranking filters, so a genre added or retired has to reach all of them
 * without a release of anything.
 */
final readonly class ListGenresHandler
{
    public function __construct(private GenreRepository $genres)
    {
    }

    /**
     * @return list<GenreView>
     */
    public function __invoke(ListGenres $query): array
    {
        return array_map(
            static fn (Genre $genre): GenreView => new GenreView($genre->code(), $genre->name()),
            $this->genres->activeCatalogue(),
        );
    }
}
