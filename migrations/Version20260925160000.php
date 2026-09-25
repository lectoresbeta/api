<?php

declare(strict_types=1);

namespace LectoresBeta\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Avisar una vez a quien tenía trabajo a medias (`FEAT-MOD-003` `RN-4`,
 * `FEAT-WRK-006` `RN-4`, `FEAT-WRK-008` `RN-5`).
 *
 * Una marca de aviso, no de estado: si el contenido vuelve, el borrador se
 * entrega igual. Lo que impide es avisar dos veces de lo mismo, que es
 * exactamente lo que ocurriría en cuanto la cola reentregase el hecho — y un
 * segundo «has perdido tu trabajo» es gratuito y cruel.
 */
final class Version20260925160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'FEAT-MOD-003: aviso a quien tenía una corrección a medias';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE feedback_ctx.correction ADD withdrawal_noticed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE feedback_ctx.correction DROP withdrawal_noticed_at');
    }
}
