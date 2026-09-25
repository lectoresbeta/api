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
