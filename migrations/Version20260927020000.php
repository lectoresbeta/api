<?php

declare(strict_types=1);

namespace LectoresBeta\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Los manuscritos subidos y todavía sin confirmar (`FEAT-WRK-002`).
 *
 * Es una **tabla de paso**: entre subir el fichero y confirmar el troceado
 * hay un rato, y en ese rato el texto tiene que estar en algún sitio. Vive
 * horas, se borra al confirmar y la purga se lleva lo que nadie confirmó.
 *
 * El fichero original **no se guarda** en ninguna parte: se extrae el texto y
 * se descarta. Duplicar el almacenamiento de obra inédita, y con él su
 * superficie de exposición, a cambio de un valor probatorio que hoy nadie usa
 * no sale a cuenta.
 *
 * El índice va por caducidad, que es como la recorre la purga.
 */
final class Version20260927020000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Uploaded manuscripts awaiting the author to confirm how they split into chapters.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE work_ctx.manuscript_upload (
              id UUID NOT NULL,
              author_id UUID NOT NULL,
              filename VARCHAR(120) NOT NULL,
              chapters JSONB NOT NULL,
              uploaded_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_manuscript_upload_expiry ON work_ctx.manuscript_upload (expires_at)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE work_ctx.manuscript_upload');
    }
}
