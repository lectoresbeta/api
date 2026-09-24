<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\User;

use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use Symfony\Component\HttpFoundation\Response;

/**
 * Los ajustes de privacidad (`FEAT-USR-038`).
 *
 * **No son preferencias de visualización: son reglas de autorización.** Esa
 * es la única lectura correcta, y lo que se defiende aquí es precisamente
 * eso: que el servidor las aplique al llamar al endpoint directamente, y no
 * el cliente escondiendo botones.
 */
final class PrivacySettingsTest extends EconomyScenario
{
    /**
     * `RN-4`: una cuenta nace con valores por defecto **explícitos**. Un
     * ajuste ausente es lo que alguien acaba leyendo como «todo permitido».
     */
    public function testAnAccountIsBornWithExplicitDefaults(): void
    {
        $person = $this->activatedPerson('persona');

        $this->settings($person['token']);

        self::assertResponseIsSuccessful();
        self::assertSame('EVERYONE', $this->payload()['profileVisibility']);
        self::assertSame('EVERYONE', $this->payload()['commentPermission']);
        self::assertSame('EVERYONE', $this->payload()['messagePermission']);
    }

    /**
     * Lo que no se envía se queda como estaba: la pantalla manda un
     * desplegable cada vez, y obligar a enviar los tres haría que el cliente
     * reenviase valores que no ha leído.
     */
    public function testChangingOneSettingLeavesTheOthersAlone(): void
    {
        $person = $this->activatedPerson('persona');

        $this->change($person['token'], ['commentPermission' => 'NOBODY']);

        self::assertResponseIsSuccessful();
        self::assertSame('NOBODY', $this->payload()['commentPermission']);
        self::assertSame('EVERYONE', $this->payload()['profileVisibility']);
        self::assertSame('EVERYONE', $this->payload()['messagePermission']);

        $this->settings($person['token']);
        self::assertSame('NOBODY', $this->payload()['commentPermission'], 'Y se ha guardado.');
    }

