<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Community;

use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use Symfony\Component\HttpFoundation\Response;

/**
 * Comentar una publicación (`FEAT-COM-006`).
 *
 * No confundir con `Feedback`: un comentario es social, no mueve créditos y
 * no tiene reglas de acceso propias. Son conceptos de contextos distintos que
 * la interfaz llama parecido.
 *
 * Lo que se vigila es `RN-1`: **solo comenta quien puede ver la
 * publicación**. Con audiencias distintas de «cualquiera», comentar es la
 * otra vía por la que alguien tocaría contenido que no debería ver, y es una
 * puerta que se abre sola si nadie la prueba.
 */
final class PostCommentTest extends EconomyScenario
{
    public function testCommentingAVisiblePostCreatesTheComment(): void
    {
        $autora = $this->person('autora');
        $lectora = $this->person('lectora');

        $postId = $this->publish($autora['token'], 'Hoy he terminado el tercer capítulo');
        $commentId = $this->comment($postId, $lectora['token'], '¡Enhorabuena! 🎉');

        $this->comments($postId, $autora['token']);
        self::assertSame($commentId, $this->payload()['comments'][0]['commentId']);
        self::assertSame('¡Enhorabuena! 🎉', $this->payload()['comments'][0]['body']);
        self::assertSame($lectora['userId'], $this->payload()['comments'][0]['author']['userId']);
    }

    /**
     * **El criterio que sostiene la ficha.** Si la audiencia le excluye, no
     * puede comentar; y como no puede verla, la respuesta es que no existe.
     */
    public function testSomebodyExcludedByTheAudienceCannotComment(): void
    {
        $autora = $this->person('autora');
        $extrana = $this->person('extrana');

        $postId = $this->publish($autora['token'], 'Solo para los míos', 'FOLLOWERS');

        $this->post(\sprintf('/api/v1/posts/%s/comments', $postId), $extrana['token'], ['body' => 'Hola']);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertSame('POST_NOT_FOUND', $this->payload()['code']);
    }

