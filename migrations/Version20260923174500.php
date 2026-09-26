<?php

declare(strict_types=1);

namespace LectoresBeta\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * El catálogo de géneros (`FEAT-USR-023`, `OB-14`).
 *
 * Son datos de referencia, no datos de negocio: la aplicación no funciona sin
 * ellos, y por eso van en una migración y no en una carga aparte que alguien
 * tiene que acordarse de ejecutar.
 *
 * Dieciocho, ni seis ni cincuenta. Con seis todo el mundo elige «ficción» y el
 * filtro no sirve; con cincuenta nadie los lee y se eligen los tres primeros.
 * La lista mezcla géneros y formas —«poesía», «teatro», «relato corto»— porque
 * es como los autores describen lo que escriben.
 *
 * `active` permite retirar uno sin borrarlo: hay obras y personas apuntando al
 * código, y esas referencias tienen que seguir resolviendo.
 */
final class Version20260923174500 extends AbstractMigration
{
    /**
     * @var list<array{string, string}>
     */
    private const GENRES = [
        ['LITERARY_FICTION', 'Ficción literaria'],
        ['CRIME', 'Novela negra y policíaca'],
        ['THRILLER', 'Thriller y suspense'],
        ['SCIENCE_FICTION', 'Ciencia ficción'],
        ['FANTASY', 'Fantasía'],
        ['HORROR', 'Terror'],
        ['ROMANCE', 'Romántica'],
        ['HISTORICAL', 'Histórica'],
        ['ADVENTURE', 'Aventuras'],
        ['YOUNG_ADULT', 'Juvenil'],
        ['CHILDRENS', 'Infantil'],
        ['SHORT_STORY', 'Relato corto'],
        ['POETRY', 'Poesía'],
        ['DRAMA', 'Teatro'],
        ['ESSAY', 'Ensayo'],
        ['MEMOIR', 'Biografía y memorias'],
        ['NARRATIVE_NONFICTION', 'Crónica y no ficción narrativa'],
        ['HUMOUR', 'Humor'],
    ];

    public function getDescription(): string
    {
        return 'Catálogo de géneros: los dieciocho de FEAT-USR-023.';
    }

    public function up(Schema $schema): void
    {
        foreach (self::GENRES as $position => [$code, $name]) {
            $this->addSql(
                'INSERT INTO user_ctx.genre (code, name, position, active) VALUES (?, ?, ?, true)',
                [$code, $name, $position + 1],
            );
        }
    }

    public function down(Schema $schema): void
    {
        $codes = array_column(self::GENRES, 0);
        $placeholders = implode(', ', array_fill(0, \count($codes), '?'));

        $this->addSql("DELETE FROM user_ctx.genre WHERE code IN ({$placeholders})", $codes);
    }
}
