<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Domain\Entity;

use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Community\Post\Domain\ValueObject\PostId;

/**
 * El apoyo a una publicación (`FEAT-COM-008`).
 *
 * **La identidad es el par**, y eso es lo que hace que «uno por persona»
 * (`RN-1`) no dependa de que nadie se acuerde: una segunda fila del mismo par
 * no cabe.
 *
 * La duda sobre si convivía con una reacción con emoji (`CM-1`) está
 * resuelta: `post-interactions.md` dice que el selector de emoji **inserta
 * emojis en el texto** y no es un mecanismo de reacción, así que
 * `FEAT-COM-007` queda derogada y esta es la única forma de apoyar algo.
 */
class PostLike
{
    private string $postId;

    private string $memberId;

    private \DateTimeImmutable $likedAt;

    public function __construct(PostId $postId, MemberId $memberId, \DateTimeImmutable $now)
    {
        $this->postId = $postId->value();
        $this->memberId = $memberId->value();
        $this->likedAt = $now;
    }

    public function postId(): PostId
    {
        return PostId::fromString($this->postId);
    }

    public function memberId(): MemberId
    {
        return MemberId::fromString($this->memberId);
    }
}
