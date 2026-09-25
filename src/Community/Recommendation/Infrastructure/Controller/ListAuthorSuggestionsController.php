<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Recommendation\Infrastructure\Controller;

use LectoresBeta\Community\Recommendation\Application\DTO\AuthorSuggestion;
use LectoresBeta\Community\Recommendation\Application\Handler\ListAuthorSuggestionsHandler;
use LectoresBeta\Community\Recommendation\Application\Query\ListAuthorSuggestions;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `GET /api/v1/onboarding/author-suggestions` (`FEAT-COM-016`).
 *
 * Responde `200` siempre, incluso con la lista vacía: **la ausencia de
 * autores es un estado normal** de una plataforma recién lanzada, no un
 * fallo. Lo que dice si el paso se enseña es `shouldDisplay`, y lo decide el
 * servidor.
 */
#[AsController]
final readonly class ListAuthorSuggestionsController
{
    public function __construct(
        private ListAuthorSuggestionsHandler $suggestions,
        private Security $security,
    ) {
    }

    public function __invoke(): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $answer = ($this->suggestions)(new ListAuthorSuggestions($user->getUserIdentifier()));

        return new JsonResponse([
            'shouldDisplay' => $answer->shouldDisplay,
            'reason' => $answer->reason,
            'suggestions' => array_map(
                static fn (AuthorSuggestion $one): array => [
                    'userId' => $one->userId,
                    'displayName' => $one->displayName,
                    'avatarUrl' => $one->avatarUrl,
                    'followerCount' => $one->followerCount,
                    'publicationCount' => $one->publicationCount,
                    'matchedGenres' => $one->matchedGenres,
                    'following' => $one->following,
                ],
                $answer->suggestions,
            ),
        ]);
    }
}
