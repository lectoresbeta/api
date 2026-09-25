<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Domain\Repository;

use LectoresBeta\Feedback\Correction\Domain\Entity\Correction;
use LectoresBeta\Feedback\Correction\Domain\Enum\CorrectionStatus;
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
     * La bandeja del autor: **todo lo que ha recibido, de todas sus obras**
     * (`FEAT-FBK-004`).
     *
     * `deliveredOnWork` responde por obra y sigue haciendo falta; esto
     * responde la pregunta que se hace quien abre la aplicación y quiere
     * saber qué ha llegado, que no viene ordenada por obra.
     *
     * @return list<Correction>
     */
    public function receivedBy(
        AuthorId $ownerId,
        ?WorkId $workId,
        ?ChapterId $chapterId,
        bool $unreadOnly,
        int $limit,
        int $offset,
    ): array;

    /**
     * «Mis correcciones» (`FEAT-FBK-010`): lo que esa persona ha escrito,
     * **incluidos sus borradores**.
     *
     * Un borrador es trabajo empezado y quien lo dejó a medias necesita
     * encontrarlo. Es privado suyo: no aparece en ninguna lista de nadie más.
     *
     * @return list<Correction>
     */
    public function writtenBy(
        ReaderId $readerId,
        ?WorkId $workId,
        ?CorrectionStatus $status,
        int $limit,
        int $offset,
    ): array;

    /**
     * Solo las entregadas, para el contador público del perfil.
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

    /**
     * Los borradores vivos sobre esa obra, o sobre ese capítulo.
     *
     * Existe para avisar a quien tiene trabajo a medias cuando el autor
     * retira, bloquea u oculta lo que estaba corrigiendo: lo descubre al
     * intentar entregar, que es tarde y desconcertante.
     *
     * @return list<Correction>
     */
    public function draftsOn(?WorkId $workId, ?ChapterId $chapterId): array;

    public function discardDraft(Correction $correction): void;
}
