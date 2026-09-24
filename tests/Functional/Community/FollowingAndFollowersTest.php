<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Community;

use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use Symfony\Component\HttpFoundation\Response;

/**
 * Seguidos y seguidores (`FEAT-COM-027`).
 *
 * **Lo que se defiende aquí es `CM-14`, la pregunta que `FEAT-COM-010` dejó
 * aplazada**: quién puede ver el grafo de quién. La respuesta tiene dos
 * mitades, y la segunda es la que se olvidaría:
 *
 * 1. la lista se ve tanto como el perfil que la tiene;
 * 2. **cada fila se ve tanto como la persona que la ocupa**.
 *
 * Sin la segunda, cerrar el perfil no serviría de nada: bastaría con abrir los
 * seguidores de cualquier autor conocido para encontrar a quien no quiere ser
 * encontrado.
 */
final class FollowingAndFollowersTest extends EconomyScenario
{
    public function testTheListsShowWhoFollowsWhom(): void
    {
        $autora = $this->person('autora');
        $lectora = $this->person('lectora');

        $this->follow($lectora['token'], $autora['userId']);

        // La autora no sigue a nadie: seguir es asimétrico.
        $this->subscriptions($autora['userId'], $lectora['token']);
        self::assertResponseIsSuccessful();
        self::assertSame([], $this->ids());

        $this->subscriptions($lectora['userId'], $lectora['token']);
        self::assertSame([$autora['userId']], $this->ids());

        $this->subscribers($autora['userId'], $lectora['token']);
        self::assertSame([$lectora['userId']], $this->ids());
    }

    /**
     * `RN-7`: tarjeta de perfil, no identificadores. Una lista de UUID
     * obligaría al cliente a una petición por fila para poder pintarla.
     */
    public function testEachRowIsAProfileCard(): void
    {
        $autora = $this->person('autora', 'Ana García');
        $lectora = $this->person('lectora');

        $this->follow($lectora['token'], $autora['userId']);

        $this->subscriptions($lectora['userId'], $lectora['token']);

        $fila = $this->payload()['data'][0];
        self::assertSame(['userId', 'username', 'name', 'avatarUrl', 'subscribedAt'], array_keys($fila));
        self::assertSame('Ana García', $fila['name']);
        self::assertSame($autora['username'], $fila['username']);
    }

    /**
     * `RN-1`: de lo más reciente a lo más antiguo, que es como se lee una
     * lista de «a quién he seguido».
     */
    public function testTheMostRecentComesFirst(): void
    {
        $lectora = $this->person('lectora');
        $primera = $this->person('primera');
        $segunda = $this->person('segunda');

        $this->follow($lectora['token'], $primera['userId']);
        $this->follow($lectora['token'], $segunda['userId']);

        $this->subscriptions($lectora['userId'], $lectora['token']);

        self::assertSame([$segunda['userId'], $primera['userId']], $this->ids());
    }

    /**
     * Por cursor, y la segunda página **ni repite ni se salta a nadie**. Es
     * lo único que hay que comprobar de una paginación.
     */
    public function testItPaginatesByCursor(): void
    {
        $lectora = $this->person('lectora');
        $seguidos = [];

        foreach (['una', 'dos', 'tres'] as $local) {
            $persona = $this->person($local);
            $this->follow($lectora['token'], $persona['userId']);
            $seguidos[] = $persona['userId'];
        }

        $this->subscriptions($lectora['userId'], $lectora['token'], 'limit=2');
        self::assertCount(2, $this->payload()['data']);
        self::assertTrue($this->payload()['pageInfo']['hasNextPage']);
        $primera = $this->ids();
        $cursor = (string) $this->payload()['pageInfo']['nextCursor'];

        $this->subscriptions($lectora['userId'], $lectora['token'], 'limit=2&cursor='.urlencode($cursor));
        self::assertFalse($this->payload()['pageInfo']['hasNextPage']);

        self::assertSame(array_reverse($seguidos), [...$primera, ...$this->ids()], 'Las tres, sin repetir ni saltarse ninguna.');
    }

    public function testAManipulatedCursorIsRefused(): void
    {
        $lectora = $this->person('lectora');

        $this->subscriptions($lectora['userId'], $lectora['token'], 'cursor=noesuncursor');

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('INVALID_CURSOR', $this->payload()['code']);
    }

