<?php

declare(strict_types=1);

namespace LectoresBeta\User\AuthorPage\Application\Command;

/**
 * Las referencias de la página de autor, **enteras** (`FEAT-USR-015`).
 *
 * No hay «añadir una» ni «quitar aquella»: lo que el formulario manda es la
 * lista como su dueño la ve en pantalla, igual que con las temáticas de una
 * obra. Reconciliar diferencias daría el mismo resultado con más código y una
 * forma más de equivocarse.
 */
final readonly class UpdateAuthorLinks
{
    /**
     * @param list<array{label: ?string, url: ?string}> $links en el orden en que se enseñan
     */
    public function __construct(
        public string $userId,
        public array $links,
    ) {
    }
}
