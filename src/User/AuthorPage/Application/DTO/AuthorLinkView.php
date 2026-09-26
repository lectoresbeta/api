<?php

declare(strict_types=1);

namespace LectoresBeta\User\AuthorPage\Application\DTO;

/**
 * Una referencia, como se pinta en la página de autor (`FEAT-USR-015`).
 */
final readonly class AuthorLinkView
{
    public function __construct(
        public string $label,
        public string $url,
    ) {
    }
}
