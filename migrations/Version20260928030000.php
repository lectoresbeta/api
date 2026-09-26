<?php

declare(strict_types=1);

namespace LectoresBeta\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * De qué color ve cada quien la aplicación (`FEAT-USR-042`).
 *
 * Una fila por persona, y se guarda **en el servidor y no en el navegador**
 * por una razón concreta: el tema tiene que sobrevivir al dispositivo. Quien
 * eligió oscuro en el portátil y abre el móvil espera oscuro, y una
 * preferencia en `localStorage` no viaja.
 *
 * Sin fila, `SYSTEM`. La ausencia no se interpreta en la base de datos sino
 * en el código, que es donde se puede explicar.
 */
final class Version20260928030000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Per-user appearance preference: the theme.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE user_ctx.appearance_settings (
              user_id UUID NOT NULL,
              theme VARCHAR(16) NOT NULL,
              updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY (user_id)
            )
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE user_ctx.appearance_settings');
    }
}
