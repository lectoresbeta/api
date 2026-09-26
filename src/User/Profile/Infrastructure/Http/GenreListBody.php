<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Infrastructure\Http;

use LectoresBeta\User\Profile\Application\DTO\GenreView;

/**
 * La forma de una lista de géneros en HTTP, **la misma en los tres sitios**
 * que la devuelven: el catálogo, mis preferencias y la respuesta a
 * guardarlas.
 *
 * Que sea la misma no es estética: el cliente pinta chips con lo que recibe,
 * y una lista con otra forma según de dónde venga es cómo aparece una
 * pantalla en la que unos chips tienen nombre y otros no.
 */
final readonly class GenreListBody
{
    /**
     * @param list<GenreView> $genres
     *
     * @return array<string, mixed>
     */
    public static function of(array $genres): array
    {
        return [
            'genres' => array_map(
                static fn (GenreView $genre): array => ['code' => $genre->code, 'name' => $genre->name],
                $genres,
            ),
        ];
    }
}
