<?php

declare(strict_types=1);

namespace LectoresBeta\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Las referencias de la página de autor (`FEAT-USR-015`).
 *
 * **La página de autor es el perfil que ya existe** (`P-5`, resuelta): el
 * nombre, la biografía, la foto y la portada viven en la cuenta, y las obras
 * publicadas en su propia tabla. Lo único que faltaba eran las referencias, y
 * es lo único que esta migración añade.
 *
 * Filas y no una columna JSON en la cuenta: se leen en lista, se ordenan como
 * su dueño quiere, y una de ellas puede tener que retirarse sola el día que
 * una moderación lo pida. Una columna JSON obliga a reescribirlo todo para
 * quitar una.
 *
 * Sin clave ajena hacia la cuenta por la misma razón que el resto del
 * esquema: la baja de una cuenta la anonimiza cada contexto por su lado, y un
 * borrado en cascada se llevaría por delante ese trabajo.
 */
final class Version20260927060000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'External references shown on an author page.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE user_ctx.author_link (
              id UUID NOT NULL,
              user_id UUID NOT NULL,
              label VARCHAR(60) NOT NULL,
              url VARCHAR(512) NOT NULL,
              position INT NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_author_link_owner ON user_ctx.author_link (user_id, position)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE user_ctx.author_link');
    }
}
