<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Recommendation\Infrastructure\Controller;

use LectoresBeta\Community\Recommendation\Application\Handler\ListRecommendedWorksHandler;
use LectoresBeta\Community\Recommendation\Application\Query\ListRecommendedWorks;
use LectoresBeta\Work\Catalogue\Application\Contract\RecommendedWork;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `GET /api/v1/home/recommended-works` (`FEAT-COM-017`).
 *
 * Lo primero que ve alguien al entrar, y la vía principal para que empiece a
 * corregir — que es como se gana crédito y como entra en el ciclo del
 * producto.
 *
 * **Funciona con la cuenta sin activar**: es solo lectura, y la restricción
 * de `FEAT-USR-025` es sobre escribir. Alguien que acaba de registrarse tiene
 * que poder ver qué hay antes de confirmar su correo.
 *
 * La sinopsis se recorta aquí y no en la consulta: cuánto cabe en una tarjeta
 * es cosa de la pantalla.
 */
#[AsController]
final readonly class ListRecommendedWorksController
{
    /**
     * Lo que cabe en una tarjeta del carrusel sin que la maqueta recorte.
     */
    private const SYNOPSIS_PREVIEW = 280;

    public function __construct(
        private ListRecommendedWorksHandler $recommended,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $carousel = ($this->recommended)(new ListRecommendedWorks(
            $user->getUserIdentifier(),
            $request->query->has('howMany') ? $request->query->getInt('howMany') : null,
        ));

        return new JsonResponse([
            'shouldDisplay' => $carousel->shouldDisplay,
            'reason' => $carousel->reason,
            'works' => array_map(
                static fn (RecommendedWork $work): array => [
                    'workId' => $work->workId,
                    'title' => $work->title,
                    'synopsis' => self::preview($work->synopsis),
                    'status' => $work->status,
                    'chapterCount' => $work->chapterCount,
                    'readingMinutes' => $work->readingMinutes,
                    'adultsOnly' => $work->adultsOnly,
                    'contentWarnings' => $work->contentWarnings,
                    'genres' => $work->genres,
                    'credits' => $work->credits,
                    'correctionsReceived' => $work->correctionsReceived,
                ],
                $carousel->works,
            ),
        ]);
    }

    private static function preview(?string $synopsis): ?string
    {
        if (null === $synopsis) {
            return null;
        }

        return mb_strlen($synopsis) <= self::SYNOPSIS_PREVIEW
            ? $synopsis
            : rtrim(mb_substr($synopsis, 0, self::SYNOPSIS_PREVIEW)).'…';
    }
}
