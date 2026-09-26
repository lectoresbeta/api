<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Curation\Domain\Entity;

use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Community\Post\Domain\ValueObject\PostId;

/**
 * Alguien apartó una publicación para volver a ella (`FEAT-COM-021`).
 *
 * **Privado.** No se cuenta, no se anuncia y el autor no se entera: un
 * contador de guardados convertiría una nota para uno mismo en una señal
 * pública, y entonces la gente dejaría de usarla para lo que sirve.
 *
 * La clave es el par, porque guardar dos veces es guardar.
 */
class SavedPost
{
    private string $memberId;

    private string $postId;

    private \DateTimeImmutable $savedAt;

    public function __construct(MemberId $memberId, PostId $postId, \DateTimeImmutable $now)
    {
        $this->memberId = $memberId->value();
        $this->postId = $postId->value();
        $this->savedAt = $now;
    }

    public function memberId(): MemberId
    {
        return MemberId::fromString($this->memberId);
    }

    public function postId(): PostId
    {
        return PostId::fromString($this->postId);
    }

    public function savedAt(): \DateTimeImmutable
    {
        return $this->savedAt;
    }
}
