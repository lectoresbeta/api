<?php

declare(strict_types=1);

namespace LectoresBeta\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Qué propuestas admite cada persona (`FEAT-USR-011`).
 *
 * Una fila por usuario, y solo cuando la toca: la ausencia se lee como los
 * valores por defecto, que son los dos abiertos. Sembrar una fila por cuenta
 * para guardar dos `true` sería mantener una tabla del tamaño del padrón para
 * no escribir un `?? true`.
 *
 * Sin clave ajena a `user_ctx.account`: el mismo criterio que el resto de las
 * tablas de preferencias.
 */
final class Version20260927010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Per-user switches for beta-reader invitations and writing-buddy proposals.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE user_ctx.user_reception_settings (
              user_id UUID NOT NULL,
              beta_reader_invitations BOOLEAN NOT NULL,
              writing_buddy_proposals BOOLEAN NOT NULL,
              updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY (user_id)
            )
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE user_ctx.user_reception_settings');
    }
}
