<?php

declare(strict_types=1);

namespace LectoresBeta\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * El contenido de un capítulo se guarda en sus dos formas
 * (`FEAT-WRK-001`, glosario).
 *
 * `content_html` es lo que se sirve —saneado al escribir, nunca al leer— y
 * `content_text` es el texto plano derivado de él.
 *
 * Guardar el texto y no derivarlo al vuelo no es redundancia: de ese recuento
 * depende **el precio de toda corrección** (`FEAT-CRD-016`), así que atarlo a
 * lo que haga hoy el código que quita etiquetas significaría que un cambio
 * inocente en ese código reprecia en silencio todos los capítulos de la
 * plataforma.
 *
 * La tabla está vacía —nada crea capítulos todavía— así que el renombrado es
 * gratis ahora y caro en cuanto haya obras.
 */
final class Version20260923210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'El capítulo guarda el HTML saneado y el texto plano derivado.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE work_ctx.chapter RENAME COLUMN content TO content_html');
        $this->addSql("ALTER TABLE work_ctx.chapter ADD content_text TEXT NOT NULL DEFAULT ''");
        $this->addSql('ALTER TABLE work_ctx.chapter ALTER COLUMN content_text DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE work_ctx.chapter DROP COLUMN content_text');
        $this->addSql('ALTER TABLE work_ctx.chapter RENAME COLUMN content_html TO content');
    }
}
