<?php

declare(strict_types=1);

namespace LectoresBeta\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * La bandeja de correcciones recibidas (`FEAT-FBK-004`).
 *
 * Una sola columna: cuándo abrió el autor cada corrección. Es del
 * destinatario y de nadie más — quien la escribió no ve si se ha leído
 * (`F-14`)— y es lo que permite filtrar por «sin leer», que es como se
 * trabaja una bandeja.
 *
 * El índice es parcial: solo interesan las que **siguen** sin leer, que son
 * unas pocas de todas las que alguien acumula con los meses.
 */
final class Version20260925110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'FEAT-FBK-004: marca de lectura de las correcciones recibidas';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE feedback_ctx.correction ADD read_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_correction_owner_unread ON feedback_ctx.correction (owner_id, read_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX feedback_ctx.idx_correction_owner_unread');
        $this->addSql('ALTER TABLE feedback_ctx.correction DROP read_at');
    }
}
