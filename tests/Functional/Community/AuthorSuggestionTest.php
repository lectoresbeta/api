<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Community;

use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use Symfony\Component\HttpFoundation\Response;

/**
 * Paso 3 del onboarding: sugerencias de autores (`FEAT-COM-016`).
 *
 * Es el único paso opcional y **el único que puede no llegar a mostrarse**:
 * en una plataforma recién lanzada no hay autores que sugerir, y ese es
 * justamente el momento en que todos los usuarios pasan por aquí. Por eso la
 * mitad de estas pruebas son sobre la lista corta, que es el caso normal de
 * los primeros meses, y no sobre la llena.
 */
final class AuthorSuggestionTest extends EconomyScenario
{
    public function testWithNobodyToSuggestTheStepIsSkipped(): void
    {
        $person = $this->onboarded('nueva', ['DRAMA', 'FANTASY', 'ROMANCE']);

        $this->suggestions($person['token']);

        self::assertResponseIsSuccessful('No hay autores es un estado normal, no un fallo.');
        self::assertFalse($this->payload()['shouldDisplay']);
        self::assertSame('NOT_ENOUGH_AUTHORS', $this->payload()['reason']);
        self::assertSame([], $this->payload()['suggestions']);
    }

    /**
     * Dos autores están por debajo del mínimo: por debajo de tres tarjetas la
     * pantalla no se percibe como una recomendación, sino como un catálogo
     * vacío.
     */
    public function testWithTwoAuthorsTheStepIsStillSkipped(): void
    {
        $this->authorPublishing('una', ['DRAMA']);
        $this->authorPublishing('otra', ['DRAMA']);

        $person = $this->onboarded('nueva', ['DRAMA', 'FANTASY', 'ROMANCE']);
        $this->suggestions($person['token']);

        self::assertFalse($this->payload()['shouldDisplay']);
        self::assertSame('NOT_ENOUGH_AUTHORS', $this->payload()['reason']);
    }

    public function testSuggestionsFollowTheChosenGenresAndSayWhy(): void
    {
        $drama = $this->authorPublishing('dramaturga', ['DRAMA']);
        $this->authorPublishing('otra', ['DRAMA']);
        $this->authorPublishing('tercera', ['DRAMA']);

        $person = $this->onboarded('nueva', ['DRAMA', 'FANTASY', 'ROMANCE']);
        $this->suggestions($person['token']);

        self::assertTrue($this->payload()['shouldDisplay']);
        self::assertNull($this->payload()['reason']);
        self::assertContains($drama['userId'], $this->ids());

        foreach ($this->payload()['suggestions'] as $suggestion) {
            self::assertSame(['DRAMA'], $suggestion['matchedGenres'], 'La tarjeta dice por qué se sugiere.');
            self::assertSame(1, $suggestion['publicationCount'], 'Obras publicadas, no mensajes del muro.');
        }
    }

    /**
     * La cadena de relleno: **es preferible proponer autores populares aunque
     * no encajen** que enseñar una pantalla casi vacía. Y se marcan, para que
     * la interfaz no prometa una afinidad que no hay.
     */
    public function testWithNobodyInThoseGenresThePopularOnesFillTheGapAndAreMarked(): void
    {
        $this->authorPublishing('una', ['THRILLER']);
        $this->authorPublishing('otra', ['THRILLER']);
        $this->authorPublishing('tercera', ['THRILLER']);

        $person = $this->onboarded('nueva', ['DRAMA', 'FANTASY', 'ROMANCE']);
        $this->suggestions($person['token']);

        self::assertTrue($this->payload()['shouldDisplay']);
        self::assertCount(3, $this->payload()['suggestions']);

        foreach ($this->payload()['suggestions'] as $suggestion) {
            self::assertSame([], $suggestion['matchedGenres'], 'Sin afinidad, y lo dice.');
        }
    }

    public function testNobodyIsSuggestedToThemselves(): void
    {
        $this->authorPublishing('una', ['DRAMA']);
        $this->authorPublishing('otra', ['DRAMA']);

        $yo = $this->authorPublishing('yo', ['DRAMA']);
        $this->chooseGenres($yo['token'], ['DRAMA', 'FANTASY', 'ROMANCE']);

        $this->suggestions($yo['token']);

        self::assertNotContains($yo['userId'], $this->ids());
    }

