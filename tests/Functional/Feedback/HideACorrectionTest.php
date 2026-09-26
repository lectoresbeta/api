<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Feedback;

use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use Symfony\Component\HttpFoundation\Response;

/**
 * Apartar de la bandeja una corrección recibida (`FEAT-FBK-007`).
 *
 * **Apartar no es borrar, y eso es toda la funcionalidad.** Una corrección
 * entregada no se elimina nunca: alguien la escribió, cobró por ella y la
 * tiene en «Mis correcciones». Lo que el autor decide es qué sigue viendo él.
 *
 * Las dos pruebas que importan son las que comprueban lo que **no** cambia:
 * la lista de quien la escribió, y la posibilidad de deshacerlo.
 */
final class HideACorrectionTest extends EconomyScenario
{
    /**
     * `RN-1`: apartada sale de la bandeja, y `RN-3`: vuelve.
     */
    public function testSettingItAsideTakesItOutOfTheInboxAndItComesBack(): void
    {
        [$autora, , $correctionId] = $this->aDeliveredCorrection();

        self::assertSame([$correctionId], $this->receivedBy($autora['token']));

        self::assertSame('HIDDEN_BY_AUTHOR', $this->setHidden($autora['token'], $correctionId, true));
        self::assertSame([], $this->receivedBy($autora['token']), 'Ya no está en la bandeja.');

        self::assertSame('VISIBLE', $this->setHidden($autora['token'], $correctionId, false));
        self::assertSame([$correctionId], $this->receivedBy($autora['token']), 'Y vuelve entera.');
    }

    /**
     * **La segunda bandeja**, sin la cual apartar sería irreversible en la
     * práctica: nadie recuerda el identificador de algo que apartó hace tres
     * meses.
     */
    public function testTheHiddenOnesHaveTheirOwnList(): void
    {
        [$autora, , $correctionId] = $this->aDeliveredCorrection();

        self::assertSame([], $this->receivedBy($autora['token'], hidden: true));

        $this->setHidden($autora['token'], $correctionId, true);

        self::assertSame([$correctionId], $this->receivedBy($autora['token'], hidden: true));
        self::assertSame([], $this->receivedBy($autora['token']), 'Son dos bandejas, no una con filtro.');
    }

    /**
     * `RN-2`, la regla que protege a quien hizo el trabajo: **apartar no se
     * lo quita**. Si afectara a la otra parte, sería una forma de castigar
     * una corrección que no gustó.
     */
    public function testItIsNeverHiddenFromWhoeverWroteIt(): void
    {
        [$autora, $lectora, $correctionId] = $this->aDeliveredCorrection();

        $this->setHidden($autora['token'], $correctionId, true);

        $this->client->request('GET', '/api/v1/me/corrections', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$lectora['token'],
        ]);
        self::assertResponseIsSuccessful();

        self::assertSame(
            [$correctionId],
            array_column($this->payload()['corrections'], 'correctionId'),
            'Su lista no cambia.',
        );

