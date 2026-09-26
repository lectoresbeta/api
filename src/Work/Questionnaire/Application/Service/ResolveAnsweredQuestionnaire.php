<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Questionnaire\Application\Service;

use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId;
use LectoresBeta\Work\Questionnaire\Application\Contract\AnsweredQuestion;
use LectoresBeta\Work\Questionnaire\Application\Contract\AnsweredQuestionnaires;
use LectoresBeta\Work\Questionnaire\Domain\Entity\Question;
use LectoresBeta\Work\Questionnaire\Domain\Repository\QuestionnaireRepository;

/**
 * La implementación del contrato, y todo lo que hace es leer.
 *
 * **No comprueba quién pregunta**: eso lo decide quien tiene el dato para
 * decidirlo. Aquí no se sabe quién corrigió ese capítulo, y un contrato que
 * intentara autorizar tendría que preguntárselo a otro contexto mientras
 * responde, que es justo lo que `decision:0014` prohíbe.
 */
final readonly class ResolveAnsweredQuestionnaire implements AnsweredQuestionnaires
{
    public function __construct(private QuestionnaireRepository $questionnaires)
    {
    }

    public function questionsOf(string $workId, int $version): array
    {
        try {
            $questionnaire = $this->questionnaires->ofVersion(WorkId::fromString($workId), $version);
        } catch (InvalidValue) {
            return [];
        }

        if (null === $questionnaire) {
            return [];
        }

        return array_map(
            static fn (Question $question): AnsweredQuestion => new AnsweredQuestion(
                $question->id()->value(),
                $question->position(),
                $question->statement(),
            ),
            $this->questionnaires->questionsOf($questionnaire->id()),
        );
    }
}
