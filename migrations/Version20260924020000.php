<?php

declare(strict_types=1);

namespace LectoresBeta\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Cuántas correcciones de un capítulo puede pagar su autor
 * (`decision:0008`, `FEAT-WRK-012`).
 *
 * Se guarda con el precio para poder publicar **solo los cambios**: el saldo
 * de un autor se mueve mucho más a menudo de lo que esa cifra cambia, sobre
 * todo con el tope de diez.
 *
 * No es un saldo ni un precio. Es lo único de esa aritmética que sale de
 * `Credits`, y lo que permite al catálogo ordenar por el trabajo que produce
 * enseñar una obra sin saber lo que cuesta nada.
 */
final class Version20260924020000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds how many corrections of a chapter its author can currently afford.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE credits_ctx.chapter_price ADD affordable_corrections SMALLINT NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE credits_ctx.chapter_price ALTER COLUMN affordable_corrections DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE credits_ctx.chapter_price DROP affordable_corrections');
    }
}
