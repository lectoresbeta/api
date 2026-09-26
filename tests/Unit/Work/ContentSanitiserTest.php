<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Unit\Work;

use LectoresBeta\Work\Chapter\Domain\ValueObject\ChapterContent;
use LectoresBeta\Work\Chapter\Infrastructure\Content\WhitelistContentSanitiser;
use PHPUnit\Framework\TestCase;

/**
 * El saneado del contenido (`FEAT-WRK-001` `RN-8`).
 *
 * Tiene dos mitades que tiran en direcciones opuestas, y las dos importan:
 * **no guardar nada que no se estuviera dispuesto a servir**, y **no perder
 * ni una palabra de quien pega cincuenta mil desde Word**.
 */
final class ContentSanitiserTest extends TestCase
{
    public function testTheAllowedMarkupSurvivesIntact(): void
    {
        $content = $this->sanitise('<p>Un <strong>golpe</strong> y una <em>pausa</em>.</p><blockquote>Cita</blockquote><hr>');

        self::assertStringContainsString('<strong>golpe</strong>', $content->html);
        self::assertStringContainsString('<em>pausa</em>', $content->html);
        self::assertStringContainsString('<blockquote>', $content->html);
    }

    public function testScriptsAreRemovedWithTheirContents(): void
    {
        $content = $this->sanitise('<p>Hola</p><script>alert("robo")</script>');

        self::assertStringNotContainsString('script', $content->html);
        self::assertStringNotContainsString('robo', $content->html);
        self::assertStringNotContainsString('robo', $content->text);
    }

    public function testAttributesNeverSurvive(): void
    {
        $content = $this->sanitise('<p class="x" onclick="robar()" style="color:red">Hola</p>');

        self::assertSame('<p>Hola</p>', $content->html);
    }

    /**
     * **El caso que más caro sale si se hace mal.** El comportamiento por
     * defecto del saneador es tirar el elemento *con sus hijos*, así que un
     * pegado desde un procesador de textos —todo `span` y `div`— llegaría
     * vacío mientras la petición responde «creado».
     */
    public function testAPasteFromAWordProcessorKeepsEveryWord(): void
    {
        $content = $this->sanitise(
            '<div class="Section1"><p><span style="font-family:Calibri">Era</span>'
            .'<span> una noche </span><b>oscura</b> y <i>tormentosa</i></p></div>',
        );

        self::assertSame('Era una noche oscura y tormentosa', $content->text);
    }

    /**
     * El texto de un enlace es prosa y se conserva; su destino no, porque no
     * hay enlaces en esta plataforma.
     */
    public function testALinkLosesItsDestinationAndKeepsItsWords(): void
    {
        $content = $this->sanitise('<p>Visita <a href="http://spam.example">mi página</a> ahora</p>');

        self::assertStringNotContainsString('spam.example', $content->html);
        self::assertStringNotContainsString('<a', $content->html);
        self::assertSame('Visita mi página ahora', $content->text);
    }

    public function testImagesDisappearEntirely(): void
    {
        $content = $this->sanitise('<p>Antes<img src="http://x/y.png" alt="tracker">después</p>');

        self::assertStringNotContainsString('img', $content->html);
        self::assertStringNotContainsString('y.png', $content->html);
    }

    /**
     * Sin esto, `<p>uno</p><p>dos</p>` contaría **una** palabra, y de ese
     * número depende el precio de toda corrección (`FEAT-CRD-016`).
     */
    public function testBlocksDoNotGlueWordsTogether(): void
    {
        self::assertSame('uno dos', $this->sanitise('<p>uno</p><p>dos</p>')->text);
        self::assertSame('uno dos', $this->sanitise('uno<br>dos')->text);
        self::assertSame('uno dos', $this->sanitise('<h2>uno</h2><p>dos</p>')->text);
    }

    public function testContentThatIsOnlyForbiddenMarkupComesBackEmpty(): void
    {
        self::assertSame('', $this->sanitise('<img src="x"><br>')->text);
    }

    /**
     * Sin decodificar, `caf&eacute;` contaría como dos palabras en vez de una.
     */
    public function testEntitiesAreDecodedBeforeCounting(): void
    {
        self::assertSame('café a solas', $this->sanitise('<p>caf&eacute; a solas</p>')->text);
    }

    private function sanitise(string $html): ChapterContent
    {
        return (new WhitelistContentSanitiser())->sanitise($html);
    }
}
