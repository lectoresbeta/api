<?php

declare(strict_types=1);

namespace LectoresBeta\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Qué géneros le interesan a cada persona, **como copia de `Community`**
 * (`FEAT-COM-016` `RN-1`).
 *
 * `Community` no consulta la tabla de géneros de `User`: recibe el hecho y
 * mantiene su propia proyección. Es la frontera entre contextos, y esta tabla
 * es lo que cuesta tenerla — sin ella, sugerir autores por afinidad exigiría
 * leer por dentro de otro contexto en cada carga del onboarding.
 */
final class Version20260926120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'FEAT-COM-016: proyección de los géneros de interés en Community';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE community_ctx.member_genre (
                member_id UUID NOT NULL,
                genre_code VARCHAR(32) NOT NULL,
                PRIMARY KEY (member_id, genre_code)
            )
            SQL);
        $this->addSql('CREATE INDEX idx_member_genre_code ON community_ctx.member_genre (genre_code)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE community_ctx.member_genre');
    }
}
