<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Unit\Work;

use LectoresBeta\Work\Ingest\Domain\Service\ChapterSplitter;
use LectoresBeta\Work\Ingest\Domain\ValueObject\DocumentBlock;
use LectoresBeta\Work\Ingest\Domain\ValueObject\ExtractedDocument;
use LectoresBeta\Work\Ingest\Domain\ValueObject\ProposedChapter;
use PHPUnit\Framework\TestCase;

/**
 * Dónde se propone que acabe cada capítulo (`FEAT-WRK-002`, `W-4`).
 *
 * Es una propuesta y no una decisión —el autor la revisa antes de que se cree
 * nada—, así que puede permitirse ser simple. Lo que **no** puede permitirse
 * es perder texto: eso es lo que comprueba la mitad de estas pruebas.
 */
final class ChapterSplitterTest extends TestCase
{
    /**
     * Corta por los títulos que el autor ya había puesto, y solo por ellos.
     */
    public function testItCutsAtTheHeadingsTheAuthorWrote(): void
    {
        $chapters = $this->split([
            ['Capítulo 1', true],
            ['Todo empezó una mañana.', false],
            ['Capítulo 2', true],
            ['Y siguió por la tarde.', false],
        ]);

        self::assertCount(2, $chapters);
        self::assertSame('Capítulo 1', $chapters[0]->title);
        self::assertSame('Todo empezó una mañana.', $chapters[0]->text());
        self::assertSame('Capítulo 2', $chapters[1]->title);
    }

    /**
     * **Sin títulos, un solo capítulo con todo dentro.** Es la respuesta
     * honesta a un `.txt`: no se sabe dónde corta, así que no se corta.
     */
    public function testWithoutHeadingsItProposesOneChapter(): void
    {
        $chapters = $this->split([
            ['Primer párrafo.', false],
            ['Segundo párrafo.', false],
        ]);

        self::assertCount(1, $chapters);
        self::assertNull($chapters[0]->title);
        self::assertSame("Primer párrafo.\nSegundo párrafo.", $chapters[0]->text());
    }

    /**
     * **Lo que hay antes del primer título no se tira.** Suele ser un
     * prólogo, y perder texto de alguien porque no encaja en el modelo es el
     * peor error posible aquí.
     */
    public function testTextBeforeTheFirstHeadingBecomesTheFirstChapter(): void
    {
        $chapters = $this->split([
            ['Para quien ya no está.', false],
            ['Capítulo 1', true],
            ['Todo empezó una mañana.', false],
        ]);

        self::assertCount(2, $chapters);
        self::assertNull($chapters[0]->title, 'Un prólogo sin título sigue siendo un capítulo.');
        self::assertSame('Para quien ya no está.', $chapters[0]->text());
        self::assertSame('Capítulo 1', $chapters[1]->title);
    }

    /**
     * Un título **sin nada debajo** no abre capítulo. Pasa con una portada o
     * una dedicatoria marcadas como título, y crear un capítulo vacío por
     * cada una dejaría al autor borrando basura antes de empezar.
     */
    public function testAHeadingWithNothingUnderItOpensNoChapter(): void
    {
        $chapters = $this->split([
            ['La ciudad de los pájaros', true],
            ['Una novela', true],
            ['Capítulo 1', true],
            ['Todo empezó una mañana.', false],
        ]);

        self::assertCount(1, $chapters);
        self::assertSame('Capítulo 1', $chapters[0]->title, 'Gana el último título seguido.');
    }

    /**
     * Un documento que es **solo títulos** se devuelve entero como un
     * capítulo: el texto es del autor y no se tira nada.
     */
    public function testADocumentOfNothingButHeadingsKeepsAllItsText(): void
    {
        $chapters = $this->split([
            ['Uno', true],
            ['Dos', true],
        ]);

        self::assertCount(1, $chapters);
        self::assertSame("Uno\nDos", $chapters[0]->text());
    }

    /**
     * El extracto es para reconocer el capítulo en la pantalla de revisión,
     * no para leerlo.
     */
    public function testThePreviewIsShortAndOnOneLine(): void
    {
        $chapters = $this->split([['Una   frase    con espacios de más.', false]]);

        self::assertSame('Una frase con…', $chapters[0]->preview(14));
        self::assertSame('Una frase con espacios de más.', $chapters[0]->preview(500), 'Si cabe, entera y sin puntos.');
    }

    /**
     * @param list<array{0: string, 1: bool}> $blocks
     *
     * @return list<ProposedChapter>
     */
    private function split(array $blocks): array
    {
        return (new ChapterSplitter())->split(ExtractedDocument::of(array_map(
            static fn (array $block): DocumentBlock => new DocumentBlock($block[0], $block[1]),
            $blocks,
        )));
    }
}
