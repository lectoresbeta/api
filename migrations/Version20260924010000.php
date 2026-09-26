<?php

declare(strict_types=1);

namespace LectoresBeta\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Si un acceso de lector beta se **ganó** entregando una corrección
 * (`FEAT-RDG-001` `RN-5`).
 *
 * Es lo que distingue revocar a quien se echó atrás de quitarle el acceso a
 * quien ya trabajó. Un booleano basta: lo único que decide es si descartar un
 * borrador puede deshacer el acceso, y para eso una corrección entregada vale
 * lo mismo que veinte.
 *
 * Los accesos que ya existan se marcan como **ganados**: son anteriores a
 * esta vía, vinieron de una solicitud o una invitación, y no deben poder
 * revocarse por descartar nada.
 */
final class Version20260924010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Marks whether a beta reader access was earned by delivering a correction.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reading_ctx.beta_reader_access ADD earned BOOLEAN NOT NULL DEFAULT TRUE');
        $this->addSql('ALTER TABLE reading_ctx.beta_reader_access ALTER COLUMN earned DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reading_ctx.beta_reader_access DROP earned');
    }
}
