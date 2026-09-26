<?php

declare(strict_types=1);

namespace LectoresBeta\User\AuthorPage\Domain\Repository;

use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\AuthorPage\Domain\Entity\Award;
use LectoresBeta\User\AuthorPage\Domain\ValueObject\AwardId;

interface AwardRepository
{
    public function save(Award $award): void;

    public function remove(Award $award): void;

    public function ofId(AwardId $id): ?Award;

    /**
     * Los méritos de alguien, **ya ordenados** (`RN-8`): año descendente, y
     * los que no llevan año al final por orden de alta descendente.
     *
     * Devolverlos ordenados en vez de dejar que ordene quien llama es lo que
     * impide que el perfil público y la pantalla de edición acaben
     * enseñándolos de dos maneras distintas.
     *
     * @return list<Award>
     */
    public function ofAuthor(UserId $userId): array;

    public function countOfAuthor(UserId $userId): int;
}
