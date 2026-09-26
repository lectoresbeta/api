<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Credits;

use LectoresBeta\Credits\Pricing\Domain\Repository\ChapterPriceRepository;
use LectoresBeta\Credits\Pricing\Domain\ValueObject\ChapterId as PricedChapterId;
use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use Symfony\Component\HttpFoundation\Response;

/**
 * La propina del autor (`FEAT-CRD-017`).
 *
 * **Lo que hay que demostrar aquí es la masa constante**: lo que sale de una
 * cuenta entra en la otra, exactamente. Es lo que separa esta propina de la
 * bonificación automática que sustituye, y es lo que hace que dos cuentas que
 * se valoran mutuamente no ganen nada.
 *
 * Lo segundo es que **ser generoso no endeuda** (`RN-1`). El descubierto de
 * `FEAT-CRD-018` existe para que un lector nunca trabaje sin cobrar; usarlo
 * para regalar sería una trampa, y por eso la propina se rechaza en vez de
 * pasar de cero.
 */
final class TipCorrectionTest extends EconomyScenario
{
    /**
     * El caso entero, ida y vuelta: la autora paga de lo suyo, la lectora
     * recibe lo mismo, y el sistema no crea ni destruye un solo crédito.
     */
    public function testTheAuthorTipsAndTheReaderReceivesExactlyThat(): void
    {
        [$autora, $lectora, $correctionId] = $this->aDeliveredCorrection();

        $antesAutora = (int) $this->balanceOf($autora['userId']);
        $antesLectora = (int) $this->balanceOf($lectora['userId']);

        $this->tip($correctionId, $autora['token'], 3);

        self::assertResponseIsSuccessful();
        self::assertSame($antesAutora - 3, $this->payload()['balance'], 'Devuelve lo que le queda, sin pedirlo aparte.');
        $this->capture();
        $this->consumeEverything();

        self::assertSame($antesAutora - 3, (int) $this->balanceOf($autora['userId']));
        self::assertSame($antesLectora + 3, (int) $this->balanceOf($lectora['userId']), 'El importe exacto, sin comisión.');

        $sumaAntes = $antesAutora + $antesLectora;
        $sumaDespues = (int) $this->balanceOf($autora['userId']) + (int) $this->balanceOf($lectora['userId']);
        self::assertSame($sumaAntes, $sumaDespues, 'Masa constante: la propina mueve créditos, no los crea.');

        $hecho = $this->lastAnnouncementOf('CorrectionTipped');
        self::assertSame($correctionId, $hecho['correctionId']);
        self::assertSame(3, $hecho['amount']);
        self::assertSame($lectora['userId'], $hecho['readerId']);

        $abono = $this->tipsReceivedBy($lectora['token']);
        self::assertCount(1, $abono);
        self::assertSame(3, $abono[0]['amount']);
        self::assertSame($correctionId, $abono[0]['correctionId'], 'El abono dice de qué corrección viene.');
    }

    /**
     * `RN-3c`: la lectora ve que su corrección fue propinada. El importe no
     * se publica corrección a corrección —eso expondría quién recibe
     * reconocimiento y quién no—, así que lo que cruza es el hecho.
     */
    public function testTheReaderSeesThatHerCorrectionWasTipped(): void
    {
        [$autora, $lectora, $correctionId] = $this->aDeliveredCorrection();

        self::assertFalse($this->myCorrection($lectora['token'], $correctionId)['tipped']);

        $this->tip($correctionId, $autora['token'], 2);
        self::assertResponseIsSuccessful();
        $this->capture();
        $this->consumeEverything();

        self::assertTrue($this->myCorrection($lectora['token'], $correctionId)['tipped']);
    }

