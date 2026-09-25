<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Chapter\Domain\Entity;

use LectoresBeta\Work\Chapter\Domain\Enum\ChapterVisibility;
use LectoresBeta\Work\Chapter\Domain\Service\WordCounter;
use LectoresBeta\Work\Chapter\Domain\ValueObject\ChapterContent;
use LectoresBeta\Work\Chapter\Domain\ValueObject\ChapterId;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId;

/**
 * A fragment of a work, and **the unit that gets corrected**
 * (`FEAT-FBK-003`).
 *
 * That is why the word count lives here and not only on the work: the price
 * of a correction is per chapter.
 *
 * The content is a column and not external storage. A chapter is at most a
 * few tens of thousands of words, it is read whole, and keeping it in
 * PostgreSQL means the text is covered by the same backup and the same
 * transaction as everything else. `P-2` can still move it later; nothing in
 * the domain model would change.
 */
class Chapter
{
    private string $id;

    private string $workId;

    private int $position;

    private ?string $title = null;

    private string $contentHtml = '';

    /**
     * The plain text derived from the HTML, and **the source of truth for the
     * word count**. Stored rather than derived on read: deriving it would tie
     * the count to whatever the stripping code does today, so changing that
     * code would silently reprice every chapter.
     */
    private string $contentText = '';

    private int $wordCount = 0;

    /**
     * Qué número de versión tiene el texto que hay ahora
     * ([`FEAT-WRK-005`](../../../../../docs/features/work/FEAT-WRK-005-edit-work-and-chapter.md)).
     *
     * Empieza en 1 y solo sube cuando se archiva la anterior, que es cuando
     * alguien la ha leído. Un autor que teclea y guarda veinte veces antes de
     * abrir su obra a nadie sigue en la 1.
     */
    private int $version = 1;

    /**
     * Cuándo empezó alguien a corregir **esta** versión, si es que alguien lo
     * hizo. Es lo que convierte el texto en algo que ya no se puede
     * sobrescribir sin dejar copia: hay una corrección escribiéndose sobre
     * él, o escrita.
     */
    private ?\DateTimeImmutable $currentVersionReadAt = null;

    private ChapterVisibility $visibility;

    /** Blocked by an upheld claim (`FEAT-MOD-003`, `MOD-13`). */
    private ?\DateTimeImmutable $blockedAt = null;

    private \DateTimeImmutable $createdAt;

    private \DateTimeImmutable $updatedAt;

    public function __construct(
        ChapterId $id,
        WorkId $workId,
        int $position,
        \DateTimeImmutable $now,
        ?string $title = null,
    ) {
        $this->id = $id->value();
        $this->workId = $workId->value();
        $this->position = $position;
        $this->title = $title;
        $this->visibility = ChapterVisibility::VISIBLE;
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function id(): ChapterId
    {
        return ChapterId::fromString($this->id);
    }

    public function workId(): WorkId
    {
        return WorkId::fromString($this->workId);
    }

    public function position(): int
    {
        return $this->position;
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

    public function version(): int
    {
        return $this->version;
    }

    /**
     * Alguien ha empezado a corregir el texto que hay ahora. A partir de
     * aquí, editarlo cuesta una copia.
     */
    public function markVersionRead(\DateTimeImmutable $now): void
    {
        $this->currentVersionReadAt ??= $now;
        $this->updatedAt = $now;
    }

    /**
     * Si sobrescribir el texto perdería algo que alguien leyó.
     *
     * Es la regla entera de `RN-2` en una línea, y la razón de que viva aquí
     * y no en el caso de uso: la pregunta «¿se puede pisar este texto?» es
     * del capítulo.
     */
    public function needsArchivingBeforeEditing(): bool
    {
        return null !== $this->currentVersionReadAt;
    }

    /**
     * La versión actual ya está archivada: este capítulo pasa a ser la
     * siguiente, todavía sin leer por nadie.
     */
    public function openNextVersion(\DateTimeImmutable $now): void
    {
        ++$this->version;
        $this->currentVersionReadAt = null;
        $this->updatedAt = $now;
    }

    public function visibility(): ChapterVisibility
    {
        return $this->visibility;
    }

    public function isBlocked(): bool
    {
        return null !== $this->blockedAt;
    }

    /**
     * The word count is recomputed here and never passed in: it is derived
     * from the content, and the whole price depends on it.
     *
     * It takes a `ChapterContent`, which can only be built by the sanitiser.
     * Raw input has no way into this method, and that is the point: the
     * platform never stores what it would not be willing to serve.
     */
    public function replaceContent(ChapterContent $content, WordCounter $counter, \DateTimeImmutable $now): void
    {
        $this->contentHtml = $content->html;
        $this->contentText = $content->text;
        $this->wordCount = $counter->count($content->text);
        $this->updatedAt = $now;
    }

    public function retitle(?string $title, \DateTimeImmutable $now): void
    {
        $this->title = null === $title ? null : trim($title);
        $this->updatedAt = $now;
    }

    public function moveTo(int $position, \DateTimeImmutable $now): void
    {
        $this->position = $position;
        $this->updatedAt = $now;
    }

    public function hide(\DateTimeImmutable $now): void
    {
        $this->visibility = ChapterVisibility::HIDDEN;
        $this->updatedAt = $now;
    }

    public function show(\DateTimeImmutable $now): void
    {
        $this->visibility = ChapterVisibility::VISIBLE;
        $this->updatedAt = $now;
    }

    public function block(\DateTimeImmutable $now): void
    {
        $this->blockedAt ??= $now;
        $this->updatedAt = $now;
    }

    public function unblock(\DateTimeImmutable $now): void
    {
        $this->blockedAt = null;
        $this->updatedAt = $now;
    }
}
