<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Domain\Entity;

use LectoresBeta\Feedback\Correction\Domain\Enum\CorrectionOrigin;
use LectoresBeta\Feedback\Correction\Domain\Enum\CorrectionStatus;
use LectoresBeta\Feedback\Correction\Domain\Enum\CorrectionVisibility;
use LectoresBeta\Feedback\Correction\Domain\Exception\CorrectionAlreadySubmitted;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\AuthorId;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\ChapterId;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\CorrectionId;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\ReaderId;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\WorkId;

/**
 * The author's questionnaire, answered by one beta reader for one chapter
 * (`FEAT-FBK-003`).
 *
 * **One per reader and chapter**, guaranteed by a unique index and not by a
 * check in code: under concurrency, code is not a guarantee.
 *
 * It answers a **specific version** of the questionnaire. Whoever started
 * under certain terms keeps them, even if the author raises the demands
 * afterwards (`decision:0006` `RN-7`).
 *
 * Free comments under a chapter are **not** this. Those are social
 * interaction and belong to `Community` (`FEAT-COM-036`); they move no
 * credits. The two share a screen, which is what made them easy to confuse.
 */
class Correction
{
    private string $id;

    private string $workId;

    private string $chapterId;

    /**
     * Null for a correction that arrived through a public link: there is no
     * account behind it. The unique index on (chapter, reader) does not apply
     * to those, and several people may correct the same chapter that way.
     */
    private ?string $readerId = null;

    /** The name a public corrector typed for themselves. Not an identity. */
    private ?string $authorLabel = null;

    private string $ownerId;

    private int $questionnaireVersion;

    private CorrectionStatus $status;

    private CorrectionOrigin $origin;

    private CorrectionVisibility $visibility;

    /** Whether the author found it useful (`F-5` is still open on the scale). */
    private ?bool $helpful = null;

    private ?\DateTimeImmutable $ratedAt = null;

    /**
     * The tip the author chose to give (`FEAT-CRD-017`). The intent is
     * recorded here; the credits are moved by `Credits` on `CorrectionTipped`.
     */
    private ?int $tipAmount = null;

    private ?\DateTimeImmutable $tippedAt = null;

    private \DateTimeImmutable $startedAt;

    private ?\DateTimeImmutable $submittedAt = null;

    private \DateTimeImmutable $updatedAt;

    private function __construct(
        CorrectionId $id,
        WorkId $workId,
        ChapterId $chapterId,
        AuthorId $ownerId,
        int $questionnaireVersion,
        CorrectionOrigin $origin,
        \DateTimeImmutable $now,
    ) {
        $this->id = $id->value();
        $this->workId = $workId->value();
        $this->chapterId = $chapterId->value();
        $this->ownerId = $ownerId->value();
        $this->questionnaireVersion = $questionnaireVersion;
        $this->origin = $origin;
        $this->status = CorrectionStatus::DRAFT;
        $this->visibility = CorrectionVisibility::VISIBLE;
        $this->startedAt = $now;
        $this->updatedAt = $now;
    }

    public static function start(
        CorrectionId $id,
        WorkId $workId,
        ChapterId $chapterId,
        ReaderId $readerId,
        AuthorId $ownerId,
        int $questionnaireVersion,
        \DateTimeImmutable $now,
    ): self {
        $correction = new self(
            $id,
            $workId,
            $chapterId,
            $ownerId,
            $questionnaireVersion,
            CorrectionOrigin::BETA_READER,
            $now,
        );
        $correction->readerId = $readerId->value();

        return $correction;
    }

    /**
     * A correction left through a public link (`FEAT-FBK-008`). Outside the
     * economy: it costs nothing and pays nobody.
     */
    public static function startFromPublicLink(
        CorrectionId $id,
        WorkId $workId,
        ChapterId $chapterId,
        AuthorId $ownerId,
        int $questionnaireVersion,
        \DateTimeImmutable $now,
        ?string $authorLabel = null,
    ): self {
        $correction = new self(
            $id,
            $workId,
            $chapterId,
            $ownerId,
            $questionnaireVersion,
            CorrectionOrigin::PUBLIC_LINK,
            $now,
        );
        $correction->authorLabel = $authorLabel;

        return $correction;
    }

    public function id(): CorrectionId
    {
        return CorrectionId::fromString($this->id);
    }

    public function workId(): WorkId
    {
        return WorkId::fromString($this->workId);
    }

    public function chapterId(): ChapterId
    {
        return ChapterId::fromString($this->chapterId);
    }

    public function readerId(): ?ReaderId
    {
        return null === $this->readerId ? null : ReaderId::fromString($this->readerId);
    }

    public function ownerId(): AuthorId
    {
        return AuthorId::fromString($this->ownerId);
    }

    public function questionnaireVersion(): int
    {
        return $this->questionnaireVersion;
    }

    public function status(): CorrectionStatus
    {
        return $this->status;
    }

    public function origin(): CorrectionOrigin
    {
        return $this->origin;
    }

    public function visibility(): CorrectionVisibility
    {
        return $this->visibility;
    }

    public function submittedAt(): ?\DateTimeImmutable
    {
        return $this->submittedAt;
    }

    public function isDraft(): bool
    {
        return CorrectionStatus::DRAFT === $this->status;
    }

    public function submit(\DateTimeImmutable $now): void
    {
        $this->guardEditable();

        $this->status = CorrectionStatus::SUBMITTED;
        $this->submittedAt = $now;
        $this->updatedAt = $now;
    }

    /**
     * The charge left the author negative, so the content is withheld until
     * they top up (`FEAT-CRD-018`). The corrector keeps what they earned.
     */
    public function lock(\DateTimeImmutable $now): void
    {
        if (CorrectionVisibility::HIDDEN_BY_AUTHOR === $this->visibility) {
            return;
        }

        $this->visibility = CorrectionVisibility::LOCKED;
        $this->updatedAt = $now;
    }

    public function unlock(\DateTimeImmutable $now): void
    {
        if (CorrectionVisibility::LOCKED !== $this->visibility) {
            return;
        }

        $this->visibility = CorrectionVisibility::VISIBLE;
        $this->updatedAt = $now;
    }

    /**
     * Hidden, never deleted (`RN-3`), and never hidden from whoever wrote it
     * (`RN-6`).
     */
    public function hide(\DateTimeImmutable $now): void
    {
        $this->visibility = CorrectionVisibility::HIDDEN_BY_AUTHOR;
        $this->updatedAt = $now;
    }

    public function rate(bool $helpful, \DateTimeImmutable $now): void
    {
        $this->helpful = $helpful;
        $this->ratedAt = $now;
        $this->updatedAt = $now;
    }

    public function tip(int $amount, \DateTimeImmutable $now): void
    {
        $this->tipAmount = $amount;
        $this->tippedAt = $now;
        $this->updatedAt = $now;
    }

    private function guardEditable(): void
    {
        if (!$this->status->isEditable()) {
            throw CorrectionAlreadySubmitted::withId($this->id);
        }
    }
}
