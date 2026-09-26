<?php

declare(strict_types=1);

namespace LectoresBeta\User\AuthorPage\Application\DTO;

use LectoresBeta\Shared\Application\Storage\MediaUrl;
use LectoresBeta\User\AuthorPage\Domain\Entity\PublishedBook;

/**
 * Una obra publicada tal y como sale de la API.
 *
 * `purchaseUrlIsExternal` es constante a propósito y no sobra: dice que ese
 * enlace **sale de la plataforma** (`RN-5`), que es lo que necesita saber
 * quien lo pinta para abrirlo aparte y sin arrastrar la sesión
 * (`rel="noopener noreferrer"`). La alternativa —que cada cliente compare el
 * dominio por su cuenta— es la que acaba olvidándose en una pantalla.
 */
final readonly class PublishedBookView
{
    public function __construct(
        public string $publishedBookId,
        public string $title,
        public ?string $publisher,
        public ?int $publicationYear,
        public ?string $purchaseUrl,
        public bool $purchaseUrlIsExternal,
        public ?string $coverUrl,
        public int $position,
    ) {
    }

    public static function of(PublishedBook $book): self
    {
        return new self(
            $book->id()->value(),
            $book->title(),
            $book->publisher(),
            $book->publicationYear(),
            $book->purchaseUrl(),
            null !== $book->purchaseUrl(),
            // Sin portada viaja `null`, y el marcador por defecto lo pone la
            // interfaz (`P-20`). Devolver aquí una imagen de relleno le
            // quitaría al cliente la única forma de distinguir «no hay
            // portada» de «esta es la portada».
            MediaUrl::of($book->coverUrl()),
            $book->position(),
        );
    }
}
