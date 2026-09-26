<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Review\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Moderation\Claim\Domain\ValueObject\ClaimId;
use LectoresBeta\Moderation\Review\Domain\Entity\ClaimMessage;
use LectoresBeta\Moderation\Review\Domain\Enum\MessageAuthorType;
use LectoresBeta\Moderation\Review\Domain\Enum\ThreadParty;
use LectoresBeta\Moderation\Review\Domain\Repository\ClaimMessageRepository;
use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;

/**
 * @extends DoctrineRepository<ClaimMessage>
 */
final class DoctrineClaimMessageRepository extends DoctrineRepository implements ClaimMessageRepository
{
    public function add(ClaimMessage $message): void
    {
        $this->register($message);
    }

    public function thread(ClaimId $claimId, ThreadParty $party): array
    {
        /** @var list<ClaimMessage> $found */
        $found = $this->repository()->createQueryBuilder('m')
            ->where('m.claimId = :claim')
            ->andWhere('m.threadParty = :party')
            ->setParameter('claim', $claimId->value())
            ->setParameter('party', $party)
            ->orderBy('m.sentAt', 'ASC')
            ->addOrderBy('m.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $found;
    }

    public function isOpen(ClaimId $claimId, ThreadParty $party): bool
    {
        return $this->repository()->count([
            'claimId' => $claimId->value(),
            'threadParty' => $party,
            'authorType' => MessageAuthorType::MODERATOR,
        ]) > 0;
    }

    protected function entityClass(): string
    {
        return ClaimMessage::class;
    }
}