    public function testAnAuthorAlreadyFollowedIsNotSuggestedAgain(): void
    {
        $seguido = $this->authorPublishing('seguido', ['DRAMA']);
        $this->authorPublishing('otra', ['DRAMA']);
        $this->authorPublishing('tercera', ['DRAMA']);
        $this->authorPublishing('cuarta', ['DRAMA']);

        $person = $this->onboarded('nueva', ['DRAMA', 'FANTASY', 'ROMANCE']);
        $this->follow($person['token'], $seguido['userId']);

        $this->suggestions($person['token']);

        self::assertNotContains($seguido['userId'], $this->ids());
    }

    /**
     * Los dos silencios significan cosas distintas, y la interfaz no dice lo
     * mismo en cada caso: no hay gente, o ya los sigues a todos.
     */
    public function testFollowingEverybodyIsADifferentSilence(): void
    {
        $autores = [
            $this->authorPublishing('una', ['DRAMA']),
            $this->authorPublishing('otra', ['DRAMA']),
            $this->authorPublishing('tercera', ['DRAMA']),
        ];

        $person = $this->onboarded('nueva', ['DRAMA', 'FANTASY', 'ROMANCE']);

        foreach ($autores as $autor) {
            $this->follow($person['token'], $autor['userId']);
        }

        $this->suggestions($person['token']);

        self::assertFalse($this->payload()['shouldDisplay']);
        self::assertSame('ALREADY_FOLLOWING_ALL', $this->payload()['reason']);
    }

