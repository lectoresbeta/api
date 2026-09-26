<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Feedback;

use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use Symfony\Component\HttpFoundation\Response;

/**
 * Valorar una obra de 1 a 5 (`FEAT-FBK-002`).
 *
 * **Solo valora quien la ha corregido**, y es la regla que da sentido al
 * número. Una media abierta a cualquiera que pueda abrir la obra mide cuánta
 * gente pasó por allí; una de quienes entregaron una corrección dice lo que
 * opina quien de verdad la leyó.
 *
 * Es además la única señal de lectura que la plataforma tiene —no hay
 * seguimiento de lectura (`H-3`)— y la que resiste el fraude que
 * `FEAT-FBK-012` teme: inflar una nota exigiría entregar correcciones.
 */
final class RateAWorkTest extends EconomyScenario
{
    /**
     * `RN-1`: quien entregó una corrección valora, y el hecho sale con la
     * nota dentro.
     */
    public function testWhoeverDeliveredACorrectionCanRateIt(): void
    {
        [, $lectora, , $workId] = $this->aDeliveredCorrection();

        self::assertSame(4, $this->rate($lectora['token'], $workId, 4));

        $hecho = $this->lastAnnouncementOf('WorkRated');
        self::assertSame($workId, $hecho['workId']);
        self::assertSame($lectora['userId'], $hecho['readerId']);
        self::assertSame(4, $hecho['rating']);
        self::assertTrue($hecho['firstTime']);
    }

    /**
     * **La regla que da sentido al número**: sin haber corregido, no se
     * valora. Y se dice por qué, que es lo correcto — quien llega aquí ya ve
     * la obra, así que no se revela nada, y «corrige un capítulo y podrás
     * valorarla» es una instrucción y no un muro.
     */
    public function testWithoutHavingCorrectedItThereIsNoRating(): void
    {
        [, , , $workId] = $this->aDeliveredCorrection();
        $extranya = $this->activatedPerson('extranya');

        $this->put($extranya['token'], $workId, 5);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame('CORRECTION_REQUIRED_TO_RATE', $this->payload()['code']);
    }

    /**
     * Un **borrador no cuenta**: lo que acredita haber leído es el trabajo
     * entregado, no el empezado.
     */
    public function testADraftIsNotEnough(): void
    {
        $autora = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');

        $workId = $this->createWork($autora['token'], 'La obra a medias');
        $chapterId = $this->addChapter($workId, $autora['token'], words: 900);
        $this->saveQuestionnaire($workId, $autora['token'], [
            ['statement' => '¿Cómo funciona el ritmo?', 'minWords' => 10],
        ]);
        $this->putAs(\sprintf('/api/v1/works/%s/access-mode', $workId), $autora['token'], ['accessMode' => 'PUBLIC']);
        $this->putAs(\sprintf('/api/v1/works/%s/status', $workId), $autora['token'], ['status' => 'PUBLISHED']);
        $this->putAs(\sprintf('/api/v1/works/%s/status', $workId), $autora['token'], ['status' => 'IN_CORRECTION']);
        $this->consumeEverything();

        $this->client->request('POST', \sprintf('/api/v1/chapters/%s/corrections/start', $chapterId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$lectora['token'],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->capture();
        $this->consumeEverything();

        $this->put($lectora['token'], $workId, 5);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN, 'Empezar no es entregar.');
    }

    /**
     * `RN-2`: una por lector y obra. Volver a valorar **sustituye**, y el
     * hecho lo dice para que quien promedie no sume dos veces.
     */
    public function testRatingAgainReplacesThePreviousOne(): void
    {
        [, $lectora, , $workId] = $this->aDeliveredCorrection();

        self::assertSame(2, $this->rate($lectora['token'], $workId, 2));
        self::assertSame(5, $this->rate($lectora['token'], $workId, 5));

        $hecho = $this->lastAnnouncementOf('WorkRated');
        self::assertSame(5, $hecho['rating']);
        self::assertFalse($hecho['firstTime'], 'Sustituye, no suma.');
    }

    /**
     * `RN-3`: el autor no se valora a sí mismo. No hace falta explicar por
     * qué sería un problema.
     */
    public function testAnAuthorDoesNotRateTheirOwnWork(): void
    {
        [$autora, , , $workId] = $this->aDeliveredCorrection();

        $this->put($autora['token'], $workId, 5);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame('CANNOT_RATE_YOUR_OWN_WORK', $this->payload()['code']);
    }

    /**
     * `RN-4`: la escala es 1 a 5, y fuera de ella no hay nota. Cero tampoco:
     * una escala de estrellas empieza en una.
     */
    public function testTheScaleIsOneToFive(): void
    {
        [, $lectora, , $workId] = $this->aDeliveredCorrection();

        foreach ([0, 6, -1, 100] as $value) {
            $this->put($lectora['token'], $workId, $value);
            self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY, (string) $value);
        }

        self::assertSame(1, $this->rate($lectora['token'], $workId, 1));
        self::assertSame(5, $this->rate($lectora['token'], $workId, 5));
    }

