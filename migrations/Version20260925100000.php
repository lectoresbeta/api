<?php

declare(strict_types=1);

namespace LectoresBeta\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Las condiciones y la política que rigen hoy (`FEAT-USR-024`).
 *
 * La tabla existía vacía, y eso hacía imposible cumplir `RN-2`: comprobar que
 * alguien acepta **la versión vigente** no se puede hacer si no hay ninguna
 * publicada. Sin la comprobación, lo que se guardaba era una versión
 * cualquiera enviada por el cliente — una constancia que parece una prueba y
 * no lo es.
 *
 * Se siembran aquí y no desde un fixture porque **el alta no funciona sin
 * ellas**: es un dato del que depende el arranque, no de ejemplo.
 *
 * Publicar una versión nueva es insertar una fila con su `effective_from`, y
 * puede hacerse por adelantado: lo vigente es lo de mayor fecha **ya
 * cumplida**, así que la política del mes que viene no cambia lo que se
 * acepta hoy.
 */
final class Version20260925100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seeds the legal documents in force, without which no account can be created.';
    }

    public function up(Schema $schema): void
    {
        foreach ([
            ['TERMS_OF_USE', 'condiciones-de-uso'],
            ['PRIVACY_POLICY', 'politica-de-privacidad'],
            ['COOKIE_POLICY', 'politica-de-cookies'],
            ['LEGAL_NOTICE', 'aviso-legal'],
        ] as [$type, $slug]) {
            $this->addSql(
                <<<'SQL'
                    INSERT INTO user_ctx.legal_document (id, type, version, effective_from, url)
                    VALUES (:id, :type, '2026-01-15', '2026-01-15 00:00:00', :url)
                    ON CONFLICT (type, version) DO NOTHING
                    SQL,
                [
                    'id' => \sprintf('%s-0000-4000-8000-%012d', substr(md5($type), 0, 8), crc32($type) % 1_000_000),
                    'type' => $type,
                    'url' => \sprintf('https://lectoresbeta.com/legal/%s', $slug),
                ],
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM user_ctx.legal_document WHERE version = '2026-01-15'");
    }
}
