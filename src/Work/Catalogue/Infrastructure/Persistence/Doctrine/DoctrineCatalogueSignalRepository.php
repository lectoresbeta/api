<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Catalogue\Infrastructure\Persistence\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use LectoresBeta\Work\Catalogue\Domain\Entity\ChapterSignal;
use LectoresBeta\Work\Catalogue\Domain\Entity\DeliveredCorrection;
use LectoresBeta\Work\Catalogue\Domain\Repository\CatalogueSignalRepository;
use LectoresBeta\Work\Chapter\Domain\ValueObject\ChapterId;

final readonly class DoctrineCatalogueSignalRepository implements CatalogueSignalRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function saveSignal(ChapterSignal $chapter): void
    {
        $this->entityManager->persist($chapter);
    }

    public function signalOf(ChapterId $chapterId): ?ChapterSignal
    {
        return $this->entityManager->find(ChapterSignal::class, $chapterId->value());
    }

    public function countDelivered(DeliveredCorrection $correction): bool
    {
        if (null !== $this->entityManager->find(DeliveredCorrection::class, $correction->correctionId())) {
            return false;
        }

        $this->entityManager->persist($correction);

        return true;
    }
}
