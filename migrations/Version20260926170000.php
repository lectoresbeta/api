<?php

declare(strict_types=1);

namespace LectoresBeta\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * El cifrado del texto revisado (`FEAT-MOD-011` `RN-9`).
 *
 * Es lo que convierte «se revisa solo lo que cambia» en algo comprobable, y
 * **es el texto lo que se mira, no su versión**: un capítulo puede editarse
 * sin cambiar de versión, porque el versionado solo se abre cuando ya lo ha
 * corregido alguien.
 *
 * El índice único no es una optimización — es lo que impide que una reentrega
 * de la cola abra una segunda reclamación automática sobre el mismo capítulo.
 *
 * La tabla está vacía en producción —el revisor se estrena con esta
 * funcionalidad—, así que la columna entra `NOT NULL` sin relleno.
 */
final class Version20260926170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'FEAT-MOD-011: un veredicto por texto revisado';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE moderation_ctx.content_review ADD content_hash VARCHAR(64) DEFAULT '' NOT NULL");
        $this->addSql('ALTER TABLE moderation_ctx.content_review ALTER content_hash DROP DEFAULT');
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX uniq_content_review_hash
              ON moderation_ctx.content_review (target_type, target_id, content_hash)
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX moderation_ctx.uniq_content_review_hash');
        $this->addSql('ALTER TABLE moderation_ctx.content_review DROP content_hash');
    }
}
