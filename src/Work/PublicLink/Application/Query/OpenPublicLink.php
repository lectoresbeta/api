<?php

declare(strict_types=1);

namespace LectoresBeta\Work\PublicLink\Application\Query;

final readonly class OpenPublicLink
{
    public function __construct(
        public string $token,
        /** Quién lo abre, si es alguien. Nulo es el caso normal. */
        public ?string $readerId = null,
        /** El capítulo que quiere leer, si ya eligió uno. */
        public ?string $chapterId = null,
    ) {
    }
}
