<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Application\Command;

final readonly class SetContentRating
{
    /**
     * @param list<string> $contentWarnings
     * @param bool|null    $adultsOnly      nulo es «nadie lo ha dicho», que no
     *                                      es lo mismo que `false` y se
     *                                      rechaza (`FEAT-WRK-017`)
     */
    public function __construct(
        public string $workId,
        public string $authorId,
        public ?bool $adultsOnly,
        public array $contentWarnings,
    ) {
    }
}
