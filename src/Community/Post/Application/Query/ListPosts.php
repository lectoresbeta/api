<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Post\Application\Query;

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
    ) {
    }
}
