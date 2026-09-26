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

    /**
     * Which of these codes are not in the catalogue **at all**, retired ones
     * included.
     *
     * It exists to tell apart the two ways of not being available. A retired
     * genre is still somebody's choice and stays valid for whoever already
     * had it (`FEAT-USR-009` `RN-6`); a code that was never there is a
     * mistake whatever the context.
     *
     * @param list<string> $codes
     *
     * @return list<string>
     */
    public function missingAmong(array $codes): array;

    /**
     * These codes as catalogue entries, in curated order, **retired ones
     * included**.
     *
     * Reading somebody's preferences has to show what they chose, not only
     * what is still on offer.
     *
     * @param list<string> $codes
     *
     * @return list<Genre>
     */
    public function ofCodes(array $codes): array;
}
