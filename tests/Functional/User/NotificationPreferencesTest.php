<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\User;

use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use Symfony\Component\HttpFoundation\Response;

/**
 * Las preferencias de notificación (`FEAT-USR-039`).
 *
 * **Lo que esta prueba defiende de verdad son dos cosas que, mal hechas,
 * dejan a alguien fuera de su cuenta o sin enterarse de lo que ha pagado:**
 *
 * - el interruptor general **no alcanza a los mensajes operativos**. Si lo
 *   hiciera, quien lo active no podría recuperar su contraseña;
 * - **corrección recibida y comentario son tipos distintos**. Si fueran uno,
 *   silenciar las reacciones sociales ocultaría el trabajo que el autor
 *   compró.
 */
final class NotificationPreferencesTest extends EconomyScenario
{
    /**
     * `RN-4`: todo activado de fábrica salvo las actualizaciones de la
     * plataforma, y el catálogo entero con sus canales — para que el cliente
     * no lleve la lista codificada.
     */
    public function testTheCatalogueComesWithItsChannelsAndItsDefaults(): void
    {
        $persona = $this->activatedPerson('persona');

        $preferencias = $this->preferences($persona['token']);

        self::assertFalse($preferencias['allMuted']);
        self::assertNotEmpty($preferencias['topics']);

        $porTipo = $this->indexed($preferencias);

        self::assertSame(
            ['EMAIL' => true, 'PLATFORM' => true],
            $porTipo['CORRECTION_RECEIVED'],
            'Lo que el autor ha pagado llega por los dos canales.',
        );
        self::assertSame(
            ['PLATFORM' => true],
            $porTipo['DIRECT_MESSAGE_RECEIVED'],
            'Nadie quiere un correo por cada mensaje directo.',
        );
        self::assertSame(
            ['EMAIL' => false],
            $porTipo['PLATFORM_UPDATES'],
            'La única que viene apagada.',
        );
    }

    /**
     * `RN-4b` y `S-11`: **corrección recibida y comentario son casillas
     * separadas**. Desde que comentar un capítulo no es corregirlo, meterlos
     * juntos significaría que quien silencia lo social deja de enterarse de
     * lo que ha comprado.
     */
    public function testSilencingCommentsDoesNotSilenceCorrections(): void
    {
        [$autora, $lectora] = $this->aDeliveredCorrection();

        $this->mute($autora['token'], 'CHAPTER_COMMENT');

        $this->submitAnotherCorrection($autora, $lectora);

        self::assertContains(
            'CORRECTION_RECEIVED',
            $this->inboxKindsOf($autora['token']),
            'Silenciar los comentarios no puede ocultar una corrección pagada.',
        );
    }

    /**
     * `RN-1`: lo silenciado no se entrega. Es la mitad observable de todo
     * esto.
     */
    public function testWhatIsSilencedDoesNotArrive(): void
    {
        [$autora, $lectora] = $this->aDeliveredCorrection();

        self::assertContains('CORRECTION_RECEIVED', $this->inboxKindsOf($autora['token']));

        $otra = $this->activatedPerson('otra');
        $this->mute($otra['token'], 'CORRECTION_RECEIVED');

        // La misma autora, ahora con el aviso apagado, no lo recibe.
        $this->mute($autora['token'], 'CORRECTION_RECEIVED');
        $antes = \count($this->inboxKindsOf($autora['token']));

        $this->submitAnotherCorrection($autora, $lectora);

        self::assertCount($antes, $this->inboxKindsOf($autora['token']), 'No llega nada nuevo.');
    }

