<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Application\DTO;

/**
 * Lo que una obra declara contener, y para quién (`FEAT-WRK-017`).
 *
 * Las etiquetas salen como códigos y no como el enum: quien las recibe es el
 * borde HTTP, y sacar el enum de aquí haría que la forma de la respuesta
 * dependiera de un tipo del dominio.
 */
final readonly class ContentRating
{
    /**
     * @param list<string> $contentWarnings
     */
    public function __construct(
        public bool $adultsOnly,
        public array $contentWarnings,
    ) {
    }
}
