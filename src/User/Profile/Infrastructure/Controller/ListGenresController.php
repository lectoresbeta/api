<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Infrastructure\Controller;

use LectoresBeta\User\Profile\Application\DTO\GenreView;
use LectoresBeta\User\Profile\Application\Handler\ListGenresHandler;
use LectoresBeta\User\Profile\Application\Query\ListGenres;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

/**
 * `GET /api/v1/genres` (`FEAT-USR-023`).
 *
 * Público: el catálogo no es información sensible y hace falta también en las
 * pantallas de búsqueda, que se pueden usar sin sesión.
 */
#[AsController]
final readonly class ListGenresController
{
    public function __construct(private ListGenresHandler $genres)
    {
    }

    public function __invoke(): Response
    {
        return new JsonResponse([
            'genres' => array_map(
                static fn (GenreView $genre): array => ['code' => $genre->code, 'name' => $genre->name],
                ($this->genres)(new ListGenres()),
            ),
        ]);
    }
}
