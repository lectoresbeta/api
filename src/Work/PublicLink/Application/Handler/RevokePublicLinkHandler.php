<?php

declare(strict_types=1);

namespace LectoresBeta\Work\PublicLink\Application\Handler;

use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\Work\Manuscript\Domain\Repository\WorkRepository;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\AuthorId;
use LectoresBeta\Work\PublicLink\Application\Command\RevokePublicLink;
use LectoresBeta\Work\PublicLink\Domain\Exception\PublicLinkRefused;
use LectoresBeta\Work\PublicLink\Domain\Repository\PublicLinkRepository;
use LectoresBeta\Work\PublicLink\Domain\ValueObject\PublicLinkId;

/**
 * Revocar un enlace (`FEAT-WRK-010` `RN-9`).
 *
 * **Inmediata e irreversible**, y silenciosamente idempotente: revocar dos
 * veces no es un error, porque el resultado que el autor pedía —que ese
 * enlace no sirva— ya se ha cumplido.
 */
final readonly class RevokePublicLinkHandler
{
    public function __construct(
        private PublicLinkRepository $links,
        private WorkRepository $works,
        private TransactionalSession $session,
        private Clock $clock,
    ) {
    }

    public function __invoke(RevokePublicLink $command): void
    {
        try {
            $link = $this->links->ofId(PublicLinkId::fromString($command->publicLinkId));
            $author = AuthorId::fromString($command->authorId);
        } catch (InvalidValue) {
            throw PublicLinkRefused::linkNotYours();
        }

        if (null === $link) {
            throw PublicLinkRefused::linkNotYours();
        }

        $work = $this->works->ofId($link->workId());

        if (null === $work || !$work->authorId()->equals($author)) {
            throw PublicLinkRefused::linkNotYours();
        }

        if ($link->isRevoked()) {
            return;
        }

        $link->revoke($this->clock->now());

        $this->session->execute(function () use ($link): void {
            $this->links->save($link);
        });
    }
}
