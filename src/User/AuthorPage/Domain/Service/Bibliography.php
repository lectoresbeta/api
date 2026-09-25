<?php

declare(strict_types=1);

namespace LectoresBeta\User\AuthorPage\Domain\Service;

use LectoresBeta\User\AuthorPage\Domain\Entity\PublishedBook;

/**
 * El orden de la bibliografía (`FEAT-USR-029` `RN-7`, `P-15`).
 *
 * **Lo decide el autor, y hasta que decide algo manda el año descendente.**
 * Las dos mitades de esa frase se sostienen a la vez porque esta clase solo
 * coloca **la obra nueva**: busca dónde la pondría el año y la mete ahí, sin
 * tocar el orden de las que ya estaban. Reordenar la lista entera por año
 * cada vez que se añade un libro desharía en silencio lo que el autor
 * acababa de arrastrar.
 *
 * Una obra sin año va al final. No es un descarte: es que «no sé de cuándo
 * es» ordena peor que cualquier fecha, y ponerla arriba encabezaría la
 * bibliografía con lo que menos dice.
 *
 * Es un servicio de dominio porque la regla no es de ninguna de las obras:
 * habla de la relación entre ellas.
 */
final class Bibliography
{
    /**
     * @param list<PublishedBook> $ordered la bibliografía actual, en su orden
     */
    public static function place(array $ordered, PublishedBook $book): void
    {
        $placed = [];
        $inserted = false;

        foreach ($ordered as $existing) {
            if (!$inserted && self::goesBefore($book, $existing)) {
                $placed[] = $book;
                $inserted = true;
            }

            $placed[] = $existing;
        }

        if (!$inserted) {
            $placed[] = $book;
        }

        self::renumber($placed);
    }

    /**
     * Mover una obra a un hueco concreto: lo que hace el autor al arrastrar.
     *
     * La posición se recorta a lo que existe en vez de rechazarse. Pedir el
     * hueco 40 de una lista de tres no es un error de nadie —es lo que manda
     * una interfaz que se ha quedado desfasada— y la intención («al final»)
     * se entiende perfectamente.
     *
     * @param list<PublishedBook> $ordered
     */
    public static function moveTo(array $ordered, PublishedBook $book, int $position): void
    {
        $rest = array_values(array_filter(
            $ordered,
            static fn (PublishedBook $existing): bool => $existing->id()->value() !== $book->id()->value(),
        ));

        $position = max(0, min($position, \count($rest)));

        array_splice($rest, $position, 0, [$book]);

        self::renumber($rest);
    }

    private static function goesBefore(PublishedBook $book, PublishedBook $existing): bool
    {
        $year = $book->publicationYear();

        if (null === $year) {
            return false;
        }

        return null === $existing->publicationYear() || $year > $existing->publicationYear();
    }

    /**
     * Posiciones consecutivas desde cero, siempre. Dejar huecos funciona
     * hasta que dos obras acaban compartiendo número y la lista empieza a
     * cambiar sola entre dos lecturas iguales.
     *
     * @param list<PublishedBook> $ordered
     */
    private static function renumber(array $ordered): void
    {
        foreach ($ordered as $index => $book) {
            $book->moveTo($index);
        }
    }
}
