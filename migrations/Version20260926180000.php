<?php

declare(strict_types=1);

namespace LectoresBeta\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * El gancho de reactivación (`FEAT-CRD-019`).
 *
 * `overdraft_grant` gana la diferencia entre **concedido** y **usado**, que es
 * la que sostiene la ficha entera: el cupo limita a cuánta gente se le abre la
 * puerta, no cuánta deuda aparece, y solo lo usado cuenta en la tasa de
 * recuperación.
 *
 * `overdraft_opt_out` guarda a quién no hay que seleccionar. Es una copia
 * local de una decisión que se toma en `User`, y vive aquí porque `Credits` no
 * puede preguntar (`decision:0002`).
 */
final class Version20260926180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'FEAT-CRD-019: overdraft eligibility, its use, and who declined it';
    }

    public function up(Schema $schema): void
    {
        // Las filas que hubiera caducan de inmediato y cuentan como usadas:
        // no hay ninguna en producción, y dejarlas eternamente elegibles
        // sería peor que cualquier otra elección.
        $this->addSql('ALTER TABLE credits_ctx.overdraft_grant ADD expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE credits_ctx.overdraft_grant ADD used_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('UPDATE credits_ctx.overdraft_grant SET expires_at = granted_at, used_at = granted_at WHERE expires_at IS NULL');
        $this->addSql('ALTER TABLE credits_ctx.overdraft_grant ALTER COLUMN expires_at SET NOT NULL');
        $this->addSql('CREATE INDEX idx_overdraft_author ON credits_ctx.overdraft_grant (author_id, expires_at)');

        $this->addSql(<<<'SQL'
            CREATE TABLE credits_ctx.overdraft_opt_out (
              user_id UUID NOT NULL,
              declined_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY (user_id)
            )
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE credits_ctx.overdraft_opt_out');
        $this->addSql('DROP INDEX credits_ctx.idx_overdraft_author');
        $this->addSql('ALTER TABLE credits_ctx.overdraft_grant DROP used_at');
        $this->addSql('ALTER TABLE credits_ctx.overdraft_grant DROP expires_at');
    }
}
