<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Domain\Service;

/**
 * Cuántas temáticas puede declarar una obra (`FEAT-WRK-001`, `FEAT-WRK-012`).
 *
 * **Ninguna es una respuesta válida.** La ficha dice que la temática es
 * opcional, y una obra sin clasificar tiene que poder existir: lo que le pasa
 * es que no aparece cuando alguien filtra, que es consecuencia suficiente.
 *
 * El tope, en cambio, no es cosmético. El catálogo filtra por temática con
 * multiselección y en `O`: una obra que declarase ocho temáticas aparecería
 * en casi cualquier búsqueda, y el filtro dejaría de filtrar. Tres es
 * bastante para decir qué es una obra y poco para convertirla en comodín.
 */
final class GenrePolicy
{
    public const MAX_GENRES = 3;
}
