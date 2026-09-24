<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Application\Command;

/**
 * Un `PUT`, y por eso no hay booleanos de presencia como en
 * `UpdateMyProfile`: aquí el dato **es** la lista, y una lista parcial no
 * significa nada. La selección que llega sustituye a la anterior.
 */
final readonly class UpdateMyLiteraryPreferences
{
    /**
     * @param list<string> $genreCodes
     */
    public function __construct(
        public string $userId,
        public array $genreCodes,
    ) {
    }
}
