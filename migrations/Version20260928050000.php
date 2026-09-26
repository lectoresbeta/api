<?php

declare(strict_types=1);

namespace LectoresBeta\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * La deuda congelada durante una suspensión parcial (`FEAT-CRD-018` `RN-8b`,
 * `FEAT-MOD-006` `RN-9`).
 *
 * **Dos fechas por lado y ninguna bandera.** Una dice hasta cuándo dura la
 * congelación; la otra, si alguien la levantó antes. La segunda existe porque
 * la cola reentrega: si levantar borrase la primera, la siguiente reentrega
 * del `SanctionImposed` original volvería a congelar y la de `SanctionLifted`
 * a descongelar, en un vaivén que no termina. Con las dos, reaplicar un hecho
 * ya aplicado no cambia nada.
 *
 * **Y ninguna bandera.** Ni `credits_ctx.credit_account` ni
 * `feedback_ctx.frozen_debt` guardan «está congelada»: guardan hasta cuándo.
 * La diferencia importa porque una suspensión parcial caduca sola y **nadie
 * publica un hecho al vencer el plazo**; con una fecha, la congelación se
 * apaga sin que haga falta un proceso programado que pueda dejar de
 * ejecutarse — y sin que un fallo suyo deje a alguien congelado para siempre.
 *
 * `feedback_ctx.frozen_debt` es una proyección, como `correctable_chapter`:
 * `Feedback` no decide nada sobre saldos, solo recuerda la fecha que le llegó
 * en `CreditDebtFrozen` para poder responder «¿ahora mismo?» cada vez que
 * entregue una corrección. Sin clave ajena a `credit_account`, que vive en
 * otro contexto ([`decision:0002`](../docs/decisions/0002-credits-as-isolated-bounded-context.md)).
 *
 * Sin índices en ninguna de las dos columnas de fecha, y es deliberado: nada
 * barre estas tablas buscando vencimientos. Siempre se llega por el
 * identificador de la persona.
 */
final class Version20260928050000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Debt freeze while a partial suspension lasts: an end date on both sides.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE credits_ctx.credit_account ADD debt_frozen_until TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE credits_ctx.credit_account ADD debt_freeze_lifted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');

        $this->addSql(<<<'SQL'
            CREATE TABLE feedback_ctx.frozen_debt (
              author_id UUID NOT NULL,
              frozen_until TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              lifted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY (author_id)
            )
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE feedback_ctx.frozen_debt');
        $this->addSql('ALTER TABLE credits_ctx.credit_account DROP debt_freeze_lifted_at');
        $this->addSql('ALTER TABLE credits_ctx.credit_account DROP debt_frozen_until');
    }
}
