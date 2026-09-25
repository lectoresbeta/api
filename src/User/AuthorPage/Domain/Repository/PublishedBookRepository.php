<?php

declare(strict_types=1);

namespace LectoresBeta\User\AuthorPage\Domain\Repository;

use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\AuthorPage\Domain\Entity\PublishedBook;
use LectoresBeta\User\AuthorPage\Domain\ValueObject\PublishedBookId;

interface PublishedBookRepository
{
    public function save(PublishedBook $book): void;

    public function remove(PublishedBook $book): void;

    public function ofId(PublishedBookId $id): ?PublishedBook;

    /**
     * La bibliografía de alguien, **en el orden en que la enseña** (`RN-7`).
     *
     * Devolverla ya ordenada y no dejar que ordene quien llama es lo que
     * impide que el perfil público y la pantalla de edición acaben
     * enseñándola de dos maneras distintas.
     *
     * @return list<PublishedBook>
     */
    public function ofAuthor(UserId $userId): array;

    public function countOfAuthor(UserId $userId): int;
}
