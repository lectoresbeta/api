<?php

declare(strict_types=1);

namespace LectoresBeta\User\AuthorPage\Application\Service;

use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\AuthorPage\Domain\Entity\Award;
use LectoresBeta\User\AuthorPage\Domain\Exception\AwardRefused;
use LectoresBeta\User\AuthorPage\Domain\Repository\AwardRepository;
use LectoresBeta\User\AuthorPage\Domain\ValueObject\AwardId;

/**
 * El premio de quien pregunta, o ninguno (`RN-1`).
 *
 * El hermano de `MyPublishedBook`, y está por lo mismo: dos casos de uso
 * necesitan exactamente esta comprobación, y dos copias son dos
 * oportunidades de que una se quede corta.
 */
final readonly class MyAward
{
    public function __construct(private AwardRepository $awards)
    {
    }

    public function of(string $userId, string $awardId): Award
    {
        try {
            $award = $this->awards->ofId(AwardId::fromString($awardId));
        } catch (InvalidValue) {
            throw AwardRefused::notFound();
        }

        if (null === $award) {
            throw AwardRefused::notFound();
        }

        if (!$award->belongsTo(UserId::fromString($userId))) {
            throw AwardRefused::notYours();
        }

        return $award;
    }
}
