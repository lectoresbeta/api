<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Domain\Entity;

use LectoresBeta\Community\Interaction\Domain\ValueObject\ChapterCommentId;
use LectoresBeta\Community\Interaction\Domain\ValueObject\ChapterId;
use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;

/**
 * Un comentario bajo el texto de un capítulo, o una respuesta a uno
 * (`FEAT-COM-036`).
 *
 * **No es un `PostComment`, y es la distinción que da sentido a esta ficha**
 * (`R-9`, resuelta). Se parecen en la forma y no en lo que son:
 *
 * - un `PostComment` cuelga de una publicación y hereda **su audiencia**;
 *   este cuelga de un capítulo y hereda **la regla de lectura de la obra**,
 *   que es otra cosa y vive en otro contexto;
 * - un comentario aquí convive en la misma pantalla con la **corrección**, y
 *   confundirlos ya pasó una vez: comentar no mueve créditos, responder al
 *   cuestionario sí (`FEAT-FBK-003`).
 *
 * Meterlos en la misma tabla con un `postId` anulable habría dejado dos
 * caminos de autorización dentro de una entidad, que es la forma más rápida
 * de que un día se aplique el que no toca.
 *
 * `parentCommentId` aplana las respuestas a un nivel, igual que en el muro:
 * una respuesta a una respuesta cuelga del comentario raíz. Los hilos
 * profundos parecen baratos y convierten cada lectura en una consulta
 * recursiva.
 */
class ChapterComment
{
    private string $id;

    private string $chapterId;

    private string $authorId;

    private ?string $parentCommentId = null;

    private string $body;

    private int $replyCount = 0;

    private \DateTimeImmutable $createdAt;

    private ?\DateTimeImmutable $deletedAt = null;

    public function __construct(
        ChapterCommentId $id,
        ChapterId $chapterId,
        MemberId $authorId,
        string $body,
        \DateTimeImmutable $now,
        ?ChapterCommentId $parentCommentId = null,
    ) {
        $this->id = $id->value();
        $this->chapterId = $chapterId->value();
        $this->authorId = $authorId->value();
        $this->body = trim($body);
        $this->createdAt = $now;
        $this->parentCommentId = $parentCommentId?->value();
    }

    public function id(): ChapterCommentId
    {
        return ChapterCommentId::fromString($this->id);
    }

    public function chapterId(): ChapterId
    {
        return ChapterId::fromString($this->chapterId);
    }

    public function authorId(): MemberId
    {
        return MemberId::fromString($this->authorId);
    }

    public function parentCommentId(): ?ChapterCommentId
    {
        return null === $this->parentCommentId ? null : ChapterCommentId::fromString($this->parentCommentId);
    }

    public function body(): string
    {
        return $this->body;
    }

    public function replyCount(): int
    {
        return $this->replyCount;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isDeleted(): bool
    {
        return null !== $this->deletedAt;
    }

    public function isBy(MemberId $member): bool
    {
        return $this->authorId === $member->value();
    }

    public function replied(): void
    {
        ++$this->replyCount;
    }

    /**
     * Una respuesta menos. No baja de cero: un contador negativo es una
     * mentira más visible que la que intentaba arreglar.
     */
    public function replyRemoved(): void
    {
        $this->replyCount = max(0, $this->replyCount - 1);
    }

    /**
     * **Borrado lógico**, igual que en el muro: una respuesta que cuelga de
     * un comentario desaparecido se quedaría huérfana, y quien la escribió no
     * ha hecho nada.
     */
    public function deleted(\DateTimeImmutable $now): void
    {
        $this->deletedAt ??= $now;
    }
}
