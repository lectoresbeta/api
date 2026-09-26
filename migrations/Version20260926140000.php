<?php

declare(strict_types=1);

namespace LectoresBeta\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * La suspensión parcial, en la cuenta (`FEAT-MOD-006`).
 *
 * Una fecha y no un estado: **caduca sola** (`RN-2`), y un estado que hay que
 * acordarse de apagar es un estado que alguien olvidará. La suspensión total
 * y la expulsión sí son estados, porque no caducan: viven en `status`.
 */
final class Version20260926140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'FEAT-MOD-006: hasta cuándo una cuenta no puede escribir';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_ctx.account ADD restricted_until TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_ctx.account DROP restricted_until');
    }
}
