<?php

declare(strict_types=1);

namespace LectoresBeta\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Cuándo se contó por última vez si un capítulo admite correcciones.
 *
 * Cierra un agujero de `RN-2` de `FEAT-CRD-018` que solo se ve cuando se
 * prueba el camino entero: **la puerta solo se cerraba si alguna vez había
 * estado abierta**.
 *
 * Un capítulo nace con `correctable = false`, así que uno cuyo autor no ha
 * podido pagarlo nunca coincide con el valor inicial, no se publica ningún
 * cambio, y `Feedback` se queda sin saberlo. Como ahí «no sé» significaba
 * «adelante», ese capítulo admitía correcciones que su autor no podía pagar.
 *
 * Con esta marca, la primera respuesta siempre es noticia: nadie la ha oído
 * todavía, y quien escucha no puede distinguir «no ha cambiado» de «nunca me
 * lo han dicho».
 *
 * Las filas existentes se quedan a `NULL`, que es lo correcto: nadie puede
 * afirmar que se anunciaron.
 */
final class Version20260925080000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Records when a chapter last announced whether it admits corrections, so the first answer is news too.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE credits_ctx.chapter_price ADD correctability_announced_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE credits_ctx.chapter_price DROP correctability_announced_at');
    }
}
