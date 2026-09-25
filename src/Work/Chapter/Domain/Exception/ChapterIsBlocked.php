<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Chapter\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * El capítulo está bloqueado por una reclamación estimada
 * ([`FEAT-MOD-003`](../../../../../docs/features/moderation/FEAT-MOD-003-block-work.md)).
 *
 * Se distingue del `404` a propósito: es del autor, él sabe que existe y
 * sabe por qué está bloqueado — se lo dijimos por correo—. Lo que no puede es
 * editarlo: el contenido reclamado se conserva tal cual, porque es justo lo
 * que hace falta si alguien discute la decisión.
 */
final class ChapterIsBlocked extends \DomainException implements BusinessFailure
{
    public static function create(): self
    {
        return new self('That chapter is blocked by moderation and cannot be edited.');
    }

    public function errorCode(): string
    {
        return 'CHAPTER_BLOCKED';
    }

    public function kind(): FailureKind
    {
        return FailureKind::CONFLICT;
    }
}
