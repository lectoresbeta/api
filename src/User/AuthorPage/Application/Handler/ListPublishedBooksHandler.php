<?php

declare(strict_types=1);

namespace LectoresBeta\User\AuthorPage\Application\Handler;

use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\AuthorPage\Application\DTO\PublishedBookView;
use LectoresBeta\User\AuthorPage\Application\Query\ListPublishedBooks;
use LectoresBeta\User\AuthorPage\Domain\Repository\PublishedBookRepository;
use LectoresBeta\User\Profile\Application\Handler\GetProfileByUserIdHandler;
use LectoresBeta\User\Profile\Application\Query\GetProfileByUserId;

/**
 * La bibliografía de alguien (`FEAT-USR-029` `RN-2`).
 *
 * **Pública**, como el perfil del que forma parte: acreditar una trayectoria
 * es justamente enseñarla, y exigir sesión para leerla haría inútil compartir
 * el perfil.
 *
 * Pero se pide el perfil primero, y no por adorno: un perfil restringido o
 * eliminado responde `404`, y si esta lista contestara por su cuenta sería un
 * camino lateral para confirmar que una cuenta existe justo cuando su
 * titular ha pedido que no se sepa (`FEAT-USR-038`). La regla de visibilidad
 * se decide en un solo sitio y esto la consulta.
 */
final readonly class ListPublishedBooksHandler
{
    public function __construct(
        private GetProfileByUserIdHandler $profile,
        private PublishedBookRepository $books,
    ) {
    }

    /**
     * @return list<PublishedBookView>
     */
    public function __invoke(ListPublishedBooks $query): array
    {
        $profile = ($this->profile)(new GetProfileByUserId($query->userId, $query->viewerId));

        return array_map(
            PublishedBookView::of(...),
            $this->books->ofAuthor(UserId::fromString($profile->userId)),
        );
    }
}
