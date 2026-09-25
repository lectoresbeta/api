<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Recommendation\Domain\Repository;

use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Community\Recommendation\Domain\Entity\MemberGenre;

interface MemberGenreRepository
{
    /**
     * Deja los géneros de esta persona **exactamente** en estos.
     *
     * No hay «añadir uno»: el hecho que alimenta esta proyección dice cuáles
     * son, no cuál se ha añadido.
     *
     * @param list<MemberGenre> $genres
     */
    public function replaceAllOf(MemberId $memberId, array $genres): void;
}
