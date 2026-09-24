<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Catalogue\Application\Handler;

use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\User\Account\Application\Contract\ReaderMaturity;
use LectoresBeta\Work\Catalogue\Application\DTO\CatalogueCriteria;
use LectoresBeta\Work\Catalogue\Application\DTO\CataloguePage;
use LectoresBeta\Work\Catalogue\Application\Port\CatalogueQuery;
use LectoresBeta\Work\Catalogue\Application\Query\ListCatalogue;
use LectoresBeta\Work\Catalogue\Domain\Exception\UnknownCatalogueFilter;
use LectoresBeta\Work\Manuscript\Domain\Enum\WorkStatus;

/**
 * La sección «Leer» (`FEAT-WRK-012`).
 *
 * Es la puerta de entrada al ciclo del producto —descubrir, leer, corregir—,
 * y ordena por **relevancia**, que aquí significa una cosa concreta y poco
 * habitual: reparto de trabajo, nunca popularidad
 * ([`decision:0008`](../../../../../docs/decisions/0008-catalogue-ordering.md)).
 *
 * Los filtros se validan en vez de ignorarse. Un valor desconocido devuelve
 * los resultados de otra consulta si se ignora, y el usuario no tiene cómo
 * notarlo; `status=DRAFT` además tiene que rechazarse **aunque el desplegable
 * no lo ofrezca**, porque un desplegable no es una autorización.
 */
final readonly class ListCatalogueHandler
{
    public const DEFAULT_PER_PAGE = 20;
    public const MAX_PER_PAGE = 50;

    private const SORTS = ['relevance', 'recent'];

    public function __construct(
        private CatalogueQuery $catalogue,
        private ReaderMaturity $maturity,
        private Clock $clock,
    ) {
    }

    public function __invoke(ListCatalogue $query): CataloguePage
    {
        if ($query->page < 1) {
            throw UnknownCatalogueFilter::page();
        }

        if (!\in_array($query->sort, self::SORTS, true)) {
            throw UnknownCatalogueFilter::sort();
        }

        return $this->catalogue->page(new CatalogueCriteria(
            $query->readerId,
            $this->maturity->isOfAge($query->readerId),
            self::status($query->status),
            'relevance' === $query->sort,
            $query->page,
            min(max($query->perPage, 1), self::MAX_PER_PAGE),
            $this->clock->now(),
        ));
    }

    /**
     * `DRAFT` no es un valor de este filtro y no se trata como uno
     * equivocado: se rechaza igual que un valor inventado, para no confirmar
     * de paso que es un estado que existe.
     */
    private static function status(?string $status): ?string
    {
        if (null === $status) {
            return null;
        }

        $parsed = WorkStatus::tryFrom($status);

        if (null === $parsed || WorkStatus::DRAFT === $parsed) {
            throw UnknownCatalogueFilter::status();
        }

        return $parsed->value;
    }
}
