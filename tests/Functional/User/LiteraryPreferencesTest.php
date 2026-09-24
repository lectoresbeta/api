<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\User;

use Doctrine\ORM\EntityManagerInterface;
use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use Symfony\Component\HttpFoundation\Response;

/**
 * Editar las preferencias literarias (`FEAT-USR-009`).
 *
 * **Lo que esta prueba defiende no es el endpoint: es que sea el mismo dato
 * que el del onboarding** (`FEAT-USR-023` `RN-6`). Dos copias que puedan
 * discrepar significan recomendaciones que contradicen lo que la persona cree
 * haber elegido, y nada lo detectaría — ni ella, que no ve las dos.
 *
 * Y el caso que parece menor y no lo es: **un género retirado del catálogo no
 * puede hacer fallar un guardado a quien ya lo tenía**. Esa persona entra a
 * cambiar otra cosa, el cliente reenvía lo que está viendo, y sin la
 * excepción su guardado se cae por un campo que no estaba tocando.
 */
final class LiteraryPreferencesTest extends EconomyScenario
{
    public function testTheyOpenWithWhatTheOnboardingChose(): void
    {
        $person = $this->readerOf(['DRAMA', 'FANTASY', 'ROMANCE']);

        $this->preferences($person['token']);

        self::assertResponseIsSuccessful();
        self::assertSame(['FANTASY', 'ROMANCE', 'DRAMA'], $this->codes());
        self::assertSame('Fantasía', $this->payload()['genres'][0]['name'], 'Con su nombre, no solo el código.');
    }

    /**
     * El orden es el del catálogo y no el de llegada: es el orden en el que
     * la pantalla enseña los chips, y que dependa de cómo se enviaron haría
     * que la misma selección se viese distinta cada vez.
     */
    public function testTheyComeInCatalogueOrder(): void
    {
        $person = $this->readerOf(['ROMANCE', 'DRAMA', 'FANTASY']);

        $this->preferences($person['token']);

        self::assertSame(['FANTASY', 'ROMANCE', 'DRAMA'], $this->codes(), 'Fantasía, Romántica y Teatro, en ese orden.');
    }

    /**
     * `RN-1`: sustituye, no acumula. Quien quita un género espera que
     * desaparezca.
     */
    public function testSavingReplacesTheWholeSelection(): void
    {
        $person = $this->readerOf(['DRAMA', 'FANTASY', 'ROMANCE']);

        $this->save($person['token'], ['HORROR', 'THRILLER', 'CRIME']);

        self::assertResponseIsSuccessful();
        self::assertSame(['CRIME', 'THRILLER', 'HORROR'], $this->codes(), 'Y devuelve cómo ha quedado.');

        $this->preferences($person['token']);
        self::assertSame(['CRIME', 'THRILLER', 'HORROR'], $this->codes());
    }

    /**
     * **La afirmación central de la ficha.** Un solo sitio, dos puertas: lo
     * que se guarda aquí es lo que el onboarding devuelve, y al revés.
     */
    public function testItIsTheSameDataTheOnboardingWrites(): void
    {
        $person = $this->readerOf(['DRAMA', 'FANTASY', 'ROMANCE']);

        $this->save($person['token'], ['HORROR', 'THRILLER', 'CRIME']);

        self::assertSame(['CRIME', 'THRILLER', 'HORROR'], $this->storedCodesOf($person['userId']));

        // Y volver a pasar por el onboarding se ve desde aquí.
        $this->client->request('PUT', '/api/v1/me/onboarding/genres', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$person['token'],
        ], content: json_encode(['genres' => ['POETRY', 'ESSAY', 'MEMOIR']], \JSON_THROW_ON_ERROR));
        $this->capture();

