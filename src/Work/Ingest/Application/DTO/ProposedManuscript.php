<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Ingest\Application\DTO;

/**
 * Lo que se le enseña al autor para que revise antes de crear nada
 * (`FEAT-WRK-002`).
 *
 * **No lleva el texto de los capítulos, solo un extracto.** Devolver una
 * novela de setenta y cinco mil palabras para pintar una lista de treinta
 * filas sería mandar el manuscrito entero de vuelta por el gusto de
 * enseñarle su primer renglón a cada uno.
 */
final readonly class ProposedManuscript
{
    /**
     * @param list<array{position: int, title: string|null, wordCount: int, preview: string}> $chapters
     */
    public function __construct(
        public string $uploadId,
        public string $filename,
        public array $chapters,
        public int $wordCount,
        public \DateTimeImmutable $expiresAt,
    ) {
    }
}
