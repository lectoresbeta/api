<?php

declare(strict_types=1);

namespace LectoresBeta\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Una respuesta por corrección (`FEAT-FBK-005` `RN-2`).
 *
 * El índice pasa de ordinario a único. No es una optimización: es la regla.
 * Volver a enviar la respuesta **sustituye** la que había, y bajo
 * concurrencia —dos pestañas abiertas, un doble clic— una comprobación en
 * código dejaría dos respuestas donde tiene que haber una.
 */
final class Version20260925130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'FEAT-FBK-005: una respuesta por corrección';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX feedback_ctx.idx_correction_reply_correction');
        $this->addSql('CREATE UNIQUE INDEX uniq_correction_reply ON feedback_ctx.correction_reply (correction_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX feedback_ctx.uniq_correction_reply');
        $this->addSql('CREATE INDEX idx_correction_reply_correction ON feedback_ctx.correction_reply (correction_id, created_at)');
    }
}
