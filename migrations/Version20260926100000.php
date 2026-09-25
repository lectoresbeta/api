<?php

declare(strict_types=1);

namespace LectoresBeta\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Lo que le faltaba a una publicación para poder existir (`FEAT-COM-002`).
 *
 * `link_url` porque un enlace se guarda **como dirección y nada más** (`C-8`):
 * no es un fichero del almacén, así que no cabe en `post_attachment`, donde
 * lo que hay son referencias a lo que se subió.
 *
 * `edited_at` porque «esta publicación se editó» tiene que poder afirmarse
 * delante de un comentario, y `updated_at` no sirve: se mueve por cualquier
 * cosa, y lo que hay que decirle a quien lee es que el texto de arriba no es
 * el que había cuando respondió.
 */
final class Version20260926100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'FEAT-COM-002: enlace externo y marca de edición en una publicación';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE community_ctx.post ADD link_url VARCHAR(2048) DEFAULT NULL');
        $this->addSql('ALTER TABLE community_ctx.post ADD edited_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE community_ctx.post DROP link_url');
        $this->addSql('ALTER TABLE community_ctx.post DROP edited_at');
    }
}