    /**
     * `RN-1`, la regla que más importa de la ficha: **la propina sale del
     * disponible y nunca del descubierto**.
     *
     * La autora está en rojo por haber recibido más correcciones de las que
     * podía pagar, que es el camino real al saldo negativo. Que ahí no pueda
     * regalar es lo que impide convertir la deuda en un grifo.
     */
    public function testWithoutAvailableCreditsTheTipIsRefusedAndNothingGoesFurtherIntoDebt(): void
    {
        [$autora, $primera, $correctionId] = $this->anAuthorInTheRed();

        $antesAutora = (int) $this->balanceOf($autora['userId']);
        $antesLectora = (int) $this->balanceOf($primera['userId']);
        self::assertLessThan(0, $antesAutora, 'El escenario tiene que dejarla en rojo para probar nada.');

        $this->tip($correctionId, $autora['token'], 1);

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame('INSUFFICIENT_CREDITS', $this->payload()['code']);
        self::assertSame($antesAutora, $this->payload()['balance'], 'Le dice cuánto tiene, que es lo que necesita saber.');

        $this->consumeEverything();

        self::assertSame($antesAutora, (int) $this->balanceOf($autora['userId']), 'La deuda no crece por intentarlo.');
        self::assertSame($antesLectora, (int) $this->balanceOf($primera['userId']));
    }

    /**
     * `RN-4`: una por corrección. Y `RN-5`: es irrevocable, así que volver a
     * pulsar no puede significar «otra vez».
     */
    public function testTheSameCorrectionCannotBeTippedTwice(): void
    {
        [$autora, $lectora, $correctionId] = $this->aDeliveredCorrection();

        $this->tip($correctionId, $autora['token'], 1);
        self::assertResponseIsSuccessful();
        $this->capture();
        $this->consumeEverything();

        $despues = (int) $this->balanceOf($autora['userId']);

        $this->tip($correctionId, $autora['token'], 1);

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame('ALREADY_TIPPED', $this->payload()['code']);
        self::assertSame($despues, (int) $this->balanceOf($autora['userId']));
        self::assertCount(1, $this->tipsReceivedBy($lectora['token']), 'Un solo abono, no dos.');
    }

    /**
     * Un reintento de red **no es un segundo gesto**. Es la única diferencia
     * que la llave introduce: sin ella las dos peticiones son idénticas y la
     * segunda se rechaza, que es la lectura prudente.
     */
    public function testARetryWithTheSameKeyMovesNothingAndAnswersTheBalance(): void
    {
        [$autora, $lectora, $correctionId] = $this->aDeliveredCorrection();

        $this->tip($correctionId, $autora['token'], 2, 'una-llave');
        self::assertResponseIsSuccessful();
        $saldo = $this->payload()['balance'];
        $this->capture();
        $this->consumeEverything();

        $this->tip($correctionId, $autora['token'], 2, 'una-llave');

        self::assertResponseIsSuccessful();
        self::assertSame($saldo, $this->payload()['balance']);
        self::assertCount(1, $this->tipsReceivedBy($lectora['token']), 'Un solo movimiento.');

        // Otra llave es otra intención, y una corrección solo admite una.
        $this->tip($correctionId, $autora['token'], 2, 'otra-llave');
        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame('ALREADY_TIPPED', $this->payload()['code']);
    }

    /**
     * `RN-2`: solo se propina lo propio.
     *
     * La respuesta es `CORRECTION_NOT_FOUND` y no «no es tuya»: este contexto
     * sabe quién pagó cada corrección, y distinguir las dos cosas diría algo
     * sobre las correcciones de otra persona.
     */
    public function testACorrectionThatIsNotYoursCannotBeTipped(): void
    {
        [, $lectora, $correctionId] = $this->aDeliveredCorrection();
        $ajena = $this->activatedPerson('ajena');

        foreach ([$lectora['token'], $ajena['token']] as $token) {
            $this->tip($correctionId, $token, 1);

            self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
            self::assertSame('CORRECTION_NOT_FOUND', $this->payload()['code']);
        }

        // Y una que no existe se contesta igual.
        $this->tip('11111111-1111-4111-8111-111111111111', $ajena['token'], 1);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * `RN-6`: una corrección por enlace público **no tiene cuenta a la que
     * abonar**, porque quien la escribió no tiene cuenta.
     *
     * Se rechaza sin ninguna regla que la nombre: `Credits` no tiene nada
     * apuntado sobre ella —no costó nada y no pagó a nadie—, así que responde
     * lo mismo que a una corrección ajena.
     */
    public function testAPublicLinkCorrectionCannotBeTipped(): void
    {
        [$autora, , , $workId, $chapterId] = $this->aDeliveredCorrection();

        $porEnlace = $this->aPublicLinkCorrectionOn($workId, $chapterId, $autora['token']);
        $antes = (int) $this->balanceOf($autora['userId']);

        $this->tip($porEnlace, $autora['token'], 1);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertSame('CORRECTION_NOT_FOUND', $this->payload()['code']);
        self::assertSame($antes, (int) $this->balanceOf($autora['userId']));
    }

    /**
     * `RN-3`: de uno a cinco. Por encima la propina se parece al precio de
     * una corrección y deja de leerse como un extra.
     */
    public function testTheAmountHasToBeWithinRange(): void
    {
        [$autora, , $correctionId] = $this->aDeliveredCorrection();

        foreach ([0, -1, 6, 100] as $importe) {
            $this->tip($correctionId, $autora['token'], $importe);

            self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY, \sprintf('%d no es una propina.', $importe));
            self::assertSame('TIP_OUT_OF_RANGE', $this->payload()['code']);
            self::assertSame(1, $this->payload()['minimumTip']);
            self::assertSame(5, $this->payload()['maximumTip']);
        }
    }