        $this->preferences($person['token']);
        self::assertSame(['POETRY', 'ESSAY', 'MEMOIR'], $this->codes());
    }

    /**
     * `RN-2`. El mínimo rige también al editar: si solo valiera el primer
     * día, bastaría con terminar el onboarding y volver a esta pantalla para
     * quedarse con uno.
     */
    public function testTheMinimumOfThreeStillAppliesWhenEditing(): void
    {
        $person = $this->readerOf(['DRAMA', 'FANTASY', 'ROMANCE']);

        $this->save($person['token'], ['HORROR', 'THRILLER']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('NOT_ENOUGH_GENRES', $this->payload()['code']);

        $this->preferences($person['token']);
        self::assertSame(['FANTASY', 'ROMANCE', 'DRAMA'], $this->codes(), 'No se ha guardado nada.');
    }

    /**
     * `RN-4`: los duplicados se normalizan, con la consecuencia de que tres
     * veces el mismo género **no** llega al mínimo. Son un género.
     */
    public function testRepeatedGenresAreOneGenre(): void
    {
        $person = $this->readerOf(['DRAMA', 'FANTASY', 'ROMANCE']);

        $this->save($person['token'], ['HORROR', 'horror', 'HORROR']);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('NOT_ENOUGH_GENRES', $this->payload()['code']);

        $this->save($person['token'], ['HORROR', 'horror', 'THRILLER', 'CRIME']);
        self::assertResponseIsSuccessful();
        self::assertSame(['CRIME', 'THRILLER', 'HORROR'], $this->codes(), 'Y se guarda una sola vez.');
    }

    /**
     * `RN-5`: se rechaza **y se nombra**. Ignorarlo en silencio dejaría a
     * alguien creyendo que eligió cuatro cosas cuando se guardaron tres.
     */
    public function testAnUnknownGenreIsRefusedAndNamed(): void
    {
        $person = $this->readerOf(['DRAMA', 'FANTASY', 'ROMANCE']);

        $this->save($person['token'], ['HORROR', 'THRILLER', 'ESTEGENERONOEXISTE']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('UNKNOWN_GENRE', $this->payload()['code']);
        self::assertStringContainsString('ESTEGENERONOEXISTE', (string) $this->payload()['detail']);
    }

    /**
     * `RN-6`, la excepción que evita el guardado imposible: **conservar no es
     * elegir**. Quien ya tenía un género retirado puede seguir teniéndolo
     * mientras cambia otra cosa.
     */
    public function testARetiredGenreYouAlreadyHadCanBeKept(): void
    {
        $person = $this->readerOf(['DRAMA', 'FANTASY', 'ROMANCE']);
        $this->retire('DRAMA');

        $this->preferences($person['token']);
        self::assertContains('DRAMA', $this->codes(), 'Sigue siendo suyo, y se sigue enseñando.');

        $this->save($person['token'], ['DRAMA', 'FANTASY', 'HORROR']);

        self::assertResponseIsSuccessful();
        self::assertSame(['FANTASY', 'HORROR', 'DRAMA'], $this->codes());
    }

    /**
     * Y la otra mitad: retirado **sigue queriendo decir retirado** para quien
     * no lo tenía. Si conservarlo también valiera para elegirlo, retirar un
     * género no serviría de nada.
     */
    public function testARetiredGenreCannotBeChosenAfresh(): void
    {
        $person = $this->readerOf(['DRAMA', 'FANTASY', 'ROMANCE']);
        $this->retire('HORROR');

        $this->save($person['token'], ['HORROR', 'THRILLER', 'CRIME']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('UNKNOWN_GENRE', $this->payload()['code']);
        self::assertStringContainsString('HORROR', (string) $this->payload()['detail']);
    }

    /**
     * Un género retirado desaparece del catálogo, y eso es lo que permite al
     * cliente reconocerlo sin un campo más: está en las preferencias de
     * alguien y no en `GET /genres`.
     */
    public function testARetiredGenreLeavesTheCatalogueButNotTheSelection(): void
    {
        $person = $this->readerOf(['DRAMA', 'FANTASY', 'ROMANCE']);
        $this->retire('DRAMA');

        $this->client->request('GET', '/api/v1/genres');
        self::assertResponseIsSuccessful();
        self::assertNotContains('DRAMA', $this->codes());

        $this->preferences($person['token']);
        self::assertContains('DRAMA', $this->codes());
    }

    /**
     * `RN-8`: la selección entera, no lo que cambió. Quien lo consume quiere
     * con qué quedarse, y aplicar una secuencia de diferencias acabaría en un
     * conjunto equivocado el primer día que se pierda un mensaje.
     */
    public function testSavingAnnouncesTheWholeSelection(): void
    {
        $person = $this->readerOf(['DRAMA', 'FANTASY', 'ROMANCE']);

        $this->save($person['token'], ['HORROR', 'THRILLER', 'CRIME']);

        $anunciado = $this->lastAnnouncementOf('LiteraryPreferencesUpdated');

        self::assertSame($person['userId'], $anunciado['userId']);
        self::assertSame(['HORROR', 'THRILLER', 'CRIME'], $anunciado['genres']);
    }

    /**
     * `RN-7` y [`decision:0003`](../../../../docs/decisions/0003-write-operations-require-activated-account.md).
     * El paso del onboarding es la excepción que compra la entrada, y se
     * acaba con él: a partir de ahí, escribir exige la cuenta activada.
     */
    public function testAnUnactivatedAccountCannotEditThemButCanReadThem(): void
    {
        $token = $this->signedInWithoutActivating('pendiente');

        $this->save($token, ['HORROR', 'THRILLER', 'CRIME']);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame('ACCOUNT_NOT_ACTIVATED', $this->payload()['code']);

        $this->preferences($token);
        self::assertResponseIsSuccessful('Leer sí: la pantalla tiene que poder abrirse.');
        self::assertSame([], $this->codes());
    }

    public function testWithoutASessionThereIsNothingToRead(): void
    {
        $this->client->request('GET', '/api/v1/me/literary-preferences');
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        $this->client->request('PUT', '/api/v1/me/literary-preferences', server: [
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode(['genres' => ['HORROR', 'THRILLER', 'CRIME']], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Alguien con la cuenta activada y sus géneros ya elegidos, que es de
     * donde parte esta pantalla.
     *
     * @param list<string> $genres
     *
     * @return array{token: string, userId: string}
     */
    private function readerOf(array $genres): array
    {
        $person = $this->activatedPerson('persona');

        $this->client->request('PUT', '/api/v1/me/onboarding/profile', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$person['token'],
        ], content: json_encode(['name' => 'Ana García', 'birthDate' => '1990-05-17'], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->capture();

        $this->client->request('PUT', '/api/v1/me/onboarding/genres', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$person['token'],
        ], content: json_encode(['genres' => $genres], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->capture();

        return $person;
    }

    private function preferences(string $token): void
    {
        $this->client->request('GET', '/api/v1/me/literary-preferences', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
    }

    /**
     * @param list<string> $genres
     */
    private function save(string $token, array $genres): void
    {
        $this->client->request('PUT', '/api/v1/me/literary-preferences', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(['genres' => $genres], \JSON_THROW_ON_ERROR));

        $this->capture();
    }

    /**
     * Los códigos de la última respuesta, en el orden en que vinieron.
     *
     * @return list<string>
     */
    private function codes(): array
    {
        /** @var list<array{code: string, name: string}> $genres */
        $genres = $this->payload()['genres'];

        return array_map(static fn (array $genre): string => $genre['code'], $genres);
    }

    /**
     * Lo que hay en la tabla, sin pasar por ninguna de las dos puertas.
     *
     * @return list<string>
     */
    private function storedCodesOf(string $userId): array
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');

        /** @var list<array{genre_code: string}> $rows */
        $rows = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT g.code AS genre_code FROM user_ctx.user_genre p
             JOIN user_ctx.genre g ON g.code = p.genre_code
             WHERE p.user_id = :user ORDER BY g.position',
            ['user' => $userId],
        );

        return array_map(static fn (array $row): string => $row['genre_code'], $rows);
    }

    /**
     * Retirar un género es lo que hace el catálogo cuando deja de ofrecer
     * uno, y nunca borrarlo: hay obras y personas apuntando a su código.
     */
    private function retire(string $code): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');

        $entityManager->getConnection()->update('user_ctx.genre', ['active' => 'false'], ['code' => $code]);
        $entityManager->clear();
    }
}