    /**
     * **La regla que hace que cerrar el perfil signifique algo** (`RN-3`).
     * Quien está en `NOBODY` no aparece en la lista de nadie, ni siquiera en
     * la de alguien a quien sigue.
     */
    public function testSomebodyHiddenDoesNotAppearInAnybodysList(): void
    {
        $autora = $this->person('autora');
        $escondida = $this->person('escondida');
        $curiosa = $this->person('curiosa');

        $this->follow($escondida['token'], $autora['userId']);
        $this->restrict($escondida['token'], 'NOBODY');

        $this->subscribers($autora['userId'], $curiosa['token']);

        self::assertResponseIsSuccessful();
        self::assertSame([], $this->ids(), 'No sale, y la lista sigue existiendo.');
    }

    /**
     * Y `FOLLOWERS` a medias: aparece **solo para quien le sigue**. Es la
     * misma regla del perfil, aplicada fila a fila.
     */
    public function testSomebodyRestrictedToFollowersAppearsOnlyForTheirFollowers(): void
    {
        $autora = $this->person('autora');
        $reservada = $this->person('reservada');
        $curiosa = $this->person('curiosa');

        $this->follow($reservada['token'], $autora['userId']);
        $this->restrict($reservada['token'], 'FOLLOWERS');

        $this->subscribers($autora['userId'], $curiosa['token']);
        self::assertSame([], $this->ids(), 'La curiosa no la sigue.');

        $this->follow($curiosa['token'], $reservada['userId']);

        $this->subscribers($autora['userId'], $curiosa['token']);
        self::assertSame([$reservada['userId']], $this->ids(), 'Ahora sí.');
    }

