<?php

declare(strict_types=1);

namespace LectoresBeta\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * La copia del saldo que `User` mantiene para pintar el menú lateral
 * (`FEAT-USR-027`).
 *
 * Existe porque **`Credits` no publica contratos**, ni siquiera de consulta
 * ([`decision:0002`](../docs/decisions/0002-credits-as-isolated-bounded-context.md),
 * `AGENTS.md`). Una excepción «solo para leer» abriría la misma puerta que
 * mañana alguien usa para otra cosa, así que se hace lo que el proyecto ya
 * hace con el grafo de seguidores y con los bloqueos: escuchar el hecho y
 * quedarse con lo justo.
 *
 * Una fila por cuenta, con un número. No es un historial: el historial es de
 * `Credits` y no sale de ahí.
 */
final class Version20260925090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Projects the credit balance into user_ctx so the layout can be painted in one request.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE user_ctx.known_credit_balance (
              user_id UUID NOT NULL,
              balance INT NOT NULL,
              known_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY (user_id)
            )
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE user_ctx.known_credit_balance');
    }
}
