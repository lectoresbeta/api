<?php

declare(strict_types=1);

namespace LectoresBeta\Work\PublicLink\Application\Service;

use LectoresBeta\Work\PublicLink\Application\DTO\PublicLinkView;
use LectoresBeta\Work\PublicLink\Domain\Entity\PublicLink;

/**
 * Un enlace contado al autor, sin su token (`FEAT-WRK-010` `RN-2`).
 *
 * En un servicio y no en el DTO porque `usable` depende del reloj, y un DTO
 * que mirase la hora daría una respuesta distinta cada vez que se le
 * preguntase.
 */
final readonly class DescribePublicLink
{
    public function one(PublicLink $link, \DateTimeImmutable $now): PublicLinkView
    {
        return new PublicLinkView(
            $link->id()->value(),
            $link->label(),
            $link->maxCorrections(),
            $link->createdAt()->format(\DATE_ATOM),
            $link->expiresAt()?->format(\DATE_ATOM),
            $link->revokedAt()?->format(\DATE_ATOM),
            $link->isUsableAt($now),
        );
    }
}
