<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Domain\Entity;

use LectoresBeta\Work\Manuscript\Domain\Enum\BetaReaderAccessMode;
use LectoresBeta\Work\Manuscript\Domain\Enum\WorkStatus;
use LectoresBeta\Work\Manuscript\Domain\Exception\IllegalWorkTransition;
use LectoresBeta\Work\Manuscript\Domain\Exception\WorkHasNoChapters;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\AuthorId;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkTitle;

/**
 * A work: what the interface calls a «relato» (`FEAT-WRK-001`).
 *
 * One author, at least one chapter, and always in exactly one status. The
 * word count is derived from the chapters and never set by hand — `Credits`
 * prices a chapter from it, so a number the author could type would be a
 * number the author could game.
 *
 * Chapters are not a Doctrine collection here on purpose. A novel can run to
 * 75.000 words and loading the work must not drag the text of every chapter
 * into memory; chapters are read through their own repository, by work.
 */
class Work
{
    private string $id;

    private string $authorId;

    private string $title;

    private ?string $synopsis = null;

    private WorkStatus $status;

    private \DateTimeImmutable $statusChangedAt;

    private BetaReaderAccessMode $accessMode;

    /**
     * Declared at publication and changeable afterwards (`FEAT-WRK-017`).
     * Adults-only travels on its own axis, not as one more label.
     */
    private bool $adultsOnly = false;

    private int $wordCount = 0;

    private int $chapterCount = 0;

    /**
     * Blocked by an upheld claim (`FEAT-MOD-003`). The work stays readable
     * for its author, marked as blocked, and disappears for everybody else
     * (`RN-9`). `Moderation` decides; this context applies.
     */
    private ?\DateTimeImmutable $blockedAt = null;

    /**
     * Retirada por su autor ([`FEAT-WRK-006`](../../../../../docs/features/work/FEAT-WRK-006-delete-work.md)).
     *
     * **Archivada, no borrada.** Una obra de esta plataforma casi nunca es
     * solo de quien la escribió: pueden colgar de ella correcciones pagadas y
     * reclamaciones resueltas, y borrarla destruiría el trabajo de otros y la
     * prueba de lo que se decidió.
     */
    private ?\DateTimeImmutable $archivedAt = null;

    private \DateTimeImmutable $createdAt;

    private \DateTimeImmutable $updatedAt;

    public function __construct(
        WorkId $id,
        AuthorId $authorId,
        WorkTitle $title,
        \DateTimeImmutable $now,
        BetaReaderAccessMode $accessMode = BetaReaderAccessMode::ON_REQUEST,
    ) {
        $this->id = $id->value();
        $this->authorId = $authorId->value();
        $this->title = $title->value();
        $this->status = WorkStatus::DRAFT;
        $this->statusChangedAt = $now;
        $this->accessMode = $accessMode;
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function id(): WorkId
    {
        return WorkId::fromString($this->id);
    }

    public function authorId(): AuthorId
    {
        return AuthorId::fromString($this->authorId);
    }

    public function title(): WorkTitle
    {
        return WorkTitle::fromString($this->title);
    }

    public function synopsis(): ?string
    {
        return $this->synopsis;
    }

    public function status(): WorkStatus
    {
        return $this->status;
    }

    public function accessMode(): BetaReaderAccessMode
    {
        return $this->accessMode;
    }

    public function isAdultsOnly(): bool
    {
        return $this->adultsOnly;
    }

    public function wordCount(): int
    {
        return $this->wordCount;
    }

    public function chapterCount(): int
    {
        return $this->chapterCount;
    }

    public function isBlocked(): bool
    {
        return null !== $this->blockedAt;
    }

    public function isArchived(): bool
    {
        return null !== $this->archivedAt;
    }

    /**
     * El botón dice «Eliminar» y esto es lo que ocurre: desaparece para todos
     * menos para su autor, y nada de lo que cuelga de ella se pierde.
     */
    public function archive(\DateTimeImmutable $now): void
    {
        $this->archivedAt ??= $now;
        $this->touch($now);
    }

    /**
     * Vuelve **a borrador**, nunca publicada ni en corrección (`RN-7`):
     * reabrir la puerta es una decisión aparte, y tomarla por el autor sería
     * volver a enseñar a lectores beta una obra que él retiró.
     */
    public function restore(\DateTimeImmutable $now): void
    {
        $this->archivedAt = null;
        $this->status = WorkStatus::DRAFT;
        $this->statusChangedAt = $now;
        $this->touch($now);
    }

    public function isVisibleTo(AuthorId $reader): bool
    {
        if ($this->authorId === $reader->value()) {
            return true;
        }

        return !$this->isBlocked() && $this->status->isReadableByOthers();
    }

    public function rename(WorkTitle $title, \DateTimeImmutable $now): void
    {
        $this->title = $title->value();
        $this->touch($now);
    }

    public function describe(?string $synopsis, \DateTimeImmutable $now): void
    {
        $this->synopsis = null === $synopsis ? null : trim($synopsis);
        $this->touch($now);
    }

    /**
     * Recomputed from the chapters whenever one changes. `Credits` learns the
     * new figure through `WorkContentUpdated` and reprices on its own.
     */
    public function recountContent(int $wordCount, int $chapterCount, \DateTimeImmutable $now): void
    {
        $this->wordCount = max(0, $wordCount);
        $this->chapterCount = max(0, $chapterCount);
        $this->touch($now);
    }

    public function classify(bool $adultsOnly, \DateTimeImmutable $now): void
    {
        $this->adultsOnly = $adultsOnly;
        $this->touch($now);
    }

    public function changeAccessMode(BetaReaderAccessMode $mode, \DateTimeImmutable $now): void
    {
        $this->accessMode = $mode;
        $this->touch($now);
    }

    /**
     * Draft → visible. Readable, but not yet open to correction: that is a
     * second, separate decision.
     */
    public function publish(\DateTimeImmutable $now): void
    {
        if (0 === $this->chapterCount) {
            throw WorkHasNoChapters::cannotBePublished($this->id);
        }

        $this->changeStatus(WorkStatus::PUBLISHED, $now);
    }

    public function openForCorrection(\DateTimeImmutable $now): void
    {
        if (0 === $this->chapterCount) {
            throw WorkHasNoChapters::cannotBePublished($this->id);
        }

        if (WorkStatus::DRAFT === $this->status) {
            throw IllegalWorkTransition::from($this->status, WorkStatus::IN_CORRECTION);
        }

        $this->changeStatus(WorkStatus::IN_CORRECTION, $now);
    }

    public function closeForCorrection(\DateTimeImmutable $now): void
    {
        if (WorkStatus::IN_CORRECTION !== $this->status) {
            throw IllegalWorkTransition::from($this->status, WorkStatus::PUBLISHED);
        }

        $this->changeStatus(WorkStatus::PUBLISHED, $now);
    }

    public function unpublish(\DateTimeImmutable $now): void
    {
        $this->changeStatus(WorkStatus::DRAFT, $now);
    }

    public function block(\DateTimeImmutable $now): void
    {
        $this->blockedAt ??= $now;
        $this->touch($now);
    }

    public function unblock(\DateTimeImmutable $now): void
    {
        $this->blockedAt = null;
        $this->touch($now);
    }

    private function changeStatus(WorkStatus $status, \DateTimeImmutable $now): void
    {
        if ($this->status === $status) {
            return;
        }

        $this->status = $status;
        $this->statusChangedAt = $now;
        $this->touch($now);
    }

    private function touch(\DateTimeImmutable $now): void
    {
        $this->updatedAt = $now;
    }
}
