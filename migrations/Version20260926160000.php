<?php

declare(strict_types=1);

namespace LectoresBeta\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Quién registró una reclamación en nombre de otro (`FEAT-MOD-005` `RN-12`).
 *
 * `filed_on_behalf` ya decía **que** entró por la puerta de atrás; esto dice
 * **quién** la abrió, y es lo que hace posible `RN-14`: quien la redactó a
 * partir de un correo no puede resolverla. Registrarla no es decidir, pero
 * quien la ha escrito ya se ha formado una opinión.
 */
final class Version20260926160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'FEAT-MOD-005: quién registró una reclamación en nombre de otro';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE moderation_ctx.claim ADD registered_by_id UUID DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE moderation_ctx.claim DROP registered_by_id');
    }
}
