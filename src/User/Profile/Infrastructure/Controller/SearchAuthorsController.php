<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Infrastructure\Controller;

use LectoresBeta\Shared\Application\Storage\MediaUrl;
use LectoresBeta\User\Profile\Application\DTO\AuthorCard;
use LectoresBeta\User\Profile\Application\Handler\SearchAuthorsHandler;
use LectoresBeta\User\Profile\Application\Query\SearchAuthors;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `GET /api/v1/authors` (`FEAT-USR-017`).
 *
 * **Exige sesión, y abrir un perfil suelto no.** No es incoherente: abrir el
 * perfil de alguien que te ha pasado su enlace es una cosa, y poder recorrer
 * el padrón de la plataforma es otra. Un buscador de personas abierto es una
 * superficie de extracción de datos, y quien la use no deja rastro de quién
 * es.
 *
 * Sin criterios no devuelve nada, y tampoco es un error: pedir «todo el
 * mundo» no es una búsqueda.
 */
#[AsController]
final readonly class SearchAuthorsController
{
    public function __construct(
        private SearchAuthorsHandler $search,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $authors = ($this->search)(new SearchAuthors(
            $user->getUserIdentifier(),
            $request->query->getString('q') ?: null,
            self::genresOf($request),
            $request->query->getInt('limit', 20),
        ));

        return new JsonResponse([
            'authors' => array_map(
                static fn (AuthorCard $card): array => [
                    'userId' => $card->userId,
                    'username' => $card->username,
                    'name' => $card->name,
                    'avatarUrl' => MediaUrl::of($card->avatarUrl),
                ],
                $authors,
            ),
        ]);
    }

    /**
     * `genre` se puede repetir. Se admite también separado por comas, que es
     * lo que escribe a mano quien prueba la API, y no cuesta nada aceptarlo.
     *
     * @return list<string>
     */
    private static function genresOf(Request $request): array
    {
        // `all('genre')` exige que el parámetro sea un array y revienta con
        // `?genre=HORROR`, que es justo la forma más natural de escribirlo.
        // Se lee el saco entero y se normaliza aquí.
        $raw = $request->query->all()['genre'] ?? [];
        $codes = [];

        foreach (\is_array($raw) ? $raw : [$raw] as $value) {
            if (!\is_string($value)) {
                continue;
            }

            foreach (explode(',', $value) as $code) {
                $code = strtoupper(trim($code));

                if ('' !== $code) {
                    $codes[] = $code;
                }
            }
        }

        return array_values(array_unique($codes));
    }
}
