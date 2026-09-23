<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Domain\Repository;

use LectoresBeta\User\Profile\Domain\Entity\Genre;

interface GenreRepository
{
    /**
     * The catalogue as the interface shows it: active entries, in the order
     * somebody curated (`FEAT-USR-023` `RN-5`).
     *
     * @return list<Genre>
     */
    public function activeCatalogue(): array;

    /**
     * Which of these codes are **not** in the active catalogue.
     *
     * Asked this way round because that is the answer the caller needs: an
     * unknown genre is rejected and named, never dropped in silence
     * (`RN-3`).
     *
     * @param list<string> $codes
     *
     * @return list<string>
     */
    public function unknownAmong(array $codes): array;
}
