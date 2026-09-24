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
use LectoresBeta\Work\Manuscript\Domain\Enum\ContentWarning;
use LectoresBeta\Work\Manuscript\Domain\Enum\WorkStatus;

/**
 * La sección «Leer» (`FEAT-WRK-012`).
 *
 * Es la puerta de entrada al ciclo del producto —descubrir, leer, corregir—,
 * y ordena por **relevancia**, que aquí significa una cosa concreta y poco
 * habitual: reparto de trabajo, nunca popularidad
 * ([`decision:0008`](../../../../../docs/decisions/0008-catalogue-ordering.md)).
 *
 * El filtro de temática es **multiselección y en `O`** (`RN-5`, `L-7`):
 * pedir «Ficción» y «YoungAdult» devuelve las obras que son cualquiera de las
 * dos, no las que son ambas. Es lo que se espera de un catálogo de
 * descubrimiento — con `Y`, añadir una temática al filtro reduce los
 * resultados hasta dejar la pantalla vacía, que es lo contrario de descubrir.
 *
 * Una temática que no existe **no se valida aquí**: no hay forma de
 * distinguirla de una retirada del catálogo, y las obras que la tuvieran
 * siguen apuntando a ella. Simplemente no encuentra nada.
 *
 * Las etiquetas de contenido van al revés en las dos cosas: **quitan** obras
 * en vez de añadirlas (`FEAT-WRK-017` `RN-10`) y sí se validan, porque su
 * lista es cerrada y aceptar una errata enseñaría justo lo que el lector ha
 * pedido no ver.
 *
 * Los demás filtros se validan en vez de ignorarse. Un valor desconocido devuelve
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
            array_values(array_unique(array_map(strtoupper(...), $query->genres))),
            self::excluded($query->excludedWarnings),
            self::status($query->status),
            'relevance' === $query->sort,
            $query->page,
            min(max($query->perPage, 1), self::MAX_PER_PAGE),
            $this->clock->now(),
        ));
    }

    /**
     * Las etiquetas que el lector no quiere ver **sí se validan**, al revés
     * que las temáticas: la lista es cerrada, así que un valor que no está en
     * ella es una errata, y darla por buena enseñaría exactamente lo que se
     * ha pedido no ver.
     *
     * @param list<string> $warnings
     *
     * @return list<string>
     */
    private static function excluded(array $warnings): array
    {
        $excluded = [];

        foreach (array_unique(array_map(strtoupper(...), $warnings)) as $code) {
            $warning = ContentWarning::tryFrom($code);

            if (null === $warning) {
                throw UnknownCatalogueFilter::contentWarning();
            }

            $excluded[] = $warning->value;
        }

        return $excluded;
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
