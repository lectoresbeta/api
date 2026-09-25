<?php

declare(strict_types=1);

namespace LectoresBeta\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * El registro de hechos ya aplicados por `Community` (`FEAT-COM-016`).
 *
 * Hace falta porque las proyecciones de sugerencias **acumulan**: un contador
 * de obras publicadas que sube dos veces con el mismo hecho deja a un autor
 * arriba en las sugerencias por una reentrega, y RabbitMQ no garantiza
 * entrega única.
 *
 * Tabla propia y no la de `Credits`: un contexto no lee el registro de otro.
 */
final class Version20260926130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'FEAT-COM-016: registro de hechos ya aplicados por Community';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE community_ctx.processed_event (
                event_id VARCHAR(64) NOT NULL,
                consumer VARCHAR(64) NOT NULL,
                event_name VARCHAR(64) NOT NULL,
                processed_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (event_id, consumer)
            )
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE community_ctx.processed_event');
    }
}
