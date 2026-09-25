<?php

declare(strict_types=1);

namespace LectoresBeta\Work\PublicLink\Infrastructure\Controller;

use LectoresBeta\Work\PublicLink\Application\DTO\PublicChapterSummary;
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
 * `GET /api/v1/public/{token}` (`FEAT-WRK-010`).
 *
 * La obra y sus capítulos visibles, **sin sesión**. Es la única lectura de
 * una obra que no la exige, y por eso es la que más cuidado lleva: `noindex`,
 * `no-store` y límite por origen.
 *
 * Que la sesión se **lea** aunque no se exija es lo que hace posible
 * `FEAT-FBK-008` `RN-1`: a quien ya tiene cuenta se le manda al flujo normal,
 * donde su corrección se paga. Por eso estas rutas no viven en un cortafuegos
 * sin seguridad, sino en `access_control` con `PUBLIC_ACCESS`.
 */
#[AsController]
final readonly class GetPublicCorrectionPageController
{
    public function __construct(
        private OpenPublicLinkHandler $open,
        private PublicLinkThrottle $throttle,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request, string $token): Response
    {
        $this->throttle->check($request->getClientIp() ?? '');

        $page = $this->open->page(new OpenPublicLink(
            $token,
            $this->security->getUser()?->getUserIdentifier(),
        ));

        return NoIndex::on(new JsonResponse([
            'workId' => $page->workId,
            'title' => $page->title,
            'synopsis' => $page->synopsis,
            'adultsOnly' => $page->adultsOnly,
            'chapters' => array_map(
                static fn (PublicChapterSummary $chapter): array => [
                    'chapterId' => $chapter->chapterId,
                    'position' => $chapter->position,
                    'title' => $chapter->title,
                    'wordCount' => $chapter->wordCount,
                ],
                $page->chapters,
            ),
        ]));
    }
}
