<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Domain\Entity;

use LectoresBeta\Community\Interaction\Domain\ValueObject\ChapterId;
use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;

/**
 * Que alguien ha apoyado un capítulo (`FEAT-COM-036`).
 *
 * El par es la identidad, que es lo que hace que apoyar sea idempotente sin
 * esfuerzo: el botón se pulsa dos veces sin querer y el cliente reintenta
 * cuando la red falla.
 */
class ChapterLike
{
    private string $chapterId;

    private string $memberId;

    private \DateTimeImmutable $likedAt;

    public function __construct(ChapterId $chapterId, MemberId $memberId, \DateTimeImmutable $now)
    {
        $this->chapterId = $chapterId->value();
        $this->memberId = $memberId->value();
        $this->likedAt = $now;
    }

    public function chapterId(): ChapterId
    {
        return ChapterId::fromString($this->chapterId);
    }

    public function memberId(): MemberId
    {
        return MemberId::fromString($this->memberId);
    }
}
