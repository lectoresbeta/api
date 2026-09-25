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

    /**
     * La versión del **texto** del capítulo que esta persona leyó
     * ([`FEAT-WRK-005`](../../../../../docs/features/work/FEAT-WRK-005-edit-work-and-chapter.md)).
     *
     * Es el hermano de `questionnaireVersion` y existe por lo mismo: quien
     * empezó a trabajar sobre unas condiciones las conserva. Nula en las
     * correcciones anteriores al versionado, que no tienen versión que
     * recordar.
     */
    private ?int $chapterVersion = null;

    private CorrectionStatus $status;

    private CorrectionOrigin $origin;

    private CorrectionVisibility $visibility;

    /** Whether the author found it useful (`F-5` is still open on the scale). */
    private ?bool $helpful = null;

    private ?\DateTimeImmutable $ratedAt = null;

    /**
     * Lo que el autor dio de propina (`FEAT-CRD-017`).
     *
     * **Se apunta aquí después, no se decide aquí.** La propina la mueve
     * `Credits`, que es quien sabe si el autor tenía saldo, y este contexto
     * la anota al recibir `CorrectionTipped` porque es donde autor y
     * corrector la van a ver.
     */
    private ?int $tipAmount = null;

    private ?\DateTimeImmutable $tippedAt = null;

    /**
     * Cuándo la abrió el autor por primera vez (`FEAT-FBK-004` `RN-7`).
     *
     * Es del destinatario y de nadie más: quien la escribió **no** ve si se
     * ha leído. Sería una confirmación de lectura entre dos personas que no
     * han elegido tener una conversación (`F-14`).
     */
    private ?\DateTimeImmutable $readAt = null;

    /**
     * Cuándo se avisó a quien lo escribía de que lo que estaba corrigiendo
     * había dejado de estar disponible.
     *
     * **Es una marca de aviso, no de estado.** Si el contenido vuelve, el
     * borrador se entrega igual: quién puede corregir lo decide `Work` cada
     * vez que se pregunta. Esto solo impide avisar dos veces de lo mismo, que
     * es lo que ocurriría en cuanto la cola reentregase el hecho.
     */
    private ?\DateTimeImmutable $withdrawalNoticedAt = null;

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
        ?int $chapterVersion = null,
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
        $correction->chapterVersion = $chapterVersion;

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
        ?int $chapterVersion = null,
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
        $correction->chapterVersion = $chapterVersion;

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

    public function chapterVersion(): ?int
    {
        return $this->chapterVersion;
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

    public function startedAt(): \DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function submittedAt(): ?\DateTimeImmutable
    {
        return $this->submittedAt;
    }

    public function authorLabel(): ?string
    {
        return $this->authorLabel;
    }

    public function helpful(): ?bool
    {
        return $this->helpful;
    }

    public function ratedAt(): ?\DateTimeImmutable
    {
        return $this->ratedAt;
    }

    public function tipAmount(): ?int
    {
        return $this->tipAmount;
    }

    /**
     * Se **fija**, no se acumula: el hecho dice cuánto fue la propina, así
     * que una reentrega escribe la misma cifra. Sumar convertiría un
     * reintento de la cola en una propina más grande de la que nadie dio.
     */
    public function recordTip(int $amount, \DateTimeImmutable $now): void
    {
        $this->tipAmount = $amount;
        $this->tippedAt ??= $now;
    }

    public function readAt(): ?\DateTimeImmutable
    {
        return $this->readAt;
    }

    /**
     * Si su contenido se puede enseñar. Una retenida por descubierto existe
     * y se ve que existe, pero no se lee (`FEAT-CRD-018`).
     */
    public function isReadable(): bool
    {
        return CorrectionVisibility::LOCKED !== $this->visibility;
    }

    /**
     * La primera vez y solo la primera: la fecha dice **cuándo se leyó**, y
     * pisarla en cada visita la convertiría en «cuándo se miró por última
     * vez», que es otra cosa y no la que hace falta.
     *
     * Una retenida no se marca: no se ha leído nada.
     */
    public function markRead(\DateTimeImmutable $now): bool
    {
        if (null !== $this->readAt || !$this->isReadable() || CorrectionStatus::SUBMITTED !== $this->status) {
            return false;
        }

        $this->readAt = $now;
        $this->updatedAt = $now;

        return true;
    }

    /**
     * Avisa una vez y solo una. Devuelve si este aviso es el primero, que es
     * lo que decide si el hecho llega a publicarse: el transporte entrega al
     * menos una vez, y un segundo «has perdido tu trabajo» por el mismo
     * motivo es gratuito y cruel.
     */
    public function noteWithdrawalNotice(\DateTimeImmutable $now): bool
    {
        if (null !== $this->withdrawalNoticedAt) {
            return false;
        }

        $this->withdrawalNoticedAt = $now;
        $this->updatedAt = $now;

        return true;
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
