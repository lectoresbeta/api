<?php

declare(strict_types=1);

namespace LectoresBeta\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Los avisos salen también por correo (`FEAT-NOT-002`, `FEAT-NOT-003`).
 *
 * `emailed_at` distingue «ya se mandó» de «existe el aviso», que es lo que
 * hace correcto el reintento cuando el proveedor de correo falla.
 *
 * `inbox` permite apagar los dos canales por separado, como promete la
 * pantalla de Configuración: hasta ahora un aviso silenciado no se creaba, y
 * sin fila no hay dónde anotar que el correo sí salió.
 *
 * Las filas que ya existen quedan con `emailed_at` nulo y **no se les manda
 * nada**: nadie vuelve a recorrer avisos viejos, el correo se decide en el
 * momento de entregar. Rellenarlo con la fecha de creación sería escribir en
 * el registro algo que no ocurrió.
 */
final class Version20260926200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'FEAT-NOT-002: notifications can be emailed, and each channel silenced apart';
    }

    public function up(Schema $schema): void
    {
        // El valor por defecto es solo para rellenar las filas que ya
        // existen; después se retira, porque quien crea un aviso decide
        // siempre si se enseña y un valor por defecto en la columna
        // escondería el día que se le olvide.
        $this->addSql('ALTER TABLE notification_ctx.notification ADD inbox BOOLEAN DEFAULT TRUE NOT NULL');
        $this->addSql('ALTER TABLE notification_ctx.notification ALTER inbox DROP DEFAULT');
        $this->addSql('ALTER TABLE notification_ctx.notification ADD emailed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');

        $this->addSql('DROP INDEX notification_ctx.idx_notification_recipient');
        $this->addSql('CREATE INDEX idx_notification_recipient ON notification_ctx.notification (recipient_id, inbox, read_at, created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX notification_ctx.idx_notification_recipient');
        $this->addSql('CREATE INDEX idx_notification_recipient ON notification_ctx.notification (recipient_id, read_at, created_at)');

        $this->addSql('ALTER TABLE notification_ctx.notification DROP emailed_at');
        $this->addSql('ALTER TABLE notification_ctx.notification DROP inbox');
    }
}
