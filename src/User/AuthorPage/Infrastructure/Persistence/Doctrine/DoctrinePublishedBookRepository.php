<?php

declare(strict_types=1);

namespace LectoresBeta\User\AuthorPage\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\AuthorPage\Domain\Entity\PublishedBook;
use LectoresBeta\User\AuthorPage\Domain\Repository\PublishedBookRepository;
use LectoresBeta\User\AuthorPage\Domain\ValueObject\PublishedBookId;

/**
 * @extends DoctrineRepository<PublishedBook>
 */
final class DoctrinePublishedBookRepository extends DoctrineRepository implements PublishedBookRepository
{
    public function save(PublishedBook $book): void
    {
        $this->register($book);
    }

    public function remove(PublishedBook $book): void
    {
        $this->forget($book);
    }

    public function ofId(PublishedBookId $id): ?PublishedBook
    {
        return $this->repository()->find($id->value());
    }

    public function ofAuthor(UserId $userId): array
    {
        /** @var list<PublishedBook> $books */
        $books = $this->repository()->findBy(
            ['userId' => $userId->value()],
            // El desempate por fecha no es decorativo: sin él, dos obras que
            // comparten posición salen en el orden que quiera la base de
            // datos, y la lista cambia sola entre dos peticiones iguales.
            ['position' => 'ASC', 'createdAt' => 'ASC'],
        );

        return $books;
    }

    public function countOfAuthor(UserId $userId): int
    {
        return $this->repository()->count(['userId' => $userId->value()]);
    }

    protected function entityClass(): string
    {
        return PublishedBook::class;
    }
}
