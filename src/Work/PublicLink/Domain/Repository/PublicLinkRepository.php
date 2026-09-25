<?php

declare(strict_types=1);

namespace LectoresBeta\Work\PublicLink\Domain\Repository;

use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId;
use LectoresBeta\Work\PublicLink\Domain\Entity\PublicLink;
use LectoresBeta\Work\PublicLink\Domain\ValueObject\PublicLinkId;

interface PublicLinkRepository
{
    public function save(PublicLink $link): void;

    public function ofId(PublicLinkId $id): ?PublicLink;

    /**
     * **Se busca siempre por el cifrado, nunca por el token.** Es lo que
     * permite que la credencial no esté guardada en ninguna parte.
     */
    public function ofTokenHash(string $tokenHash): ?PublicLink;

    /**
     * @return list<PublicLink>
     */
    public function ofWork(WorkId $workId): array;
}
