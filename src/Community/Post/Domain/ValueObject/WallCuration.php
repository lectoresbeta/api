<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Post\Domain\ValueObject;

/**
 * Lo que **quien mira** ha decidido sobre su propio muro (`FEAT-COM-021`,
 * `FEAT-COM-022`).
 *
 * Va junto y con nombre en lugar de como dos parámetros más porque `wallFor`
 * ya tiene siete, y porque las dos cosas son la misma idea: preferencias
 * privadas de un espectador que la consulta tiene que respetar.
 *
 * **Van en la consulta, no después.** Filtrar en memoria rompería la
 * paginación exactamente igual que la rompería filtrar ahí la audiencia: una
 * página de veinte devolvería diecisiete sin que nadie sepa por qué.
 *
 * Lo que **no** está aquí son los silenciados (`FEAT-COM-033`): esos viajan
 * con los bloqueados, porque el efecto sobre la consulta es el mismo —quitar
 * a una persona del muro— y el caso de uso es el que decide cuándo se
 * aplican. Distinguirlos aquí habría duplicado una cláusula para no
 * distinguir nada.
 */
final readonly class WallCuration
{
    /**
     * @param list<string>  $hiddenPostIds las que este espectador escondió una a una
     * @param ?list<string> $onlyPostIds   restringe el muro a estas, que es como se
     *                                     sirve la lista de guardados: el muro con un
     *                                     filtro, y no una consulta aparte que tendría
     *                                     su propia copia de las reglas de audiencia
     */
    public function __construct(
        public array $hiddenPostIds = [],
        public ?array $onlyPostIds = null,
    ) {
    }

    /**
     * Cuando nadie ha escondido ni guardado nada, que es el caso normal.
     */
    public static function none(): self
    {
        return new self();
    }

    /**
     * Si el filtro deja fuera todo. Un `IN ()` vacío no es una consulta que
     * se pueda escribir, y la respuesta correcta es la lista vacía.
     */
    public function excludesEverything(): bool
    {
        return null !== $this->onlyPostIds && [] === $this->onlyPostIds;
    }
}
