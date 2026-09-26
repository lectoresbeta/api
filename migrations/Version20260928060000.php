<?php

declare(strict_types=1);

namespace LectoresBeta\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * «Más valorados» en «Mis relatos» (`FEAT-WRK-015`).
 *
 * **La suma y el recuento, no la media.** Una media guardada se redondea, y
 * sumarle una nota nueva arrastra el redondeo de todas las anteriores. Con
 * `rating_sum` y `rating_count` la media se calcula cuando se pide y siempre
 * es la de verdad.
 *
 * `work_reader_rating` guarda **la última nota conocida de cada persona**, y
 * no es un duplicado de la tabla de `Feedback`: existe porque `WorkRated` no
 * lleva la nota anterior, y sin ella una valoración cambiada dejaría la suma
 * contando las dos. De paso hace el consumo idempotente sin registro de
 * duplicados. Sin clave ajena al lector, que vive en otro contexto.
 *
 * Los contadores arrancan a cero para las obras que ya existen. Las
 * valoraciones dejadas antes de esta migración no se recuperan: `Feedback`
 * las tiene, pero reconstruirlas exigiría que `Work` leyera su tabla, que es
 * exactamente lo que la frontera prohíbe. El día que haga falta, se
 * reconstruyen reproduciendo `WorkRated`.
 */
final class Version20260928060000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Work keeps its own rating aggregate so «Mis relatos» can sort by it.';
    }

    public function up(Schema $schema): void
    {
        // Se añaden con valor por defecto para rellenar las obras que ya
        // existen, y se les quita después: el defecto lo pone el modelo, y
        // dos sitios que deciden el valor inicial acaban discrepando.
        $this->addSql('ALTER TABLE work_ctx.work ADD rating_count INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE work_ctx.work ADD rating_sum INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE work_ctx.work ALTER rating_count DROP DEFAULT');
        $this->addSql('ALTER TABLE work_ctx.work ALTER rating_sum DROP DEFAULT');

        $this->addSql(<<<'SQL'
            CREATE TABLE work_ctx.work_reader_rating (
              work_id UUID NOT NULL,
              reader_id UUID NOT NULL,
              value SMALLINT NOT NULL,
              rated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY (work_id, reader_id)
            )
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE work_ctx.work_reader_rating');
        $this->addSql('ALTER TABLE work_ctx.work DROP rating_sum');
        $this->addSql('ALTER TABLE work_ctx.work DROP rating_count');
    }
}
