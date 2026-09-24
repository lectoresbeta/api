<?php

declare(strict_types=1);

namespace LectoresBeta\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Las dos señales con las que el catálogo ordena (`FEAT-WRK-012`,
 * `decision:0008`).
 *
 * Viven en `work_ctx` y no en el contexto que las produce, y esa es toda la
 * razón de que existan: el catálogo **no puede hacer un `JOIN` con las tablas
 * de `Credits` ni de `Feedback`**, y ordenar y paginar exige tener el dato en
 * la misma consulta que filtra.
 *
 * Ninguna guarda dinero ni contenido. Se reconstruyen enteras reprocesando
 * `ChapterCorrectabilityChanged` y `FeedbackSubmitted`.
 *
 * `catalogue_delivered_correction` guarda una fila por corrección en vez de
 * un contador: la clave primaria es lo que hace idempotente la reentrega del
 * mismo hecho, y contar es un `COUNT(*)` con índice.
 */
final class Version20260924030000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds the two catalogue signals: chapter correctability and delivered corrections.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE work_ctx.catalogue_chapter_signal (
              chapter_id UUID NOT NULL,
              work_id UUID NOT NULL,
              correctable BOOLEAN NOT NULL,
              affordable_corrections SMALLINT NOT NULL,
              changed_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY (chapter_id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_catalogue_signal_work ON work_ctx.catalogue_chapter_signal (work_id, correctable)');

        $this->addSql(<<<'SQL'
            CREATE TABLE work_ctx.catalogue_delivered_correction (
              correction_id UUID NOT NULL,
              work_id UUID NOT NULL,
              submitted_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY (correction_id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_catalogue_delivered_work ON work_ctx.catalogue_delivered_correction (work_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE work_ctx.catalogue_chapter_signal');
        $this->addSql('DROP TABLE work_ctx.catalogue_delivered_correction');
    }
}
