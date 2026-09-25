<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Domain\Entity;

use LectoresBeta\Community\Interaction\Domain\ValueObject\ChapterId;

/**
 * Los contadores sociales de un capítulo: apoyos y comentarios
 * (`FEAT-COM-036`, `R-1`).
 *
 * **Contados aquí y no con un `COUNT` al leer.** La maqueta enseña «Likes
 * 327K» en la cabecera de la pantalla de lectura, y contar 327.000 filas cada
 * vez que alguien abre un capítulo es lo mismo que no poder enseñarlo. Es el
 * criterio que ya siguen `Post` y `PostComment`.
 *
 * No es una copia del capítulo: `Community` no guarda ni su título ni su
 * texto ni su posición, y no sabría reconstruirlo. Es **lo suyo sobre él**,
 * con el identificador ajeno como clave.
 *
 * Se crea con la primera interacción. Un capítulo que nadie ha tocado no
 * tiene fila, y eso se lee como ceros: no hace falta sembrar una fila por
 * capítulo para poder enseñar dos ceros.
 */
class ChapterEngagement
{
    private string $chapterId;

    private int $likeCount = 0;

    private int $commentCount = 0;

    public function __construct(ChapterId $chapterId)
    {
        $this->chapterId = $chapterId->value();
    }

    public function chapterId(): ChapterId
    {
        return ChapterId::fromString($this->chapterId);
    }

    public function likeCount(): int
    {
        return $this->likeCount;
    }

    public function commentCount(): int
    {
        return $this->commentCount;
    }

    public function liked(): void
    {
        ++$this->likeCount;
    }

    public function unliked(): void
    {
        $this->likeCount = max(0, $this->likeCount - 1);
    }

    public function commented(): void
    {
        ++$this->commentCount;
    }

    public function commentRemoved(): void
    {
        $this->commentCount = max(0, $this->commentCount - 1);
    }
}
