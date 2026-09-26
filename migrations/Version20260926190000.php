<?php

declare(strict_types=1);

namespace LectoresBeta\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Constancia del último acceso (`FEAT-USR-005` `RN-5`).
 *
 * **Una fecha y nada más** (`RN-6`): ni dirección, ni navegador, ni
 * localización. Nulo significa que esa cuenta todavía no ha entrado, que es
 * distinto de que lleve mucho sin hacerlo.
 */
final class Version20260926190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'FEAT-USR-005: record when an account last signed in';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_ctx.account ADD last_signed_in_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_ctx.account DROP last_signed_in_at');
    }
}
