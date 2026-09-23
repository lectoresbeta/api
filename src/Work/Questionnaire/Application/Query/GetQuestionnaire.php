<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Questionnaire\Application\Query;

final readonly class GetQuestionnaire
{
    public function __construct(
        public string $workId,
        public string $readerId,
    ) {
    }
}
