<?php

declare(strict_types=1);

namespace LectoresBeta\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * El versionado del capítulo (`FEAT-WRK-005`).
 *
 * Hasta ahora una obra se creaba y no se podía tocar. Al abrir la edición
 * aparece el problema de fondo: **alguien puede haber leído ese texto**, y
 * una corrección entregada que habla de un párrafo que ya no existe no es que
 * envejezca, es que deja de tener sentido — y el autor pagó por ella.
 *
 * Tres piezas:
 *
 * - `chapter.version`, el número del texto que hay ahora;
 * - `chapter.current_version_read_at`, cuándo empezó alguien a corregirlo. Es
 *   lo que decide si editar cuesta una copia o no: sin nadie leyendo, se
 *   sobrescribe y no se archiva nada;
 * - `chapter_version`, las copias, que se escriben una vez y no se tocan.
 *
 * Y en `Feedback`, la versión que cada corrección respondió. Nula en las
 * anteriores a esto, que no tienen versión que recordar: se les sirve el
 * texto vigente, dicho como lo que es.
 */
final class Version20260925120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'FEAT-WRK-005: versionado del contenido de un capítulo';
    }

    public function up(Schema $schema): void
    {
        // El valor por defecto es solo para las filas que ya existen: todas
        // arrancan en la versión 1. Se retira acto seguido porque la entidad
        // no declara ninguno, y una columna con un `DEFAULT` que el mapeo
        // desconoce es una diferencia que `doctrine:schema:validate` canta en
        // cada despliegue.
        $this->addSql('ALTER TABLE work_ctx.chapter ADD version SMALLINT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE work_ctx.chapter ALTER version DROP DEFAULT');
        $this->addSql('ALTER TABLE work_ctx.chapter ADD current_version_read_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');

        $this->addSql(<<<'SQL'
            CREATE TABLE work_ctx.chapter_version (
                id UUID NOT NULL,
                chapter_id UUID NOT NULL,
                version SMALLINT NOT NULL,
                title VARCHAR(180) DEFAULT NULL,
                content_html TEXT NOT NULL,
                content_text TEXT NOT NULL,
                word_count INT NOT NULL,
                archived_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
            SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_chapter_version ON work_ctx.chapter_version (chapter_id, version)');

        $this->addSql('ALTER TABLE feedback_ctx.correction ADD chapter_version SMALLINT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE feedback_ctx.correction DROP chapter_version');
        $this->addSql('DROP TABLE work_ctx.chapter_version');
        $this->addSql('ALTER TABLE work_ctx.chapter DROP current_version_read_at');
        $this->addSql('ALTER TABLE work_ctx.chapter DROP version');
    }
}
