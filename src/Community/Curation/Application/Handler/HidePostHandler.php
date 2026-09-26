<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Curation\Application\Handler;

use LectoresBeta\Community\Curation\Application\Command\HidePost;
use LectoresBeta\Community\Curation\Domain\Entity\HiddenPost;
use LectoresBeta\Community\Curation\Domain\Repository\HiddenPostRepository;
use LectoresBeta\Community\Post\Application\Service\VisiblePost;
use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

/**
 * «Esto no me interesa» (`FEAT-COM-022`).
 *
 * Se pide poder verla, con el mismo `VisiblePost` de siempre: esconder lo que
 * no te alcanzaba no significa nada, y aceptarlo dejaría que alguien
 * comprobara qué publicaciones existen probando identificadores.
 *
 * **No borra nada y no avisa a nadie.** La publicación sigue existiendo para
 * todo el mundo; lo que desaparece es la tarjeta, y solo de este muro.
 */
final readonly class HidePostHandler
{
    public function __construct(
        private VisiblePost $visible,
        private HiddenPostRepository $hidden,
        private TransactionalSession $session,
        private Clock $clock,
    ) {
    }

    public function __invoke(HidePost $command): void
    {
        $post = $this->visible->to($command->postId, $command->memberId);
        $member = MemberId::fromString($command->memberId);

        if (\in_array($post->id()->value(), $this->hidden->hiddenBy($member), true)) {
            return;
        }

        $entry = new HiddenPost($member, $post->id(), $this->clock->now());

        $this->session->execute(function () use ($entry): void {
            $this->hidden->save($entry);
        });
    }
}
