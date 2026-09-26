<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Community;

use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lo que cada quien decide sobre su propio muro: guardar (`FEAT-COM-021`),
 * ocultar (`FEAT-COM-022`) y silenciar (`FEAT-COM-033`).
 *
 * Las tres van juntas porque son lo mismo visto desde tres lados —una
 * preferencia privada de un espectador que la consulta del muro tiene que
 * respetar— y porque las tres tocan la misma consulta. Probarlas por separado
 * dejaría sin cubrir justo lo que se rompe: que una se coma a las otras.
 *
 * Lo que más importa defender es **dónde para cada una**. Silenciar no es
 * bloquear, ocultar no es borrar y guardar no es un «me gusta»; si algún día
 * una de las tres crece hacia las otras, estos casos son los que se ponen
 * rojos.
 */
final class WallCurationTest extends EconomyScenario
{
    // ---------------------------------------------------------------- guardar

    public function testASavedPostComesBackInTheSavedList(): void
    {
        $lectora = $this->activatedPerson('lectora');
        $autora = $this->activatedPerson('autora');
        $postId = $this->publish($autora['token'], 'La primera');
        $this->publish($autora['token'], 'La segunda');

        $this->save($postId, $lectora['token']);

        $this->savedPosts($lectora['token']);
        self::assertSame([$postId], $this->ids());
    }

    /**
     * `RN-1`: guardar es **privado**. No se cuenta y el autor no se entera. De
     * ahí que la tarjeta no tenga `savedCount`: un contador convertiría una
     * nota para uno mismo en una señal pública.
     */
    public function testSavingIsPrivateAndUncounted(): void
    {
        $lectora = $this->activatedPerson('lectora');
        $autora = $this->activatedPerson('autora');
        $postId = $this->publish($autora['token'], 'La primera');

        $this->save($postId, $lectora['token']);

        $this->wall($autora['token']);
        self::assertArrayNotHasKey('savedCount', $this->entryOf($postId));

        $this->savedPosts($autora['token']);
        self::assertSame([], $this->ids(), 'Su autora no ve en sus guardados lo que guardó otra.');
    }

    /** `RN-3` y `RN-4`: es idempotente por los dos lados. */
    public function testSavingAndUnsavingAreIdempotent(): void
    {
        $lectora = $this->activatedPerson('lectora');
        $autora = $this->activatedPerson('autora');
        $postId = $this->publish($autora['token'], 'La primera');

        $this->save($postId, $lectora['token']);
        $this->save($postId, $lectora['token']);

        $this->savedPosts($lectora['token']);
        self::assertSame([$postId], $this->ids());

        $this->unsave($postId, $lectora['token']);
        $this->unsave($postId, $lectora['token']);

        $this->savedPosts($lectora['token']);
        self::assertSame([], $this->ids());
    }