    /**
     * `RN-7`: la propina **no influye en el precio** de ninguna corrección
     * futura ni en ninguna fórmula. Es un regalo, no una tarifa.
     */
    public function testTheTipDoesNotChangeWhatAnythingCosts(): void
    {
        [$autora, , $correctionId, , $chapterId] = $this->aDeliveredCorrection();

        $precio = $this->priceOf($chapterId);
        self::assertNotNull($precio);

        $this->tip($correctionId, $autora['token'], 5);
        self::assertResponseIsSuccessful();
        $this->capture();
        $this->consumeEverything();

        self::assertSame($precio, $this->priceOf($chapterId), 'El precio sale del texto y del cuestionario, de nada más.');
    }

    /**
     * Una autora en rojo, con una corrección entregada que podría querer
     * propinar.
     *
     * El camino es el de `FEAT-CRD-018`: dos lectoras dentro del mismo
     * capítulo a la vez. Con una sola no se llega —no se empieza una
     * corrección que el autor no pueda pagar—, y la deuda aparece cuando la
     * segunda entrega lo que ya no estaba cubierto.
     *
     * @return array{0: array{token: string, userId: string}, 1: array{token: string, userId: string}, 2: string}
     */
    private function anAuthorInTheRed(): array
    {
        $autora = $this->activatedPerson('autora');
        $primera = $this->activatedPerson('primera');
        $segunda = $this->activatedPerson('segunda');

        $workId = $this->createWork($autora['token'], 'La obra que endeuda');
        $chapterId = $this->addChapter($workId, $autora['token'], words: 6000);
        $this->saveQuestionnaire($workId, $autora['token'], [
            ['statement' => '¿Cómo funciona el ritmo?', 'minWords' => 50],
        ]);
        $this->putAs(\sprintf('/api/v1/works/%s/access-mode', $workId), $autora['token'], ['accessMode' => 'PUBLIC']);
        $this->putAs(\sprintf('/api/v1/works/%s/status', $workId), $autora['token'], ['status' => 'PUBLISHED']);
        $this->putAs(\sprintf('/api/v1/works/%s/status', $workId), $autora['token'], ['status' => 'IN_CORRECTION']);
        $this->consumeEverything();

        foreach ([$primera, $segunda] as $lectora) {
            $this->client->request('POST', \sprintf('/api/v1/chapters/%s/corrections/start', $chapterId), server: [
                'HTTP_AUTHORIZATION' => 'Bearer '.$lectora['token'],
            ]);
            self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
            $this->capture();
        }

        $this->consumeEverything();

        // La de `$primera` es la que estaba cubierta; la de `$segunda` es la
        // que deja a la autora en rojo. Se devuelve la primera porque es la
        // que la autora podría querer propinar sin que nadie la culpe de
        // haberse endeudado por ella.
        $correctionId = $this->submitAs($chapterId, $primera['token']);
        $this->submitAs($chapterId, $segunda['token']);

        $this->consumeEverything();

        return [$autora, $primera, $correctionId];
    }

