<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Feedback\Correction\Domain\Entity\CorrectionReply;
use LectoresBeta\Feedback\Correction\Domain\Repository\CorrectionReplyRepository;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\CorrectionId;
use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;

/**
 * @extends DoctrineRepository<CorrectionReply>
 */
final class DoctrineCorrectionReplyRepository extends DoctrineRepository implements CorrectionReplyRepository
{
    public function save(CorrectionReply $reply): void
    {
        $this->register($reply);
    }

    public function ofCorrection(CorrectionId $correctionId): ?CorrectionReply
    {
        return $this->repository()->findOneBy(['correctionId' => $correctionId->value()]);
    }

    public function ofCorrections(array $correctionIds): array
    {
        if ([] === $correctionIds) {
            return [];
        }

        $replies = [];

        foreach ($this->repository()->findBy([
            'correctionId' => array_map(static fn (CorrectionId $id): string => $id->value(), $correctionIds),
        ]) as $reply) {
            $replies[$reply->correctionId()->value()] = $reply;
        }

        return $replies;
    }

    public function remove(CorrectionReply $reply): void
    {
        $this->forget($reply);
    }

    protected function entityClass(): string
    {
        return CorrectionReply::class;
    }
}
