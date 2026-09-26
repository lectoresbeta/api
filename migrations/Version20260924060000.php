<?php

declare(strict_types=1);

namespace LectoresBeta\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Cuándo se recuperó por última vez un nombre propio (`FEAT-USR-034`
 * `RN-1b`).
 *
 * Recuperar un alias propio esquiva el plazo de 30 días, y esta columna es lo
 * que impide encadenar la excepción: cada recuperación deja como alias el
 * nombre que se abandona, así que sin ella la vuelta siguiente volvería a ser
 * una recuperación y el plazo no llegaría a aplicarse nunca — que es el
 * vaivén que la regla existe para bloquear.
 *
 * Nula para todo lo existente, que es lo correcto: ninguna cuenta ha
 * recuperado nada todavía.
 */
final class Version20260924060000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds account.username_reclaimed_at so the username cooldown exception cannot be chained.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_ctx.account ADD COLUMN username_reclaimed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_ctx.account DROP COLUMN username_reclaimed_at');
    }
}
