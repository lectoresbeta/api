<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Post\Domain\Entity;

use LectoresBeta\Community\Post\Domain\Enum\PostAudience;
use LectoresBeta\Community\Post\Domain\Enum\PostFormat;
use LectoresBeta\Community\Post\Domain\Enum\PostType;
use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Community\Post\Domain\ValueObject\PostId;
use LectoresBeta\Community\Post\Domain\ValueObject\WorkId;

/**
 * A post on the wall (`FEAT-COM-002`).
 *
 * Type, format and audience are three separate columns because they are three
 * separate questions (`RN-5`).
 *
 * Counters live here rather than being counted on read. A wall that runs
 * `COUNT(*)` over comments and likes for every card degrades exactly when the
 * platform starts working.
 */
class Post
{
    private string $id;

    private string $authorId;

    private string $body;

    private PostType $type;

    private PostFormat $format;

    private PostAudience $audience;

    /** Set when the post promotes a work. The work itself belongs to `Work`. */
    private ?string $workId = null;

    /**
     * La dirección del enlace externo, cuando el formato es `LINK`.
     *
     * Se guarda la URL y **nada más** (`FEAT-COM-002` `RN-6`): el título y la
     * descripción de la tarjeta de artículo los generaría el servidor
     * visitando la dirección que escribió alguien, y eso es una capacidad que
     * se decide aparte.
     */
    private ?string $linkUrl = null;

    private int $commentCount = 0;

    private int $likeCount = 0;

    private int $repostCount = 0;

    private \DateTimeImmutable $createdAt;

    private \DateTimeImmutable $updatedAt;

    /**
     * Cuándo se cambió el texto, si se cambió.
     *
     * Columna propia y no `updatedAt != createdAt`: el registro de
     * modificación se mueve por cualquier cosa, y lo que hay que poder
     * afirmar delante de un comentario es **que el texto de arriba no es el
     * que había** cuando se escribió (`RN-13`).
     */
    private ?\DateTimeImmutable $editedAt = null;

    private ?\DateTimeImmutable $deletedAt = null;

    public function __construct(
        PostId $id,
        MemberId $authorId,
        string $body,
        PostType $type,
        PostFormat $format,
        PostAudience $audience,
        \DateTimeImmutable $now,
    ) {
        $this->id = $id->value();
        $this->authorId = $authorId->value();
        $this->body = trim($body);
        $this->type = $type;
        $this->format = $format;
        $this->audience = $audience;
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function id(): PostId
    {
        return PostId::fromString($this->id);
    }

    public function authorId(): MemberId
    {
        return MemberId::fromString($this->authorId);
    }

    public function body(): string
    {
        return $this->body;
    }

    public function type(): PostType
    {
        return $this->type;
    }

    public function format(): PostFormat
    {
        return $this->format;
    }

    public function audience(): PostAudience
    {
        return $this->audience;
    }

    public function workId(): ?WorkId
    {
        return null === $this->workId ? null : WorkId::fromString($this->workId);
    }

    public function linkUrl(): ?string
    {
        return $this->linkUrl;
    }

    public function isDeleted(): bool
    {
        return null !== $this->deletedAt;
    }

    public function wasEdited(): bool
    {
        return null !== $this->editedAt;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function commentCount(): int
    {
        return $this->commentCount;
    }

    public function likeCount(): int
    {
        return $this->likeCount;
    }

    public function repostCount(): int
    {
        return $this->repostCount;
    }

    public function promoteWork(WorkId $workId): void
    {
        $this->workId = $workId->value();
    }

    public function linkTo(string $url): void
    {
        $this->linkUrl = $url;
    }

    /**
     * Cambiar el texto, que es lo **único** que se puede cambiar (`RN-13`).
     *
     * Ni la audiencia ni el adjunto: ampliar la audiencia haría aparecer ante
     * todos algo escrito para un círculo cerrado, y cambiar el adjunto
     * alteraría aquello a lo que ya respondió alguien.
     *
     * Un texto idéntico **no marca la publicación como editada**. Quien pulsa
     * «guardar» sin haber tocado nada no ha editado nada, y la marca existe
     * para avisar a quien lee, no para contar pulsaciones.
     */
    public function edit(string $body, \DateTimeImmutable $now): bool
    {
        $body = trim($body);

        if ($body === $this->body) {
            return false;
        }

        $this->body = $body;
        $this->editedAt = $now;
        $this->updatedAt = $now;

        return true;
    }

    public function delete(\DateTimeImmutable $now): void
    {
        $this->deletedAt ??= $now;
        $this->updatedAt = $now;
    }

    public function commentAdded(): void
    {
        ++$this->commentCount;
    }

    public function commentRemoved(): void
    {
        $this->commentCount = max(0, $this->commentCount - 1);
    }

    public function liked(): void
    {
        ++$this->likeCount;
    }

    public function unliked(): void
    {
        $this->likeCount = max(0, $this->likeCount - 1);
    }

    public function reposted(): void
    {
        ++$this->repostCount;
    }
}
