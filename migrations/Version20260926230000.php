<?php

declare(strict_types=1);

namespace LectoresBeta\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * La copia del grafo de seguidores con la que `Notification` reparte los
 * avisos de obra nueva (`FEAT-NOT-004`).
 *
 * Es la tercera copia del mismo grafo, y está justificada en la ficha: un
 * reparto de avisos no tiene a nadie esperando al otro lado, así que el hecho
 * asíncrono basta y no hace falta un contrato que entregue la lista de
 * seguidores de alguien.
 *
 * El par es la clave primaria, y en ese orden: se pregunta siempre por autor,
 * y `(author_id, follower_id)` sirve además para continuar la página por el
 * segundo componente sin un índice aparte.
 *
 * Nace vacía. Los seguimientos anteriores a esta migración no están aquí, y
 * es aceptable: lo que se pierde es el aviso de la siguiente obra de alguien
 * a quien ya se seguía, no el seguimiento.
 */
final class Version20260926230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Notification projects the follower graph, to fan out the new-work notice.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE notification_ctx.author_follower (
              author_id UUID NOT NULL,
              follower_id UUID NOT NULL,
              followed_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY (author_id, follower_id)
            )
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE notification_ctx.author_follower');
    }
}