    public function testSomebodyExcludedByTheAudienceCannotEvenReadTheComments(): void
    {
        $autora = $this->person('autora');
        $extrana = $this->person('extrana');

        $postId = $this->publish($autora['token'], 'Solo para los míos', 'FOLLOWERS');
        $this->comment($postId, $autora['token'], 'Me respondo a mí misma');

        $this->comments($postId, $extrana['token']);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testAFollowerCanCommentARestrictedPost(): void
    {
        $autora = $this->person('autora');
        $seguidora = $this->person('seguidora');

        $this->follow($seguidora['token'], $autora['userId']);
        $postId = $this->publish($autora['token'], 'Solo para los míos', 'FOLLOWERS');

        $this->comment($postId, $seguidora['token'], 'Aquí estoy');

        $this->comments($postId, $seguidora['token']);
        self::assertCount(1, $this->payload()['comments']);
    }

    public function testTheTextIsStoredAsPlainTextAndKeepsItsEmoji(): void
    {
        $autora = $this->person('autora');
        $postId = $this->publish($autora['token'], 'Algo');

        $this->comment($postId, $autora['token'], '<b>Muy</b> bueno 👏<script>alert(1)</script>');

        $this->comments($postId, $autora['token']);
        $body = (string) $this->payload()['comments'][0]['body'];

        self::assertStringNotContainsString('<b>', $body);
        self::assertStringNotContainsString('<script>', $body);
        self::assertStringContainsString('👏', $body);
    }

    public function testAnEmptyCommentIsRefused(): void
    {
        $autora = $this->person('autora');
        $postId = $this->publish($autora['token'], 'Algo');

        $this->post(\sprintf('/api/v1/posts/%s/comments', $postId), $autora['token'], ['body' => '   ']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * El contador de la tarjeta cuenta **toda la conversación** (`I-8`), que
     * es lo que espera quien lee «3 comentarios».
     */
    public function testThePostCounterFollowsTheConversation(): void
    {
        $autora = $this->person('autora');
        $lectora = $this->person('lectora');

        $postId = $this->publish($autora['token'], 'Algo');

        self::assertSame(0, $this->counterOf($postId, $autora['token']));

        $this->comment($postId, $lectora['token'], 'Uno');
        $this->comment($postId, $lectora['token'], 'Dos');

        self::assertSame(2, $this->counterOf($postId, $autora['token']));
    }

    public function testTheAuthorEditsTheirOwnCommentAndItIsMarkedAsEdited(): void
    {
        $autora = $this->person('autora');
        $postId = $this->publish($autora['token'], 'Algo');
        $commentId = $this->comment($postId, $autora['token'], 'Primera versión');

        $this->client->request('PATCH', \sprintf('/api/v1/comments/%s', $commentId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$autora['token'],
        ], content: json_encode(['body' => 'Segunda versión'], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->comments($postId, $autora['token']);
        self::assertSame('Segunda versión', $this->payload()['comments'][0]['body']);
        self::assertTrue($this->payload()['comments'][0]['edited']);
    }

    public function testNobodyEditsOrDeletesSomebodyElsesComment(): void
    {
        $autora = $this->person('autora');
        $otra = $this->person('otra');

        $postId = $this->publish($autora['token'], 'Algo');
        $commentId = $this->comment($postId, $autora['token'], 'Mío');

        $this->client->request('PATCH', \sprintf('/api/v1/comments/%s', $commentId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$otra['token'],
        ], content: json_encode(['body' => 'Tuyo ya no'], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $this->client->request('DELETE', \sprintf('/api/v1/comments/%s', $commentId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$otra['token'],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testDeletingACommentTakesItOffTheListAndOffTheCounter(): void
    {
        $autora = $this->person('autora');
        $postId = $this->publish($autora['token'], 'Algo');
        $commentId = $this->comment($postId, $autora['token'], 'Me arrepiento');

        $this->client->request('DELETE', \sprintf('/api/v1/comments/%s', $commentId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$autora['token'],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->comments($postId, $autora['token']);
        self::assertSame([], $this->payload()['comments']);
        self::assertSame(0, $this->counterOf($postId, $autora['token']));
    }

    /**
     * `RN-6`: al retirarse la publicación, sus comentarios dejan de existir
     * con ella. No hay camino que los sirva.
     */
    public function testDeletingThePostTakesItsCommentsWithIt(): void
    {
        $autora = $this->person('autora');
        $postId = $this->publish($autora['token'], 'Algo');
        $this->comment($postId, $autora['token'], 'Un comentario');

        $this->client->request('DELETE', \sprintf('/api/v1/posts/%s', $postId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$autora['token'],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->comments($postId, $autora['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testCommentingMovesNoCredits(): void
    {
        $autora = $this->person('autora');
        $lectora = $this->person('lectora');

        $postId = $this->publish($autora['token'], 'Algo');
        $antes = $this->balanceOf($lectora['userId']);

        $this->comment($postId, $lectora['token'], 'Un comentario');

        self::assertSame($antes, $this->balanceOf($lectora['userId']));
    }

    public function testAnUnactivatedAccountCannotComment(): void
    {
        $autora = $this->person('autora');
        $postId = $this->publish($autora['token'], 'Algo');

        $token = $this->signedInWithoutActivating('nueva');

        $this->post(\sprintf('/api/v1/posts/%s/comments', $postId), $token, ['body' => 'Hola']);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame('ACCOUNT_NOT_ACTIVATED', $this->payload()['code']);
    }

    /**
     * `RN-8`: el hecho lleva **a quién avisar**, no el texto. `Notification`
     * no necesita el cuerpo de lo escrito, y copiarlo en la cola lo pondría
     * en un sitio que persiste y reintenta.
     */
    public function testCommentingAnnouncesTheFactWithBothPeople(): void
    {
        $autora = $this->person('autora');
        $lectora = $this->person('lectora');

        $postId = $this->publish($autora['token'], 'Algo');
        $this->comment($postId, $lectora['token'], 'Un comentario');

        $anunciado = $this->lastAnnouncementOf('PostCommented');

        self::assertSame($autora['userId'], $anunciado['postAuthorId']);
        self::assertSame($lectora['userId'], $anunciado['commentAuthorId']);
        self::assertNull($anunciado['parentAuthorId'], 'No es una respuesta.');
        self::assertArrayNotHasKey('body', $anunciado);
    }

    public function testTheListIsPagedNewestFirstAndCanBeReversed(): void
    {
        $autora = $this->person('autora');
        $postId = $this->publish($autora['token'], 'Algo');

        $uno = $this->comment($postId, $autora['token'], 'Uno');
        $dos = $this->comment($postId, $autora['token'], 'Dos');
        $tres = $this->comment($postId, $autora['token'], 'Tres');

        $this->comments($postId, $autora['token'], '&limit=2');
        self::assertSame([$tres, $dos], $this->ids());
        self::assertTrue($this->payload()['pageInfo']['hasNextPage']);

        $this->comments($postId, $autora['token'], '&limit=2&cursor='.$this->payload()['pageInfo']['nextCursor']);
        self::assertSame([$uno], $this->ids());

        $this->comments($postId, $autora['token'], '&sort=OLDEST');
        self::assertSame([$uno, $dos, $tres], $this->ids());
    }

    /**
     * «Más relevantes» es lo que enseña el desplegable por defecto y es lo
     * que **no se puede servir todavía**: su fórmula cuenta apoyos, y los
     * apoyos no existen. Responder `422` es preferible a ordenar por media
     * fórmula y dejar que alguien se fíe.
     */
    public function testAnUnsupportedSortIsRefusedByNameWithTheOnesThatWork(): void
    {
        $autora = $this->person('autora');
        $postId = $this->publish($autora['token'], 'Algo');

        $this->comments($postId, $autora['token'], '&sort=RELEVANCE');

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('UNSUPPORTED_SORT', $this->payload()['code']);
        self::assertSame('RECENT,OLDEST', $this->payload()['supportedSorts'], 'Y dice cuáles valen.');
    }

    private function comment(string $postId, string $token, string $body): string
    {
        $this->post(\sprintf('/api/v1/posts/%s/comments', $postId), $token, ['body' => $body]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->capture();

        return (string) $this->payload()['commentId'];
    }

    private function comments(string $postId, string $token, string $query = ''): void
    {
        $this->client->request('GET', \sprintf('/api/v1/posts/%s/comments?%s', $postId, ltrim($query, '&')), server: [
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
            static fn (array $comment): string => (string) $comment['commentId'],
            $this->payload()['comments'],
        ));
    }

    private function counterOf(string $postId, string $token): int
    {
        $this->client->request('GET', '/api/v1/posts', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        self::assertResponseIsSuccessful();

        foreach ($this->payload()['posts'] as $post) {
            if ($post['postId'] === $postId) {
                return (int) $post['commentCount'];
            }
        }

        self::fail('La publicación no está en el muro.');
    }

    private function publish(string $token, string $body, string $audience = 'EVERYONE'): string
    {
        $this->post('/api/v1/posts', $token, ['body' => $body, 'audience' => $audience]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->capture();

        return (string) $this->payload()['postId'];
    }

    /**
     * @param array<string, string> $body
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
