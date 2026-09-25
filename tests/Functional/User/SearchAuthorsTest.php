<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\User;

use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use Symfony\Component\HttpFoundation\Response;

/**
 * Buscar personas (`FEAT-USR-017`).
 *
 * **Lo que estas pruebas defienden no es encontrar, es no encontrar.** Un
 * buscador de personas es la forma cómoda de saltarse todos los ajustes de
 * privacidad de la plataforma: quien se esconde de un perfil aparecería en un
 * listado, y quien bloqueó a alguien se lo encontraría en una caja de
 * búsqueda.
 *
 * Por eso la mitad de los casos comprueban ausencias, y por eso el primero de
 * todos es que **no se busca por correo**.
 */
final class SearchAuthorsTest extends EconomyScenario
{
    /**
     * `RN-2`: **no se busca por correo**, nunca.
     *
     * Responder si una dirección tiene cuenta es exactamente lo que el alta y
     * la recuperación de contraseña se cuidan de no decir, y un buscador que
     * lo hiciera tiraría por tierra las dos.
     */
    public function testNobodyIsFoundByTheirEmail(): void
    {
        $quien = $this->activatedPerson('quienbusca');
        $this->activatedPerson('escondida');

        self::assertSame([], $this->search($quien['token'], $this->address('escondida')));
        self::assertSame([], $this->search($quien['token'], 'escondida+'.$this->run.'@ejemplo.com'));
    }

    /**
     * Lo que sí encuentra: parte del nombre de usuario y parte del nombre.
     */
    public function testAPartialNameOrUsernameFindsSomebody(): void
    {
        $quien = $this->activatedPerson('quienbusca');
        $buscada = $this->activatedPerson('buscada');

        $this->nameYourself($buscada['token'], 'Aurora Boreal');

        self::assertContains($buscada['userId'], $this->search($quien['token'], 'buscada'));
        self::assertContains($buscada['userId'], $this->search($quien['token'], 'Aurora'));
        self::assertContains($buscada['userId'], $this->search($quien['token'], 'borea'), 'Sin distinguir mayúsculas.');
    }

    /**
     * `RN-7`: sin criterios no hay resultados.
     *
     * Un buscador que con la caja vacía devuelve el padrón entero **es** el
     * padrón entero.
     */
    public function testWithoutCriteriaThereIsNothingToReturn(): void
    {
        $quien = $this->activatedPerson('quienbusca');
        $this->activatedPerson('otra');

        $this->client->request('GET', '/api/v1/authors', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$quien['token'],
        ]);

