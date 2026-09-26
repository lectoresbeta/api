<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Curation\Application\Handler;

use LectoresBeta\Community\Curation\Application\Command\SavePost;
use LectoresBeta\Community\Curation\Domain\Entity\SavedPost;
use LectoresBeta\Community\Curation\Domain\Repository\SavedPostRepository;
use LectoresBeta\Community\Post\Application\Service\VisiblePost;
use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

/**
 * Apartar una publicación para volver a ella (`FEAT-COM-021`).
 *
 * **Solo se guarda lo que se puede ver** (`RN-2`), y eso lo decide
 * `VisiblePost`, que es el mismo sitio donde lo deciden comentar, repostear y
 * abrir la imagen. Una quinta copia de la regla de audiencia sería la que un
 * día se quedase corta.
 *
 * Es idempotente y no publica nada: guardar es una nota privada, y el autor
 * de la publicación no se entera.
 */
final readonly class SavePostHandler
{
    public function __construct(
        private VisiblePost $visible,
        private SavedPostRepository $saved,
        private TransactionalSession $session,
        private Clock $clock,
    ) {
    }

    public function __invoke(SavePost $command): void
    {
        $post = $this->visible->to($command->postId, $command->memberId);
        $member = MemberId::fromString($command->memberId);

        if ($this->saved->has($member, $post->id())) {
            return;
        }

        $entry = new SavedPost($member, $post->id(), $this->clock->now());

        $this->session->execute(function () use ($entry): void {
            $this->saved->save($entry);
        });
    }
}
