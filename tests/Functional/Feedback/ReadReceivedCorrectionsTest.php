<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Feedback;

use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use Symfony\Component\HttpFoundation\Response;

/**
 * Leer lo que se ha recibido (`FEAT-FBK-004`).
 *
 * **Es el final que le faltaba al ciclo económico.** Hasta aquí se abría la
 * obra a corrección, alguien entregaba, se le cobraba al autor y se le
 * abonaba al lector — y el autor no tenía ninguna forma de leer aquello por
 * lo que había pagado.
 *
 * De ahí que lo que más se prueba aquí sea **quién no puede leer**: una
 * corrección es lo que una persona le escribe a otra sobre un texto inédito,
 * y el daño de enseñarla de más no se deshace.
 */
final class ReadReceivedCorrectionsTest extends EconomyScenario
{
    public function testTheAuthorReadsWhatTheyPaidFor(): void
    {
        [$autora, $lectora, $correctionId] = $this->aDeliveredCorrection();

        $this->inbox($autora['token']);
        self::assertResponseIsSuccessful();

        $recibidas = $this->payload()['corrections'];
        self::assertCount(1, $recibidas);
        self::assertSame($correctionId, $recibidas[0]['correctionId']);
        self::assertSame($lectora['userId'], $recibidas[0]['readerId']);
        self::assertSame('VISIBLE', $recibidas[0]['visibility']);
        self::assertFalse($recibidas[0]['read'], 'Recién llegada, sin abrir.');
        self::assertArrayNotHasKey('answers', $recibidas[0], 'La bandeja no lleva contenido.');

        $this->correction($autora['token'], $correctionId);
        self::assertResponseIsSuccessful();
        self::assertNotEmpty($this->payload()['answers']);
        self::assertSame(
            '¿Cómo funciona el ritmo?',
            $this->payload()['answers'][0]['statement'],
            'Con el enunciado de la versión que se respondió.',
        );
        self::assertStringContainsString('palabra', $this->payload()['answers'][0]['text']);
    }

    /**
     * `RN-7`. Y solo la primera vez: la fecha dice cuándo se leyó, no cuándo
     * se miró por última vez.
     */
    public function testOpeningItMarksItRead(): void
    {
        [$autora, , $correctionId] = $this->aDeliveredCorrection();

        $this->inbox($autora['token'], ['unread' => 'true']);
        self::assertCount(1, $this->payload()['corrections'], 'Antes de abrirla, está sin leer.');

        $this->correction($autora['token'], $correctionId);
        self::assertTrue($this->payload()['read'], 'Y la respuesta ya dice el estado en que queda.');

        $this->inbox($autora['token'], ['unread' => 'true']);
        self::assertSame([], $this->payload()['corrections'], 'Y deja de estar sin leer.');

        $this->inbox($autora['token']);
        self::assertTrue($this->payload()['corrections'][0]['read']);
    }

