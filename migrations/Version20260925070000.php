<?php

declare(strict_types=1);

namespace LectoresBeta\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * El token del cambio de correo se acuña **al enviar** (`FEAT-USR-040`).
 *
 * La columna pasa a admitir nulos porque la solicitud nace sin token: lo
 * rellena el contrato publicado en el momento de mandar el correo, igual que
 * el enlace de activación y el de recuperación.
 *
 * Dos consecuencias, y las dos son el motivo: el valor en claro no pasa por
 * la cola —donde se persiste, se reintenta y se aparca— y el plazo empieza a
 * contar cuando el mensaje sale, no mientras espera en un reintento.
 */
final class Version20260925070000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Lets the email change token be minted when the email is sent, not when the change is asked for.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_ctx.email_change_request ALTER token_hash DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DELETE FROM user_ctx.email_change_request WHERE token_hash IS NULL');
        $this->addSql('ALTER TABLE user_ctx.email_change_request ALTER token_hash SET NOT NULL');
    }
}