    /**
     * `RN-5`: seguir desde aquí es **exactamente la misma acción** que desde
     * un perfil. No hay una suscripción «de onboarding» distinta.
     */
    public function testFollowingFromHereIsTheSameSubscriptionAsAnywhereElse(): void
    {
        $autor = $this->authorPublishing('autora', ['DRAMA']);
        $person = $this->onboarded('nueva', ['DRAMA', 'FANTASY', 'ROMANCE']);

        $this->follow($person['token'], $autor['userId']);

        $this->client->request('GET', \sprintf('/api/v1/users/%s/subscription', $autor['userId']), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$person['token'],
        ]);
        self::assertResponseIsSuccessful();
        self::assertTrue($this->payload()['subscribed']);
    }

    /**
     * `RN-2`: el paso es opcional. Se puede terminar sin seguir a nadie, y
     * **también cuando el paso ni se ha mostrado**: si dependiera de haber
     * pasado por tres pantallas, una plataforma recién lanzada no completaría
     * el onboarding de nadie.
     */
    public function testTheOnboardingIsCompletedWithoutFollowingAnybody(): void
    {
        $person = $this->onboarded('nueva', ['DRAMA', 'FANTASY', 'ROMANCE']);

        $this->client->request('GET', '/api/v1/me/onboarding', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$person['token'],
        ]);
        self::assertSame('SUGGESTIONS_PENDING', $this->payload()['status']);

        $this->complete($person['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $anunciado = $this->lastAnnouncementOf('OnboardingCompleted');

        $this->client->request('GET', '/api/v1/me/onboarding', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$person['token'],
        ]);
        self::assertSame('COMPLETED', $this->payload()['status']);

        self::assertSame($person['userId'], $anunciado['userId']);
    }

    public function testCompletingTwiceIsNotAnErrorAndAnnouncesOnce(): void
    {
        $person = $this->onboarded('nueva', ['DRAMA', 'FANTASY', 'ROMANCE']);

        $this->complete($person['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertCount(1, $this->announced('OnboardingCompleted'));
        $this->capture();

        $this->complete($person['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        self::assertSame([], $this->announced('OnboardingCompleted'), 'El segundo intento no publica nada.');
        self::assertCount(1, $this->queued('OnboardingCompleted'), 'Un solo hecho en total.');
    }

    public function testTheOnboardingCannotBeCompletedOutOfOrder(): void
    {
        $token = $this->signedInWithoutActivating('nueva');

        $this->complete($token);

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame('ONBOARDING_STEP_OUT_OF_ORDER', $this->payload()['code']);
    }

    /**
     * Todo el paso funciona **con la cuenta sin activar**, que es justo cuando
     * ocurre: el onboarding pasa antes de seguir el enlace del correo.
     */
    public function testItAllWorksWithAnUnactivatedAccount(): void
    {
        $token = $this->signedInWithoutActivating('nueva');

        $this->client->request('PUT', '/api/v1/me/onboarding/profile', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(['name' => 'Ana García', 'birthDate' => '1990-05-17'], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->capture();

        $this->chooseGenres($token, ['DRAMA', 'FANTASY', 'ROMANCE']);

        $this->suggestions($token);
        self::assertResponseIsSuccessful();

        $this->complete($token);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }

    public function testWithoutASessionThereAreNoSuggestions(): void
    {
        $this->client->request('GET', '/api/v1/onboarding/author-suggestions');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * `FEAT-COM-018`: el mismo motor en la Home, con una condición más —solo
     * a quien no sigue a nadie— y menos tarjetas. Cierra el cabo suelto del
     * onboarding: quien saltó el paso 3, o a quien se le omitió, encuentra
     * aquí la vía de arreglarlo.
     */
    public function testTheHomeBlockShowsTheSameSuggestionsToSomebodyFollowingNobody(): void
    {
        $uno = $this->authorPublishing('una', ['DRAMA']);
        $this->authorPublishing('otra', ['DRAMA']);
        $this->authorPublishing('tercera', ['DRAMA']);

        $person = $this->onboarded('nueva', ['DRAMA', 'FANTASY', 'ROMANCE']);

        $this->homeSuggestions($person['token']);

        self::assertResponseIsSuccessful();
        self::assertTrue($this->payload()['shouldDisplay']);
        self::assertContains($uno['userId'], $this->ids());
    }

    /**
     * `RN-6`: el bloque desaparece en cuanto sigue a alguien, porque deja de
     * cumplirse la razón por la que existe. Y no se muestra a quien ya sigue
     * a alguien **aunque su muro esté vacío**: el problema que resuelve es no
     * seguir a nadie, no tener poco que leer.
     */
    public function testTheHomeBlockDisappearsAsSoonAsTheyFollowSomebody(): void
    {
        $uno = $this->authorPublishing('una', ['DRAMA']);
        $this->authorPublishing('otra', ['DRAMA']);
        $this->authorPublishing('tercera', ['DRAMA']);

        $person = $this->onboarded('nueva', ['DRAMA', 'FANTASY', 'ROMANCE']);
        $this->follow($person['token'], $uno['userId']);

        $this->homeSuggestions($person['token']);

        self::assertResponseIsSuccessful();
        self::assertFalse($this->payload()['shouldDisplay']);
        self::assertSame('ALREADY_FOLLOWING_SOMEBODY', $this->payload()['reason']);
        self::assertSame([], $this->payload()['suggestions']);
    }

    /**
     * `RN-5`: sin candidatos el bloque no se pinta. No se sustituye por un
     * mensaje vacío que no ofrece salida.
     */
    public function testWithoutCandidatesTheHomeBlockIsNotShownEither(): void
    {
        $person = $this->onboarded('nueva', ['DRAMA', 'FANTASY', 'ROMANCE']);

        $this->homeSuggestions($person['token']);

        self::assertResponseIsSuccessful();
        self::assertFalse($this->payload()['shouldDisplay']);
        self::assertSame('NOT_ENOUGH_AUTHORS', $this->payload()['reason']);
    }

    /**
     * `RN-2` y `RN-3`: las sugerencias son **las mismas** en los dos sitios.
     * La prueba de que el criterio no está escrito dos veces.
     */
    public function testBothScreensAgreeForTheSamePersonAtTheSameMoment(): void
    {
        $this->authorPublishing('una', ['DRAMA']);
        $this->authorPublishing('otra', ['DRAMA']);
        $this->authorPublishing('tercera', ['DRAMA']);

        $person = $this->onboarded('nueva', ['DRAMA', 'FANTASY', 'ROMANCE']);

        $this->suggestions($person['token']);
        $enOnboarding = $this->ids();

        $this->homeSuggestions($person['token']);
        $enHome = $this->ids();

        self::assertSame($enOnboarding, $enHome);
    }

    /**
     * `RN-7`: solo se sugieren cuentas **en uso**.
     *
     * Una sin activar no puede publicar, así que proponer seguirla no lleva a
     * ninguna parte; y a una expulsada, menos. El agujero real no era la
     * primera —publicar ya exige activarse— sino la segunda: quien publicó y
     * después fue expulsado se seguía sugiriendo, porque la proyección de
     * `Community` no conoce el estado de una cuenta. Y no debería: es de
     * `User`, y llega por contrato.
     */
    public function testAnExpelledAuthorIsNoLongerSuggested(): void
    {
        $expulsada = $this->authorPublishing('expulsada', ['DRAMA']);
        $this->authorPublishing('otra', ['DRAMA']);
        $this->authorPublishing('tercera', ['DRAMA']);

        $person = $this->onboarded('nueva', ['DRAMA', 'FANTASY', 'ROMANCE']);
        $this->suggestions($person['token']);
        self::assertContains($expulsada['userId'], $this->ids(), 'Antes de la sanción se sugiere.');

        $moderadora = $this->moderator('moderadora');
        $this->client->request('POST', '/api/v1/admin/sanctions', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$moderadora['token'],
        ], content: json_encode([
            'userId' => $expulsada['userId'],
            'type' => 'EXPULSION',
            'reason' => 'Plagio reiterado',
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->capture();
        $this->consumeEverything();

        $this->suggestions($person['token']);
        self::assertNotContains($expulsada['userId'], $this->ids(), 'Después, no.');
    }

    private function homeSuggestions(string $token): void
    {
        $this->client->request('GET', '/api/v1/home/author-suggestions', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
    }

    private function suggestions(string $token): void
    {
        $this->client->request('GET', '/api/v1/onboarding/author-suggestions', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
    }

    private function complete(string $token): void
    {
        $this->client->request('POST', '/api/v1/me/onboarding/complete', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
    }

    /**
     * @return list<string>
     */
    private function ids(): array
    {
        self::assertResponseIsSuccessful();

        return array_values(array_map(
            static fn (array $one): string => (string) $one['userId'],
            $this->payload()['suggestions'],
        ));
    }

    /**
     * Alguien que ha terminado los dos primeros pasos y está en el tercero.
     *
     * @param list<string> $genres
     *
     * @return array{token: string, userId: string}
     */
    private function onboarded(string $local, array $genres): array
    {
        $person = $this->activatedPerson($local);

        $this->client->request('PUT', '/api/v1/me/onboarding/profile', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$person['token'],
        ], content: json_encode(['name' => 'Ana '.$local, 'birthDate' => '1990-05-17'], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->capture();

        $this->chooseGenres($person['token'], $genres);

        return $person;
    }

    /**
     * Un autor con una obra publicada en esos géneros, que es lo que le hace
     * sugerible.
     *
     * @param list<string> $genres
     *
     * @return array{token: string, userId: string}
     */
    private function authorPublishing(string $local, array $genres): array
    {
        $author = $this->onboarded($local, ['DRAMA', 'FANTASY', 'ROMANCE']);

        $workId = $this->createWork($author['token'], 'La obra de '.$local);
        $this->addChapter($workId, $author['token'], words: 600);

        $this->client->request('PUT', \sprintf('/api/v1/works/%s/genres', $workId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$author['token'],
        ], content: json_encode(['genres' => $genres], \JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
        $this->capture();

        $this->putAs(\sprintf('/api/v1/works/%s/status', $workId), $author['token'], ['status' => 'PUBLISHED']);
        $this->consumeEverything();

        return $author;
    }

    /**
     * @param list<string> $genres
     */
    private function chooseGenres(string $token, array $genres): void
    {
        $this->client->request('PUT', '/api/v1/me/onboarding/genres', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(['genres' => $genres], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->capture();
        $this->consumeEverything();
    }

    private function follow(string $token, string $userId): void
    {
        $this->client->request('PUT', \sprintf('/api/v1/users/%s/subscription', $userId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->capture();
        $this->consumeEverything();
    }
}
