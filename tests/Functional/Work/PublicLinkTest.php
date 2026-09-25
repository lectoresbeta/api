<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Work;

use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use Symfony\Component\HttpFoundation\Response;

/**
 * El enlace público (`FEAT-WRK-010`) y lo que se corrige por él
 * (`FEAT-FBK-008`).
 *
 * **Lo que hay que demostrar aquí es que no mueve ni un crédito.** Es lo que
 * convierte esto en la válvula de seguridad de la economía: un autor a cero
 * no recibe correcciones, y para conseguir saldo necesita textos que
 * corregir; el enlace rompe ese círculo por fuera, sin tocar la masa.
 *
 * Y lo segundo, la fuga que hay que tapar: **quien tiene sesión no corrige
 * por aquí**. Sin esa regla, el autor pegaría el enlace en su muro y
 * conseguiría que usuarios registrados le corrigiesen gratis — él se
 * ahorraría los créditos y ellos perderían los suyos.
 */
final class PublicLinkTest extends EconomyScenario
{
    /**
     * El recorrido entero: repartir, leer sin cuenta, corregir y que la
     * corrección llegue al autor sin que nadie pague ni cobre.
     */
    public function testSomebodyWithoutAnAccountReadsAndCorrects(): void
    {
        [$autora, $workId, $chapterId] = $this->aWorkWithAQuestionnaire();
        $token = $this->aPublicLinkFor($workId, $autora['token']);

        $antes = (int) $this->balanceOf($autora['userId']);

        // La portada: la obra y sus capítulos, sin una sola cabecera de
        // sesión.
        $this->client->request('GET', \sprintf('/api/v1/public/%s', $token));
        self::assertResponseIsSuccessful();
        self::assertSame('La obra repartida', $this->payload()['title']);
        self::assertCount(1, $this->payload()['chapters']);
        self::assertSame(
            'noindex, nofollow, noarchive, nosnippet',
            $this->client->getResponse()->headers->get('X-Robots-Tag'),
            'RN-11: obra inédita no puede acabar en un buscador.',
        );

        // El capítulo, con lo que su autora pregunta y lo que valdría.
        $this->client->request('GET', \sprintf('/api/v1/public/%s/chapters/%s', $token, $chapterId));
        self::assertResponseIsSuccessful();
        $capitulo = $this->payload();
        self::assertStringContainsString('palabra', (string) $capitulo['contentHtml']);
        self::assertCount(1, $capitulo['questions']);

        $this->submitPublicly($token, $chapterId, $capitulo['questions']);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $recibo = $this->payload();
        self::assertNotEmpty($recibo['correctionId']);
        self::assertSame($capitulo['wouldBeWorth'], $recibo['wouldHaveBeenWorth'], 'La cifra del mensaje de captación.');
        self::assertGreaterThan(0, (int) $recibo['wouldHaveBeenWorth']);

        $this->capture();
        $this->consumeEverything();

        // `RN-2`: ni un crédito, en ninguna dirección.
        self::assertSame($antes, (int) $this->balanceOf($autora['userId']), 'La autora no paga.');

        // Y le llega, marcada.
        $recibida = $this->receivedBy($autora['token'])[0];
        self::assertSame($recibo['correctionId'], $recibida['correctionId']);
        self::assertSame('PUBLIC_LINK', $recibida['origin']);
    }

