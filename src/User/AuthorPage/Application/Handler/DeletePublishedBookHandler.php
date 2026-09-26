<?php

declare(strict_types=1);

namespace LectoresBeta\User\AuthorPage\Application\Handler;

use LectoresBeta\Shared\Application\Storage\FileStorage;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\User\AuthorPage\Application\Command\DeletePublishedBook;
use LectoresBeta\User\AuthorPage\Application\Service\MyPublishedBook;
use LectoresBeta\User\AuthorPage\Domain\Entity\PublishedBook;
use LectoresBeta\User\AuthorPage\Domain\Repository\PublishedBookRepository;

/**
 * Quitar una obra de la bibliografía (`FEAT-USR-029`).
 *
 * Se lleva su portada por delante. Un fichero que ya no tiene fila que lo
 * nombre no lo va a borrar nadie nunca, y la alternativa es un almacén que
 * solo crece.
 */
final readonly class DeletePublishedBookHandler
{
    public function __construct(
        private MyPublishedBook $mine,
        private PublishedBookRepository $books,
        private FileStorage $storage,
        private TransactionalSession $session,
    ) {
    }

    public function __invoke(DeletePublishedBook $command): void
    {
        $book = $this->mine->of($command->userId, $command->publishedBookId);
        $cover = $book->coverUrl();

        $remaining = array_values(array_filter(
            $this->books->ofAuthor($book->userId()),
            static fn (PublishedBook $other): bool => $other->id()->value() !== $book->id()->value(),
        ));

        $this->session->execute(function () use ($book, $remaining): void {
            $this->books->remove($book);

            // Las que quedan se recolocan sin hueco: una lista con un número
            // saltado funciona hasta que alguien añade una obra y dos acaban
            // compartiendo posición.
            foreach ($remaining as $index => $other) {
                $other->moveTo($index);
                $this->books->save($other);
            }
        });

        // Después de que el borrado esté firme: antes, una transacción que no
        // llegara a cerrarse dejaría la ficha sin portada y sin forma de
        // recuperarla.
        if (null !== $cover) {
            $this->storage->delete($cover);
        }
    }
}
