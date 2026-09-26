<?php

declare(strict_types=1);

namespace LectoresBeta\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * La insignia de créditos de la tarjeta del catálogo (`FEAT-CRD-013`).
 *
 * Dos columnas en una señal que ya existía, y no una tabla nueva: es un dato
 * más de lo que `Credits` decide sobre un capítulo, y el catálogo lo lee en
 * la misma consulta que filtra, ordena y pagina.
 *
 * `credits` es lo que gana quien corrija el capítulo. `priced_at` es aparte
 * de `changed_at` porque los dos hechos llegan por separado y cada uno tiene
 * que poder descartar lo viejo sin mirar al otro.
 *
 * Las filas anteriores arrancan sin precio: el cero significa que todavía no
 * ha llegado ninguno, la insignia no se pinta, y el siguiente
 * `ChapterPriceChanged` de cada capítulo lo rellena. No hay nada que
 * reconstruir a mano.
 */
final class Version20260924040000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds the credit badge to the catalogue chapter signal.';
    }

    public function up(Schema $schema): void
    {
        // El valor por defecto es solo para rellenar lo que ya estaba: se
        // retira acto seguido, para que a partir de aquí la columna la
        // escriba siempre la aplicación y nunca la base de datos.
        $this->addSql('ALTER TABLE work_ctx.catalogue_chapter_signal ADD credits SMALLINT NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE work_ctx.catalogue_chapter_signal ALTER COLUMN credits DROP DEFAULT');

        $this->addSql(<<<'SQL'
            ALTER TABLE work_ctx.catalogue_chapter_signal
              ADD priced_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT '1970-01-01 00:00:00'
        SQL);
        $this->addSql('ALTER TABLE work_ctx.catalogue_chapter_signal ALTER COLUMN priced_at DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE work_ctx.catalogue_chapter_signal DROP credits');
        $this->addSql('ALTER TABLE work_ctx.catalogue_chapter_signal DROP priced_at');
    }
}
