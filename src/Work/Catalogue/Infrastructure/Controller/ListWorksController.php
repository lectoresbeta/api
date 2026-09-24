<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Catalogue\Infrastructure\Controller;

use LectoresBeta\Work\Catalogue\Application\DTO\CatalogueEntry;
use LectoresBeta\Work\Catalogue\Application\Handler\ListCatalogueHandler;
use LectoresBeta\Work\Catalogue\Application\Query\ListCatalogue;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `GET /api/v1/works` — la sección «Leer» (`FEAT-WRK-012`).
 *
 * Devuelve **metadatos, nunca contenido**. Quién puede leer el texto de una
 * obra es otra decisión y la resuelve `FEAT-WRK-004`: que una obra aparezca
 * aquí no significa que quien la ve pueda abrirla.
 *
 * La sinopsis se trunca en el borde HTTP y no en la consulta: cuánto cabe en
 * una tarjeta es cosa de la pantalla, no del catálogo.
 */
#[AsController]
final readonly class ListWorksController
{
    /**
     * Lo que cabe en una tarjeta sin que la maqueta tenga que recortar.
     */
    private const SYNOPSIS_PREVIEW = 280;

    public function __construct(
        private ListCatalogueHandler $catalogue,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $page = ($this->catalogue)(new ListCatalogue(
            $user->getUserIdentifier(),
            self::optional($request, 'status'),
            self::optional($request, 'sort') ?? 'relevance',
            $request->query->getInt('page', 1),
            $request->query->getInt('perPage', ListCatalogueHandler::DEFAULT_PER_PAGE),
        ));

        return new JsonResponse([
            'total' => $page->total,
            'totalPages' => $page->totalPages(),
            'page' => $page->page,
            'perPage' => $page->perPage,
            'works' => array_map(
                static fn (CatalogueEntry $entry): array => [
                    'workId' => $entry->workId,
                    'title' => $entry->title,
                    'synopsis' => self::preview($entry->synopsis),
                    'status' => $entry->status,
                    'wordCount' => $entry->wordCount,
                    'chapterCount' => $entry->chapterCount,
                    'adultsOnly' => $entry->adultsOnly,
                    'correctableChapters' => $entry->correctableChapters,
                    'correctionsReceived' => $entry->correctionsReceived,
                ],
                $page->entries,
            ),
        ]);
    }

    private static function optional(Request $request, string $parameter): ?string
    {
        $value = $request->query->get($parameter);

        return \is_string($value) && '' !== $value ? $value : null;
    }

    private static function preview(?string $synopsis): ?string
    {
        if (null === $synopsis || mb_strlen($synopsis) <= self::SYNOPSIS_PREVIEW) {
            return $synopsis;
        }

        return mb_substr($synopsis, 0, self::SYNOPSIS_PREVIEW).'…';
    }
}
