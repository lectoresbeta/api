<?php

declare(strict_types=1);

namespace LectoresBeta\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * El enlace público, con tope y con marca (`FEAT-WRK-010`, `FEAT-FBK-008`).
 *
 * Dos columnas en dos contextos, y la separación entre ellas es la decisión:
 * **el tope lo guarda `Work`, que es de quien es el enlace; las correcciones
 * gastadas las cuenta `Feedback`, que es quien las tiene**. Una copia del
 * contador en el enlace sería un segundo número, y uno de los dos se quedaría
 * viejo.
 *
 * `public_link_id` en la corrección es lo que permite contarlas por enlace, y
 * además es la marca de `RN-8`: sin ella el autor no entendería por qué en
 * unas correcciones puede dar propina y en otras no.
 */
final class Version20260926150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'FEAT-WRK-010: tope del enlace público y marca en la corrección';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE work_ctx.public_link ADD max_corrections SMALLINT DEFAULT 10 NOT NULL');
        $this->addSql('ALTER TABLE work_ctx.public_link ALTER max_corrections DROP DEFAULT');

        $this->addSql('ALTER TABLE feedback_ctx.correction ADD public_link_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE feedback_ctx.correction ADD terms_accepted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_correction_public_link ON feedback_ctx.correction (public_link_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX feedback_ctx.idx_correction_public_link');
        $this->addSql('ALTER TABLE feedback_ctx.correction DROP terms_accepted_at');
        $this->addSql('ALTER TABLE feedback_ctx.correction DROP public_link_id');
        $this->addSql('ALTER TABLE work_ctx.public_link DROP max_corrections');
    }
}
