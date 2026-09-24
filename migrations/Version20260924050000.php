<?php

declare(strict_types=1);

namespace LectoresBeta\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Los ajustes de privacidad de las cuentas que ya existían (`FEAT-USR-038`
 * `RN-4`).
 *
 * La tabla estaba desde el principio y vacía: nadie la escribía. A partir de
 * ahora la fila nace con la cuenta, en la misma transacción, y esta migración
 * pone al día a las que se crearon antes.
 *
 * **La invariante es que no falte ninguna fila**, y no es una manía de
 * integridad: «esta cuenta no tiene ajustes» es la frase que alguien acaba
 * leyendo como «entonces todo está permitido». El código sabe responder con
 * los valores por defecto explícitos si no encuentra fila, pero conviene que
 * no tenga que hacerlo.
 *
 * `EVERYONE` en los tres es el valor por defecto documentado, así que rellenar
 * con él no cambia el comportamiento de nadie: escribe lo que ya se estaba
 * suponiendo.
 */
final class Version20260924050000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Backfills default privacy settings for accounts created before they existed.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            INSERT INTO user_ctx.user_privacy_settings
              (user_id, profile_visibility, comment_permission, message_permission, activity_visible, updated_at)
            SELECT a.id, 'EVERYONE', 'EVERYONE', 'EVERYONE', TRUE, NOW()
            FROM user_ctx.account a
            WHERE NOT EXISTS (
              SELECT 1 FROM user_ctx.user_privacy_settings s WHERE s.user_id = a.id
            )
        SQL);
    }

    /**
     * No se deshace, y es deliberado: borrar los ajustes de privacidad de
     * alguien para volver a un estado anterior sería destruir una decisión
     * suya. Lo que esta migración escribe es el valor por defecto, así que no
     * queda nada que revertir.
     */
    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException(
            'Privacy settings are not removed: doing so would destroy a decision its owner made.',
        );
    }
}
