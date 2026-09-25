<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Feedback\Correction\Domain\Entity\Correction;
use LectoresBeta\Feedback\Correction\Domain\Enum\CorrectionStatus;
use LectoresBeta\Feedback\Correction\Domain\Enum\CorrectionVisibility;
use LectoresBeta\Feedback\Correction\Domain\Repository\CorrectionRepository;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\AuthorId;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\ChapterId;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\CorrectionId;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\ReaderId;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\WorkId;
use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;

/**
 * @extends DoctrineRepository<Correction>
 */
final class DoctrineCorrectionRepository extends DoctrineRepository implements CorrectionRepository
{
    public function save(Correction $correction): void
    {
        $this->register($correction);
    }

    public function ofId(CorrectionId $id): ?Correction
    {
        return $this->repository()->find($id->value());
    }

    public function ofReaderAndChapter(ReaderId $readerId, ChapterId $chapterId): ?Correction
    {
        return $this->repository()->findOneBy([
            'readerId' => $readerId->value(),
            'chapterId' => $chapterId->value(),
        ]);
    }

    public function deliveredOnWork(WorkId $workId, int $limit = 50, int $offset = 0): array
    {
        return array_values($this->repository()->findBy(
            ['workId' => $workId->value(), 'status' => CorrectionStatus::SUBMITTED],
            ['submittedAt' => 'DESC'],
            $limit,
            $offset,
        ));
    }

    public function receivedBy(
        AuthorId $ownerId,
        ?WorkId $workId,
        ?ChapterId $chapterId,
        bool $unreadOnly,
        int $limit,
        int $offset,
    ): array {
        $query = $this->repository()->createQueryBuilder('c')
            ->where('c.ownerId = :owner')
            ->andWhere('c.status = :submitted')
            ->setParameter('owner', $ownerId->value())
            ->setParameter('submitted', CorrectionStatus::SUBMITTED)
            ->orderBy('c.submittedAt', 'DESC')
            ->addOrderBy('c.id', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        if (null !== $workId) {
            $query->andWhere('c.workId = :work')->setParameter('work', $workId->value());
        }

        if (null !== $chapterId) {
            $query->andWhere('c.chapterId = :chapter')->setParameter('chapter', $chapterId->value());
        }

        if ($unreadOnly) {
            $query->andWhere('c.readAt IS NULL');
        }

        return array_values($query->getQuery()->getResult());
    }

    public function deliveredBy(ReaderId $readerId, int $limit = 50, int $offset = 0): array
    {
        return array_values($this->repository()->findBy(
            ['readerId' => $readerId->value(), 'status' => CorrectionStatus::SUBMITTED],
            ['submittedAt' => 'DESC'],
            $limit,
            $offset,
        ));
    }

    public function deliveredCountBy(ReaderId $readerId): int
    {
        return $this->repository()->count([
            'readerId' => $readerId->value(),
            'status' => CorrectionStatus::SUBMITTED,
        ]);
    }

    public function lockedFor(AuthorId $ownerId): array
    {
        return array_values($this->repository()->findBy([
            'ownerId' => $ownerId->value(),
            'visibility' => CorrectionVisibility::LOCKED,
        ]));
    }

    public function discardDraft(Correction $correction): void
    {
        if (!$correction->isDraft()) {
            throw new \LogicException('A delivered correction is never removed: the author has paid for it.');
        }

        $this->forget($correction);
    }

    protected function entityClass(): string
    {
        return Correction::class;
    }
}
