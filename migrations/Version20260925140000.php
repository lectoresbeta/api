<?php

declare(strict_types=1);

namespace LectoresBeta\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Lo que se ganó por cada corrección (`FEAT-FBK-010`).
 *
 * Una proyección alimentada por los hechos de `Credits`, porque ese contexto
 * **no publica ningún contrato**, ni siquiera de consulta: preguntarle en
 * caliente sería la dependencia que `decision:0002` prohíbe.
 *
 * Tabla aparte y no una columna en `correction` a propósito: la corrección es
 * de este contexto y el importe es un eco de otro. Mezclarlos invitaría a
 * creer que `Feedback` sabe de dinero.
 */
final class Version20260925140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'FEAT-FBK-010: proyección de lo ganado por cada corrección';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE feedback_ctx.correction_earning (
                correction_id UUID NOT NULL,
                credits INT NOT NULL,
                recorded_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (correction_id)
            )
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE feedback_ctx.correction_earning');
    }
}
