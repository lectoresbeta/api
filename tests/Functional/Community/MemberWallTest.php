<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Community;

use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use Symfony\Component\HttpFoundation\Response;

/**
 * El muro de una persona (`FEAT-COM-026`).
 *
 * **Es el muro general con un filtro**, y de ahí sale gratis lo único que de
 * verdad importa de esta ficha: *mirar el perfil de alguien no enseña nada
 * que su muro no enseñara ya*.
 *
 * Con una consulta aparte, las tres reglas de visibilidad —audiencia, bloqueo
 * y privacidad de perfil— habrían tenido dos copias, y a la larga una de las
 * dos se queda atrás. Media prueba de este fichero existe para defender eso:
 * no comprueban que el filtro funcione, comprueban que **las reglas siguen
 * aplicándose a través de él**.
 */
final class MemberWallTest extends EconomyScenario
{
    /**
     * `RN-1`: lo mío y solo lo mío, de lo más reciente a lo más antiguo.
     */
    public function testMyWallHasMyPostsAndNobodyElsesInOrder(): void
    {
        $yo = $this->activatedPerson('yo');
        $otra = $this->activatedPerson('otra');

        $primera = $this->publish($yo['token'], 'La primera');
        $segunda = $this->publish($yo['token'], 'La segunda');
        $ajena = $this->publish($otra['token'], 'La de otra persona');

        self::assertSame([$segunda, $primera], $this->ids('/api/v1/me/posts', $yo['token']));
        self::assertNotContains($ajena, $this->ids('/api/v1/me/posts', $yo['token']));
    }

    /**
     * `RN-2`: **los reposts cuentan**. Lo que alguien saca a su muro es suyo
     * aunque el texto sea de otro, que es justo lo que la cabecera del repost
     * dice.
     */
    public function testWhatIRepostIsOnMyWallToo(): void
    {
        $yo = $this->activatedPerson('yo');
        $otra = $this->activatedPerson('otra');

        $ajena = $this->publish($otra['token'], 'Algo que me ha gustado');
        $this->repost($ajena, $yo['token']);

        $mio = $this->ids('/api/v1/me/posts', $yo['token']);

        self::assertContains($ajena, $mio, 'Aparece el original, con mi cabecera encima.');

        $this->client->request('GET', '/api/v1/me/posts', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$yo['token'],
        ]);

