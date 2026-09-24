<?php

declare(strict_types=1);

namespace LectoresBeta\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * La copia local del grafo de seguidores (`FEAT-COM-010`).
 *
 * `Community` es el dueño del seguimiento; esta tabla es una **proyección**
 * que `User` mantiene con los hechos `AuthorSubscribed` y
 * `AuthorUnsubscribed`, y sirve para una sola pregunta: si alguien cuenta
 * como seguidor cuando su titular restringe algo a `FOLLOWERS`.
 *
 * Existe por una regla, no por comodidad: `CheckAuthorAudience` es un
 * contrato publicado y `decision:0014` prohíbe que un contrato llame al de
 * otro contexto mientras responde.
 *
 * La clave primaria es el par, que es lo que hace idempotente reprocesar un
 * hecho. **Sin claves foráneas**: una copia que restringe a su origen es una
 * copia que lo bloquea, y las dos tablas viven en esquemas de contextos
 * distintos.
 */
final class Version20260924070000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Projects the follower graph into user_ctx so FOLLOWERS audiences can be resolved locally.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE user_ctx.author_follower (
              author_id UUID NOT NULL,
              follower_id UUID NOT NULL,
              followed_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY (author_id, follower_id)
            )
        SQL);

        // La dirección que no cubre la clave primaria: a quién sigue alguien.
        $this->addSql('CREATE INDEX idx_author_follower_follower ON user_ctx.author_follower (follower_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE user_ctx.author_follower');
    }
}
