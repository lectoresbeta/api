<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Chapter\Domain\Entity;

use LectoresBeta\Work\Chapter\Domain\ValueObject\ChapterContent;
use LectoresBeta\Work\Chapter\Domain\ValueObject\ChapterId;
use LectoresBeta\Work\Chapter\Domain\ValueObject\ChapterVersionId;

/**
 * El texto de un capítulo **tal y como estaba** cuando alguien lo corrigió
 * ([`FEAT-WRK-005`](../../../../../docs/features/work/FEAT-WRK-005-edit-work-and-chapter.md)).
 *
 * Existe por una razón concreta: una corrección que dice «el diálogo de la
 * página tres no suena» y apunta a un texto donde ya no hay ningún diálogo no
 * es que envejezca, es que deja de tener sentido — y el autor pagó por ella.
 *
 * **Se escribe una vez y no se toca nunca más.** No hay mutadores y el
 * repositorio no ofrece actualización: archivar es copiar. Una copia que se
 * puede editar no sirve para lo único que hace falta, que es decir qué había
 * entonces.
 *
 * Solo se guarda cuando alguien ha leído esa versión (`RN-2`). Un autor que
 * guarda veinte veces antes de abrir su obra no deja veinte filas.
 */
class ChapterVersion
{
    private string $id;

    private string $chapterId;

    private int $version;

    private ?string $title = null;

    private string $contentHtml = '';

    private string $contentText = '';

    private int $wordCount = 0;

    private \DateTimeImmutable $archivedAt;

    public function __construct(
        ChapterVersionId $id,
        ChapterId $chapterId,
        int $version,
        ?string $title,
        ChapterContent $content,
        int $wordCount,
        \DateTimeImmutable $now,
    ) {
        $this->id = $id->value();
        $this->chapterId = $chapterId->value();
        $this->version = $version;
        $this->title = $title;
        $this->contentHtml = $content->html;
        $this->contentText = $content->text;
        $this->wordCount = $wordCount;
        $this->archivedAt = $now;
    }

    public function id(): ChapterVersionId
    {
        return ChapterVersionId::fromString($this->id);
    }

    public function chapterId(): ChapterId
    {
        return ChapterId::fromString($this->chapterId);
    }

    public function version(): int
    {
        return $this->version;
    }

    public function title(): ?string
    {
        return $this->title;
    }

    public function content(): ChapterContent
    {
        return new ChapterContent($this->contentHtml, $this->contentText);
    }

    public function wordCount(): int
    {
        return $this->wordCount;
    }

    public function archivedAt(): \DateTimeImmutable
    {
        return $this->archivedAt;
    }
}
