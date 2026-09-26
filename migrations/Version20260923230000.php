<?php

declare(strict_types=1);

namespace LectoresBeta\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Si un capítulo admite correcciones ahora mismo (`FEAT-CRD-009`).
 *
 * Se guarda la **respuesta anterior**, no para consultarla —`Feedback` tiene
 * la suya— sino para poder publicar solo los cambios: el precio de un
 * capítulo se recalcula muchas más veces de las que su respuesta se mueve.
 *
 * Arranca en `false` y la recalcula el primer evento que toque esa obra o el
 * saldo de su autor. No hace falta backfill: es un read model.
 */
final class Version20260923230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds the previous correctability answer to the chapter price read model.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE credits_ctx.chapter_price ADD correctable BOOLEAN NOT NULL DEFAULT FALSE');
        $this->addSql('ALTER TABLE credits_ctx.chapter_price ALTER COLUMN correctable DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE credits_ctx.chapter_price DROP correctable');
    }
}
