<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Infrastructure\Controller;

use LectoresBeta\Feedback\Correction\Application\DTO\ReceivedCorrection;
use LectoresBeta\Feedback\Correction\Application\Handler\ListCorrectionsReceivedHandler;
use LectoresBeta\Feedback\Correction\Application\Query\ListCorrectionsReceived;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `GET /api/v1/me/corrections/received` (`FEAT-FBK-004`).
 *
 * La bandeja del autor, de lo más reciente a lo más antiguo. **Sin el
 * contenido de ninguna corrección**: quien abre esta pantalla está
 * decidiendo cuál leer.
 */
#[AsController]
final readonly class ListCorrectionsReceivedController
{
    public function __construct(
        private ListCorrectionsReceivedHandler $received,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $author = $this->security->getUser();

        if (null === $author) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $corrections = ($this->received)(new ListCorrectionsReceived(
            $author->getUserIdentifier(),
            $request->query->get('workId'),
            $request->query->get('chapterId'),
            $request->query->getBoolean('unread'),
            $request->query->getInt('limit', 50),
            $request->query->getInt('offset'),
        ));

        return new JsonResponse([
            'corrections' => array_map(
                static fn (ReceivedCorrection $correction): array => [
                    'correctionId' => $correction->correctionId,
                    'workId' => $correction->workId,
                    'chapterId' => $correction->chapterId,
                    'chapterTitle' => $correction->chapterTitle,
                    'chapterPosition' => $correction->chapterPosition,
                    'readerId' => $correction->readerId,
                    'authorLabel' => $correction->authorLabel,
                    'visibility' => $correction->visibility,
                    'questionnaireVersion' => $correction->questionnaireVersion,
                    'submittedAt' => $correction->submittedAt,
                    'read' => $correction->read,
                    'helpful' => $correction->helpful,
                ],
                $corrections,
            ),
        ]);
    }
}