    /**
     * Un valor desconocido **se rechaza, nunca se interpreta**: tomarlo por
     * «todos» abriría una puerta que su dueño cree cerrada, y tomarlo por
     * «nadie» cerraría una que cree abierta.
     */
    public function testAnUnknownValueIsRefusedInsteadOfGuessed(): void
    {
        $person = $this->activatedPerson('persona');

        $this->change($person['token'], ['commentPermission' => 'SOLO_LOS_BUENOS']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('UNKNOWN_AUDIENCE', $this->payload()['code']);

        $this->settings($person['token']);
        self::assertSame('EVERYONE', $this->payload()['commentPermission'], 'No se ha tocado nada.');
    }

    public function testNobodyReadsSomebodyElsesSettings(): void
    {
        $this->activatedPerson('persona');

        $this->client->request('GET', '/api/v1/me/privacy-settings');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * **El techo, que es la razón de ser de esta ficha** (`RN-2`, `S-14`). Una
     * obra `PUBLIC` no pasa por encima de un perfil cerrado: si el ajuste
     * global fuese perdible por una obra, quien lo endureciera creería haber
     * cerrado una puerta que sigue abierta en cada obra publicada.
     */
    public function testAClosedProfileBeatsAPublicWork(): void
    {
        [$author, $lectora, $chapterId] = $this->aWorkOpenForCorrection();

        $this->start($chapterId, $lectora['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED, 'Antes se podía.');

        $this->discard($chapterId, $lectora['token']);
        $this->change($author['token'], ['commentPermission' => 'NOBODY']);

        $this->start($chapterId, $lectora['token']);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame('AUTHOR_DOES_NOT_ACCEPT_COMMENTS', $this->payload()['code']);
    }

    /**
     * Y no gana ni quien ya tiene acceso concedido: lo que se cerró es la
     * puerta de comentar, no la de entrar.
     */
    public function testNotEvenABetaReaderWithAccessGetsPast(): void
    {
        [$author, $lectora, $chapterId] = $this->aWorkOpenForCorrection();

        $this->start($chapterId, $lectora['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->consumeEverything();

        $this->change($author['token'], ['commentPermission' => 'NOBODY']);

        $this->start($chapterId, $lectora['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    /**
     * `FOLLOWERS` se comporta hoy como `NOBODY`, y **es la respuesta
     * correcta**: nadie puede seguir a nadie todavía, así que el conjunto de
     * seguidores de cualquier autor está vacío.
     *
     * Conviene ver hacia qué lado falla. Si algún día se olvida volver aquí,
     * `FOLLOWERS` sigue comportándose como `NOBODY`: un ajuste demasiado
     * estricto, que su dueño nota y del que se queja. La equivocación
     * contraria no la nota nadie.
     */
    public function testFollowersMeansNobodyWhileNobodyCanFollow(): void
    {
        [$author, $lectora, $chapterId] = $this->aWorkOpenForCorrection();

        $this->change($author['token'], ['commentPermission' => 'FOLLOWERS']);

        $this->start($chapterId, $lectora['token']);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame('AUTHOR_DOES_NOT_ACCEPT_COMMENTS', $this->payload()['code']);
    }

    /**
     * `RN-3` y `S-14`: relajar el ajuste devuelve a cada obra la modalidad que
     * su autor eligió. Endurecerlo **no reescribe** las obras, así que volver
     * atrás es posible — si las reescribiera, al relajar el perfil nadie
     * sabría qué modalidad tenía antes cada una.
     */
    public function testRelaxingTheSettingGivesEachWorkItsModeBack(): void
    {
        [$author, $lectora, $chapterId] = $this->aWorkOpenForCorrection();

        $this->change($author['token'], ['commentPermission' => 'NOBODY']);
        $this->start($chapterId, $lectora['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $this->change($author['token'], ['commentPermission' => 'EVERYONE']);

        $this->start($chapterId, $lectora['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
    }

    /**
     * `profileVisibility` en `NOBODY` **vuelve la cuenta invisible**: deja de
     * aparecer al buscar a quién invitar. Es el ajuste que más sorprende y el
     * que hace de esta ficha algo más que una pantalla de configuración.
     */
    public function testAnInvisibleAccountDoesNotTurnUpWhenSearchingWhoToInvite(): void
    {
        $author = $this->activatedPerson('autora');
        $lectora = $this->namedPerson('lectora', 'Ana García');
        $workId = $this->aPrivateWork($author);

        $this->searchInvitable($workId, $author['token'], 'García');
        self::assertSame([$lectora['userId']], $this->foundIds(), 'Antes aparecía.');

        $this->change($lectora['token'], ['profileVisibility' => 'NOBODY']);

        $this->searchInvitable($workId, $author['token'], 'García');
        self::assertSame([], $this->foundIds());
    }

    /**
     * @return array{array{token: string, userId: string}, array{token: string, userId: string}, string}
     */
    private function aWorkOpenForCorrection(): array
    {
        $author = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');

        $workId = $this->createWork($author['token'], 'La ciudad de los pájaros');
        $chapterId = $this->addChapter($workId, $author['token'], words: 900);
        $this->saveQuestionnaire($workId, $author['token'], [
            ['statement' => '¿Cómo funciona el ritmo?', 'minWords' => 50],
        ]);
        $this->put(\sprintf('/api/v1/works/%s/access-mode', $workId), $author['token'], ['accessMode' => 'PUBLIC']);
        $this->put(\sprintf('/api/v1/works/%s/status', $workId), $author['token'], ['status' => 'PUBLISHED']);
        $this->put(\sprintf('/api/v1/works/%s/status', $workId), $author['token'], ['status' => 'IN_CORRECTION']);
        $this->consumeEverything();

        return [$author, $lectora, $chapterId];
    }

    /**
     * @param array{token: string, userId: string} $author
     */
    private function aPrivateWork(array $author): string
    {
        $workId = $this->createWork($author['token'], 'La obra privada');
        $this->addChapter($workId, $author['token'], words: 900);
        $this->put(\sprintf('/api/v1/works/%s/status', $workId), $author['token'], ['status' => 'PUBLISHED']);
        $this->put(\sprintf('/api/v1/works/%s/access-mode', $workId), $author['token'], ['accessMode' => 'PRIVATE']);

        return $workId;
    }

    /**
     * @return array{token: string, userId: string}
     */
    private function namedPerson(string $local, string $name): array
    {
        $person = $this->activatedPerson($local);

        $this->client->request('PUT', '/api/v1/me/onboarding/profile', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$person['token'],
        ], content: json_encode(['name' => $name, 'birthDate' => '1990-05-17'], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->capture();

        return $person;
    }

    private function settings(string $token): void
    {
        $this->client->request('GET', '/api/v1/me/privacy-settings', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
    }

    /**
     * @param array<string, string> $body
     */
    private function change(string $token, array $body): void
    {
        $this->client->request('PUT', '/api/v1/me/privacy-settings', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode($body, \JSON_THROW_ON_ERROR));

        $this->capture();
    }

    private function start(string $chapterId, string $token): void
    {
        $this->client->request('POST', \sprintf('/api/v1/chapters/%s/corrections/start', $chapterId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        $this->capture();
    }

    private function discard(string $chapterId, string $token): void
    {
        $this->client->request('DELETE', \sprintf('/api/v1/chapters/%s/correction/draft', $chapterId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseIsSuccessful();
        $this->capture();
        $this->consumeEverything();
    }

    private function searchInvitable(string $workId, string $token, string $query): void
    {
        $this->client->request(
            'GET',
            \sprintf('/api/v1/works/%s/invitable-readers?%s', $workId, http_build_query(['query' => $query])),
            server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token],
        );

        self::assertResponseIsSuccessful();
    }

    /**
     * @return list<string>
     */
    private function foundIds(): array
    {
        /** @var list<array<string, mixed>> $readers */
        $readers = $this->payload()['readers'];

        return array_map(static fn (array $reader): string => (string) $reader['userId'], $readers);
    }

    /**
     * @param array<string, string> $body
     */
    private function put(string $path, string $token, array $body): void
    {
        $this->client->request('PUT', $path, server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode($body, \JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        $this->capture();
    }
}
