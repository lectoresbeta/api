<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Questionnaire\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId;
use LectoresBeta\Work\Questionnaire\Domain\Entity\Question;
use LectoresBeta\Work\Questionnaire\Domain\Entity\Questionnaire;
use LectoresBeta\Work\Questionnaire\Domain\Repository\QuestionnaireRepository;
use LectoresBeta\Work\Questionnaire\Domain\ValueObject\QuestionnaireId;

/**
 * @extends DoctrineRepository<Questionnaire>
 */
final class DoctrineQuestionnaireRepository extends DoctrineRepository implements QuestionnaireRepository
{
    public function save(Questionnaire $questionnaire): void
    {
        $this->register($questionnaire);
    }

    public function addQuestion(Question $question): void
    {
        $this->entityManager->persist($question);
    }

    public function currentOf(WorkId $workId): ?Questionnaire
    {
        return $this->repository()->findOneBy(['workId' => $workId->value()], ['version' => 'DESC']);
    }

    public function ofVersion(WorkId $workId, int $version): ?Questionnaire
    {
        return $this->repository()->findOneBy(['workId' => $workId->value(), 'version' => $version]);
    }

    public function questionsOf(QuestionnaireId $questionnaireId): array
    {
        return array_values($this->entityManager
            ->getRepository(Question::class)
            ->findBy(['questionnaireId' => $questionnaireId->value()], ['position' => 'ASC']));
    }

    protected function entityClass(): string
    {
        return Questionnaire::class;
    }
}