    /**
     * Una obra que no se puede ver no se distingue de una que no existe.
     */
    public function testAnUnseeableWorkIsIndistinguishableFromNone(): void
    {
        $extranya = $this->activatedPerson('extranya');
        $autora = $this->activatedPerson('autora');
        $borrador = $this->createWork($autora['token'], 'Inédita');

        $this->put($extranya['token'], $borrador, 5);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $inedita = $this->payload();

        $this->put($extranya['token'], '11111111-1111-4111-8111-111111111111', 5);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        self::assertSame($inedita['code'], $this->payload()['code']);
        self::assertSame('WORK_NOT_FOUND', $inedita['code']);
    }

    public function testWithoutASessionThereIsNoRating(): void
    {
        [, , , $workId] = $this->aDeliveredCorrection();

        $this->client->request('PUT', \sprintf('/api/v1/works/%s/rating', $workId), server: [
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode(['rating' => 5], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * La valoración llega a `Work`, que mantiene su propio agregado para
     * poder ordenar «Mis relatos» por él (`FEAT-WRK-015`).
     *
     * **Es una proyección, no un traslado de la propiedad**: `Feedback` sigue
     * decidiendo quién puede valorar y con qué nota. Lo que cruza es el hecho.
     */
    public function testTheRatingReachesTheAuthorsOwnList(): void
    {
        [$autora, $lectora, , $workId] = $this->aDeliveredCorrection();

        $this->rate($lectora['token'], $workId, 4);
        $this->consumeEverything();

        $card = $this->myWork($autora['token'], $workId);

        self::assertSame(1, $card['ratingCount']);
        // En JSON un 4.0 viaja como `4`: la media se compara como número.
        self::assertSame(4.0, (float) $card['ratingAverage']);
    }

    /**
     * Y **cambiar la nota no la suma dos veces**.
     *
     * Es el caso que obliga a `Work` a recordar la última nota de cada
     * persona: el hecho no lleva la anterior, así que sin esa fila la suma
     * contaría las dos y la media saldría de siete entre uno.
     */
    public function testChangingARatingDoesNotCountItTwice(): void
    {
        [$autora, $lectora, , $workId] = $this->aDeliveredCorrection();

        $this->rate($lectora['token'], $workId, 2);
        $this->consumeEverything();
        $this->rate($lectora['token'], $workId, 5);
        $this->consumeEverything();

        $card = $this->myWork($autora['token'], $workId);

        self::assertSame(1, $card['ratingCount'], 'Sigue siendo una persona.');
        self::assertSame(5.0, (float) $card['ratingAverage']);
    }

    /**
     * Sin valorar, `null`, que **no es lo mismo que cero**: una obra que
     * nadie ha valorado no es una obra mal valorada, y es la razón de que el
     * orden «más valorados» las mande al final en vez de mezclarlas.
     */
    public function testAnUnratedWorkHasNoAverageAndGoesLast(): void
    {
        [$autora, $lectora, , $valorada] = $this->aDeliveredCorrection();
        $sinValorar = $this->createWork($autora['token'], 'Nadie la ha valorado');

        $this->rate($lectora['token'], $valorada, 3);
        $this->consumeEverything();

        self::assertNull($this->myWork($autora['token'], $sinValorar)['ratingAverage']);
        self::assertSame(0, $this->myWork($autora['token'], $sinValorar)['ratingCount']);

        $this->myWorks($autora['token'], '&sort=rated');

        self::assertSame([$valorada, $sinValorar], array_map(
            static fn (array $work): string => (string) $work['workId'],
            $this->payload()['works'],
        ));
    }

    public function testAnUnsupportedSortSaysWhichOnesWork(): void
    {
        $autora = $this->activatedPerson('autora');

        $this->myWorks($autora['token'], '&sort=mostRead');

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('recent,oldest,rated', $this->payload()['supportedSorts']);
    }

    /**
     * @return array<string, mixed>
     */
    private function myWork(string $token, string $workId): array
    {
        $this->myWorks($token);

        foreach ($this->payload()['works'] as $work) {
            if ($work['workId'] === $workId) {
                return $work;
            }
        }

        self::fail('La obra no está en «Mis relatos».');
    }

    private function myWorks(string $token, string $query = ''): void
    {
        $this->client->request('GET', '/api/v1/me/works?'.ltrim($query, '&'), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
    }

    private function rate(string $token, string $workId, int $rating): int
    {
        $this->put($token, $workId, $rating);

        self::assertResponseIsSuccessful();

        return (int) $this->payload()['rating'];
    }

    private function put(string $token, string $workId, int $rating): void
    {
        $this->client->request('PUT', \sprintf('/api/v1/works/%s/rating', $workId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(['rating' => $rating], \JSON_THROW_ON_ERROR));

        $this->capture();
    }
}
