<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Application\Service;

use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Work\Manuscript\Application\Contract\AuthoredWorkCount;
use LectoresBeta\Work\Manuscript\Domain\Repository\WorkRepository;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\AuthorId;

final readonly class CountAuthoredWorks implements AuthoredWorkCount
{
    public function __construct(private WorkRepository $works)
    {
    }

    public function ofAuthor(string $authorId): int
    {
        try {
            return $this->works->countByAuthor(AuthorId::fromString($authorId));
        } catch (InvalidValue) {
            return 0;
        }
    }
}
