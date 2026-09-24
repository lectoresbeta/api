<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Credits;

use LectoresBeta\Feedback\Correction\Domain\Event\CorrectionDraftDiscarded;
use LectoresBeta\Feedback\Correction\Domain\Event\CorrectionStarted;
use LectoresBeta\Feedback\Correction\Domain\Event\FeedbackSubmitted;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\AuthorId;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\ChapterId;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\CorrectionId;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\ReaderId;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\WorkId;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Tests\Functional\Support\EconomyScenario;

/**
 * La economía entera, de punta a punta (`FEAT-CRD-006`, `FEAT-CRD-009`).
 *
 * Registro → activación → obra → cuestionario → corrección empezada →
 * corrección entregada → los dos saldos se mueven, y la persona lo ve por la
 * API con su propia sesión.
 *
 * Los hechos se fabrican aquí **con las clases del contexto que los publica**
 * y viajan por el serializador real, en vez de recorrer la interfaz entera:
 * lo que se prueba es la economía, no el panel de corrección, que tiene sus
 * propias pruebas. Lo que comparten los dos lados sigue siendo solo el nombre
 * del hecho y la forma de su payload.
 */
final class CorrectionEconomyTest extends EconomyScenario
{
    /**
     * **Una corrección mueve créditos; no los crea.** Es la invariante
     * contable del contexto, y aquí se comprueba sobre la base de datos real.
     */
    public function testDeliveringACorrectionMovesTheSameFigureFromOnePersonToTheOther(): void
    {
        [$author, $reader, $chapterId, $workId] = $this->aWorkReadyToBeCorrected();

        $this->consumeEverything();

        self::assertSame(10, $this->balanceOf($author['userId']), 'Los diez de bienvenida.');
        self::assertSame(10, $this->balanceOf($reader['userId']));

        $this->correctionStarted($chapterId, $workId, $author['userId'], $reader['userId']);
        $this->consumeEverything();

        // Anotar el precio no mueve un solo crédito (`FEAT-CRD-009` `RN-3`).
        self::assertSame(10, $this->balanceOf($author['userId']));

        $this->feedbackSubmitted($chapterId, $workId, $author['userId'], $reader['userId']);
        $this->consumeEverything();

        // 1.200 palabras y 100 exigidas: 2 + 1.
        self::assertSame(7, $this->balanceOf($author['userId']));
        self::assertSame(13, $this->balanceOf($reader['userId']));

        self::assertSame(20, $this->balanceOf($author['userId']) + $this->balanceOf($reader['userId']));

        // Y el lector lo ve con su sesión, que es como se vive de verdad.
        self::assertSame(['balance' => 13], $this->balanceAsSeenBy($reader['token']));
    }

    /**
     * **La regla que sostiene el producto entero**: un lector nunca trabaja
     * sin cobrar. Lo que antes garantizaba una reserva lo garantiza ahora el
     * saldo negativo, sin apartar un solo crédito.
     */
    public function testTheReaderIsPaidEvenWhenTheAuthorCannotAffordIt(): void
    {
        // Un capítulo enorme: 20 créditos, el tope, y la autora tiene 10.
        [$author, $reader, $chapterId, $workId] = $this->aWorkReadyToBeCorrected(words: 40_000, minWords: 2000);

        $this->consumeEverything();
        $this->correctionStarted($chapterId, $workId, $author['userId'], $reader['userId']);
        $this->feedbackSubmitted($chapterId, $workId, $author['userId'], $reader['userId']);
        $this->consumeEverything();

        self::assertSame(-10, $this->balanceOf($author['userId']));
        self::assertSame(30, $this->balanceOf($reader['userId']));

        $crossings = $this->announced('CreditBalanceWentNegative');
        self::assertCount(1, $crossings);
        self::assertSame($author['userId'], $crossings[0]['userId']);
    }

    /**
     * `RN-1`: se cobra lo anotado al empezar. Quien acepta un trabajo con
     * unas condiciones las conserva, aunque el autor amplíe el capítulo
     * mientras tanto.
     */
    public function testAnEnlargedChapterDoesNotChangeWhatTheReaderWasPromised(): void
    {
        [$author, $reader, $chapterId, $workId] = $this->aWorkReadyToBeCorrected();

        $this->consumeEverything();
        $this->correctionStarted($chapterId, $workId, $author['userId'], $reader['userId']);
        $this->consumeEverything();

        // La autora sigue escribiendo: un capítulo más, y mucho más exigente.
        $this->saveQuestionnaire($workId, $author['token'], [['statement' => 'Mucho más', 'minWords' => 900]]);
        $this->consumeEverything();

        $this->feedbackSubmitted($chapterId, $workId, $author['userId'], $reader['userId']);
        $this->consumeEverything();

        self::assertSame(13, $this->balanceOf($reader['userId']), 'Cobra los 3 que se le prometieron.');
    }

