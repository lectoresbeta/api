<?php

declare(strict_types=1);

namespace LectoresBeta\User\AuthorPage\Domain\Repository;

use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\AuthorPage\Domain\Entity\AuthorPageStyle;

interface AuthorPageStyleRepository
{
    public function ofUser(UserId $userId): ?AuthorPageStyle;

    public function save(AuthorPageStyle $style): void;
}
