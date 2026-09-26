<?php

declare(strict_types=1);

namespace LectoresBeta\User\AuthorPage\Application\DTO;

use LectoresBeta\User\AuthorPage\Domain\Entity\Award;

/**
 * Un premio tal y como sale de la API (`FEAT-USR-030`).
 *
 * `urlIsExternal` es constante a propósito y no sobra, por lo mismo que en
 * `PublishedBookView`: dice que ese enlace **sale de la plataforma**, que es
 * lo que necesita saber quien lo pinta para abrirlo aparte y sin arrastrar la
 * sesión (`rel="noopener noreferrer"`).
 */
final readonly class AwardView
{
    public function __construct(
        public string $awardId,
        public string $title,
        public ?string $awardedBy,
        public ?int $year,
        public ?string $note,
        public ?string $url,
        public bool $urlIsExternal,
    ) {
    }

    public static function of(Award $award): self
    {
        return new self(
            $award->id()->value(),
            $award->title(),
            $award->awardedBy(),
            $award->year(),
            $award->note(),
            $award->url(),
            null !== $award->url(),
        );
    }
}
