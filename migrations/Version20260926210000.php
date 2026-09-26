<?php

declare(strict_types=1);

namespace LectoresBeta\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Los «me gusta» (`FEAT-COM-008`, `FEAT-COM-030`).
 *
 * `post_like` ya existía desde `FEAT-COM-002`, sin nada que la usara. Lo que
 * falta es la gemela para los comentarios y su contador.
 *
 * Y se retira `post_reaction`: `FEAT-COM-007` queda derogada porque el
 * selector de emoji del diseño **inserta emojis en el texto** y no es un
 * mecanismo de reacción (`post-interactions.md`). La tabla nunca tuvo código
 * que escribiera en ella, así que está vacía; dejarla sería dejar un esquema
 * que promete una funcionalidad que no existe.
 */
final class Version20260926210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'FEAT-COM-008 + FEAT-COM-030: likes on comments; drop the deprecated reactions table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE community_ctx.post_comment_like (
              comment_id UUID NOT NULL,
              member_id UUID NOT NULL,
              liked_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY (comment_id, member_id)
            )
            SQL);
        $this->addSql('CREATE INDEX idx_post_comment_like_member ON community_ctx.post_comment_like (member_id)');

        $this->addSql('ALTER TABLE community_ctx.post_comment ADD like_count INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE community_ctx.post_comment ALTER like_count DROP DEFAULT');

        $this->addSql('DROP TABLE community_ctx.post_reaction');
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE community_ctx.post_reaction (
              post_id UUID NOT NULL,
              member_id UUID NOT NULL,
              emoji VARCHAR(16) NOT NULL,
              reacted_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY (post_id, member_id)
            )
            SQL);

        $this->addSql('ALTER TABLE community_ctx.post_comment DROP like_count');
        $this->addSql('DROP TABLE community_ctx.post_comment_like');
    }
}