    /**
     * `RN-2`: las listas de un perfil que no se puede ver responden lo mismo
     * que el perfil. Nunca `403` — confirmaría que la cuenta está ahí.
     */
    public function testTheListsOfAHiddenProfileAnswerLikeAProfileThatIsNotThere(): void
    {
        $escondida = $this->person('escondida');
        $curiosa = $this->person('curiosa');

        $this->restrict($escondida['token'], 'NOBODY');

        foreach (['subscriptions', 'subscribers'] as $lista) {
            $this->client->request('GET', \sprintf('/api/v1/users/%s/%s', $escondida['userId'], $lista), server: [
                'HTTP_AUTHORIZATION' => 'Bearer '.$curiosa['token'],
            ]);

            self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND, $lista);
            self::assertSame('PROFILE_NOT_FOUND', $this->payload()['code']);
        }
    }

    /**
     * Su titular **siempre se ve a sí mismo**, por lo mismo que ve su propio
     * perfil: esconderle lo suyo sería absurdo, y es lo que le permite
     * comprobar qué ha cerrado.
     */
    public function testTheOwnerSeesTheirOwnListsEvenWithTheProfileClosed(): void
    {
        $autora = $this->person('autora');
        $escondida = $this->person('escondida');

        $this->follow($escondida['token'], $autora['userId']);
        $this->restrict($escondida['token'], 'NOBODY');

        $this->subscriptions($escondida['userId'], $escondida['token']);

        self::assertResponseIsSuccessful();
        self::assertSame([$autora['userId']], $this->ids());
    }

    /**
     * **Públicas**, como el perfil al que pertenecen: una lista que solo se ve
     * con sesión haría inútil compartir el perfil que la contiene. Y ya
     * filtrada — sin sesión solo se ve lo público.
     */
    public function testWithoutASessionTheListsShowWhatIsPublic(): void
    {
        $autora = $this->person('autora');
        $abierta = $this->person('abierta');
        $escondida = $this->person('escondida');

        $this->follow($abierta['token'], $autora['userId']);
        $this->follow($escondida['token'], $autora['userId']);
        $this->restrict($escondida['token'], 'NOBODY');

        $this->subscribers($autora['userId']);

        self::assertResponseIsSuccessful();
        self::assertSame([$abierta['userId']], $this->ids());
    }

    /**
     * `RN-5`, que es la consecuencia rara de filtrar después de paginar: una
     * página puede venir **corta, o vacía**, y seguir teniendo siguiente. Lo
     * que dice si hay más es el cursor, nunca cuántas filas llegaron.
     */
    public function testAPageCanComeBackEmptyAndStillHaveANextOne(): void
    {
        $autora = $this->person('autora');
        $curiosa = $this->person('curiosa');
        $visible = $this->person('visible');

        // El orden importa para el caso: la visible sigue primero, así que
        // las dos escondidas son las filas más recientes y ocupan la primera
        // página entera.
        $this->follow($visible['token'], $autora['userId']);

        foreach (['unaescondida', 'otraescondida'] as $local) {
            $escondida = $this->person($local);
            $this->follow($escondida['token'], $autora['userId']);
            $this->restrict($escondida['token'], 'NOBODY');
        }

        $this->subscribers($autora['userId'], $curiosa['token'], 'limit=2');

        self::assertResponseIsSuccessful();
        self::assertSame([], $this->ids(), 'Página vacía…');
        self::assertTrue($this->payload()['pageInfo']['hasNextPage'], '…y sin embargo hay más.');

        $this->subscribers($autora['userId'], $curiosa['token'], 'limit=2&cursor='.urlencode((string) $this->payload()['pageInfo']['nextCursor']));
        self::assertSame([$visible['userId']], $this->ids());
    }

    /**
     * Dejar de seguir retira a esa persona de las dos listas. Es la misma
     * tabla, así que no hay proyección que pueda quedarse atrás.
     */
    public function testUnfollowingRemovesThePersonFromBothLists(): void
    {
        $autora = $this->person('autora');
        $lectora = $this->person('lectora');

        $this->follow($lectora['token'], $autora['userId']);
        $this->unfollow($lectora['token'], $autora['userId']);

        $this->subscriptions($lectora['userId'], $lectora['token']);
        self::assertSame([], $this->ids());

        $this->subscribers($autora['userId'], $lectora['token']);
        self::assertSame([], $this->ids());
    }

    public function testAnIdentifierThatIsNotOneAnswersLikeAProfileThatIsNotThere(): void
    {
        $curiosa = $this->person('curiosa');

        foreach (['lo-que-sea', '0192f000-0000-7000-8000-000000000000'] as $userId) {
            $this->subscriptions($userId, $curiosa['token']);

            self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND, $userId);
            self::assertSame('PROFILE_NOT_FOUND', $this->payload()['code']);
        }
    }

    /**
     * Alguien con la cuenta activada y con nombre, que es lo que estas listas
     * enseñan.
     *
     * @return array{token: string, userId: string, username: string}
     */
    private function person(string $local, string $name = 'Alguien Con Nombre'): array
    {
        $person = $this->activatedPerson($local);

        $this->client->request('PUT', '/api/v1/me/onboarding/profile', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$person['token'],
        ], content: json_encode(['name' => $name, 'birthDate' => '1990-05-17'], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->capture();

        $this->client->request('GET', '/api/v1/me/profile', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$person['token'],
        ]);
        self::assertResponseIsSuccessful();

        return [...$person, 'username' => (string) $this->payload()['username']];
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

    private function unfollow(string $token, string $userId): void
    {
        $this->client->request('DELETE', \sprintf('/api/v1/users/%s/subscription', $userId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
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
    }

    private function subscriptions(string $userId, ?string $token = null, string $query = ''): void
    {
        $this->listOf($userId, 'subscriptions', $token, $query);
    }

    private function subscribers(string $userId, ?string $token = null, string $query = ''): void
    {
        $this->listOf($userId, 'subscribers', $token, $query);
    }

    private function listOf(string $userId, string $list, ?string $token, string $query): void
    {
        $this->client->request(
            'GET',
            \sprintf('/api/v1/users/%s/%s%s', $userId, $list, '' === $query ? '' : '?'.$query),
            server: null === $token ? [] : ['HTTP_AUTHORIZATION' => 'Bearer '.$token],
        );
    }

    /**
     * Los identificadores de la última lista, en el orden en que vinieron.
     *
     * @return list<string>
     */
    private function ids(): array
    {
        /** @var list<array{userId: string}> $data */
        $data = $this->payload()['data'];

        return array_map(static fn (array $person): string => $person['userId'], $data);
    }
}
