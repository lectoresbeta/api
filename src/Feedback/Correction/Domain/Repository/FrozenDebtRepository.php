<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Domain\Repository;

use LectoresBeta\Feedback\Correction\Domain\Entity\FrozenDebt;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\AuthorId;

interface FrozenDebtRepository
{
    public function save(FrozenDebt $frozen): void;

    public function ofAuthor(AuthorId $authorId): ?FrozenDebt;
}