    /**
     * `RN-1`: una corrección ajena responde exactamente lo mismo que una
     * inexistente. Saber que existe es saber algo de una obra inédita que no
     * es suya, y de quién la está leyendo.
     */
    public function testOnlyTheTwoPartiesCanReadIt(): void
    {
        [, $lectora, $correctionId] = $this->aDeliveredCorrection();
        $extrana = $this->activatedPerson('extrana');

        $this->correction($lectora['token'], $correctionId);
        self::assertResponseIsSuccessful('Quien la escribió también la lee.');

        $this->correction($extrana['token'], $correctionId);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertSame('CORRECTION_NOT_FOUND', $this->payload()['code']);

        $this->correction($extrana['token'], $this->eventId());
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND, 'Y una inexistente responde igual.');
    }

    /**
     * Quien la escribió la lee, pero **no ve si el autor la ha abierto**
     * (`F-14`): sería una confirmación de lectura entre dos personas que no
     * han elegido tener una conversación.
     */
    public function testTheWriterDoesNotLearnWhetherItWasRead(): void
    {
        [$autora, $lectora, $correctionId] = $this->aDeliveredCorrection();

        $this->correction($autora['token'], $correctionId);
        self::assertResponseIsSuccessful();

        $this->inbox($lectora['token']);
        self::assertSame([], $this->payload()['corrections'], 'La bandeja es la de lo recibido, no la de lo escrito.');

        $this->correction($lectora['token'], $correctionId);
        self::assertNull($this->payload()['read'], 'Para quien la escribió, el dato no existe.');
    }

    /**
     * `RN-3` y `FEAT-CRD-018`: el candado que ya existía por fin cierra una
     * puerta. La corrección **aparece**, porque esconderla haría creer al
     * autor que nadie le ha corregido.
     */
    public function testACorrectionWithheldByDebtShowsItselfWithoutItsContent(): void
    {
        $autora = $this->anAuthorInDebt();

        $this->inbox($autora['token']);
        $retenidas = array_values(array_filter(
            $this->payload()['corrections'],
            static fn (array $correction): bool => 'LOCKED' === $correction['visibility'],
        ));
        self::assertNotEmpty($retenidas, 'Sigue en la bandeja, marcada.');

        $this->correction($autora['token'], $retenidas[0]['correctionId']);
        self::assertResponseIsSuccessful();
        self::assertSame('LOCKED', $this->payload()['visibility']);
        self::assertSame([], $this->payload()['answers'], 'Existe, y su contenido no se sirve.');
        self::assertFalse($this->payload()['read'], 'Y no se marca como leída: no se ha leído nada.');

        $this->chapterText($autora['token'], $retenidas[0]['correctionId']);
        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT, 'Ni el texto, que sería la vía para deducirla.');
        self::assertSame('CORRECTION_LOCKED', $this->payload()['code']);
    }

    /**
     * `RN-8`. Mientras el capítulo no se versione (`FEAT-WRK-005`), lo que
     * llega es el texto vigente **dicho como lo que es**.
     */
    public function testTheChapterTextComesWithWhetherItIsTheOneThatWasRead(): void
    {
        [$autora, $lectora, $correctionId] = $this->aDeliveredCorrection();

        $this->chapterText($autora['token'], $correctionId);
        self::assertResponseIsSuccessful();
        self::assertNotEmpty($this->payload()['contentHtml']);
        self::assertTrue($this->payload()['isCurrentVersion']);

        $this->chapterText($lectora['token'], $correctionId);
        self::assertResponseIsSuccessful('Quien corrigió también puede releerlo.');

        $this->chapterText($this->activatedPerson('curiosa')['token'], $correctionId);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testTheInboxFiltersByWorkAndChapter(): void
    {
        [$autora, , $correctionId] = $this->aDeliveredCorrection();
        $otra = $this->publishedWork($autora, 'Otra obra sin correcciones');

        $this->inbox($autora['token'], ['workId' => $otra]);
        self::assertSame([], $this->payload()['corrections']);

        $this->inbox($autora['token'], ['workId' => $this->payloadWorkOf($autora['token'], $correctionId)]);
        self::assertCount(1, $this->payload()['corrections']);
    }

    /**
     * `RN-7` visto desde el otro lado: un centro de notificaciones que sigue
     * marcando como nuevo algo que ya se ha leído deja de significar nada en
     * una semana, y entonces la gente deja de mirarlo.
     */
    public function testOpeningItRetiresThePendingNotice(): void
    {
        [$autora, , $correctionId] = $this->aDeliveredCorrection();

        $this->unreadCount($autora['token']);
        self::assertSame(1, $this->payload()['unreadCount'], 'El aviso de la corrección recibida está ahí.');

        $this->correction($autora['token'], $correctionId);
        $this->capture();
        $this->consumeEverything();

        $this->unreadCount($autora['token']);
        self::assertSame(0, $this->payload()['unreadCount'], 'Y se retira al abrirla.');
    }

    public function testWithoutASessionThereIsNothing(): void
    {
        $this->client->request('GET', '/api/v1/me/corrections/received');
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        $this->client->request('GET', \sprintf('/api/v1/corrections/%s', $this->eventId()));
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Una autora con el saldo bajo cero, que es el único camino real al
     * descubierto: **dos lectoras dentro a la vez**. Con una sola no se
     * puede, porque no se empieza una corrección que el autor no pueda pagar;
     * la deuda aparece cuando dos coinciden sobre el mismo capítulo y solo la
     * primera estaba cubierta (`FEAT-CRD-018`).
     *
     * @return array{token: string, userId: string}
     */
    private function anAuthorInDebt(): array
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

        foreach ([$primera, $segunda] as $lectora) {
            $this->deliver_($chapterId, $lectora['token']);
        }

        return $autora;
    }

    private function deliver_(string $chapterId, string $token): void
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
                    'text' => implode(' ', array_fill(0, 2100, 'palabra')),
                ],
                $questions,
            ),
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->capture();
        $this->consumeEverything();
    }

    private function unreadCount(string $token): void
    {
        $this->client->request('GET', '/api/v1/me/notifications/unread-count', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        self::assertResponseIsSuccessful();
    }

    private function payloadWorkOf(string $token, string $correctionId): string
    {
        $this->correction($token, $correctionId);

        return (string) $this->payload()['workId'];
    }

    /**
     * @param array<string, string> $query
     */
    private function inbox(string $token, array $query = []): void
    {
        $this->client->request('GET', '/api/v1/me/corrections/received', $query, server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
    }

    private function correction(string $token, string $correctionId): void
    {
        $this->client->request('GET', \sprintf('/api/v1/corrections/%s', $correctionId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
    }

    private function chapterText(string $token, string $correctionId): void
    {
        $this->client->request('GET', \sprintf('/api/v1/corrections/%s/chapter-text', $correctionId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
    }
}
