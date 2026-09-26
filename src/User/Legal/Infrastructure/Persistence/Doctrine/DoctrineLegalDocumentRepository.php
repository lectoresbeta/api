<?php

declare(strict_types=1);

namespace LectoresBeta\User\Legal\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;
use LectoresBeta\User\Legal\Domain\Entity\LegalDocument;
use LectoresBeta\User\Legal\Domain\Enum\LegalDocumentType;
use LectoresBeta\User\Legal\Domain\Repository\LegalDocumentRepository;

/**
 * @extends DoctrineRepository<LegalDocument>
 */
final class DoctrineLegalDocumentRepository extends DoctrineRepository implements LegalDocumentRepository
{
    public function save(LegalDocument $document): void
    {
        $this->register($document);
    }

    public function inForce(LegalDocumentType $type, \DateTimeImmutable $now): ?LegalDocument
    {
        /** @var ?LegalDocument $found */
        $found = $this->repository()->createQueryBuilder('d')
            ->where('d.type = :type')
            ->andWhere('d.effectiveFrom <= :now')
            ->setParameter('type', $type)
            ->setParameter('now', $now)
            ->orderBy('d.effectiveFrom', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $found;
    }

    public function allInForce(\DateTimeImmutable $now): array
    {
        $documents = [];

        foreach (LegalDocumentType::cases() as $type) {
            $document = $this->inForce($type, $now);

            if (null !== $document) {
                $documents[] = $document;
            }
        }

        return $documents;
    }

    protected function entityClass(): string
    {
        return LegalDocument::class;
    }
}
