<?php

declare(strict_types=1);

namespace LectoresBeta\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Las interacciones sociales bajo el texto de un capítulo (`FEAT-COM-036`).
 *
 * Tres tablas y ninguna clave ajena a `work_ctx`: los capítulos son de otro
 * contexto, y una copia que restringe a su origen es una copia que lo
 * bloquea.
 *
 * `chapter_comment` es tabla aparte de `post_comment`, y no un `post_id`
 * anulable en aquella. Heredan audiencias distintas —una publicación frente a
 * la regla de lectura de una obra— y dos caminos de autorización dentro de
 * una entidad es la forma más rápida de aplicar un día el que no toca
 * (`R-9`, resuelta).
 *
 * `chapter_engagement` guarda los contadores en vez de contarlos al leer: la
 * cabecera de la pantalla de lectura enseña la cifra en cada visita, y contar
 * cientos de miles de filas cada vez es lo mismo que no poder enseñarla.
 */
final class Version20260927000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Likes and comments on a chapter, with their counters.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE community_ctx.chapter_comment (
              id UUID NOT NULL,
              chapter_id UUID NOT NULL,
              author_id UUID NOT NULL,
              parent_comment_id UUID DEFAULT NULL,
              body TEXT NOT NULL,
              reply_count INT NOT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_chapter_comment_chapter
                ON community_ctx.chapter_comment (chapter_id, created_at)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_chapter_comment_parent
                ON community_ctx.chapter_comment (parent_comment_id)
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE community_ctx.chapter_like (
              chapter_id UUID NOT NULL,
              member_id UUID NOT NULL,
              liked_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY (chapter_id, member_id)
            )
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE community_ctx.chapter_engagement (
              chapter_id UUID NOT NULL,
              like_count INT NOT NULL,
              comment_count INT NOT NULL,
              PRIMARY KEY (chapter_id)
            )
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE community_ctx.chapter_engagement');
        $this->addSql('DROP TABLE community_ctx.chapter_like');
        $this->addSql('DROP TABLE community_ctx.chapter_comment');
    }
}
