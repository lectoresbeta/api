<?php

declare(strict_types=1);

namespace LectoresBeta\User\AuthorPage\Application\Command;

/**
 * Editar un premio (`FEAT-USR-030`).
 *
 * Cada campo lleva **si venía en la petición**, aparte de su valor. Sin esa
 * distinción, `PATCH` no puede separar «no lo toques» de «déjalo en blanco»,
 * y una de las dos intenciones se pierde.
 */
final readonly class UpdateAward
{
    public function __construct(
        public string $userId,
        public string $awardId,
        public bool $titleGiven,
        public ?string $title,
        public bool $awardedByGiven,
        public ?string $awardedBy,
        public bool $yearGiven,
        public ?int $year,
        public bool $noteGiven,
        public ?string $note,
        public bool $urlGiven,
        public ?string $url,
    ) {
    }
}
