<?php

declare(strict_types=1);

namespace LectoresBeta\User\AuthorPage\Application\Command;

final readonly class UpdateAuthorPageStyle
{
    public function __construct(
        public string $userId,
        public bool $themeGiven,
        public ?string $theme,
        public bool $accentGiven,
        public ?string $accentColour,
    ) {
    }
}
