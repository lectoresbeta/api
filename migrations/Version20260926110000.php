<?php

declare(strict_types=1);

namespace LectoresBeta\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Una mención puede estar en una publicación, no solo en un comentario
 * (`FEAT-COM-032`).
 *
 * `comment_mention` nació suponiendo que solo se mencionaba al responder.
 * Ahora también se menciona al publicar, y con dos tablas «dónde me han
 * mencionado» —que es **una** pregunta— obligaría a unirlas a cada quien que
 * la hiciera, y a unir tres el día que aparezca un tercer sitio.
 *
 * Se renombra en vez de crear una tabla nueva al lado, y las filas que
 * hubiera se marcan como lo que eran: menciones en un comentario.
 */
final class Version20260926110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'FEAT-COM-032: las menciones dejan de ser solo de comentarios';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE community_ctx.comment_mention RENAME TO mention');
        $this->addSql('ALTER TABLE community_ctx.mention RENAME COLUMN comment_id TO subject_id');
        $this->addSql('ALTER TABLE community_ctx.mention ADD subject_kind VARCHAR(16) DEFAULT NULL');
        $this->addSql("UPDATE community_ctx.mention SET subject_kind = 'COMMENT' WHERE subject_kind IS NULL");
        $this->addSql('ALTER TABLE community_ctx.mention ALTER subject_kind SET NOT NULL');

        $this->addSql('ALTER INDEX community_ctx.idx_comment_mention_user RENAME TO idx_mention_user');
        $this->addSql('DROP INDEX community_ctx.idx_comment_mention_comment');
        $this->addSql('CREATE INDEX idx_mention_subject ON community_ctx.mention (subject_kind, subject_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM community_ctx.mention WHERE subject_kind = 'POST'");
        $this->addSql('DROP INDEX community_ctx.idx_mention_subject');
        $this->addSql('ALTER TABLE community_ctx.mention DROP subject_kind');
        $this->addSql('ALTER TABLE community_ctx.mention RENAME COLUMN subject_id TO comment_id');
        $this->addSql('ALTER TABLE community_ctx.mention RENAME TO comment_mention');
        $this->addSql('ALTER INDEX community_ctx.idx_mention_user RENAME TO idx_comment_mention_user');
        $this->addSql('CREATE INDEX idx_comment_mention_comment ON community_ctx.comment_mention (comment_id)');
    }
}
