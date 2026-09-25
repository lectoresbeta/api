<?php

declare(strict_types=1);

namespace LectoresBeta\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * La cuenta con la que habla la plataforma (`FEAT-COM-038`).
 *
 * Una columna y ninguna tabla, porque es **una cuenta normal con una marca**.
 * La alternativa —una entidad `Announcement` aparte— habría obligado a
 * duplicar comentarios, me gusta y reposts, o a renunciar a ellos.
 *
 * Sin índice único: que solo haya una la decide quien opera, no la base de
 * datos. Un índice único aquí impediría el relevo
 * ordenado —marcar la nueva antes de desmarcar la vieja— por una restricción
 * que no protege nada, ya que dos cuentas institucionales no rompen ninguna
 * invariante: publicarían las dos.
 */
final class Version20260927050000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Marks an ordinary account as the one the platform speaks with.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE user_ctx.account
              ADD institutional BOOLEAN DEFAULT FALSE NOT NULL
        SQL);
        // El valor por defecto existe solo para rellenar las filas que ya
        // había: se retira después para que el esquema coincida con el mapeo,
        // que no declara ninguno. Quien inserta una cuenta es el modelo, y el
        // modelo ya dice que no es institucional.
        $this->addSql('ALTER TABLE user_ctx.account ALTER institutional DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_ctx.account DROP institutional');
    }
}
