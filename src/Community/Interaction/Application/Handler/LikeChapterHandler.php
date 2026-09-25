<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Application\Handler;

use LectoresBeta\Community\Interaction\Application\Command\LikeChapter;
use LectoresBeta\Community\Interaction\Application\Service\ChapterCounters;
use LectoresBeta\Community\Interaction\Application\Service\ReadableChapter;
use LectoresBeta\Community\Interaction\Domain\Entity\ChapterLike;
use LectoresBeta\Community\Interaction\Domain\Repository\ChapterLikeRepository;
use LectoresBeta\Community\Interaction\Domain\ValueObject\ChapterId;
use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

/**
 * Apoyar un capítulo, y retirar el apoyo (`FEAT-COM-036`).
 *
 * **No se apoya lo que no se puede leer**, y esa es la regla que hay que
 * vigilar: sin ella, probar identificadores contra este endpoint diría qué
 * capítulos existen en obras que su autor no ha enseñado a nadie.
 *
 * Idempotente en las dos direcciones. No es elegancia: el botón se pulsa dos
 * veces sin querer y el cliente reintenta cuando la red falla.
 */
final readonly class LikeChapterHandler
{
    public function __construct(
        private ReadableChapter $reachable,
        private ChapterLikeRepository $likes,
        private ChapterCounters $counters,
        private TransactionalSession $session,
        private Clock $clock,
    ) {
    }

    public function like(LikeChapter $command): int
    {
        $access = $this->reachable->writtenOnBy($command->chapterId, $command->memberId);
        $chapterId = ChapterId::fromString($access->chapterId);
        $member = MemberId::fromString($command->memberId);

        return $this->session->execute(function () use ($chapterId, $member): int {
            $engagement = $this->counters->of($chapterId);

            if (null === $this->likes->between($chapterId, $member)) {
                $this->likes->save(new ChapterLike($chapterId, $member, $this->clock->now()));
                $engagement->liked();
                $this->counters->save($engagement);
            }

            return $engagement->likeCount();
        });
    }

    public function unlike(LikeChapter $command): int
    {
        $access = $this->reachable->seenBy($command->chapterId, $command->memberId);
        $chapterId = ChapterId::fromString($access->chapterId);
        $member = MemberId::fromString($command->memberId);

        return $this->session->execute(function () use ($chapterId, $member): int {
            $engagement = $this->counters->of($chapterId);
            $like = $this->likes->between($chapterId, $member);

            if (null !== $like) {
                $this->likes->remove($like);
                $engagement->unliked();
                $this->counters->save($engagement);
            }

            return $engagement->likeCount();
        });
    }
}
