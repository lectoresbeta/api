<?php

declare(strict_types=1);

namespace LectoresBeta\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Los premios y reconocimientos del autor (`FEAT-USR-030`).
 *
 * La otra sub-pestaña de «Más info», hermana de `published_book`. Sin
 * `cover_url` y **sin `position`**: un premio no tiene portada, y el orden lo
 * pone el año descendente en lugar del autor (`RN-8`).
 *
 * El índice lleva `year` y `created_at` porque son exactamente el orden en el
 * que se lee la lista, y es la única consulta que existe sobre esta tabla.
 *
 * Sin clave ajena hacia la cuenta, como el resto del esquema: la baja de una
 * cuenta la anonimiza cada contexto por su lado, y un borrado en cascada se
 * llevaría por delante ese trabajo.
 */
final class Version20260928010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Awards and recognitions declared on an author page.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE user_ctx.award (
              id UUID NOT NULL,
              user_id UUID NOT NULL,
              title VARCHAR(180) NOT NULL,
              awarded_by VARCHAR(180) DEFAULT NULL,
              year SMALLINT DEFAULT NULL,
              note VARCHAR(280) DEFAULT NULL,
              url VARCHAR(512) DEFAULT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_award_user ON user_ctx.award (user_id, year, created_at)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE user_ctx.award');
    }
}
