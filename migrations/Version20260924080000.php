<?php

declare(strict_types=1);

namespace LectoresBeta\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * La copia local de los bloqueos (`FEAT-COM-034`).
 *
 * `Community` es el dueño del bloqueo; esta tabla es una **proyección** que
 * `User` mantiene con `UserBlocked` y `UserUnblocked`, y sirve para una sola
 * pregunta: si hay bloqueo entre dos personas, que es lo que convierte
 * `AuthorAudience` en un «no» sea cual sea el ajuste de privacidad.
 *
 * Existe por la misma regla que la proyección de seguidores: un contrato
 * publicado no llama al de otro contexto mientras responde (`decision:0014`).
 *
 * La clave primaria es el **par ordenado**. La pregunta no tiene dirección
 * —el efecto de un bloqueo sobre los comentarios corta en los dos sentidos— y
 * ordenar el par hace que reprocesar un hecho escriba la misma fila.
 */
final class Version20260924080000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Projects blocks into user_ctx so the audience contract can refuse without asking Community.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE user_ctx.blocked_pair (
              one_id UUID NOT NULL,
              other_id UUID NOT NULL,
              blocked_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY (one_id, other_id)
            )
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE user_ctx.blocked_pair');
    }
}
