<?php

declare(strict_types=1);

namespace LectoresBeta\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Lo que cada quien decide sobre su propio muro (`FEAT-COM-021`,
 * `FEAT-COM-022`, `FEAT-COM-033`).
 *
 * Las tres tablas nacen juntas porque las tres son lo mismo visto desde tres
 * lados: **una fila por espectador** que la consulta del muro tiene que
 * respetar. Guardar aparta, ocultar quita una tarjeta y silenciar quita a una
 * persona.
 *
 * Ninguna lleva identificador propio: la clave es el par, porque guardar dos
 * veces es guardar. Y ninguna lleva contador ni se publica: son preferencias
 * privadas, y un contador de guardados o de ocultaciones convertiría una nota
 * para uno mismo en una señal pública.
 *
 * `muted_member` guarda **una sola dirección**, al revés que `user_block`:
 * silenciar es unilateral también en el efecto. Que yo no quiera leerte no
 * significa que tú no puedas leerme.
 *
 * Sin claves ajenas, como el resto del esquema: la baja de una cuenta la
 * anonimiza cada contexto por su lado.
 */
final class Version20260928020000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Per-viewer curation of the wall: saved posts, hidden posts and muted members.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE community_ctx.saved_post (
              member_id UUID NOT NULL,
              post_id UUID NOT NULL,
              saved_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY (member_id, post_id)
            )
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE community_ctx.hidden_post (
              member_id UUID NOT NULL,
              post_id UUID NOT NULL,
              hidden_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY (member_id, post_id)
            )
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE community_ctx.muted_member (
              member_id UUID NOT NULL,
              muted_id UUID NOT NULL,
              muted_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY (member_id, muted_id)
            )
        SQL);

        // La lista de silenciados se lee por fecha descendente, que es la
        // única consulta que existe sobre esta tabla aparte de la clave.
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_muted_member_owner ON community_ctx.muted_member (member_id, muted_at)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE community_ctx.saved_post');
        $this->addSql('DROP TABLE community_ctx.hidden_post');
        $this->addSql('DROP TABLE community_ctx.muted_member');
    }
}
