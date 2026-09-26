<?php

declare(strict_types=1);

namespace LectoresBeta\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * La deduplicación pasa a ser por regla, no por evento (`FEAT-CRD-011` `RN-4`).
 *
 * La clave de `processed_event` era `event_id` a secas, y con esa clave **la
 * primera regla que procesa un hecho lo marca como hecho para todas las
 * demás**. En `Credits` hay hechos que dos reglas independientes necesitan
 * ver: entregar una corrección mueve créditos (`FEAT-CRD-006`) y además puede
 * pagar la bonificación por invitación (`FEAT-CRD-005`). La segunda no se
 * ejecutaría nunca.
 *
 * Se arregla ahora porque la tabla está vacía y no la usa nadie. Con
 * movimientos dentro, cambiar la clave primaria sería una migración con la
 * aplicación parada.
 *
 * `consumer` nombra la **regla** —`welcome-grant`— y no la clase que la
 * implementa, de modo que un renombrado no reabre eventos ya aplicados.
 *
 * De paso, el índice que hace barata la invariante «como máximo un
 * `WELCOME_GRANT` por usuario» (`FEAT-CRD-002` `RN-3`), que es una pregunta
 * que se hace en cada activación.
 */
final class Version20260923190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'La deduplicación de eventos pasa a ser por (event_id, consumer).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE credits_ctx.processed_event DROP CONSTRAINT processed_event_pkey');
        $this->addSql('ALTER TABLE credits_ctx.processed_event ADD consumer VARCHAR(64) NOT NULL');
        $this->addSql('ALTER TABLE credits_ctx.processed_event ADD PRIMARY KEY (event_id, consumer)');

        $this->addSql('CREATE INDEX idx_credit_transaction_user_reason ON credits_ctx.credit_transaction (user_id, reason)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX credits_ctx.idx_credit_transaction_user_reason');

        // Revertir sólo es seguro si ninguna pareja (event_id, consumer)
        // comparte event_id, que es justo lo que esta migración permite. Por
        // eso se limpian las filas: no hay forma de elegir cuál conservar.
        $this->addSql('TRUNCATE TABLE credits_ctx.processed_event');
        $this->addSql('ALTER TABLE credits_ctx.processed_event DROP CONSTRAINT processed_event_pkey');
        $this->addSql('ALTER TABLE credits_ctx.processed_event DROP COLUMN consumer');
        $this->addSql('ALTER TABLE credits_ctx.processed_event ADD PRIMARY KEY (event_id)');
    }
}