        $this->client->request('GET', \sprintf('/api/v1/corrections/%s', $correctionId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$lectora['token'],
        ]);
        self::assertResponseIsSuccessful('Y la sigue pudiendo abrir.');
    }

    /**
     * Y **lo que cobró no se toca**: apartar no revierte nada.
     */
    public function testItMovesNoCredits(): void
    {
        [$autora, $lectora, $correctionId] = $this->aDeliveredCorrection();

        $antesAutora = $this->balanceOf($autora['userId']);
        $antesLectora = $this->balanceOf($lectora['userId']);

        $this->setHidden($autora['token'], $correctionId, true);
        $this->consumeEverything();

        self::assertSame($antesAutora, $this->balanceOf($autora['userId']));
        self::assertSame($antesLectora, $this->balanceOf($lectora['userId']));
    }

    /**
     * Idempotente en las dos direcciones: la respuesta describe el estado
     * final, no lo que se hizo para llegar a él.
     */
    public function testRepeatingItChangesNothing(): void
    {
        [$autora, , $correctionId] = $this->aDeliveredCorrection();

        self::assertSame('HIDDEN_BY_AUTHOR', $this->setHidden($autora['token'], $correctionId, true));
        self::assertSame('HIDDEN_BY_AUTHOR', $this->setHidden($autora['token'], $correctionId, true));

        self::assertSame('VISIBLE', $this->setHidden($autora['token'], $correctionId, false));
        self::assertSame('VISIBLE', $this->setHidden($autora['token'], $correctionId, false));
    }

    /**
     * `RN-5`: solo el destinatario. Quien la escribió no aparta nada — no es
     * su bandeja— y responde `404`, igual que alguien ajeno.
     */
    public function testOnlyTheRecipientSetsItAside(): void
    {
        [, $lectora, $correctionId] = $this->aDeliveredCorrection();
        $extranya = $this->activatedPerson('extranya');

        foreach ([$lectora['token'], $extranya['token']] as $token) {
            $this->put($token, $correctionId, true);
            self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * Una apartada **se sigue pudiendo abrir** por su destinatario, con su
     * contenido: la apartó él y es quien pide verla. Sin esto, la segunda
     * bandeja sería una lista de títulos que no llevan a ninguna parte.
     */
    public function testTheOwnerCanStillOpenWhatTheySetAside(): void
    {
        [$autora, , $correctionId] = $this->aDeliveredCorrection();

        $this->setHidden($autora['token'], $correctionId, true);

        $this->client->request('GET', \sprintf('/api/v1/corrections/%s', $correctionId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$autora['token'],
        ]);
        self::assertResponseIsSuccessful();
        self::assertSame('HIDDEN_BY_AUTHOR', $this->payload()['visibility']);
        self::assertNotEmpty($this->payload()['answers'], 'Con su contenido.');
    }

    /**
     * `RN-4`: una **retenida por descubierto** no se puede apartar.
     *
     * Apartar es una decisión sobre algo que se ha leído, y de una `LOCKED`
     * no se ha leído nada. Se dice por qué y con la salida: reponer saldo la
     * libera, y entonces sí.
     */
    public function testAWithheldCorrectionCannotBeSetAside(): void
    {
        $autora = $this->anAuthorInDebt();

        $this->client->request('GET', '/api/v1/me/corrections/received', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$autora['token'],
        ]);
        self::assertResponseIsSuccessful();

        $retenidas = array_values(array_filter(
            $this->payload()['corrections'],
            static fn (array $correction): bool => 'LOCKED' === $correction['visibility'],
        ));
        self::assertNotEmpty($retenidas, 'Hace falta una retenida para poder probar esto.');

        $this->put($autora['token'], (string) $retenidas[0]['correctionId'], true);

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame('CORRECTION_IS_LOCKED', $this->payload()['code']);
    }

    public function testWithoutASessionThereIsNothingToSetAside(): void
    {
        [, , $correctionId] = $this->aDeliveredCorrection();

        $this->client->request('PUT', \sprintf('/api/v1/corrections/%s/visibility', $correctionId), server: [
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode(['hidden' => true], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    private function setHidden(string $token, string $correctionId, bool $hidden): string
    {
        $this->put($token, $correctionId, $hidden);

        self::assertResponseIsSuccessful();

        return (string) $this->payload()['visibility'];
    }

    private function put(string $token, string $correctionId, bool $hidden): void
    {
        $this->client->request('PUT', \sprintf('/api/v1/corrections/%s/visibility', $correctionId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(['hidden' => $hidden], \JSON_THROW_ON_ERROR));

        $this->capture();
    }

    /**
     * Una autora con una corrección retenida por descubierto: dos lectoras
     * corrigen un capítulo largo, y la segunda deja el saldo en negativo.
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
            $this->deliverA($chapterId, $lectora['token']);
        }

        return $autora;
    }

    private function deliverA(string $chapterId, string $token): void
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
                    'text' => implode(' ', array_fill(0, 80, 'palabra')),
                ],
                $questions,
            ),
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->capture();
        $this->consumeEverything();
    }

    /**
     * @return list<string>
     */
    private function receivedBy(string $token, bool $hidden = false): array
    {
        $this->client->request('GET', '/api/v1/me/corrections/received', $hidden ? ['hidden' => 1] : [], server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        self::assertResponseIsSuccessful();

        /** @var list<array{correctionId: string}> $rows */
        $rows = $this->payload()['corrections'];

        return array_map(static fn (array $row): string => $row['correctionId'], $rows);
    }
}