    /**
     * `RN-3`, la regla que impide que alguien se quede fuera de su cuenta:
     * **el interruptor general no alcanza a los mensajes operativos**.
     */
    public function testTheMasterSwitchNeverSilencesAnOperationalMessage(): void
    {
        $persona = $this->activatedPerson('persona');

        $this->put('/api/v1/me/notification-preferences', $persona['token'], ['allMuted' => true]);
        self::assertResponseIsSuccessful();
        self::assertTrue($this->payload()['allMuted']);

        // Cambiar la contraseña es la defensa contra el robo de cuenta: su
        // aviso llega con el interruptor puesto.
        $this->client->request('PUT', '/api/v1/me/password', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$persona['token'],
        ], content: json_encode([
            'currentPassword' => 'Valida1!',
            'newPassword' => 'OtraValida1!',
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
        $this->capture();
        $this->consumeEverything();

        self::assertContains(
            'PASSWORD_CHANGED',
            $this->inboxKindsOf($persona['token']),
            'Sin este aviso nadie se entera de que le han robado la cuenta.',
        );
    }

    /**
     * `RN-2`: el interruptor **suspende**, no sobrescribe. Apagarlo devuelve
     * la configuración tal y como estaba.
     *
     * Si sobrescribiera, quien lo encendiera y lo apagara volvería con todo
     * silenciado y no sabría por qué dejó de recibir avisos.
     */
    public function testTheMasterSwitchSuspendsAndGivesEverythingBack(): void
    {
        $persona = $this->activatedPerson('persona');

        $this->mute($persona['token'], 'MENTION');

        $this->put('/api/v1/me/notification-preferences', $persona['token'], ['allMuted' => true]);
        self::assertResponseIsSuccessful();

        // Las casillas siguen diciendo lo que su dueño eligió.
        $porTipo = $this->indexed($this->preferences($persona['token']));
        self::assertFalse($porTipo['MENTION']['PLATFORM'], 'Lo que apagó, apagado.');
        self::assertTrue($porTipo['CORRECTION_RECEIVED']['PLATFORM'], 'Y lo que no tocó, encendido.');

        $this->put('/api/v1/me/notification-preferences', $persona['token'], ['allMuted' => false]);
        self::assertResponseIsSuccessful();

        $porTipo = $this->indexed($this->preferences($persona['token']));
        self::assertFalse($porTipo['MENTION']['PLATFORM'], 'Recupera su configuración, no una en blanco.');
        self::assertTrue($porTipo['CORRECTION_RECEIVED']['PLATFORM']);
    }

    /**
     * Un canal que ese aviso no admite se rechaza en vez de guardarse:
     * aceptarlo sería prometer un correo que nunca va a llegar.
     */
    public function testAChannelThatDoesNotExistForThatNoticeIsRefused(): void
    {
        $persona = $this->activatedPerson('persona');

        $this->put('/api/v1/me/notification-preferences', $persona['token'], [
            'preferences' => [['topic' => 'DIRECT_MESSAGE_RECEIVED', 'channel' => 'EMAIL', 'enabled' => true]],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('CHANNEL_NOT_AVAILABLE_FOR_TOPIC', $this->payload()['code']);

        $this->put('/api/v1/me/notification-preferences', $persona['token'], [
            'preferences' => [['topic' => 'NO_EXISTE', 'channel' => 'PLATFORM', 'enabled' => true]],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('UNKNOWN_NOTIFICATION_TOPIC', $this->payload()['code']);
    }

    /**
     * `RN-5`: silenciar un aviso **no impide que el hecho ocurra**. No querer
     * enterarse y no querer recibir son cosas distintas.
     */
    public function testSilencingANoticeDoesNotStopWhatItIsAbout(): void
    {
        [$autora, $lectora] = $this->aDeliveredCorrection();

        $this->mute($autora['token'], 'CORRECTION_RECEIVED');

        $correctionId = $this->submitAnotherCorrection($autora, $lectora);

        // El aviso no llega, y la corrección está ahí: se ha cobrado y se
        // puede leer.
        $this->client->request('GET', \sprintf('/api/v1/corrections/%s', $correctionId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$autora['token'],
        ]);
        self::assertResponseIsSuccessful('Lo que se silencia es el aviso, no el hecho.');
    }

    /**
     * El `PUT` guarda **lo que se toca** y devuelve la foto entera, que es lo
     * que la pantalla necesita para repintarse sin una segunda petición.
     */
    public function testSavingReturnsTheWholePictureAndOnlyChangesWhatWasSent(): void
    {
        $persona = $this->activatedPerson('persona');

        $this->put('/api/v1/me/notification-preferences', $persona['token'], [
            'preferences' => [['topic' => 'MENTION', 'channel' => 'EMAIL', 'enabled' => false]],
        ]);

        self::assertResponseIsSuccessful();
        $porTipo = $this->indexed($this->payload());

        self::assertFalse($porTipo['MENTION']['EMAIL']);
        self::assertTrue($porTipo['MENTION']['PLATFORM'], 'El otro canal del mismo aviso no se toca.');
        self::assertCount(\count($this->indexed($this->preferences($persona['token']))), $porTipo);
    }

    private function mute(string $token, string $topic): void
    {
        $this->put('/api/v1/me/notification-preferences', $token, [
            'preferences' => [['topic' => $topic, 'channel' => 'PLATFORM', 'enabled' => false]],
        ]);
        self::assertResponseIsSuccessful();
    }

    /**
     * @return array<string, mixed>
     */
    private function preferences(string $token): array
    {
        $this->client->request('GET', '/api/v1/me/notification-preferences', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        self::assertResponseIsSuccessful();

        return $this->payload();
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, array<string, bool>>
     */
    private function indexed(array $payload): array
    {
        /** @var list<array{topic: string, channels: array<string, bool>}> $topics */
        $topics = $payload['topics'];
        $indexed = [];

        foreach ($topics as $topic) {
            $indexed[$topic['topic']] = $topic['channels'];
        }

        return $indexed;
    }

    /**
     * @param array{token: string, userId: string} $autora
     * @param array{token: string, userId: string} $lectora
     */
    private function submitAnotherCorrection(array $autora, array $lectora): string
    {
        $workId = $this->createWork($autora['token'], 'La segunda obra');
        $chapterId = $this->addChapter($workId, $autora['token'], words: 500);
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

        $this->client->request('GET', \sprintf('/api/v1/chapters/%s/questionnaire', $chapterId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$lectora['token'],
        ]);
        /** @var list<array{questionId: string}> $questions */
        $questions = $this->payload()['questions'];

        $this->client->request('POST', \sprintf('/api/v1/chapters/%s/corrections', $chapterId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$lectora['token'],
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
        $correctionId = (string) $this->payload()['correctionId'];
        $this->capture();
        $this->consumeEverything();

        return $correctionId;
    }

    /**
     * @return list<string>
     */
    private function inboxKindsOf(string $token): array
    {
        $this->client->request('GET', '/api/v1/me/notifications', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        self::assertResponseIsSuccessful();

        /** @var list<array{kind: string}> $data */
        $data = $this->payload()['data'];

        return array_map(static fn (array $aviso): string => $aviso['kind'], $data);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function put(string $path, string $token, array $body): void
    {
        $this->client->request('PUT', $path, server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode($body, \JSON_THROW_ON_ERROR));
    }
}
