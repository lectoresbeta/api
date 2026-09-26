<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Post\Infrastructure\Persistence\Doctrine;

use Doctrine\ORM\QueryBuilder;
use LectoresBeta\Community\Post\Domain\ValueObject\PostFilters;

/**
 * Los filtros del muro, traducidos a SQL una sola vez (`FEAT-COM-009`).
 *
 * Los aplican **dos consultas** —la de publicaciones y la de reposts— y esa
 * es toda la razón de que esto exista aparte: escritas dos veces, la de
 * reposts se quedaría atrás al añadir un filtro, y el muro devolvería cosas
 * que el usuario acaba de pedir que no le devuelvan.
 *
 * Los dos alias no sobran. En la consulta de reposts **la publicación es una
 * y el momento es otro**: el tipo, el texto y quien escribió son del
 * original, pero la fecha por la que se filtra es la del repost, porque es
 * cuando esa entrada apareció en el muro y es la misma por la que se ordena.
 * Filtrar por la fecha del original haría que un repost de hoy de algo de
 * hace un año desapareciera al acotar «esta semana», estando justo arriba.
 */
final readonly class PostFilterClauses
{
    /**
     * @param string $post   alias de la publicación: de ella son el tipo, el
     *                       texto y el autor
     * @param string $moment alias de lo que se ordena por fecha, que en la
     *                       consulta de reposts **no** es la publicación
     */
    public static function applyTo(QueryBuilder $query, PostFilters $filters, string $post, string $moment): void
    {
        if (null !== $filters->type) {
            $query
                ->andWhere(\sprintf('%s.type = :filterType', $post))
                ->setParameter('filterType', $filters->type);
        }

        if (null !== $filters->text) {
            // Índice GIN sobre `to_tsvector('spanish', body)`, creado en su
            // migración. El idioma tiene que ser el mismo en los dos sitios o
            // PostgreSQL deja de usarlo, en silencio.
            $query
                ->andWhere(\sprintf('FULLTEXT_MATCH(%s.body, :filterText) = true', $post))
                ->setParameter('filterText', $filters->text);
        }

        if (null !== $filters->authorId) {
            // Quien **escribió**, que en un repost no es quien lo sacó.
            $query
                ->andWhere(\sprintf('%s.authorId = :filterAuthor', $post))
                ->setParameter('filterAuthor', $filters->authorId);
        }

        if (null !== $filters->from) {
            $query
                ->andWhere(\sprintf('%s.createdAt >= :filterFrom', $moment))
                ->setParameter('filterFrom', $filters->from);
        }

        if (null !== $filters->to) {
            $query
                ->andWhere(\sprintf('%s.createdAt <= :filterTo', $moment))
                ->setParameter('filterTo', $filters->to);
        }
    }
}
