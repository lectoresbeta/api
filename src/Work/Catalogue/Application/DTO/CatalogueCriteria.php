<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Catalogue\Application\DTO;

/**
 * Lo que el catálogo filtra y ordena, ya validado.
 *
 * `readerId` no es un filtro más: excluye las obras propias (`RN-7`), porque
 * nadie corrige lo suyo y ocuparían sitio en la única pantalla donde se busca
 * trabajo ajeno.
 */
final readonly class CatalogueCriteria
{
    /**
     * @param list<string> $genres           en `O` y no en `Y` (`L-7`): filtrar
     *                                       por «Ficción» y «YoungAdult»
     *                                       devuelve las obras que son
     *                                       **cualquiera** de las dos
     * @param list<string> $excludedWarnings al revés que las temáticas, estas
     *                                       **quitan** obras: el lector dice
     *                                       lo que no quiere ver
     *                                       (`FEAT-WRK-017` `RN-10`)
     */
    public function __construct(
        public string $readerId,
        public bool $readerIsOfAge,
        public array $genres,
        public array $excludedWarnings,
        public ?string $status,
        public bool $byRelevance,
        public int $page,
        public int $perPage,
        public \DateTimeImmutable $now,
    ) {
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }
}
