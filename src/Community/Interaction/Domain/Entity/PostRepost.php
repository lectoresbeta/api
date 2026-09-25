<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Domain\Entity;

use LectoresBeta\Community\Interaction\Domain\ValueObject\PostRepostId;
use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Community\Post\Domain\ValueObject\PostId;

/**
 * Alguien que vuelve a sacar la publicación de otro (`FEAT-COM-019`).
 *
 * **Es una referencia, no una publicación aparte** (`C-3`). Los contadores y
 * la conversación son los del original, que es lo que enseña el diseño, y
 * así no hay cadenas de reposts anidados que mostrar ni que moderar.
 *
 * Un repost **no amplía la audiencia** del original (`RN-5`): quien no podía
 * verlo sigue sin poder. Por eso aquí no se copia ninguna audiencia — la
 * comprobación se hace siempre sobre el original, y copiarla sería crear una
 * segunda verdad que puede quedarse vieja.
 *
 * Lleva texto propio opcional, que es lo que separa reenviar de citar.
 */
class PostRepost
{
    private string $id;

    private string $postId;

    private string $memberId;

    private ?string $comment = null;

    private \DateTimeImmutable $createdAt;

    public function __construct(
        PostRepostId $id,
        PostId $postId,
        MemberId $memberId,
        \DateTimeImmutable $now,
        ?string $comment = null,
    ) {
        $this->id = $id->value();
        $this->postId = $postId->value();
        $this->memberId = $memberId->value();
        $this->createdAt = $now;
        $this->comment = $comment;
    }

    public function id(): PostRepostId
    {
        return PostRepostId::fromString($this->id);
    }

    public function postId(): PostId
    {
        return PostId::fromString($this->postId);
    }

    public function memberId(): MemberId
    {
        return MemberId::fromString($this->memberId);
    }

    public function comment(): ?string
    {
        return $this->comment;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
