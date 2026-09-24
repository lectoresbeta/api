<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Catalogue\Application\Port;

use LectoresBeta\Work\Catalogue\Application\DTO\CatalogueCriteria;
use LectoresBeta\Work\Catalogue\Application\DTO\CataloguePage;

/**
 * La consulta del catálogo, como puerto.
 *
 * Filtrar, ordenar por una fórmula y paginar con el total es trabajo de la
 * base de datos: hacerlo en PHP significaría traerse el catálogo entero a
 * memoria en cada pantalla. La SQL vive en Infrastructure y la fórmula que
 * implementa está declarada en
 * `Work\Catalogue\Domain\Service\CatalogueRelevance`.
 */
interface CatalogueQuery
{
    public function page(CatalogueCriteria $criteria): CataloguePage;
}
