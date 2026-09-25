<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Infrastructure\Controller;

use LectoresBeta\Feedback\Correction\Application\DTO\MyCorrection;
use LectoresBeta\Feedback\Correction\Application\Handler\ListMyCorrectionsHandler;
use LectoresBeta\Feedback\Correction\Application\Query\ListMyCorrections;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `GET /api/v1/me/corrections` (`FEAT-FBK-010`).
 *
 * **Para que corregir no sea escribir en un buzón.** Con los borradores
 * propios incluidos y distinguidos: un borrador es trabajo empezado, y quien
 * lo dejó a medias necesita encontrarlo.
 *
 * Solo las propias. No hay forma de listar las de otra persona: el contador
 * de correcciones es público y la lista no.
 */
#[AsController]
final readonly class ListMyCorrectionsController
{
    public function __construct(
        private ListMyCorrectionsHandler $mine,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $reader = $this->security->getUser();

        if (null === $reader) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $corrections = ($this->mine)(new ListMyCorrections(
            $reader->getUserIdentifier(),
            $request->query->get('workId'),
            $request->query->get('status'),
            $request->query->getInt('limit', 50),
            $request->query->getInt('offset'),
        ));

        return new JsonResponse([
            'corrections' => array_map(
                static fn (MyCorrection $correction): array => [
                    'correctionId' => $correction->correctionId,
                    'workId' => $correction->workId,
                    'workTitle' => $correction->workTitle,
                    'chapterId' => $correction->chapterId,
                    'chapterTitle' => $correction->chapterTitle,
                    'chapterPosition' => $correction->chapterPosition,
                    'status' => $correction->status,
                    'submittedAt' => $correction->submittedAt,
                    'earnedCredits' => $correction->earnedCredits,
                    'helpful' => $correction->helpful,
                    'replied' => $correction->replied,
                    'tipped' => $correction->tipped,
                ],
                $corrections,
            ),
        ]);
    }
}
