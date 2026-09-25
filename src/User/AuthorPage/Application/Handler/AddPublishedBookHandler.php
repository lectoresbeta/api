<?php

declare(strict_types=1);

namespace LectoresBeta\User\AuthorPage\Application\Handler;

use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\AuthorPage\Application\Command\AddPublishedBook;
use LectoresBeta\User\AuthorPage\Application\DTO\PublishedBookView;
use LectoresBeta\User\AuthorPage\Domain\Entity\PublishedBook;
use LectoresBeta\User\AuthorPage\Domain\Exception\PublishedBookRefused;
use LectoresBeta\User\AuthorPage\Domain\Repository\PublishedBookRepository;
use LectoresBeta\User\AuthorPage\Domain\Service\Bibliography;
use LectoresBeta\User\AuthorPage\Domain\Service\PublishedBookPolicy;
use LectoresBeta\User\AuthorPage\Domain\ValueObject\PublishedBookId;
use LectoresBeta\User\AuthorPage\Domain\ValueObject\PurchaseLink;

/**
 * Añadir un libro a la bibliografía (`FEAT-USR-029`).
 *
 * **Solo el título es obligatorio** (`RN-6`). Quien cita una obra
 * descatalogada no tiene editorial que poner ni enlace al que mandar a nadie,
 * y exigírselos le dejaría fuera una obra que existió.
 *
 * No publica ningún evento, y eso no es un olvido: esto no mueve créditos, ni
 * cuenta como relato, ni interesa a ningún otro contexto (`RN-4`, `P-17`).
 * Un evento «por si acaso» es un contrato que luego hay que mantener.
 */
final readonly class AddPublishedBookHandler
{
    public function __construct(
        private PublishedBookRepository $books,
        private TransactionalSession $session,
        private Clock $clock,
    ) {
    }

    public function __invoke(AddPublishedBook $command): PublishedBookView
    {
        $userId = UserId::fromString($command->userId);
        $now = $this->clock->now();

        if ($this->books->countOfAuthor($userId) >= PublishedBookPolicy::MAX_PER_AUTHOR) {
            throw PublishedBookRefused::tooMany();
        }

        $book = PublishedBook::add(
            PublishedBookId::generate(),
            $userId,
            $command->title,
            // Provisional: el sitio de verdad lo decide `Bibliography` una
            // vez el libro sabe de qué año es.
            0,
            $now,
        );

        $book->setPublisher($command->publisher);
        $book->setPublicationYear($command->publicationYear, $now);
        $book->setPurchaseLink(PurchaseLink::fromString($command->purchaseUrl));

        Bibliography::place($this->books->ofAuthor($userId), $book);

        $this->session->execute(function () use ($book): void {
            $this->books->save($book);
        });

        return PublishedBookView::of($book);
    }
}
