<?php

declare(strict_types=1);

namespace LectoresBeta\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Retirar una obra (`FEAT-WRK-006`).
 *
 * Una columna, y la decisión que hay detrás: **se archiva, no se borra**.
 * Una obra de esta plataforma casi nunca es solo de quien la escribió —
 * cuelgan de ella correcciones pagadas, reclamaciones resueltas y accesos
 * concedidos—, así que borrarla destruiría el trabajo de otros y la prueba de
 * lo que se decidió.
 *
 * El borrado definitivo sigue existiendo y llega por otras dos vías: la
 * supresión de la cuenta (`FEAT-USR-013`) y una acción administrativa
 * motivada.
 */
final class Version20260925150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'FEAT-WRK-006: archivar una obra';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE work_ctx.work ADD archived_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE work_ctx.work DROP archived_at');
    }
}
