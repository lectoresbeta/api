<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Application\Service;

use LectoresBeta\User\Profile\Application\Contract\GenreCatalogue;
use LectoresBeta\User\Profile\Domain\Repository\GenreRepository;

final readonly class CheckGenreCatalogue implements GenreCatalogue
{
    public function __construct(private GenreRepository $genres)
    {
    }

    public function unknownAmong(array $codes): array
    {
        return $this->genres->unknownAmong($codes);
    }
}
