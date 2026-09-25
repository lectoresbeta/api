<?php

declare(strict_types=1);

namespace LectoresBeta\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * El bucle de invitación, por el lado que cobra (`FEAT-CRD-005`).
 *
 * `credits_ctx.referral` es **una proyección de `Credits`**, no una copia de
 * `user_ctx.platform_invitation`. Que las dos tablas hablen de lo mismo y no
 * haya clave ajena entre ellas es deliberado (`decision:0002`,
 * `decision:0009`): una clave ajena entre esquemas de contextos distintos
 * convierte el acoplamiento que la arquitectura prohíbe en algo que la base
 * de datos obliga.
 *
 * La clave es **el invitado** y no la invitación: a cada persona la trae
 * alguien una sola vez y para siempre, y es la base de datos quien lo
 * garantiza. Dos invitaciones distintas a la misma persona —que `User` sí
 * permite, porque dos personas pueden invitarla— no pueden dar dos
 * recompensas por una sola alta.
 *
 * Y un índice más en `platform_invitation`: el tope diario de invitaciones
 * (`FEAT-USR-018` `RN-4`) se cuenta por invitador y por fecha de envío, que
 * es una pregunta que el índice existente, ordenado por si se consumieron, no
 * responde.
 */
final class Version20260927030000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Who brought whom, as Credits records it, and the index behind the daily invitation cap.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE credits_ctx.referral (
              invitee_id UUID NOT NULL,
              inviter_id UUID NOT NULL,
              linked_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              rewarded_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              PRIMARY KEY (invitee_id)
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_referral_inviter ON credits_ctx.referral (inviter_id, rewarded_at)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_platform_invitation_sent ON user_ctx.platform_invitation (inviter_id, created_at)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX user_ctx.idx_platform_invitation_sent');
        $this->addSql('DROP TABLE credits_ctx.referral');
    }
}
