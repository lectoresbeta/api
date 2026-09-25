<?php

declare(strict_types=1);

namespace LectoresBeta\User\AuthorPage\Application\Handler;

use LectoresBeta\Shared\Application\Storage\FileStorage;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\User\AuthorPage\Application\Command\DeletePublishedBookCover;
use LectoresBeta\User\AuthorPage\Application\DTO\PublishedBookView;
use LectoresBeta\User\AuthorPage\Application\Service\MyPublishedBook;
use LectoresBeta\User\AuthorPage\Domain\Repository\PublishedBookRepository;

/**
 * Quitar la portada y dejar la obra (`RN-10`).
 *
 * Sin portada la ficha se sigue enseñando: el marcador por defecto lo pone la
 * interfaz (`P-20`), y aquí lo que viaja es `null`, que es lo único que
 * permite distinguir «no hay portada» de «esta es la portada».
 */
final readonly class DeletePublishedBookCoverHandler
{
    public function __construct(
        private MyPublishedBook $mine,
        private PublishedBookRepository $books,
        private FileStorage $storage,
        private TransactionalSession $session,
    ) {
    }

    public function __invoke(DeletePublishedBookCover $command): PublishedBookView
    {
        $book = $this->mine->of($command->userId, $command->publishedBookId);
        $cover = $book->coverUrl();

        $this->session->execute(function () use ($book): void {
            $book->setCover(null);
            $this->books->save($book);
        });

        if (null !== $cover) {
            $this->storage->delete($cover);
        }

        return PublishedBookView::of($book);
    }
}
