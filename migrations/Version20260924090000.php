<?php

declare(strict_types=1);

namespace LectoresBeta\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Qué hecho abrió cada acceso de lector beta (`R-22`).
 *
 * Cierra un agujero que no se vio hasta que `Notification` empezó a escuchar
 * las concesiones y las revocaciones: **conceder acceso al corregir no era
 * idempotente frente a una reentrega tardía**.
 *
 * El handler comprobaba que no hubiera un acceso vivo, y eso basta mientras
 * nadie lo retire. Si entre la primera entrega y una reentrega el autor
 * revocaba el acceso —o bloqueaba a esa persona—, la segunda vuelta ya no
 * encontraba nada vivo y lo concedía otra vez: una persona expulsada
 * recuperaba el acceso porque la cola repitió un mensaje, en silencio.
 *
 * La columna guarda el identificador del hecho, y el índice único es lo que
 * de verdad lo garantiza cuando dos consumidores procesan la reentrega a la
 * vez. Es nula en los caminos que decide una persona —aprobar una solicitud,
 * aceptar una invitación—: ahí la idempotencia la da el estado de la
 * solicitud, que solo se resuelve una vez.
 *
 * Las filas existentes se quedan a `NULL`. No se puede saber qué hecho las
 * abrió, y `NULL` no compite en un índice único de PostgreSQL — cabe más de
 * una.
 */
final class Version20260924090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Records which fact opened each beta reader access, so a redelivery cannot undo a revocation.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reading_ctx.beta_reader_access ADD granted_by_event_id UUID DEFAULT NULL');
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX uniq_beta_reader_access_event
              ON reading_ctx.beta_reader_access (granted_by_event_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX reading_ctx.uniq_beta_reader_access_event');
        $this->addSql('ALTER TABLE reading_ctx.beta_reader_access DROP granted_by_event_id');
    }
}