    /**
     * **`RN-5`, y es la razón de que la lista sea el muro con un filtro.**
     * Guardar no conserva acceso: la publicación que deja de alcanzarte
     * desaparece de tus guardados sin que esta funcionalidad tenga que saber
     * por qué.
     */
    public function testSavingDoesNotKeepAccess(): void
    {
        $lectora = $this->activatedPerson('lectora');
        $autora = $this->activatedPerson('autora');
        $postId = $this->publish($autora['token'], 'La primera');

        $this->save($postId, $lectora['token']);
        $this->savedPosts($lectora['token']);
        self::assertSame([$postId], $this->ids());

        // La autora la borra: deja de existir para todo el mundo.
        $this->client->request('DELETE', '/api/v1/posts/'.$postId, server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$autora['token'],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->capture();

        $this->savedPosts($lectora['token']);
        self::assertSame([], $this->ids());
    }

    /** Lo mismo con un bloqueo, que es el otro camino por el que se pierde. */
    public function testABlockEmptiesWhatWasSaved(): void
    {
        $lectora = $this->activatedPerson('lectora');
        $autora = $this->activatedPerson('autora');
        $postId = $this->publish($autora['token'], 'La primera');

        $this->save($postId, $lectora['token']);

        $this->client->request('PUT', '/api/v1/users/'.$lectora['userId'].'/block', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$autora['token'],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->capture();
        $this->consumeEverything();

        $this->savedPosts($lectora['token']);
        self::assertSame([], $this->ids());
    }

    /** `RN-2`: solo se guarda lo que se puede ver. */
    public function testWhatCannotBeSeenCannotBeSaved(): void
    {
        $lectora = $this->activatedPerson('lectora');
        $autora = $this->activatedPerson('autora');
        $postId = $this->publish($autora['token'], 'Solo para quien me sigue', audience: 'FOLLOWERS');

        $this->client->request('PUT', '/api/v1/posts/'.$postId.'/saved', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$lectora['token'],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertSame('POST_NOT_FOUND', $this->payload()['code']);
    }

    /**
     * Los guardados no traen reposts: lo que se guarda es una publicación, y
     * enseñarla con la cabecera de quien la reposteó diría algo sobre por qué
     * está ahí que no es verdad.
     */
    public function testTheSavedListCarriesNoReposts(): void
    {
        $lectora = $this->activatedPerson('lectora');
        $autora = $this->activatedPerson('autora');
        $otra = $this->activatedPerson('otra');
        $postId = $this->publish($autora['token'], 'La primera');

        $this->save($postId, $lectora['token']);
        $this->repost($postId, $otra['token']);

        $this->savedPosts($lectora['token']);

        self::assertSame([$postId], $this->ids());
        self::assertNull($this->entryOf($postId)['repostedBy']);
    }

    // ---------------------------------------------------------------- ocultar

    public function testAHiddenPostLeavesTheWall(): void
    {
        $lectora = $this->activatedPerson('lectora');
        $autora = $this->activatedPerson('autora');
        $escondida = $this->publish($autora['token'], 'La que sobra');
        $otra = $this->publish($autora['token'], 'La que se queda');

        $this->hide($escondida, $lectora['token']);

        $this->wall($lectora['token']);
        self::assertSame([$otra], $this->ids());
    }

    /** `RN-1`: solo del muro de quien oculta. */
    public function testHidingIsPrivateToWhoeverHides(): void
    {
        $lectora = $this->activatedPerson('lectora');
        $otra = $this->activatedPerson('otra');
        $autora = $this->activatedPerson('autora');
        $postId = $this->publish($autora['token'], 'La que sobra');

        $this->hide($postId, $lectora['token']);

        $this->wall($otra['token']);
        self::assertContains($postId, $this->ids());

        $this->wall($autora['token']);
        self::assertContains($postId, $this->ids(), 'Ni su autora la pierde.');
    }

    /**
     * **`RN-4`, y es la regla que hace que el botón signifique algo.** Una
     * publicación oculta no vuelve porque alguien la repostee: si reaparecer
     * fuera tan fácil, «no me interesa» sería un gesto sin efecto y el usuario
     * lo aprendería a la segunda.
     */
    public function testAHiddenPostDoesNotComeBackThroughARepost(): void
    {
        $lectora = $this->activatedPerson('lectora');
        $autora = $this->activatedPerson('autora');
        $otra = $this->activatedPerson('otra');
        $postId = $this->publish($autora['token'], 'La que sobra');

        $this->hide($postId, $lectora['token']);
        $this->repost($postId, $otra['token']);

        $this->wall($lectora['token']);
        self::assertNotContains($postId, $this->ids());
    }

    /** Ni por el muro del perfil de su autora. */
    public function testAHiddenPostDoesNotComeBackOnItsAuthorsWall(): void
    {
        $lectora = $this->activatedPerson('lectora');
        $autora = $this->activatedPerson('autora');
        $postId = $this->publish($autora['token'], 'La que sobra');

        $this->hide($postId, $lectora['token']);

        $this->memberWall($autora['userId'], $lectora['token']);
        self::assertNotContains($postId, $this->ids());
    }

    /**
     * `RN-5`: **ocultar no es una regla de acceso.** La publicación sigue
     * siendo suya de comentar y de deshacer; lo que desaparece es la tarjeta
     * del muro.
     *
     * Deshacerlo solo necesita el identificador, que quien acaba de ocultar
     * todavía tiene — el «deshacer» del aviso que sale justo después—, y
     * funciona aunque la tarjeta ya no esté en el muro. Por eso no hace falta
     * una lista de ocultas.
     */
    public function testHidingCutsNoAccessAndCanBeUndone(): void
    {
        $lectora = $this->activatedPerson('lectora');
        $autora = $this->activatedPerson('autora');
        $postId = $this->publish($autora['token'], 'La que sobra');

        $this->hide($postId, $lectora['token']);

        // Sigue pudiendo comentarla: lo que se apagó es el muro.
        $this->client->request('POST', '/api/v1/posts/'.$postId.'/comments', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$lectora['token'],
        ], content: json_encode(['body' => 'Aun así'], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->capture();

        $this->client->request('DELETE', '/api/v1/posts/'.$postId.'/hidden', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$lectora['token'],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->wall($lectora['token']);
        self::assertContains($postId, $this->ids());
    }

    // -------------------------------------------------------------- silenciar

    public function testAMutedPersonLeavesTheWall(): void
    {
        $lectora = $this->activatedPerson('lectora');
        $ruidosa = $this->activatedPerson('ruidosa');
        $tranquila = $this->activatedPerson('tranquila');

        $delRuido = $this->publish($ruidosa['token'], 'Otra más');
        $deLaCalma = $this->publish($tranquila['token'], 'Una sola');

        $this->mute($ruidosa['userId'], $lectora['token']);

        $this->wall($lectora['token']);
        self::assertSame([$deLaCalma], $this->ids());
        self::assertNotContains($delRuido, $this->ids());
    }

    /** `RN-6`: también sus reposts. Silenciar es quitarle del muro, entero. */
    public function testAMutedPersonsRepostsGoTooAndTheirsOnly(): void
    {
        $lectora = $this->activatedPerson('lectora');
        $ruidosa = $this->activatedPerson('ruidosa');
        $tercera = $this->activatedPerson('tercera');
        $postId = $this->publish($tercera['token'], 'De otra persona');

        $this->repost($postId, $ruidosa['token']);
        $this->mute($ruidosa['userId'], $lectora['token']);

        $this->wall($lectora['token']);

        // La original sigue: lo que se silenció es quien la reposteó, no
        // quien la escribió.
        self::assertSame([$postId], $this->ids());
        self::assertNull($this->entryOf($postId)['repostedBy']);
    }

    /**
     * **`RN-7`.** Si visito el perfil de alguien a quien silencié, veo sus
     * publicaciones: silenciar dice «no me lo pongas delante sin pedirlo», y
     * entrar en su perfil es pedirlo. Un perfil vacío sin explicación haría
     * creer que esa persona dejó de publicar.
     */
    public function testMutingDoesNotEmptyTheirOwnProfileWall(): void
    {
        $lectora = $this->activatedPerson('lectora');
        $ruidosa = $this->activatedPerson('ruidosa');
        $postId = $this->publish($ruidosa['token'], 'Otra más');

        $this->mute($ruidosa['userId'], $lectora['token']);

        $this->memberWall($ruidosa['userId'], $lectora['token']);
        self::assertSame([$postId], $this->ids());
    }

    /**
     * **`RN-5`: silenciar no es bloquear.** El seguimiento sigue, y esa
     * persona puede seguir comentándome. Es lo que separa las dos
     * funcionalidades, y lo que más fácil sería romper ampliando esta.
     */
    public function testMutingCutsNothingElse(): void
    {
        $lectora = $this->activatedPerson('lectora');
        $ruidosa = $this->activatedPerson('ruidosa');

        $this->follow($lectora['token'], $ruidosa['userId']);
        $mio = $this->publish($lectora['token'], 'Lo mío');

        $this->mute($ruidosa['userId'], $lectora['token']);

        // Sigo siguiéndola.
        $this->client->request('GET', '/api/v1/users/'.$lectora['userId'].'/subscriptions', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$lectora['token'],
        ]);
        self::assertResponseIsSuccessful();
        self::assertCount(1, $this->payload()['data']);

        // Y puede comentarme.
        $this->client->request('POST', '/api/v1/posts/'.$mio.'/comments', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$ruidosa['token'],
        ], content: json_encode(['body' => 'Sigo aquí'], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->capture();

        // Y su perfil se sigue viendo.
        $this->client->request('GET', '/api/v1/users/'.$ruidosa['userId'], server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$lectora['token'],
        ]);
        self::assertResponseIsSuccessful();
    }

    /** `RN-9`: no publica ningún hecho. Es una preferencia de pantalla. */
    public function testMutingAnnouncesNothing(): void
    {
        $lectora = $this->activatedPerson('lectora');
        $ruidosa = $this->activatedPerson('ruidosa');

        $this->capture();
        $this->mute($ruidosa['userId'], $lectora['token']);

        self::assertSame([], $this->capture());
    }

    public function testTheMutedListLetsYouUndoIt(): void
    {
        $lectora = $this->activatedPerson('lectora');
        $ruidosa = $this->activatedPerson('ruidosa');

        $this->mute($ruidosa['userId'], $lectora['token']);

        $this->client->request('GET', '/api/v1/me/muted-users', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$lectora['token'],
        ]);
        self::assertResponseIsSuccessful();
        self::assertSame($ruidosa['userId'], $this->payload()['data'][0]['userId']);

        $this->client->request('DELETE', '/api/v1/users/'.$ruidosa['userId'].'/muted', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$lectora['token'],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->client->request('GET', '/api/v1/me/muted-users', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$lectora['token'],
        ]);
        self::assertSame([], $this->payload()['data']);
    }

    /** `RN-2` y `RN-3`. */
    public function testYouCannotMuteYourselfOrAGhost(): void
    {
        $lectora = $this->activatedPerson('lectora');

        $this->client->request('PUT', '/api/v1/users/'.$lectora['userId'].'/muted', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$lectora['token'],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('CANNOT_MUTE_YOURSELF', $this->payload()['code']);

        $this->client->request('PUT', '/api/v1/users/0192b1f0-0000-7000-8000-000000000000/muted', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$lectora['token'],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('USER_NOT_FOUND', $this->payload()['code']);
    }

    // ------------------------------------------------------- las tres juntas

    /**
     * Las tres a la vez sobre el mismo muro. Es el caso que ninguna de las
     * tres cubre por separado: que una cláusula se coma a las otras.
     */
    public function testTheThreeApplyTogether(): void
    {
        $lectora = $this->activatedPerson('lectora');
        $ruidosa = $this->activatedPerson('ruidosa');
        $autora = $this->activatedPerson('autora');

        $this->publish($ruidosa['token'], 'Ruido');
        $escondida = $this->publish($autora['token'], 'No me interesa');
        $guardada = $this->publish($autora['token'], 'Para luego');
        $normal = $this->publish($autora['token'], 'Normal');

        $this->mute($ruidosa['userId'], $lectora['token']);
        $this->hide($escondida, $lectora['token']);
        $this->save($guardada, $lectora['token']);

        $this->wall($lectora['token']);
        self::assertSame([$normal, $guardada], $this->ids(), 'Sin el ruido y sin la escondida.');

        $this->savedPosts($lectora['token']);
        self::assertSame([$guardada], $this->ids());
    }

    /**
     * Y lo escondido tampoco sale en los guardados, aunque se guardara antes.
     * Son dos decisiones distintas del mismo espectador, y la más restrictiva
     * manda.
     */
    public function testHidingWinsOverSaving(): void
    {
        $lectora = $this->activatedPerson('lectora');
        $autora = $this->activatedPerson('autora');
        $postId = $this->publish($autora['token'], 'Para luego');

        $this->save($postId, $lectora['token']);
        $this->hide($postId, $lectora['token']);

        $this->savedPosts($lectora['token']);
        self::assertSame([], $this->ids());
    }

    public function testWithoutASessionNoneOfThemWork(): void
    {
        foreach ([
            ['PUT', '/api/v1/posts/x/saved'],
            ['GET', '/api/v1/me/saved-posts'],
            ['PUT', '/api/v1/posts/x/hidden'],
            ['PUT', '/api/v1/users/x/muted'],
            ['GET', '/api/v1/me/muted-users'],
        ] as [$method, $path]) {
            $this->client->request($method, $path);

            self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED, $method.' '.$path);
        }
    }

    private function publish(string $token, string $body, string $audience = 'EVERYONE'): string
    {
        $this->client->request('POST', '/api/v1/posts', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode([
            'body' => $body,
            'type' => 'GENERAL',
            'audience' => $audience,
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->capture();

        return (string) $this->payload()['postId'];
    }

    private function save(string $postId, string $token): void
    {
        $this->client->request('PUT', '/api/v1/posts/'.$postId.'/saved', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }

    private function unsave(string $postId, string $token): void
    {
        $this->client->request('DELETE', '/api/v1/posts/'.$postId.'/saved', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }

    private function hide(string $postId, string $token): void
    {
        $this->client->request('PUT', '/api/v1/posts/'.$postId.'/hidden', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }

    private function mute(string $userId, string $token): void
    {
        $this->client->request('PUT', '/api/v1/users/'.$userId.'/muted', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }

    private function repost(string $postId, string $token): void
    {
        $this->client->request('POST', '/api/v1/posts/'.$postId.'/repost', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: '{}');

        self::assertResponseIsSuccessful();
        $this->capture();
    }

    private function follow(string $token, string $userId): void
    {
        $this->client->request('PUT', '/api/v1/users/'.$userId.'/subscription', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->capture();
        $this->consumeEverything();
    }

    private function wall(string $token): void
    {
        $this->client->request('GET', '/api/v1/posts', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);
    }

    private function memberWall(string $userId, string $token): void
    {
        $this->client->request('GET', '/api/v1/users/'.$userId.'/posts', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
    }

    private function savedPosts(string $token): void
    {
        $this->client->request('GET', '/api/v1/me/saved-posts', server: [
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
            static fn (array $post): string => (string) $post['postId'],
            $this->payload()['posts'],
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function entryOf(string $postId): array
    {
        /** @var list<array<string, mixed>> $posts */
        $posts = $this->payload()['posts'];

        foreach ($posts as $post) {
            if ($post['postId'] === $postId) {
                return $post;
            }
        }

        self::fail('No está en la respuesta: '.$postId);
    }
}