        /** @var list<array<string, mixed>> $posts */
        $posts = $this->payload()['posts'];
        self::assertSame($yo['userId'], $posts[0]['repostedBy']['userId']);
    }

    /**
     * `RN-3`: en **mi** muro veo lo mío entero, incluidas las publicaciones
     * para seguidores, aunque no me siga a mí misma.
     */
    public function testMyOwnFollowersOnlyPostsAreOnMyWall(): void
    {
        $yo = $this->activatedPerson('yo');

        $restringida = $this->publish($yo['token'], 'Solo para quien me sigue', 'FOLLOWERS');

        self::assertContains($restringida, $this->ids('/api/v1/me/posts', $yo['token']));
    }

    /**
     * `RN-4`: en el muro **de otra persona** se ve lo que esa persona te
     * dejaría ver en el muro general, y nada más.
     *
     * Una publicación `FOLLOWERS` de alguien a quien no sigues no está, y
     * aparece en cuanto empiezas a seguirle. Es la regla que un endpoint
     * escrito aparte se habría dejado.
     */
    public function testSomebodyElsesWallRespectsTheAudience(): void
    {
        $yo = $this->activatedPerson('yo');
        $otra = $this->activatedPerson('otra');

        $abierta = $this->publish($otra['token'], 'Para cualquiera');
        $restringida = $this->publish($otra['token'], 'Solo para quien me sigue', 'FOLLOWERS');

        $suyo = \sprintf('/api/v1/users/%s/posts', $otra['userId']);

        self::assertContains($abierta, $this->ids($suyo, $yo['token']));
        self::assertNotContains($restringida, $this->ids($suyo, $yo['token']));

        $this->follow($yo['token'], $otra['userId']);

        self::assertContains($restringida, $this->ids($suyo, $yo['token']));
    }

    /**
     * `RN-5`: **con bloqueo por medio, el muro sale vacío**, y en los dos
     * sentidos. Da igual quién bloqueó a quién: lo que se decide es si estas
     * dos personas se ven.
     */
    public function testABlockEmptiesTheWallBothWays(): void
    {
        $yo = $this->activatedPerson('yo');
        $otra = $this->activatedPerson('otra');

        $suya = $this->publish($otra['token'], 'Para cualquiera');
        $mia = $this->publish($yo['token'], 'Lo mío');

        $this->client->request('PUT', \sprintf('/api/v1/users/%s/block', $otra['userId']), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$yo['token'],
        ]);
        self::assertResponseIsSuccessful();
        $this->capture();
        $this->consumeEverything();

        self::assertNotContains(
            $suya,
            $this->ids(\sprintf('/api/v1/users/%s/posts', $otra['userId']), $yo['token']),
            'Quien bloqueó no ve el muro de quien bloqueó.',
        );
        self::assertNotContains(
            $mia,
            $this->ids(\sprintf('/api/v1/users/%s/posts', $yo['userId']), $otra['token']),
            'Y tampoco al revés: el bloqueo es unilateral en la intención y bidireccional en el efecto.',
        );
    }

    /**
     * `RN-6`: mi propio muro **no se ve afectado** por a quién haya
     * bloqueado. Bloquear a alguien no borra lo que escribí.
     */
    public function testBlockingSomebodyDoesNotEmptyMyOwnWall(): void
    {
        $yo = $this->activatedPerson('yo');
        $otra = $this->activatedPerson('otra');

        $mia = $this->publish($yo['token'], 'Lo mío');

        $this->client->request('PUT', \sprintf('/api/v1/users/%s/block', $otra['userId']), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$yo['token'],
        ]);
        $this->capture();
        $this->consumeEverything();

        self::assertContains($mia, $this->ids('/api/v1/me/posts', $yo['token']));
    }

    /**
     * `RN-7`: una publicación eliminada no está en ningún muro, tampoco en el
     * de su autor.
     */
    public function testADeletedPostIsGoneFromItsOwnWall(): void
    {
        $yo = $this->activatedPerson('yo');

        $postId = $this->publish($yo['token'], 'Esto lo borro');

        $this->client->request('DELETE', \sprintf('/api/v1/posts/%s', $postId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$yo['token'],
        ]);
        self::assertResponseIsSuccessful();

        self::assertNotContains($postId, $this->ids('/api/v1/me/posts', $yo['token']));
    }

    /**
     * `RN-8`: la forma de la respuesta es **la misma** que la del muro
     * general —la misma tarjeta y el mismo cursor—, y se pagina igual.
     *
     * Que sea la misma no es estética: es lo que permite que la pestaña del
     * perfil reutilice el componente del muro sin una segunda versión.
     */
    public function testItPaginatesLikeTheMainWall(): void
    {
        $yo = $this->activatedPerson('yo');

        for ($i = 0; $i < 3; ++$i) {
            $this->publish($yo['token'], 'Publicación '.$i);
        }

        $this->client->request('GET', '/api/v1/me/posts?limit=2', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$yo['token'],
        ]);
        self::assertResponseIsSuccessful();

        $primera = $this->payload();
        self::assertCount(2, $primera['posts']);
        self::assertTrue($primera['pageInfo']['hasNextPage']);

        $this->client->request('GET', '/api/v1/me/posts?limit=2&cursor='.urlencode((string) $primera['pageInfo']['nextCursor']), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$yo['token'],
        ]);
        self::assertResponseIsSuccessful();

        $segunda = $this->payload();
        self::assertCount(1, $segunda['posts']);
        self::assertFalse($segunda['pageInfo']['hasNextPage']);
    }

    /**
     * Sin sesión no se ve ningún muro, por lo mismo que el general: sin saber
     * quién mira no se puede resolver qué publicaciones `FOLLOWERS` le
     * alcanzan.
     */
    public function testBothWallsRequireASession(): void
    {
        $otra = $this->activatedPerson('otra');

        $this->client->request('GET', '/api/v1/me/posts');
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        $this->client->request('GET', \sprintf('/api/v1/users/%s/posts', $otra['userId']));
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * El muro de quien no existe **está vacío, no da error**. Es una lista, y
     * una lista de alguien que no está tiene cero elementos.
     */
    public function testTheWallOfSomebodyWhoIsNotThereIsEmpty(): void
    {
        $yo = $this->activatedPerson('yo');

        self::assertSame(
            [],
            $this->ids('/api/v1/users/0192f000-0000-7000-8000-000000000000/posts', $yo['token']),
        );
    }

    private function publish(string $token, string $body, string $audience = 'EVERYONE'): string
    {
        $this->client->request('POST', '/api/v1/posts', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(['body' => $body, 'audience' => $audience], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->capture();

        return (string) $this->payload()['postId'];
    }

    private function repost(string $postId, string $token): void
    {
        $this->client->request('POST', \sprintf('/api/v1/posts/%s/repost', $postId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        self::assertResponseIsSuccessful();
        $this->capture();
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

    /**
     * @return list<string>
     */
    private function ids(string $path, string $token): array
    {
        $this->client->request('GET', $path, server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);
        self::assertResponseIsSuccessful();

        return array_values(array_map(
            static fn (array $post): string => (string) $post['postId'],
            $this->payload()['posts'],
        ));
    }
}
