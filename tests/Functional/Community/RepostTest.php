<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Community;

use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use Symfony\Component\HttpFoundation\Response;

/**
 * Repostear (`FEAT-COM-019`).
 *
 * Un repost es **una referencia, no una publicación aparte**: los contadores
 * y la conversación son los del original, que es lo que enseña el diseño, y
 * así no hay cadenas de reposts anidados que mostrar ni que moderar.
 *
 * Lo que hay que probar de verdad son dos cosas, y la propia ficha lo dice:
 * que no se repostee lo que no se puede ver, y que **un repost no haga
 * visible el original a quien no podía verlo**. La segunda es la más fácil de
 * romper — basta con servir el muro de quien repostea sin volver a comprobar
 * la audiencia del original.
 */
final class RepostTest extends EconomyScenario
{
    public function testARepostPutsTheOriginalOnYourWallWithItsHeader(): void
    {
        $autora = $this->person('autora', 'Ana García');
        $lectora = $this->person('lectora', 'Beatriz Alonso');

        $postId = $this->publish($autora['token'], 'Hoy he terminado el tercer capítulo');
        $this->repost($postId, $lectora['token']);

        $this->wall($lectora['token']);
        $entry = $this->entryOf($postId);

        self::assertSame('Beatriz Alonso', $entry['repostedBy']['name'], 'La cabecera es de quien repostea.');
        self::assertSame('Ana García', $entry['author']['name'], 'Y el resto, del original.');
        self::assertSame('Hoy he terminado el tercer capítulo', $entry['body']);
        self::assertNotNull($entry['repostedAt']);
    }

    /**
     * **El criterio que la ficha señala como el importante.** Un repost no
     * hace visible el original a quien no podía verlo.
     */
    public function testARepostDoesNotMakeTheOriginalVisibleToSomebodyExcluded(): void
    {
        $autora = $this->person('autora');
        $seguidora = $this->person('seguidora');
        $extrana = $this->person('extrana');

        $this->follow($seguidora['token'], $autora['userId']);

        $postId = $this->publish($autora['token'], 'Solo para los míos', 'FOLLOWERS');
        $this->repost($postId, $seguidora['token']);

        $this->wall($extrana['token']);

        self::assertSame([], $this->payload()['posts'], 'El repost no abre la puerta.');
        self::assertStringNotContainsString(
            'Solo para los míos',
            (string) $this->client->getResponse()->getContent(),
            'Y el texto no llega al cliente siquiera.',
        );
    }

    public function testYouCannotRepostWhatYouCannotSee(): void
    {
        $autora = $this->person('autora');
        $extrana = $this->person('extrana');

        $postId = $this->publish($autora['token'], 'Solo para los míos', 'FOLLOWERS');

        $this->post(\sprintf('/api/v1/posts/%s/repost', $postId), $extrana['token'], []);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertSame('POST_NOT_FOUND', $this->payload()['code']);
    }

    /**
     * `C-12`: el botón alterna. Es lo que hace la gente cuando se arrepiente,
     * y un segundo `POST` que respondiera «ya estaba» dejaría a la interfaz
     * sin forma de decirlo.
     */
    public function testPressingAgainUndoesIt(): void
    {
        $autora = $this->person('autora');
        $lectora = $this->person('lectora');

        $postId = $this->publish($autora['token'], 'Algo');

        $this->post(\sprintf('/api/v1/posts/%s/repost', $postId), $lectora['token'], []);
        self::assertTrue($this->payload()['reposted']);
        $this->capture();

        $this->post(\sprintf('/api/v1/posts/%s/repost', $postId), $lectora['token'], []);
        self::assertFalse($this->payload()['reposted']);

        // La publicación sigue en su muro porque es pública; lo que ya no
        // está es la cabecera de repost.
        $this->wall($lectora['token']);
        self::assertNull($this->entryOf($postId)['repostedBy']);
    }

