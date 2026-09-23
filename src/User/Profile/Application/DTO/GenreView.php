<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Application\DTO;

/**
 * One genre as the API shows it.
 *
 * `code` is the stable identifier that travels in payloads and in other
 * contexts; `name` is what a person reads and may be reworded without
 * breaking anything.
 */
final readonly class GenreView
{
    public function __construct(
        public string $code,
        public string $name,
    ) {
    }
}
