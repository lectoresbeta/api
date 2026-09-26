<?php

declare(strict_types=1);

namespace LectoresBeta\Work\PublicLink\Infrastructure\Controller;

use LectoresBeta\Work\Chapter\Application\Contract\BriefQuestion;
use LectoresBeta\Work\Chapter\Domain\Service\ReadingTime;
use LectoresBeta\Work\PublicLink\Application\Handler\OpenPublicLinkHandler;
use LectoresBeta\Work\PublicLink\Application\Query\OpenPublicLink;
use LectoresBeta\Work\PublicLink\Infrastructure\Http\NoIndex;
use LectoresBeta\Work\PublicLink\Infrastructure\Security\PublicLinkThrottle;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

/**
 * `GET /api/v1/public/{token}/chapters/{chapterId}` (`FEAT-WRK-010`).
 *
 * El texto y lo que su autor pregunta sobre él. Aparte de la portada porque
 * una novela entera en una respuesta son megabytes que casi nadie lee de una
 * sentada, y porque así se lee: capítulo a capítulo.
 */
#[AsController]
final readonly class GetPublicChapterController
{
    public function __construct(
        private OpenPublicLinkHandler $open,
        private PublicLinkThrottle $throttle,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request, string $token, string $chapterId): Response
    {
        $this->throttle->check($request->getClientIp() ?? '');

        $chapter = $this->open->chapter(new OpenPublicLink(
            $token,
            $this->security->getUser()?->getUserIdentifier(),
            $chapterId,
        ));

        return NoIndex::on(new JsonResponse([
            'chapterId' => $chapter->chapterId,
            'workId' => $chapter->workId,
            'workTitle' => $chapter->workTitle,
            'position' => $chapter->position,
            'title' => $chapter->title,
            'contentHtml' => $chapter->contentHtml,
            'wordCount' => $chapter->wordCount,
            'readingMinutes' => ReadingTime::minutesFor($chapter->wordCount),
            'chapterVersion' => $chapter->chapterVersion,
            'questionnaireVersion' => $chapter->questionnaireVersion,
            'wouldBeWorth' => $chapter->wouldBeWorth,
            'questions' => array_map(
                static fn (BriefQuestion $question): array => [
                    'questionId' => $question->questionId,
                    'position' => $question->position,
                    'statement' => $question->statement,
                    'example' => $question->example,
                    'required' => $question->required,
                    'minWords' => $question->minWords,
                    'maxWords' => $question->maxWords,
                ],
                $chapter->questions,
            ),
        ]));
    }
}
