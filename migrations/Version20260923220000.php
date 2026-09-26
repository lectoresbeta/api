<?php

declare(strict_types=1);

namespace LectoresBeta\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * El precio de una corrección, por capítulo (`FEAT-CRD-016`).
 *
 * Las dos tablas son **read models** de `Credits`: se reconstruyen enteras
 * reprocesando `ChapterContentUpdated` y `QuestionnaireUpdated`, y no son la
 * fuente de verdad de nada. Perderlas cuesta un reproceso, no dinero.
 */
final class Version20260923220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds the questionnaire demand read model and the chapter position that resolves it.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE credits_ctx.work_questionnaire_demand (
              work_id UUID NOT NULL,
              version SMALLINT NOT NULL,
              required_words INT NOT NULL,
              required_words_for_every_chapter INT NOT NULL,
              updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY (work_id)
            )
        SQL);

        // El valor por defecto es solo para la conversión: la tabla está
        // vacía en todos los entornos —nada escribía en ella hasta ahora—,
        // pero una columna NOT NULL sin defecto falla sobre cualquier fila
        // que hubiera quedado de una prueba.
        $this->addSql('ALTER TABLE credits_ctx.chapter_price ADD position SMALLINT NOT NULL DEFAULT 1');
        $this->addSql('ALTER TABLE credits_ctx.chapter_price ALTER COLUMN position DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE credits_ctx.work_questionnaire_demand');
        $this->addSql('ALTER TABLE credits_ctx.chapter_price DROP position');
    }
}
