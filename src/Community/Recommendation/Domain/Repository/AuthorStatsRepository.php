<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Recommendation\Domain\Repository;

use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Community\Ranking\Domain\Entity\AuthorGenre;
use LectoresBeta\Community\Ranking\Domain\Entity\AuthorStats;

interface AuthorStatsRepository
{
    /**
     * Los contadores de esa persona, creándolos si es la primera vez.
     *
     * Una proyección no puede fallar porque el hecho le llegue antes que
     * ningún otro sobre la misma persona: el orden de la cola no está
     * garantizado, y exigirlo convertiría cada reintento en un error.
     */
    public function of(MemberId $authorId, \DateTimeImmutable $now): AuthorStats;

    public function save(AuthorStats $stats): void;

    public function genre(MemberId $authorId, string $genreCode): AuthorGenre;

    public function saveGenre(AuthorGenre $genre): void;
}
