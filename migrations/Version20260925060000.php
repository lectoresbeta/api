<?php

declare(strict_types=1);

namespace LectoresBeta\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * El token de «he olvidado mi contraseña» (`FEAT-USR-007`).
 *
 * Tabla aparte de `account_activation_token` aunque la forma sea idéntica.
 * Son dos credenciales con vidas distintas —dos días frente a una hora— y
 * mezclarlas haría que invalidar una tocase a la otra: pedir el enlace de
 * recuperación rompería el de activación que alguien tuviera abierto.
 *
 * Solo el hash. Durante la hora que vive, este valor **es** la cuenta: quien
 * lo tiene fija la contraseña. Quien pueda leer esta tabla no debe poder
 * entrar en ninguna.
 */
final class Version20260925060000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds the password reset token, so a forgotten password is no longer a dead end.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE user_ctx.password_reset_token (
              id UUID NOT NULL,
              user_id UUID NOT NULL,
              token_hash VARCHAR(64) NOT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              used_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              invalidated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              PRIMARY KEY (id)
            )
        SQL);

        $this->addSql('CREATE UNIQUE INDEX uniq_password_reset_hash ON user_ctx.password_reset_token (token_hash)');
        $this->addSql('CREATE INDEX idx_password_reset_user ON user_ctx.password_reset_token (user_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE user_ctx.password_reset_token');
    }
}
