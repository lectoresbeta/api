<?php

declare(strict_types=1);

namespace LectoresBeta\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Buscar texto en el muro (`FEAT-COM-009`).
 *
 * Un índice **GIN sobre una expresión**, no sobre una columna: no se guarda
 * un `tsvector` en ninguna parte. Una columna generada habría obligado a
 * mapearla en Doctrine, y con ello a meter un artefacto de persistencia
 * dentro de la entidad de dominio, que es exactamente lo que `AGENTS.md`
 * prohíbe.
 *
 * **El idioma tiene que ser el mismo aquí y en la consulta.** Si dejan de
 * coincidir, PostgreSQL no usa el índice y la búsqueda pasa de instantánea a
 * un escaneo completo de la tabla **sin dar ningún error**: se nota el día
 * que hay publicaciones de verdad, y entonces cuesta encontrarlo. La consulta
 * lo escribe en `FullTextMatch`, y cambiarlo es rehacer este índice.
 *
 * `to_tsvector` y no `ILIKE '%x%'`: aquel busca por raíz —«escribiendo»
 * encuentra «escribir»—, ignora acentos mal puestos y, sobre todo, usa
 * índice. Un `ILIKE` con comodín por delante no puede usar ninguno.
 */
final class Version20260927040000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'A Spanish full-text index over post bodies, for searching the wall.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_post_search
              ON community_ctx.post
              USING GIN (to_tsvector('spanish', body))
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX community_ctx.idx_post_search');
    }
}
