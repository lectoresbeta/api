<?php

declare(strict_types=1);

namespace LectoresBeta\User\AuthorPage\Application\DTO;

use LectoresBeta\User\AuthorPage\Domain\Enum\AccentColour;
use LectoresBeta\User\AuthorPage\Domain\Enum\AuthorPageTheme;

/**
 * Cómo se ve la página de autor, tal y como sale de la API
 * (`FEAT-USR-016`).
 */
final readonly class AuthorPageStyleView
{
    public function __construct(
        public AuthorPageTheme $theme,
        public AccentColour $accentColour,
        public \DateTimeImmutable $updatedAt,
    ) {
    }
}
