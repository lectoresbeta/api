<?php

declare(strict_types=1);

namespace LectoresBeta\User\AuthorPage\Application\Handler;

use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\User\AuthorPage\Application\Command\UpdatePublishedBook;
use LectoresBeta\User\AuthorPage\Application\DTO\PublishedBookView;
use LectoresBeta\User\AuthorPage\Application\Service\MyPublishedBook;
use LectoresBeta\User\AuthorPage\Domain\Repository\PublishedBookRepository;
use LectoresBeta\User\AuthorPage\Domain\Service\Bibliography;
use LectoresBeta\User\AuthorPage\Domain\ValueObject\PurchaseLink;

/**
 * Editar una obra publicada (`FEAT-USR-029`).
 *
 * `PATCH`: lo que no se manda se queda como estaba, y mandar `null` borra.
 * La distinción la hace la presencia de la clave, no su valor, porque un
 * autor que quita el enlace de compra de una obra descatalogada está
 * pidiendo algo distinto de uno que solo cambia el año.
 *
 * La portada **no viaja aquí**: tiene su propio endpoint, por la misma razón
 * que la foto de perfil. Mezcladas, el año se quedaría sin guardar porque
 * falló una subida.
 */
final readonly class UpdatePublishedBookHandler
{
    public function __construct(
        private MyPublishedBook $mine,
        private PublishedBookRepository $books,
        private TransactionalSession $session,
        private Clock $clock,
    ) {
    }

    public function __invoke(UpdatePublishedBook $command): PublishedBookView
    {
        $book = $this->mine->of($command->userId, $command->publishedBookId);
        $now = $this->clock->now();

        if ($command->retitles) {
            $book->retitle($command->title ?? '');
        }

        if ($command->changesPublisher) {
            $book->setPublisher($command->publisher);
        }

        if ($command->changesYear) {
            $book->setPublicationYear($command->publicationYear, $now);
        }

        if ($command->changesPurchaseUrl) {
            $book->setPurchaseLink(PurchaseLink::fromString($command->purchaseUrl));
        }

        if ($command->moves && null !== $command->position) {
            // El orden lo decide el autor (`RN-7`), y a partir de aquí manda
            // lo que arrastró: cambiar el año de una obra ya colocada no la
            // mueve de sitio.
            Bibliography::moveTo($this->books->ofAuthor($book->userId()), $book, $command->position);
        }

        $this->session->execute(function () use ($book): void {
            $this->books->save($book);
        });

        return PublishedBookView::of($book);
    }
}
