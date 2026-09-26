<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Questionnaire\Domain\Event;

use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId;

/**
 * A new version of the questionnaire (`FEAT-WRK-014`).
 *
 * It carries **what `Credits` needs to calculate, and not the questions**.
 * Their text is the author's content and has no business circulating on a
 * queue that persists and retries.
 *
 * `requiredWords` is the number that matters: it is the **second term of the
 * price** (`decision:0006`, rule 1). That a single number is enough is what
 * keeps this contract simple — `Credits` never needs to know how many
 * questions there are or what kind they are, because the author already
 * declared how much work they want when they set the minimums.
 */
final readonly class QuestionnaireUpdated implements IntegrationEvent
{
    public function __construct(
        private EventId $eventId,
        private WorkId $workId,
        private int $version,
        private int $questionCount,
        private int $requiredWords,
        private int $requiredWordsForEveryChapter,
        private \DateTimeImmutable $updatedAt,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId->value();
    }

    public function eventName(): string
    {
        return 'QuestionnaireUpdated';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function payload(): array
    {
        return [
            'workId' => $this->workId->value(),
            'version' => $this->version,
            'questionCount' => $this->questionCount,
            // What the last chapter demands: every question applies there.
            'requiredWords' => $this->requiredWords,
            // What any other chapter demands. The difference is the whole
            // point of `scope`: an author does not pay, in chapter one, for a
            // question about the ending (`W-17`).
            'requiredWordsForEveryChapter' => $this->requiredWordsForEveryChapter,
            'updatedAt' => $this->updatedAt->format(\DATE_ATOM),
        ];
    }
}
