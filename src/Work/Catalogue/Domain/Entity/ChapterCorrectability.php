<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Catalogue\Domain\Entity;

use LectoresBeta\Work\Chapter\Domain\ValueObject\ChapterId;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId;

/**
 * Si un capítulo admite correcciones y cuánto trabajo produciría enseñarlo
 * (`FEAT-WRK-012`, `decision:0008`).
 *
 * Es una proyección de lo que decide `Credits`. Existe porque el catálogo no
 * puede hacer un `JOIN` con las tablas de otro contexto acotado, y porque
 * ordenar exige tener el dato **aquí**, en la misma consulta que filtra y
 * pagina.
 *
 * Lo que guarda no es dinero: un booleano y un contador con tope de diez.
 * `Work` sigue sin saber lo que cuesta corregir nada.
 */
class ChapterCorrectability
{
    private string $chapterId;

    private string $workId;

    private bool $correctable;

    /** Correcciones que el autor puede pagar, ya acotadas por `Credits`. */
    private int $affordableCorrections;

    /**
     * Cuándo ocurrió el hecho, no cuándo llegó: la cola no promete orden, y
     * una respuesta vieja pisando a una nueva cerraría un capítulo abierto.
     */
    private \DateTimeImmutable $changedAt;

    public function __construct(
        ChapterId $chapterId,
        WorkId $workId,
        bool $correctable,
        int $affordableCorrections,
        \DateTimeImmutable $changedAt,
    ) {
        $this->chapterId = $chapterId->value();
        $this->workId = $workId->value();
        $this->correctable = $correctable;
        $this->affordableCorrections = $affordableCorrections;
        $this->changedAt = $changedAt;
    }

    public function chapterId(): ChapterId
    {
        return ChapterId::fromString($this->chapterId);
    }

    public function workId(): WorkId
    {
        return WorkId::fromString($this->workId);
    }

    public function isCorrectable(): bool
    {
        return $this->correctable;
    }

    public function affordableCorrections(): int
    {
        return $this->affordableCorrections;
    }

    public function record(bool $correctable, int $affordableCorrections, \DateTimeImmutable $changedAt): void
    {
        if ($changedAt < $this->changedAt) {
            return;
        }

        $this->correctable = $correctable;
        $this->affordableCorrections = $affordableCorrections;
        $this->changedAt = $changedAt;
    }
}
