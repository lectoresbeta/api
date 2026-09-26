<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Questionnaire\Domain\Entity;

use LectoresBeta\Work\Questionnaire\Domain\Enum\QuestionScope;
use LectoresBeta\Work\Questionnaire\Domain\ValueObject\QuestionId;
use LectoresBeta\Work\Questionnaire\Domain\ValueObject\QuestionnaireId;

/**
 * One question the author asks their beta readers (`FEAT-WRK-014`).
 *
 * `minWords` is not a formatting detail: the sum of the minimums across a
 * questionnaire is the writing term of the price (`decision:0006`). Setting
 * it is how the author declares how much work they are asking for — and what
 * they are willing to pay for it.
 *
 * A question with no minimum counts as 25 words when pricing, which `Credits`
 * decides. This context only records the absence.
 */
class Question
{
    private string $id;

    private string $questionnaireId;

    private int $position;

    private string $statement;

    private ?string $example = null;

    private bool $required = true;

    private ?int $minWords = null;

    private ?int $maxWords = null;

    private QuestionScope $scope;

    public function __construct(
        QuestionId $id,
        QuestionnaireId $questionnaireId,
        int $position,
        string $statement,
        QuestionScope $scope = QuestionScope::EVERY_CHAPTER,
    ) {
        $this->id = $id->value();
        $this->questionnaireId = $questionnaireId->value();
        $this->position = $position;
        $this->statement = trim($statement);
        $this->scope = $scope;
    }

    public function id(): QuestionId
    {
        return QuestionId::fromString($this->id);
    }

    public function questionnaireId(): QuestionnaireId
    {
        return QuestionnaireId::fromString($this->questionnaireId);
    }

    public function position(): int
    {
        return $this->position;
    }

    public function statement(): string
    {
        return $this->statement;
    }

    public function example(): ?string
    {
        return $this->example;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function minWords(): ?int
    {
        return $this->minWords;
    }

    public function maxWords(): ?int
    {
        return $this->maxWords;
    }

    public function scope(): QuestionScope
    {
        return $this->scope;
    }

    public function configure(
        ?string $example,
        bool $required,
        ?int $minWords,
        ?int $maxWords,
    ): void {
        $this->example = $example;
        $this->required = $required;
        $this->minWords = null !== $minWords && $minWords > 0 ? $minWords : null;
        $this->maxWords = null !== $maxWords && $maxWords > 0 ? $maxWords : null;
    }
}
