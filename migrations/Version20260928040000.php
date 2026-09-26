<?php

declare(strict_types=1);

namespace LectoresBeta\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Cómo decide el autor que se vea su página (`FEAT-USR-016`).
 *
 * Dos códigos por persona, y **códigos y no valores**: `theme` y
 * `accent_colour` guardan el nombre de una opción de un catálogo cerrado, no
 * un hexadecimal ni CSS. Es la decisión de `U-5`, y la columna la refleja —
 * un `VARCHAR(24)` con seis valores posibles no es un sitio donde quepa una
 * hoja de estilos.
 *
 * **El fondo no añade columna.** Es `cover_url`, que ya estaba en
 * `user_ctx.account` desde `FEAT-USR-014` y lo único que le faltaba era un
 * endpoint. Duplicar la imagen habría dejado dos sitios donde vive la misma
 * cosa.
 *
 * Sin fila, `CLASSIC` y `SLATE`. La ausencia se interpreta en el código, que
 * es donde se puede explicar.
 */
final class Version20260928040000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Author page style: a theme and an accent colour from a closed catalogue.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE user_ctx.author_page_style (
              user_id UUID NOT NULL,
              theme VARCHAR(24) NOT NULL,
              accent_colour VARCHAR(24) NOT NULL,
              updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY (user_id)
            )
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE user_ctx.author_page_style');
    }
}