        self::assertResponseIsSuccessful('Pedir «todo el mundo» no es un error, es que no es una búsqueda.');
        self::assertSame([], $this->payload()['authors']);
    }

    /**
     * `RN-6`: un `%` tecleado por alguien no convierte su búsqueda en
     * «devuélvemelo todo».
     */
    public function testAWildcardIsJustACharacter(): void
    {
        $quien = $this->activatedPerson('quienbusca');
        $this->activatedPerson('otra');

        self::assertSame([], $this->search($quien['token'], '%'));
        self::assertSame([], $this->search($quien['token'], '_'));
    }

    /**
     * La temática: los géneros que esa persona declaró.
     */
    public function testAGenreFindsWhoeverDeclaredIt(): void
    {
        $quien = $this->activatedPerson('quienbusca');
        $terror = $this->activatedPerson('terror');
        $romance = $this->activatedPerson('romance');

        // Tres como mínimo: elegir géneros es elegir un conjunto y la
        // pantalla lo exige (`FEAT-USR-009`).
        $this->chooseGenres($terror['token'], ['HORROR', 'THRILLER', 'CRIME']);
        $this->chooseGenres($romance['token'], ['ROMANCE', 'DRAMA', 'HISTORICAL']);

        $encontrados = $this->searchByGenre($quien['token'], 'HORROR');

        self::assertContains($terror['userId'], $encontrados);
        self::assertNotContains($romance['userId'], $encontrados);
    }

    /**
     * Texto y género se combinan con **y**: quien manda los dos pide las dos
     * cosas.
     */
    public function testTextAndGenreAreCombined(): void
    {
        $quien = $this->activatedPerson('quienbusca');
        $terror = $this->activatedPerson('terror');
        $this->chooseGenres($terror['token'], ['HORROR', 'THRILLER', 'CRIME']);

        $this->client->request('GET', '/api/v1/authors?q=terror&genre=ROMANCE', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$quien['token'],
        ]);
        self::assertResponseIsSuccessful();

        self::assertSame([], $this->payload()['authors'], 'Encaja el texto y no el género.');
    }

    /**
     * `RN-3`: **el ajuste de privacidad manda**.
     *
     * Quien se esconde de su propio perfil no puede aparecer en un listado:
     * sería la puerta de atrás del mismo ajuste.
     */
    public function testARestrictedProfileDoesNotAppear(): void
    {
        $quien = $this->activatedPerson('quienbusca');
        $oculta = $this->activatedPerson('oculta');

        $this->restrict($oculta['token'], 'NOBODY');

        self::assertNotContains($oculta['userId'], $this->search($quien['token'], 'oculta'));
        self::assertContains(
            $oculta['userId'],
            $this->search($oculta['token'], 'oculta'),
            'RN-5: pero ella se sigue viendo.',
        );
    }

    /**
     * Y `FOLLOWERS` aparece **solo para quien le sigue**.
     */
    public function testAFollowersOnlyProfileAppearsOnlyToItsFollowers(): void
    {
        $quien = $this->activatedPerson('quienbusca');
        $reservada = $this->activatedPerson('reservada');

        $this->restrict($reservada['token'], 'FOLLOWERS');

        self::assertNotContains($reservada['userId'], $this->search($quien['token'], 'reservada'));

        $this->follow($quien['token'], $reservada['userId']);

        self::assertContains($reservada['userId'], $this->search($quien['token'], 'reservada'));
    }

    /**
     * `RN-4`: un bloqueo **esconde en los dos sentidos**.
     *
     * Es la mitad que se olvida: quien bloquea deja de ver, y quien fue
     * bloqueado también. Mirar una sola dirección funcionaría casi siempre,
     * que es la peor clase de fallo.
     */
    public function testABlockHidesBothWays(): void
    {
        $bloqueadora = $this->activatedPerson('bloqueadora');
        $bloqueada = $this->activatedPerson('bloqueada');

        $this->client->request('PUT', \sprintf('/api/v1/users/%s/block', $bloqueada['userId']), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$bloqueadora['token'],
        ]);
        self::assertResponseIsSuccessful();
        $this->capture();
        $this->consumeEverything();

        self::assertNotContains(
            $bloqueada['userId'],
            $this->search($bloqueadora['token'], 'bloqueada'),
            'Quien bloqueó no la encuentra.',
        );
        self::assertNotContains(
            $bloqueadora['userId'],
            $this->search($bloqueada['token'], 'bloqueadora'),
            'Y la bloqueada tampoco a ella.',
        );
    }

    /**
     * `RN-1`: una cuenta que no está activa no aparece.
     */
    public function testAnUnactivatedAccountIsNotInTheDirectory(): void
    {
        $quien = $this->activatedPerson('quienbusca');
        $this->signedInWithoutActivating('pendiente');

        self::assertSame([], $this->search($quien['token'], 'pendiente'));
    }

    public function testWithoutASessionThereIsNoSearch(): void
    {
        $this->client->request('GET', '/api/v1/authors?q=alguien');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @return list<string>
     */
    private function search(string $token, string $term): array
    {
        $this->client->request('GET', '/api/v1/authors?q='.rawurlencode($term), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        self::assertResponseIsSuccessful();

        /** @var list<string> $ids */
        $ids = array_column($this->payload()['authors'], 'userId');

        return $ids;
    }

    /**
     * @return list<string>
     */
    private function searchByGenre(string $token, string $genre): array
    {
        $this->client->request('GET', '/api/v1/authors?genre='.$genre, server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        self::assertResponseIsSuccessful();

        /** @var list<string> $ids */
        $ids = array_column($this->payload()['authors'], 'userId');

        return $ids;
    }

    private function nameYourself(string $token, string $name): void
    {
        $this->client->request('PATCH', '/api/v1/me/profile', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(['name' => $name], \JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        $this->capture();
    }

    /**
     * @param list<string> $genres
     */
    private function chooseGenres(string $token, array $genres): void
    {
        $this->client->request('PUT', '/api/v1/me/literary-preferences', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(['genres' => $genres], \JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        $this->capture();
        $this->consumeEverything();
    }

    private function restrict(string $token, string $audience): void
    {
        $this->client->request('PUT', '/api/v1/me/privacy-settings', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(['profileVisibility' => $audience], \JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        $this->capture();
        $this->consumeEverything();
    }

    private function follow(string $token, string $userId): void
    {
        $this->client->request('PUT', \sprintf('/api/v1/users/%s/subscription', $userId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseIsSuccessful();
        $this->capture();
        $this->consumeEverything();
    }
}
