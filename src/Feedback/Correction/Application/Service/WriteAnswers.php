<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Application\Service;

use LectoresBeta\Feedback\Correction\Domain\Entity\Correction;
use LectoresBeta\Feedback\Correction\Domain\Entity\CorrectionAnswer;
use LectoresBeta\Feedback\Correction\Domain\Repository\CorrectionAnswerRepository;
use LectoresBeta\Feedback\Correction\Domain\Service\AnswerWordCounter;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\CorrectionAnswerId;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\CorrectionId;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\QuestionId;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\QuestionRequirement;
use LectoresBeta\Work\Chapter\Application\Contract\BriefQuestion;

/**
 * Pasar lo que el lector ha escrito a las respuestas de su corrección.
 *
 * Lo comparten guardar y enviar, y esa es la razón de que exista: son la
 * misma escritura con distinta validación delante, y duplicarla haría que el
 * día que una cambiase, un borrador y una entrega guardasen cosas distintas.
 *
 * Reescribe la respuesta que ya hubiera en vez de crear otra: una pregunta
 * tiene **una** respuesta por corrección, y el índice único lo exige.
 */
final readonly class WriteAnswers
{
    public function __construct(
        private CorrectionAnswerRepository $answers,
        private AnswerWordCounter $words,
    ) {
    }

    /**
     * Lo que cada pregunta exige, traducido al modelo de este contexto: la
     * forma de lo pedido, nunca el enunciado, que es del autor y solo pasa
     * por aquí de camino a la pantalla.
     *
     * @param list<BriefQuestion> $questions
     *
     * @return list<QuestionRequirement>
     */
    public function requirementsOf(array $questions): array
    {
        return array_map(
            static fn (BriefQuestion $question): QuestionRequirement => new QuestionRequirement(
                QuestionId::fromString($question->questionId),
                $question->position,
                $question->required,
                $question->minWords,
                $question->maxWords,
            ),
            $questions,
        );
    }

    /**
     * @param list<BriefQuestion>   $questions
     * @param array<string, string> $answers
     *
     * @return list<CorrectionAnswer>
     */
    public function onto(Correction $correction, array $questions, array $answers, \DateTimeImmutable $now): array
    {
        $existing = $this->existingOf($correction->id());
        $written = [];

        foreach ($questions as $question) {
            $text = trim($answers[$question->questionId] ?? '');

            if ('' === $text && !isset($existing[$question->questionId])) {
                // Una pregunta que nadie ha respondido no deja fila.
                continue;
            }

            $answer = $existing[$question->questionId] ?? new CorrectionAnswer(
                CorrectionAnswerId::generate(),
                $correction->id(),
                QuestionId::fromString($question->questionId),
                $question->position,
                $now,
            );
            $answer->write($text, $this->words->count($text), $now);

            $written[] = $answer;
        }

        return $written;
    }

    /**
     * @return array<string, CorrectionAnswer>
     */
    private function existingOf(CorrectionId $correctionId): array
    {
        $existing = [];

        foreach ($this->answers->ofCorrection($correctionId) as $answer) {
            $existing[$answer->questionId()->value()] = $answer;
        }

        return $existing;
    }
}
