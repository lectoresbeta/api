<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Catalogue\Application\DTO;

/**
 * Una página numerada, con el total.
 *
 * Se aparta de la convención de paginación por cursor a conciencia
 * (`FEAT-WRK-012`, `L-4`): un catálogo filtrado no es un flujo cronológico
 * que crece por arriba. El usuario quiere saber cuántos resultados hay
 * —«948 historias»— y saltar a la página 4, y ninguna de las dos cosas la da
 * un cursor.
 *
 * El coste es real y conviene tenerlo anotado: contar el total con filtros
 * combinados es la consulta cara de esta pantalla, y es lo primero que se
 * degradará con volumen.
 */
final readonly class CataloguePage
{
    /**
     * @param list<CatalogueEntry> $entries
     */
    public function __construct(
        public array $entries,
        public int $total,
        public int $page,
        public int $perPage,
    ) {
    }

    public function totalPages(): int
    {
        return (int) ceil($this->total / $this->perPage);
    }
}
