<?php

declare(strict_types=1);

namespace LectoresBeta\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * El último mensaje de cada conversación, para ordenar «Mensajes»
 * (`FEAT-COM-012` `RN-2`).
 *
 * Las fechas se guardan al segundo en todo el proyecto, así que dos
 * conversaciones que reciben algo en el mismo segundo empatan. En el resto de
 * listas ese empate lo rompe el identificador de la propia fila, y sale bien
 * porque allí id y fecha crecen juntos. Aquí no: lo que ordena es el último
 * mensaje y el id de la conversación es el de cuando se abrió, de modo que un
 * hilo antiguo que acaba de recibir algo quedaría por debajo de uno recién
 * abierto.
 *
 * Nulo solo para una conversación sin ningún mensaje, que hoy no puede
 * existir: se crean al enviar el primero.
 */
final class Version20260926220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Conversation keeps the id of its last message, to break the ordering tie exactly.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE community_ctx.conversation ADD last_message_id UUID DEFAULT NULL');

        $this->addSql(<<<'SQL'
            UPDATE community_ctx.conversation c
               SET last_message_id = (
                   SELECT m.id
                     FROM community_ctx.direct_message m
                    WHERE m.conversation_id = c.id
                 ORDER BY m.sent_at DESC, m.id DESC
                    LIMIT 1
               )
        SQL);

        $this->addSql('DROP INDEX community_ctx.idx_conversation_one');
        $this->addSql('DROP INDEX community_ctx.idx_conversation_two');
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_conversation_one
                ON community_ctx.conversation (member_one, last_message_at, last_message_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_conversation_two
                ON community_ctx.conversation (member_two, last_message_at, last_message_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX community_ctx.idx_conversation_one');
        $this->addSql('DROP INDEX community_ctx.idx_conversation_two');
        $this->addSql('ALTER TABLE community_ctx.conversation DROP last_message_id');
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_conversation_one ON community_ctx.conversation (member_one, last_message_at)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_conversation_two ON community_ctx.conversation (member_two, last_message_at)
        SQL);
    }
}
