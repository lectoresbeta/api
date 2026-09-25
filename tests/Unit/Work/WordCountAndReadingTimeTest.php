<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Unit\Work;

use LectoresBeta\Work\Chapter\Domain\Service\ReadingTime;
use LectoresBeta\Work\Chapter\Domain\Service\WordCounter;
use LectoresBeta\Work\Chapter\Infrastructure\Content\WhitelistContentSanitiser;
use PHPUnit\Framework\TestCase;

/**
 * El recuento de palabras y el tiempo de lectura (`FEAT-WRK-013`).
 *
 * **El recuento no es un adorno del catálogo: es el término de lectura del
 * precio de toda corrección** (`decision:0006`), así que de esta cifra
 * depende dinero. Por eso las pruebas van sobre el camino real —saneador
 * incluido— y no sobre el contador a solas: lo que se cuenta tiene que ser lo
 * que se guarda.
 */
final class WordCountAndReadingTimeTest extends TestCase
{
    /**
     * `RN-2`, y es el caso que la forma perezosa de quitar marcado falla:
     * `strip_tags` a secas pega la última palabra de un párrafo con la
     * primera del siguiente. En un capítulo de cuarenta párrafos son cuarenta
     * palabras menos, y eso en el precio se nota.
     */
    public function testBlockTagsSeparateWords(): void
    {
        self::assertSame(2, $this->wordsIn('<p>uno</p><p>dos</p>'));
        self::assertSame(2, $this->wordsIn('<p>uno<br>dos</p>'));
        self::assertSame(3, $this->wordsIn('<h2>uno</h2><p>dos tres</p>'));
    }

    /**
     * `RN-3`: una palabra es lo que separa un espacio. Sin diccionario y sin
     * excepciones, porque el criterio es **lo que el lector contaría**.
     */
    public function testAWordIsWhateverSpacesSeparate(): void
    {
        self::assertSame(1, $this->wordsIn('<p>bien-parecido</p>'), 'Partirla contaría dos donde se ve una.');
        self::assertSame(3, $this->wordsIn('<p>—Hola, dijo ella</p>'), 'La raya va pegada y no es una palabra.');
        self::assertSame(1, $this->wordsIn('<p>1.500</p>'));
        self::assertSame(2, $this->wordsIn('<p>puntos... suspensivos</p>'));
    }

    /**
     * El marcado no cuenta. Poner una palabra en negrita no puede cambiar lo
     * que cuesta corregir el capítulo.
     */
    public function testMarkupNeverCounts(): void
    {
        $plano = $this->wordsIn('<p>una frase corta de prueba</p>');
        $adornado = $this->wordsIn('<p>una <strong>frase</strong> <em>corta</em> de prueba</p>');

        self::assertSame($plano, $adornado);
    }

    public function testAnEmptyTextIsZeroWordsAndZeroMinutes(): void
    {
        self::assertSame(0, $this->wordsIn(''));
        self::assertSame(0, $this->wordsIn('<p></p>'));
        self::assertSame(0, ReadingTime::minutesFor(0));
    }

    /**
     * `RN-5`: doscientas palabras por minuto, redondeando hacia arriba.
     *
     * El mínimo de un minuto no es cosmético: «0 min lectura» no informa de
     * nada.
     */
    public function testReadingTimeRoundsUpAndNeverSaysZeroForATextThatExists(): void
    {
        self::assertSame(5, ReadingTime::minutesFor(1000));
        self::assertSame(1, ReadingTime::minutesFor(1), 'Una palabra es un minuto, no cero.');
        self::assertSame(1, ReadingTime::minutesFor(200));
        self::assertSame(2, ReadingTime::minutesFor(201), 'Hacia arriba.');
    }

    private function wordsIn(string $html): int
    {
        return (new WordCounter())->count((new WhitelistContentSanitiser())->sanitise($html)->text);
    }
}
