<?php

declare(strict_types=1);

namespace LectoresBeta\User\AuthorPage\Application\Service;

use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\AuthorPage\Domain\Entity\PublishedBook;
use LectoresBeta\User\AuthorPage\Domain\Exception\PublishedBookRefused;
use LectoresBeta\User\AuthorPage\Domain\Repository\PublishedBookRepository;
use LectoresBeta\User\AuthorPage\Domain\ValueObject\PublishedBookId;

/**
 * La obra publicada de quien pregunta, o ninguna (`RN-1`).
 *
 * Está en un solo sitio porque los cuatro casos de uso que editan una obra
 * necesitan exactamente lo mismo, y cuatro copias de una comprobación de
 * pertenencia son cuatro oportunidades de que una se quede corta.
 */
final readonly class MyPublishedBook
{
    public function __construct(private PublishedBookRepository $books)
    {
    }

    public function of(string $userId, string $publishedBookId): PublishedBook
    {
        try {
            $book = $this->books->ofId(PublishedBookId::fromString($publishedBookId));
        } catch (InvalidValue) {
            throw PublishedBookRefused::notFound();
        }

        if (null === $book) {
            throw PublishedBookRefused::notFound();
        }

        if (!$book->belongsTo(UserId::fromString($userId))) {
            throw PublishedBookRefused::notYours();
        }

        return $book;
    }
}
