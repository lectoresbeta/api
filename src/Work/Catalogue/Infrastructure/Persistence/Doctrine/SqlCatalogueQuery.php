<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Catalogue\Infrastructure\Persistence\Doctrine;

use Doctrine\DBAL\Connection;
use LectoresBeta\Work\Catalogue\Application\DTO\CatalogueCriteria;
use LectoresBeta\Work\Catalogue\Application\DTO\CatalogueEntry;
use LectoresBeta\Work\Catalogue\Application\DTO\CataloguePage;
use LectoresBeta\Work\Catalogue\Application\Port\CatalogueQuery;
use LectoresBeta\Work\Catalogue\Domain\Service\CatalogueRelevance;

/**
 * El catálogo, resuelto por PostgreSQL (`FEAT-WRK-012`).
 *
 * **No hay ningún `JOIN` con tablas de `Credits` ni de `Feedback`**, que es
 * el requisito de cumplimiento de
 * [`decision:0008`](../../../../../docs/decisions/0008-catalogue-ordering.md).
 * Lo que esos contextos saben llega por eventos y vive en dos tablas de
 * `work_ctx`: si un capítulo admite corrección y cuánto trabajo produce
 * enseñarlo, y qué correcciones se han entregado.
 *
 * La fórmula está declarada en `CatalogueRelevance` y se calcula aquí porque
 * ordenar y paginar en PHP significaría traerse el catálogo entero a memoria.
 * Sus constantes se interpolan desde esa clase, no se reescriben.
 *
 * `now` llega como parámetro y no como `now()` de PostgreSQL: la hora es una
 * dependencia del caso de uso, y un test que no puede fijarla no puede
 * comprobar la frescura.
 */
final readonly class SqlCatalogueQuery implements CatalogueQuery
{
    public function __construct(private Connection $connection)
    {
    }

    public function page(CatalogueCriteria $criteria): CataloguePage
    {
        $where = $this->conditions($criteria);
        $parameters = $this->parameters($criteria);

        /** @var int<0, max> $total */
        $total = (int) $this->connection->fetchOne(
            \sprintf('SELECT COUNT(*) FROM work_ctx.work w WHERE %s', $where),
            $parameters,
        );

        if (0 === $total) {
            return new CataloguePage([], 0, $criteria->page, $criteria->perPage);
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->connection->fetchAllAssociative(
            $this->sql($where, $criteria->byRelevance),
            [...$parameters, 'limit' => $criteria->perPage, 'offset' => $criteria->offset()],
        );

        return new CataloguePage(array_map(self::entry(...), $rows), $total, $criteria->page, $criteria->perPage);
    }

    /**
     * El filtro duro y la puntuación, en una sola consulta.
     *
     * Las dos subconsultas laterales agregan las señales por obra. Una obra
     * sin capítulos corregibles puntúa cero y cae al final: enseñar algo que
     * el lector no puede corregir desperdicia el sitio más valioso de la
     * pantalla.
     *
     * **El estado de la obra lo pone `Work`, no la señal.** Lo que `Credits`
     * responde es si el autor puede pagar ese capítulo y si le queda hueco;
     * que la obra esté abierta a corrección es dato propio, y combinar las
     * dos cosas aquí evita tener que proyectar el estado de cada obra dentro
     * de `Credits` (`FEAT-CRD-009`).
     */
    private function sql(string $where, bool $byRelevance): string
    {
        $order = $byRelevance
            ? 'score DESC, w.status_changed_at DESC'
            : 'w.status_changed_at DESC';

        return \sprintf(
            <<<'SQL'
                SELECT
                  w.id, w.title, w.synopsis, w.status, w.word_count, w.chapter_count, w.adults_only,
                  CASE WHEN w.status = 'IN_CORRECTION' THEN COALESCE(signal.correctable_chapters, 0) ELSE 0 END
                    AS correctable_chapters,
                  COALESCE(delivered.corrections_received, 0) AS corrections_received,
                  CASE
                    WHEN w.status <> 'IN_CORRECTION' OR COALESCE(signal.correctable_chapters, 0) = 0 THEN %4$d
                    ELSE COALESCE(signal.affordable, 0)::numeric
                         / (1 + COALESCE(delivered.corrections_received, 0))
                         / POWER(1 + GREATEST(EXTRACT(EPOCH FROM (CAST(:now AS timestamp) - w.status_changed_at)), 0) / %2$d, %3$s)
                  END AS score
                FROM work_ctx.work w
                LEFT JOIN LATERAL (
                  SELECT
                    COUNT(*) FILTER (WHERE s.correctable) AS correctable_chapters,
                    COALESCE(MAX(s.affordable_corrections) FILTER (WHERE s.correctable), 0) AS affordable
                  FROM work_ctx.catalogue_chapter_signal s
                  WHERE s.work_id = w.id
                ) signal ON TRUE
                LEFT JOIN LATERAL (
                  SELECT COUNT(*) AS corrections_received
                  FROM work_ctx.catalogue_delivered_correction d
                  WHERE d.work_id = w.id
                ) delivered ON TRUE
                WHERE %1$s
                ORDER BY %5$s
                LIMIT :limit OFFSET :offset
                SQL,
            $where,
            CatalogueRelevance::SECONDS_PER_WEEK,
            (string) CatalogueRelevance::FRESHNESS_EXPONENT,
            CatalogueRelevance::UNCORRECTABLE_SCORE,
            $order,
        );
    }

    /**
     * Lo que nunca aparece, pase lo que pase en los parámetros: un borrador
     * ajeno, una obra bloqueada por reclamación, la obra propia y —para quien
     * no tiene edad— la marcada para adultos.
     */
    private function conditions(CatalogueCriteria $criteria): string
    {
        $conditions = [
            "w.status <> 'DRAFT'",
            'w.blocked_at IS NULL',
            'w.author_id <> :reader',
        ];

        if (null !== $criteria->status) {
            $conditions[] = 'w.status = :status';
        }

        if (!$criteria->readerIsOfAge) {
            $conditions[] = 'w.adults_only = FALSE';
        }

        return implode(' AND ', $conditions);
    }

    /**
     * @return array<string, string|\DateTimeImmutable>
     */
    private function parameters(CatalogueCriteria $criteria): array
    {
        $parameters = [
            'reader' => $criteria->readerId,
            'now' => $criteria->now->format('Y-m-d H:i:s'),
        ];

        if (null !== $criteria->status) {
            $parameters['status'] = $criteria->status;
        }

        return $parameters;
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function entry(array $row): CatalogueEntry
    {
        return new CatalogueEntry(
            (string) $row['id'],
            (string) $row['title'],
            null === $row['synopsis'] ? null : (string) $row['synopsis'],
            (string) $row['status'],
            (int) $row['word_count'],
            (int) $row['chapter_count'],
            (bool) $row['adults_only'],
            (int) $row['correctable_chapters'],
            (int) $row['corrections_received'],
        );
    }
}
