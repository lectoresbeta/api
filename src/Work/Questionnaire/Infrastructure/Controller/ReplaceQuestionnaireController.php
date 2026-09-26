<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Questionnaire\Infrastructure\Controller;

use LectoresBeta\Work\Questionnaire\Application\Command\ReplaceQuestionnaire;
use LectoresBeta\Work\Questionnaire\Application\DTO\QuestionInput;
use LectoresBeta\Work\Questionnaire\Application\Handler\ReplaceQuestionnaireHandler;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `PUT /api/v1/works/{workId}/questionnaire` (`FEAT-WRK-014`).
 *
 * `If-Match` lleva la versión que el autor tenía en pantalla. Sin él, dos
 * pestañas editando el mismo cuestionario se pisan **en silencio**, y como
 * esta configuración fija el precio de cada corrección, perder la mitad sin
 * enterarse no es aceptable.
 *
 * Es opcional: un cliente que no lo envíe sigue funcionando, y asume el
 * riesgo.
 */
#[AsController]
final readonly class ReplaceQuestionnaireController
{
    public function __construct(
        private ReplaceQuestionnaireHandler $replace,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request, string $workId): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $version = ($this->replace)(new ReplaceQuestionnaire(
            $workId,
            $user->getUserIdentifier(),
            self::questionsOf($request),
            self::expectedVersionOf($request),
        ));

        return new JsonResponse(['version' => $version]);
    }

    /**
     * @return list<QuestionInput>
     */
    private static function questionsOf(Request $request): array
    {
        $raw = json_decode((string) $request->getContent(), true);

        if (!\is_array($raw) || !\is_array($raw['questions'] ?? null)) {
            throw new BadRequestHttpException('The body must carry a list of questions.');
        }

        $questions = [];

        foreach ($raw['questions'] as $item) {
            if (!\is_array($item)) {
                throw new BadRequestHttpException('Every question must be an object.');
            }

            $questions[] = new QuestionInput(
                \is_string($item['statement'] ?? null) ? $item['statement'] : '',
                \is_string($item['example'] ?? null) ? $item['example'] : null,
                !isset($item['required']) || true === $item['required'],
                \is_int($item['minWords'] ?? null) ? $item['minWords'] : null,
                \is_int($item['maxWords'] ?? null) ? $item['maxWords'] : null,
                \is_string($item['scope'] ?? null) ? $item['scope'] : 'EVERY_CHAPTER',
            );
        }

        return $questions;
    }

    private static function expectedVersionOf(Request $request): ?int
    {
        $ifMatch = trim($request->headers->get('If-Match', ''), '"');

        return '' === $ifMatch || !ctype_digit($ifMatch) ? null : (int) $ifMatch;
    }
}