    /**
     * `FEAT-FBK-008` `RN-1`, la fuga. Ni lee el formulario anónimo ni puede
     * enviarlo: se le manda al flujo normal, que es donde cobra.
     */
    public function testASignedInReaderIsSentToTheNormalFlow(): void
    {
        [$autora, $workId, $chapterId] = $this->aWorkWithAQuestionnaire();
        $token = $this->aPublicLinkFor($workId, $autora['token']);
        $lectora = $this->activatedPerson('lectora');

        $this->client->request('GET', \sprintf('/api/v1/public/%s', $token), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$lectora['token'],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame('USE_THE_NORMAL_FLOW', $this->payload()['code']);
        self::assertSame($workId, $this->payload()['workId'], 'Lleva la obra para poder llevarle allí.');

        $this->client->request('POST', \sprintf('/api/v1/public/%s/chapters/%s/corrections', $token, $chapterId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$lectora['token'],
        ], content: json_encode(['answers' => [], 'acceptedTerms' => true], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame('USE_THE_NORMAL_FLOW', $this->payload()['code']);
    }

    /**
     * `RN-9`: revocar deja el enlace inservible en el acto, y para siempre.
     */
    public function testRevokingClosesTheDoorImmediately(): void
    {
        [$autora, $workId, $chapterId] = $this->aWorkWithAQuestionnaire();
        $token = $this->aPublicLinkFor($workId, $autora['token']);

        $this->client->request('GET', '/api/v1/works/'.$workId.'/public-links', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$autora['token'],
        ]);
        self::assertResponseIsSuccessful();
        $enlaces = $this->payload()['publicLinks'];
        self::assertCount(1, $enlaces);
        self::assertArrayNotHasKey('token', $enlaces[0], 'RN-2: el token no se puede recuperar.');
        self::assertTrue($enlaces[0]['usable']);

        $this->client->request('DELETE', '/api/v1/public-links/'.$enlaces[0]['publicLinkId'], server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$autora['token'],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->client->request('GET', \sprintf('/api/v1/public/%s', $token));
        self::assertResponseStatusCodeSame(Response::HTTP_GONE);
        self::assertSame('PUBLIC_LINK_GONE', $this->payload()['code']);

        $this->client->request('POST', \sprintf('/api/v1/public/%s/chapters/%s/corrections', $token, $chapterId), server: [
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode(['answers' => [], 'acceptedTerms' => true], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_GONE);

        // Revocar otra vez no es un error: lo que se pedía ya se ha cumplido.
        $this->client->request('DELETE', '/api/v1/public-links/'.$enlaces[0]['publicLinkId'], server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$autora['token'],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }

    /**
     * `RN-13`: el capítulo que su autora ha ocultado lo está también aquí. El
     * enlace abre una puerta, no la levanta.
     */
    public function testAHiddenChapterIsNotServedThroughTheLink(): void
    {
        [$autora, $workId, $chapterId] = $this->aWorkWithAQuestionnaire();
        $token = $this->aPublicLinkFor($workId, $autora['token']);

        $this->putAs(\sprintf('/api/v1/chapters/%s/visibility', $chapterId), $autora['token'], ['visibility' => 'HIDDEN']);
        self::assertResponseIsSuccessful();

        $this->client->request('GET', \sprintf('/api/v1/public/%s', $token));
        self::assertResponseIsSuccessful();
        self::assertSame([], $this->payload()['chapters'], 'No sale en el índice.');

        $this->client->request('GET', \sprintf('/api/v1/public/%s/chapters/%s', $token, $chapterId));
        self::assertResponseStatusCodeSame(Response::HTTP_GONE, 'Ni por su URL directa.');
    }

    /**
     * `RN-4`: diez por defecto, configurable. Se puede seguir leyendo; lo que
     * se agota es la posibilidad de corregir.
     */
    public function testTheLinkStopsTakingCorrectionsAtItsCap(): void
    {
        [$autora, $workId, $chapterId] = $this->aWorkWithAQuestionnaire();
        $token = $this->aPublicLinkFor($workId, $autora['token'], ['maxCorrections' => 1]);

        $preguntas = $this->questionsOf($token, $chapterId);

        $this->submitPublicly($token, $chapterId, $preguntas);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->submitPublicly($token, $chapterId, $preguntas);
        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame('PUBLIC_LINK_FULL', $this->payload()['code']);
        self::assertSame(1, $this->payload()['maxCorrections']);

        // Y leer sigue funcionando: lo que se agotó fue el cupo de escribir.
        $this->client->request('GET', \sprintf('/api/v1/public/%s', $token));
        self::assertResponseIsSuccessful();
    }

    /**
     * `RN-7`: la casilla la comprueba el servidor. Quien envía está
     * aportando un texto propio sin haber aceptado nada.
     */
    public function testWithoutAcceptingTheTermsNothingIsSent(): void
    {
        [$autora, $workId, $chapterId] = $this->aWorkWithAQuestionnaire();
        $token = $this->aPublicLinkFor($workId, $autora['token']);

        $this->submitPublicly($token, $chapterId, $this->questionsOf($token, $chapterId), accepted: false);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('TERMS_NOT_ACCEPTED', $this->payload()['code']);
    }

    /**
     * `RN-6`: un nombre opcional y sin verificar. A la autora le importa
     * distinguir la crítica de su hermana de la de un compañero de taller.
     */
    public function testTheNameTravelsAsALabelAndNothingMore(): void
    {
        [$autora, $workId, $chapterId] = $this->aWorkWithAQuestionnaire();
        $token = $this->aPublicLinkFor($workId, $autora['token']);

        $this->submitPublicly($token, $chapterId, $this->questionsOf($token, $chapterId), name: '  Mi hermana  ');
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $hecho = $this->capture('PublicCorrectionSubmitted');
        self::assertCount(1, $hecho);

        /** @var array{authorLabel: string, readerId?: string} $payload */
        $payload = json_decode($hecho[0]['body'], true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('Mi hermana', $payload['authorLabel']);
        self::assertArrayNotHasKey('readerId', $payload, 'No hay lector: eso es lo que lo distingue.');

        $this->consumeEverything();
        self::assertSame('Mi hermana', $this->receivedBy($autora['token'])[0]['authorLabel']);
    }

    /**
     * `RN-7` de `FEAT-WRK-010`: los enlaces son del autor y de nadie más.
     */
    public function testOnlyTheAuthorHandlesTheirOwnLinks(): void
    {
        [$autora, $workId] = $this->aWorkWithAQuestionnaire();
        $ajena = $this->activatedPerson('ajena');

        $this->client->request('POST', '/api/v1/works/'.$workId.'/public-links', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$ajena['token'],
        ], content: '{}');
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertSame('WORK_NOT_FOUND', $this->payload()['code']);

        $this->client->request('GET', '/api/v1/works/'.$workId.'/public-links', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$ajena['token'],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * Un token inventado no dice nada de nada. `404` y no `410`: nunca
     * existió, así que no hay pasado que contar.
     */
    public function testAnInventedTokenOpensNothing(): void
    {
        $this->client->request('GET', '/api/v1/public/esto-no-es-un-token');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * El tope se valida al crear, no al agotarse.
     */
    public function testTheCapHasToBeSensible(): void
    {
        [$autora, $workId] = $this->aWorkWithAQuestionnaire();

        foreach ([0, -3, 101] as $tope) {
            $this->client->request('POST', '/api/v1/works/'.$workId.'/public-links', server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$autora['token'],
            ], content: json_encode(['maxCorrections' => $tope], \JSON_THROW_ON_ERROR));

            self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
            self::assertSame('PUBLIC_LINK_CAP_OUT_OF_RANGE', $this->payload()['code']);
        }

        $this->client->request('POST', '/api/v1/works/'.$workId.'/public-links', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$autora['token'],
        ], content: json_encode(['expiresAt' => '2020-01-01T00:00:00+00:00'], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('PUBLIC_LINK_EXPIRY_IN_THE_PAST', $this->payload()['code']);
    }

    /**
     * `RN-14`: funciona sobre un borrador, que es justamente el caso. Se
     * reparte para conseguir feedback **antes** de publicar.
     */
    public function testItWorksOnADraft(): void
    {
        [$autora, $workId, $chapterId] = $this->aWorkWithAQuestionnaire();
        $token = $this->aPublicLinkFor($workId, $autora['token']);

        // Sin publicar ni abrir a corrección: la obra sigue en borrador.
        $this->submitPublicly($token, $chapterId, $this->questionsOf($token, $chapterId));

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
    }

    /**
     * Una obra en borrador con su cuestionario y un capítulo, que es todo lo
     * que el enlace necesita.
     *
     * @return array{0: array{token: string, userId: string}, 1: string, 2: string}
     */
    private function aWorkWithAQuestionnaire(): array
    {
        $autora = $this->activatedPerson('autora');

        $workId = $this->createWork($autora['token'], 'La obra repartida');
        $chapterId = $this->addChapter($workId, $autora['token'], words: 900);
        $this->saveQuestionnaire($workId, $autora['token'], [
            ['statement' => '¿Cómo funciona el ritmo?', 'minWords' => 10],
        ]);
        $this->consumeEverything();

        return [$autora, $workId, $chapterId];
    }

    /**
     * @param array<string, mixed> $options
     */
    private function aPublicLinkFor(string $workId, string $token, array $options = []): string
    {
        $this->client->request('POST', '/api/v1/works/'.$workId.'/public-links', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode($options, \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));

        return (string) $this->payload()['token'];
    }

    /**
     * @return list<array{questionId: string}>
     */
    private function questionsOf(string $token, string $chapterId): array
    {
        $this->client->request('GET', \sprintf('/api/v1/public/%s/chapters/%s', $token, $chapterId));
        self::assertResponseIsSuccessful();

        /** @var list<array{questionId: string}> $questions */
        $questions = $this->payload()['questions'];

        return $questions;
    }

    /**
     * @param list<array{questionId: string}> $questions
     */
    private function submitPublicly(
        string $token,
        string $chapterId,
        array $questions,
        bool $accepted = true,
        ?string $name = null,
    ): void {
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
            'acceptedTerms' => $accepted,
            'name' => $name,
        ], \JSON_THROW_ON_ERROR));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function receivedBy(string $token): array
    {
        $this->client->request('GET', '/api/v1/me/corrections/received', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        self::assertResponseIsSuccessful();

        /** @var list<array<string, mixed>> $received */
        $received = $this->payload()['corrections'];

        return $received;
    }
}