    /**
     * Un capítulo deja de admitir correcciones cuando su autora no puede
     * pagarlo, y el aviso que sale del contexto **es un booleano**: nadie
     * fuera de `Credits` conoce un saldo ni un precio.
     */
    public function testTheOutsideWorldLearnsWhetherAChapterCanBeCorrectedAndNothingElse(): void
    {
        [$author, $reader, $chapterId, $workId] = $this->aWorkReadyToBeCorrected();

        $this->consumeEverything();

        $last = $this->lastAnnouncementOf('ChapterCorrectabilityChanged');
        self::assertTrue($last['correctable']);

        // Ni un saldo ni un precio: la conclusión, y cuánto trabajo produce
        // enseñar esta obra, con tope de diez (`decision:0008`).
        self::assertSame(['chapterId', 'workId', 'correctable', 'affordableCorrections', 'changedAt'], array_keys($last));
        self::assertSame(3, $last['affordableCorrections'], 'Diez créditos y un capítulo de tres.');

        // La autora recibe una corrección de 3 y se queda con 7, que todavía
        // llega; hace falta algo más caro para que deje de llegar.
        $this->correctionStarted($chapterId, $workId, $author['userId'], $reader['userId']);
        $this->feedbackSubmitted($chapterId, $workId, $author['userId'], $reader['userId']);
        $this->saveQuestionnaire($workId, $author['token'], [['statement' => 'Mucho más', 'minWords' => 900]]);
        $this->consumeEverything();

        // Ahora el capítulo vale 11 y a la autora le quedan 7.
        self::assertFalse($this->lastAnnouncementOf('ChapterCorrectabilityChanged')['correctable']);
    }

    /**
     * Descartar el borrador no libera nada, porque nada se había apartado.
     * Lo que sí hace es devolver el hueco: un capítulo admite tres a la vez.
     */
    public function testDiscardingADraftReleasesNothingBecauseNothingWasHeld(): void
    {
        [$author, $reader, $chapterId, $workId] = $this->aWorkReadyToBeCorrected();

        $this->consumeEverything();
        $this->correctionStarted($chapterId, $workId, $author['userId'], $reader['userId']);
        $this->consumeEverything();

        self::assertSame(10, $this->balanceOf($author['userId']));

        $this->enqueue(new CorrectionDraftDiscarded(
            EventId::generate(),
            ChapterId::fromString($chapterId),
            WorkId::fromString($workId),
            ReaderId::fromString($reader['userId']),
            new \DateTimeImmutable(),
        ));
        $this->consumeEverything();

        self::assertSame(10, $this->balanceOf($author['userId']), 'Nunca se le quitó nada.');

        // Y la entrega posterior de una corrección descartada no cobra por
        // una anotación que ya no existe: se reconstruye el precio vigente.
        $this->feedbackSubmitted($chapterId, $workId, $author['userId'], $reader['userId']);
        $this->consumeEverything();

        self::assertSame(7, $this->balanceOf($author['userId']));
    }

    /**
     * Una reentrega del hecho más importante del sistema no puede cobrar dos
     * veces. RabbitMQ no promete entrega única, y un crédito cobrado dos
     * veces no se repara del todo nunca.
     */
    public function testARedeliveredDeliveryChargesOnce(): void
    {
        [$author, $reader, $chapterId, $workId] = $this->aWorkReadyToBeCorrected();

        $this->consumeEverything();
        $this->correctionStarted($chapterId, $workId, $author['userId'], $reader['userId']);
        $this->feedbackSubmitted($chapterId, $workId, $author['userId'], $reader['userId']);

        $this->consumeEverything();
        $this->consumeEverything();
        $this->consumeEverything();

        self::assertSame(7, $this->balanceOf($author['userId']));
        self::assertSame(13, $this->balanceOf($reader['userId']));
    }

    /**
     * @return array{0: array{token: string, userId: string}, 1: array{token: string, userId: string}, 2: string, 3: string}
     */
    private function aWorkReadyToBeCorrected(int $words = 1200, int $minWords = 100): array
    {
        $author = $this->activatedPerson('autora');
        $reader = $this->activatedPerson('lectora');

        $workId = $this->createWork($author['token']);
        $chapterId = $this->addChapter($workId, $author['token'], words: $words);
        $this->saveQuestionnaire($workId, $author['token'], [
            ['statement' => '¿Cómo funciona el ritmo?', 'minWords' => $minWords],
        ]);

        return [$author, $reader, $chapterId, $workId];
    }

    private function correctionStarted(string $chapterId, string $workId, string $authorId, string $readerId): void
    {
        $this->enqueue(new CorrectionStarted(
            EventId::generate(),
            ChapterId::fromString($chapterId),
            WorkId::fromString($workId),
            AuthorId::fromString($authorId),
            ReaderId::fromString($readerId),
            new \DateTimeImmutable(),
        ));
    }

    private function feedbackSubmitted(string $chapterId, string $workId, string $authorId, string $readerId): void
    {
        $this->enqueue(new FeedbackSubmitted(
            EventId::generate(),
            CorrectionId::generate(),
            ChapterId::fromString($chapterId),
            WorkId::fromString($workId),
            AuthorId::fromString($authorId),
            ReaderId::fromString($readerId),
            1,
            new \DateTimeImmutable(),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function balanceAsSeenBy(string $token): array
    {
        $this->client->request('GET', '/api/v1/credits/balance', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);

        self::assertResponseIsSuccessful();

        return $this->payload();
    }
}