    /**
     * Entrega la corrección en curso respondiendo a todo el cuestionario.
     */
    private function submitAs(string $chapterId, string $token): string
    {
        $this->client->request('GET', \sprintf('/api/v1/chapters/%s/questionnaire', $chapterId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        self::assertResponseIsSuccessful();

        /** @var list<array{questionId: string}> $questions */
        $questions = $this->payload()['questions'];

        $this->client->request('POST', \sprintf('/api/v1/chapters/%s/corrections', $chapterId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode([
            'answers' => array_map(
                static fn (array $question): array => [
                    'questionId' => $question['questionId'],
                    'text' => implode(' ', array_fill(0, 60, 'palabra')),
                ],
                $questions,
            ),
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $id = (string) $this->payload()['correctionId'];
        $this->capture();

        return $id;
    }

    private function tip(string $correctionId, string $token, int $amount, ?string $key = null): void
    {
        $server = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ];

        if (null !== $key) {
            $server['HTTP_IDEMPOTENCY_KEY'] = $key;
        }

        $this->client->request(
            'POST',
            \sprintf('/api/v1/corrections/%s/tip', $correctionId),
            server: $server,
            content: json_encode(['amount' => $amount], \JSON_THROW_ON_ERROR),
        );
    }

    /**
     * Los abonos por propina que alguien tiene en su histórico.
     *
     * Se cuentan **movimientos** y no saldo porque lo que se quiere descartar
     * es un apunte repetido, y dos de uno suman lo mismo que uno de dos. Y se
     * leen por la API del corrector, que es donde `RN-3c` dice que la propina
     * tiene que ser visible para quien la recibe.
     *
     * @return list<array{amount: int, correctionId: string|null}>
     */
    private function tipsReceivedBy(string $token): array
    {
        $this->client->request('GET', '/api/v1/credits/movements?reason=TIP_RECEIVED', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        self::assertResponseIsSuccessful();

        /** @var list<array{amount: int, correctionId: string|null}> $movimientos */
        $movimientos = $this->payload()['movements'];

        return $movimientos;
    }

    /**
     * @return array{tipped: bool}
     */
    private function myCorrection(string $token, string $correctionId): array
    {
        $this->client->request('GET', '/api/v1/me/corrections', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        self::assertResponseIsSuccessful();

        /** @var list<array{correctionId: string, tipped: bool}> $mias */
        $mias = $this->payload()['corrections'];

        foreach ($mias as $mia) {
            if ($mia['correctionId'] === $correctionId) {
                return $mia;
            }
        }

        self::fail('La corrección no aparece entre las suyas.');
    }

    /**
     * Una corrección llegada de verdad por un enlace público
     * (`FEAT-FBK-008`), no fabricada: pasa por el endpoint anónimo, como la
     * escribiría alguien sin cuenta.
     */
    private function aPublicLinkCorrectionOn(string $workId, string $chapterId, string $authorToken): string
    {
        $this->client->request('POST', \sprintf('/api/v1/works/%s/public-links', $workId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$authorToken,
        ], content: '{}');
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $token = (string) $this->payload()['token'];

        $this->client->request('GET', \sprintf('/api/v1/public/%s/chapters/%s', $token, $chapterId));
        self::assertResponseIsSuccessful();
        /** @var list<array{questionId: string}> $questions */
        $questions = $this->payload()['questions'];

        $this->client->request('POST', \sprintf('/api/v1/public/%s/chapters/%s/corrections', $token, $chapterId), server: [
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode([
            'answers' => array_map(
                static fn (array $question): array => [
                    'questionId' => $question['questionId'],
                    'text' => implode(' ', array_fill(0, 60, 'palabra')),
                ],
                $questions,
            ),
            'acceptedTerms' => true,
            'name' => 'Alguien de fuera',
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $id = (string) $this->payload()['correctionId'];
        $this->capture();
        $this->consumeEverything();

        return $id;
    }

    private function priceOf(string $chapterId): ?int
    {
        /** @var ChapterPriceRepository $prices */
        $prices = self::getContainer()->get(ChapterPriceRepository::class);

        return $prices->ofChapter(PricedChapterId::fromString($chapterId))?->price();
    }
}
