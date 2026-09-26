<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\BetaReaderGroup\Application\Service;

use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\AuthorId;
use LectoresBeta\Reading\BetaReaderGroup\Domain\Entity\BetaReaderGroup;
use LectoresBeta\Reading\BetaReaderGroup\Domain\Exception\GroupNotFound;
use LectoresBeta\Reading\BetaReaderGroup\Domain\Repository\BetaReaderGroupRepository;
use LectoresBeta\Reading\BetaReaderGroup\Domain\ValueObject\BetaReaderGroupId;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;

/**
 * «Mi grupo, o nada» (`FEAT-RDG-007` `RN-1`).
 *
 * Los seis casos de uso empiezan igual, y la comprobación vive en un sitio
 * porque repetirla seis veces es repetir seis veces la ocasión de olvidarla.
 *
 * Un identificador mal formado, uno inexistente y el de otra persona
 * responden **lo mismo**: un grupo es una anotación privada, y distinguir
 * entre «no existe» y «no es tuyo» ya diría quién tiene apuntado a quién.
 */
final readonly class OwnGroup
{
    public function __construct(private BetaReaderGroupRepository $groups)
    {
    }

    public function of(string $groupId, string $authorId): BetaReaderGroup
    {
        try {
            $group = $this->groups->ofId(BetaReaderGroupId::fromString($groupId));
        } catch (InvalidValue) {
            throw GroupNotFound::group();
        }

        if (null === $group || !$group->belongsTo(AuthorId::fromString($authorId))) {
            throw GroupNotFound::group();
        }

        return $group;
    }
}
