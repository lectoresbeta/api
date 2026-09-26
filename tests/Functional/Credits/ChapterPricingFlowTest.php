<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Credits;

use LectoresBeta\Credits\Pricing\Domain\Repository\ChapterPriceRepository;
use LectoresBeta\Credits\Pricing\Domain\ValueObject\ChapterId;
use LectoresBeta\Tests\Functional\Support\EconomyScenario;

/**
 * El precio de una corrección, de punta a punta (`FEAT-CRD-016`).
 *
 * Lo que este test defiende no es la aritmética —eso ya lo hace
 * `ChapterPricingTest`— sino **la frontera**: `Work` publica cuánto mide un
 * capítulo y cuánto exige un cuestionario, y `Credits` decide por su cuenta
 * lo que eso vale. Ninguna de las dos mitades importa una clase de la otra.
 */
final class ChapterPricingFlowTest extends EconomyScenario
{
    /**
     * Los dos términos de la fórmula, cada uno llegando de un hecho distinto:
     * la longitud del capítulo y lo que el cuestionario exige.
     */
    public function testTheTwoFactsOfAPriceArriveSeparatelyAndMeetInCredits(): void
    {
        $author = $this->activatedPerson('autora');
        $workId = $this->createWork($author['token']);
        $chapterId = $this->addChapter($workId, $author['token'], words: 1200);

        $this->consumeEverything();

        // Todavía sin cuestionario: solo se paga por leer, con el suelo de 2.
        self::assertSame(2, $this->priceOf($chapterId));

        $this->saveQuestionnaire($workId, $author['token'], [
            ['statement' => '¿Cómo funciona el ritmo?', 'minWords' => 100],
            ['statement' => '¿Y el desenlace?', 'minWords' => 150, 'scope' => 'LAST_CHAPTER'],
        ]);

        $this->consumeEverything();

        // 2 de lectura (1.200 palabras) + 3 de escritura (250 exigidas):
        // es el único capítulo, así que responde también la del desenlace.
        self::assertSame(5, $this->priceOf($chapterId));
    }

    /**
     * `W-17` visto desde `Credits`: el autor no paga en el capítulo uno una
     * pregunta sobre el final. Y cuando aparece un capítulo nuevo, el que era
     * último deja de cobrarla.
     */
    public function testANewChapterMovesWhereTheWorkEnds(): void
    {
        $author = $this->activatedPerson('autora');
        $workId = $this->createWork($author['token']);

        $this->saveQuestionnaire($workId, $author['token'], [
            ['statement' => '¿Cómo funciona el ritmo?', 'minWords' => 100],
            ['statement' => '¿Y el desenlace?', 'minWords' => 150, 'scope' => 'LAST_CHAPTER'],
        ]);

        $primero = $this->addChapter($workId, $author['token'], words: 1200);
        $this->consumeEverything();

        self::assertSame(5, $this->priceOf($primero), 'Es el último de la obra: responde a todo.');

        $segundo = $this->addChapter($workId, $author['token'], words: 1200);
        $this->consumeEverything();

        self::assertSame(3, $this->priceOf($primero), '2 de lectura + 1 de escritura: ya no es el último.');
        self::assertSame(5, $this->priceOf($segundo));
    }

    /**
     * La cola no promete entrega única ni orden. Reentregar lo mismo no puede
     * mover un precio, y una versión vieja que llega tarde no puede deshacer
     * lo que el autor ya cambió.
     */
    public function testARedeliveryChangesNothingAndAStaleVersionIsIgnored(): void
    {
        $author = $this->activatedPerson('autora');
        $workId = $this->createWork($author['token']);
        $chapterId = $this->addChapter($workId, $author['token'], words: 1200);

        $primeraVersion = $this->saveQuestionnaire($workId, $author['token'], [['statement' => 'Una', 'minWords' => 100]]);

        $this->saveQuestionnaire($workId, $author['token'], [['statement' => 'Una', 'minWords' => 900]]);

        $this->consumeEverything();

        // 2 de lectura + 9 de escritura.
        self::assertSame(11, $this->priceOf($chapterId));

        // La versión 1, que llega tarde y con menos exigencia.
        $this->deliver($primeraVersion);
        self::assertSame(11, $this->priceOf($chapterId), 'Una versión vieja no rebaja el precio.');

        // Y el mismo hecho otra vez, entero.
        $this->consumeEverything();
        self::assertSame(11, $this->priceOf($chapterId));
    }

    /**
     * Lo que **no** viaja: ni una palabra del manuscrito, ni un importe.
     * `Work` publica un hecho; quien pone precio es `Credits` (`RN-1`).
     */
    public function testTheFactThatCrossesCarriesNoTextAndNoAmount(): void
    {
        $author = $this->activatedPerson('autora');
        $workId = $this->createWork($author['token']);
        $this->addChapter($workId, $author['token'], words: 1200, marker: 'Ryn cruzó el puente');

        $wire = array_values(array_filter(
            $this->queue,
            static fn (array $event): bool => 'ChapterContentUpdated' === ($event['headers']['X-Event-Name'] ?? null),
        ));

        self::assertCount(1, $wire);

        $body = $wire[0]['body'];

        self::assertStringNotContainsString('Ryn', $body);
        self::assertStringNotContainsString('puente', $body);

        /** @var array<string, mixed> $payload */
        $payload = json_decode($body, true, 512, \JSON_THROW_ON_ERROR);

        self::assertSame(1200, $payload['wordCount']);
        self::assertSame(1, $payload['position']);
        self::assertArrayNotHasKey('price', $payload);
        self::assertArrayNotHasKey('credits', $payload);
    }

    private function priceOf(string $chapterId): ?int
    {
        /** @var ChapterPriceRepository $prices */
        $prices = self::getContainer()->get(ChapterPriceRepository::class);

        return $prices->ofChapter(ChapterId::fromString($chapterId))?->price();
    }
}
