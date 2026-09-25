<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Infrastructure\Controller;

use LectoresBeta\Feedback\Correction\Application\Handler\GetCorrectedChapterTextHandler;
use LectoresBeta\Feedback\Correction\Application\Query\GetCorrectedChapterText;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `GET /api/v1/corrections/{correctionId}/chapter-text` (`FEAT-FBK-004`
 * `RN-8`).
 *
 * El capítulo tal y como lo leyó quien corrigió. Aparte del detalle porque
 * son decenas de miles de palabras que casi nunca hacen falta.
 *
 * `isCurrentVersion` a `true` significa **«esto es lo de ahora, y puede no
 * ser lo que se leyó»**. Mientras el capítulo no se versione
 * (`FEAT-WRK-005`), siempre es así, y decirlo es mejor que no responder.
 */
#[AsController]
final readonly class GetCorrectedChapterTextController
{
    public function __construct(
        private GetCorrectedChapterTextHandler $text,
        private Security $security,
    ) {
    }

    public function __invoke(string $correctionId): Response
    {
        $reader = $this->security->getUser();

        if (null === $reader) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $text = ($this->text)(new GetCorrectedChapterText($correctionId, $reader->getUserIdentifier()));

        return new JsonResponse([
            'correctionId' => $text->correctionId,
            'chapterId' => $text->chapterId,
            'title' => $text->title,
            'contentHtml' => $text->contentHtml,
            'wordCount' => $text->wordCount,
            'version' => $text->version,
            'isCurrentVersion' => $text->isCurrentVersion,
        ]);
    }
}