    public function testTheCounterFollowsTheReposts(): void
    {
        $autora = $this->person('autora');
        $lectora = $this->person('lectora');

        $postId = $this->publish($autora['token'], 'Algo');

        self::assertSame(0, $this->repostCountOf($postId, $autora['token']));

        $this->repost($postId, $lectora['token']);
        self::assertSame(1, $this->repostCountOf($postId, $autora['token']));

        $this->client->request('DELETE', \sprintf('/api/v1/posts/%s/repost', $postId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$lectora['token'],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        self::assertSame(0, $this->repostCountOf($postId, $autora['token']));
    }

    /**
     * `RN-1`: el repost **referencia**, no copia. Si el original se edita, el
     * repost muestra la versión de ahora.
     */
    public function testEditingTheOriginalChangesWhatTheRepostShows(): void
    {
        $autora = $this->person('autora');
        $lectora = $this->person('lectora');

        $postId = $this->publish($autora['token'], 'Primera versión');
        $this->repost($postId, $lectora['token']);

        $this->client->request('PATCH', \sprintf('/api/v1/posts/%s', $postId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$autora['token'],
        ], content: json_encode(['body' => 'Segunda versión'], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->wall($lectora['token']);
        self::assertSame('Segunda versión', $this->entryOf($postId)['body']);
    }

    /**
     * `RN-6`: si el original se elimina, el repost deja de mostrarse. Es la
     * consecuencia de que sea una referencia y no una copia.
     */
    public function testDeletingTheOriginalTakesTheRepostWithIt(): void
    {
        $autora = $this->person('autora');
        $lectora = $this->person('lectora');

        $postId = $this->publish($autora['token'], 'Algo');
        $this->repost($postId, $lectora['token']);

        $this->client->request('DELETE', \sprintf('/api/v1/posts/%s', $postId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$autora['token'],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->wall($lectora['token']);
        self::assertSame([], $this->payload()['posts']);
    }

    /**
     * `C-13`: se puede repostear lo propio. Sirve para volver a sacar algo
     * antiguo, que es para lo que la gente lo usa.
     */
    public function testYouCanRepostYourOwn(): void
    {
        $autora = $this->person('autora');
        $postId = $this->publish($autora['token'], 'Lo mío de hace un mes');

        $this->repost($postId, $autora['token']);

        $this->wall($autora['token']);
        self::assertNotNull($this->entryOf($postId)['repostedBy']);
    }

    public function testARepostCanCarryItsOwnText(): void
    {
        $autora = $this->person('autora');
        $lectora = $this->person('lectora');

        $postId = $this->publish($autora['token'], 'El original');
        $this->repost($postId, $lectora['token'], 'Esto hay que leerlo');

        $this->wall($lectora['token']);
        $entry = $this->entryOf($postId);

        self::assertSame('Esto hay que leerlo', $entry['repostComment']);
        self::assertSame('El original', $entry['body'], 'El cuerpo sigue siendo el del original.');
    }

    /**
     * El muro sirve el original **embebido**, sin una petición adicional por
     * entrada, y ordena por la fecha del repost: es cuando entró en el muro.
     */
    public function testTheWallMixesPostsAndRepostsInOneChronology(): void
    {
        $autora = $this->person('autora');
        $lectora = $this->person('lectora');

        $viejo = $this->publish($autora['token'], 'Antiguo');
        $mio = $this->publish($lectora['token'], 'Mío y reciente');
        $this->repost($viejo, $lectora['token']);

        $this->wall($lectora['token']);
        $orden = array_map(
            static fn (array $post): string => (string) $post['postId'],
            $this->payload()['posts'],
        );

        self::assertSame([$viejo, $mio], $orden, 'El repost entra por su fecha, no por la del original.');
        self::assertNotNull($this->entryOf($viejo)['repostedBy'], 'Y aparece una sola vez, como repost.');
    }

    public function testRepostingMovesNoCredits(): void
    {
        $autora = $this->person('autora');
        $lectora = $this->person('lectora');

        $postId = $this->publish($autora['token'], 'Algo');
        $antes = $this->balanceOf($lectora['userId']);

        $this->repost($postId, $lectora['token']);

        self::assertSame($antes, $this->balanceOf($lectora['userId']));
    }

    public function testAnUnactivatedAccountCannotRepost(): void
    {
        $autora = $this->person('autora');
        $postId = $this->publish($autora['token'], 'Algo');

        $token = $this->signedInWithoutActivating('nueva');

        $this->post(\sprintf('/api/v1/posts/%s/repost', $postId), $token, []);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testRepostingAnnouncesTheFactToTheOriginalAuthor(): void
    {
        $autora = $this->person('autora');
        $lectora = $this->person('lectora');

        $postId = $this->publish($autora['token'], 'Algo');
        $this->repost($postId, $lectora['token']);

        $anunciado = $this->lastAnnouncementOf('PostReposted');

        self::assertSame($postId, $anunciado['postId']);
        self::assertSame($autora['userId'], $anunciado['originalAuthorId']);
        self::assertSame($lectora['userId'], $anunciado['repostedBy']);
    }

    /**
     * Deshacer algo propio tiene que funcionar **aunque el original haya
     * dejado de ser visible**: si no, quedaría un repost que su dueño no
     * puede quitar.
     */
    public function testARepostCanBeUndoneEvenIfTheOriginalWasRestrictedAfterwards(): void
    {
        $autora = $this->person('autora');
        $lectora = $this->person('lectora');

        $postId = $this->publish($autora['token'], 'Al principio público');
        $this->repost($postId, $lectora['token']);

        // La autora lo retira: para la lectora deja de existir.
        $this->client->request('DELETE', \sprintf('/api/v1/posts/%s', $postId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$autora['token'],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->client->request('DELETE', \sprintf('/api/v1/posts/%s/repost', $postId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$lectora['token'],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }

    private function repost(string $postId, string $token, ?string $comment = null): void
    {
        $this->post(\sprintf('/api/v1/posts/%s/repost', $postId), $token, null === $comment ? [] : ['comment' => $comment]);

        self::assertResponseIsSuccessful();
        self::assertTrue($this->payload()['reposted']);
        $this->capture();
    }

    /**
     * @return array<string, mixed>
     */
    private function entryOf(string $postId): array
    {
        foreach ($this->payload()['posts'] as $post) {
            if ($post['postId'] === $postId) {
                return $post;
            }
        }

        self::fail('La entrada no está en el muro.');
    }

    private function repostCountOf(string $postId, string $token): int
    {
        $this->wall($token);

        return (int) $this->entryOf($postId)['repostCount'];
    }

    private function wall(string $token): void
    {
        $this->client->request('GET', '/api/v1/posts', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        self::assertResponseIsSuccessful();
    }

    private function publish(string $token, string $body, string $audience = 'EVERYONE'): string
    {
        $this->post('/api/v1/posts', $token, ['body' => $body, 'audience' => $audience]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->capture();

        return (string) $this->payload()['postId'];
    }

    /**
     * @param array<string, mixed> $body
     */
    private function post(string $path, string $token, array $body): void
    {
        $this->client->request('POST', $path, server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode($body, \JSON_THROW_ON_ERROR));
    }

    /**
     * @return array{token: string, userId: string}
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

        return $person;
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
