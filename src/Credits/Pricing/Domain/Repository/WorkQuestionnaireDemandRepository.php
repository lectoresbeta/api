<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Pricing\Domain\Repository;

use LectoresBeta\Credits\Pricing\Domain\Entity\WorkQuestionnaireDemand;
use LectoresBeta\Credits\Pricing\Domain\ValueObject\WorkId;

interface WorkQuestionnaireDemandRepository
{
    public function save(WorkQuestionnaireDemand $demand): void;

    public function ofWork(WorkId $workId): ?WorkQuestionnaireDemand;
}
