<?php

declare(strict_types=1);

namespace LectoresBeta\User\AuthorPage\Application\Command;

/**
 * Editar una obra publicada, campo a campo (`PATCH`).
 *
 * Cada campo viaja con un testigo de si se envió, porque «no lo mandes» y
 * «mándalo vacío» significan cosas distintas: dejarlo como está, o borrarlo.
 * Un `null` a secas no sabe decir cuál de las dos.
 */
final readonly class UpdatePublishedBook
{
    public function __construct(
        public string $userId,
        public string $publishedBookId,
        public bool $retitles,
        public ?string $title,
        public bool $changesPublisher,
        public ?string $publisher,
        public bool $changesYear,
        public ?int $publicationYear,
        public bool $changesPurchaseUrl,
        public ?string $purchaseUrl,
        public bool $moves,
        public ?int $position,
    ) {
    }
}
