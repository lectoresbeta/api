<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Post\Application\Query;

use LectoresBeta\Community\Post\Domain\ValueObject\PostFilters;

/**
 * El muro de quien mira (`FEAT-COM-001`), o el de una persona concreta
 * (`FEAT-COM-026`).
 *
 * **Una consulta y no dos.** El muro de un perfil es el muro general con un
 * filtro más: las reglas de audiencia, de bloqueo y de visibilidad de perfil
 * son exactamente las mismas, y separarlos sería tener las tres escritas dos
 * veces para que una de las copias se quede atrás.
 */
final readonly class ListPosts
{
    public function __construct(
        public string $readerId,
        public ?string $cursor,
        public ?int $limit,
        /**
         * De quién es el muro, o `null` para el general. Puede ser quien
         * mira: ese es «Mi muro».
         */
        public ?string $authorId = null,
        /**
         * Por qué se filtra (`FEAT-COM-009`), o `null` para el muro entero.
         */
        public ?PostFilters $filters = null,
        /**
         * La lista de guardados (`FEAT-COM-021`), que es este mismo muro
         * restringido a lo que uno guardó. Una bandera y no una consulta
         * aparte, por lo mismo que el muro de un perfil: las reglas de
         * audiencia son las mismas, y escribirlas dos veces es dejar que una
         * de las dos se quede atrás.
         */
        public bool $savedOnly = false,
    ) {
    }
}
