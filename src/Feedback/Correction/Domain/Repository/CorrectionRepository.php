<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Domain\Repository;

use LectoresBeta\Feedback\Correction\Domain\Entity\Correction;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\AuthorId;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\ChapterId;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\CorrectionId;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\ReaderId;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\WorkId;

interface CorrectionRepository
{
    public function save(Correction $correction): void;

    public function ofId(CorrectionId $id): ?Correction;

    /**
     * The one correction this reader has on this chapter, draft or delivered
     * (`RN-2`). The database enforces the uniqueness; this just reads it.
     */
    public function ofReaderAndChapter(ReaderId $readerId, ChapterId $chapterId): ?Correction;

    /**
     * Everything the author received for a work. **By work and not by
     * chapter**: the author wants all the feedback on their novel together.
     *
     * @return list<Correction>
     */
    public function deliveredOnWork(WorkId $workId, int $limit = 50, int $offset = 0): array;

    /**
     * «Mis correcciones» (`FEAT-FBK-010`).
     *
     * @return list<Correction>
     */
    public function deliveredBy(ReaderId $readerId, int $limit = 50, int $offset = 0): array;

    public function deliveredCountBy(ReaderId $readerId): int;

    /**
     * The corrections an author cannot read yet because their balance went
     * negative (`FEAT-CRD-018`). Topping up unlocks all of them at once.
     *
     * @return list<Correction>
     */
    public function lockedFor(AuthorId $ownerId): array;

    public function discardDraft(Correction $correction): void;
}
