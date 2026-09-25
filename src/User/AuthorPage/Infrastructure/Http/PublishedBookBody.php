<?php

declare(strict_types=1);

namespace LectoresBeta\User\AuthorPage\Infrastructure\Http;

use LectoresBeta\User\AuthorPage\Application\DTO\PublishedBookView;

/**
 * La forma que tiene una obra publicada en la API.
 *
 * Está en un solo sitio porque seis endpoints la devuelven, y seis arrays
 * escritos a mano son seis oportunidades de que uno se deje un campo que el
 * cliente ya pinta.
 */
final class PublishedBookBody
{
    /**
     * @return array<string, mixed>
     */
    public static function of(PublishedBookView $book): array
    {
        return [
            'publishedBookId' => $book->publishedBookId,
            'title' => $book->title,
            'publisher' => $book->publisher,
            'publicationYear' => $book->publicationYear,
            'purchaseUrl' => $book->purchaseUrl,
            'purchaseUrlIsExternal' => $book->purchaseUrlIsExternal,
            'coverUrl' => $book->coverUrl,
            'position' => $book->position,
        ];
    }

    /**
     * @param list<PublishedBookView> $books
     *
     * @return array<string, mixed>
     */
    public static function listOf(array $books): array
    {
        return ['publishedBooks' => array_map(self::of(...), $books)];
    }
}
