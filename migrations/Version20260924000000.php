<?php

declare(strict_types=1);

namespace LectoresBeta\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * La proyección de corregibilidad de `Feedback` (`FEAT-FBK-003`).
 *
 * Es lo que permite abrir el panel de corrección **sin preguntarle nada a
 * `Credits`**: una consulta contra la base de datos de este contexto, con el
 * lector esperando delante de la pantalla.
 *
 * Guarda un booleano y nunca un saldo ni un precio. Se reconstruye entera
 * reprocesando `ChapterCorrectabilityChanged`.
 */
final class Version20260924000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds the chapter correctability projection that Feedback reads before opening a panel.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE feedback_ctx.correctable_chapter (
              chapter_id UUID NOT NULL,
              work_id UUID NOT NULL,
              correctable BOOLEAN NOT NULL,
              changed_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY (chapter_id)
            )
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE feedback_ctx.correctable_chapter');
    }
}
