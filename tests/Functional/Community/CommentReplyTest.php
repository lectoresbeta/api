<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Community;

use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use Symfony\Component\HttpFoundation\Response;

/**
 * Responder a un comentario (`FEAT-COM-031`).
 *
 * La decisión de esta ficha es la **profundidad**: un solo nivel. Toda
 * respuesta cuelga del comentario raíz, y responder a una respuesta añade
 * otra al mismo hilo con una mención a quien se responde.
 *
 * Las dos pruebas que la sostienen son las de `RN-2`: ninguna respuesta tiene
 * como padre otra respuesta. Es una invariante fácil de romper si no se
 * declara —basta con que alguien guarde el `commentId` que le llegó— y
 * romperla convierte cada lectura de un hilo en una consulta recursiva.
 */
final class CommentReplyTest extends EconomyScenario
{
    public function testReplyingHangsUnderTheCommentAndRaisesItsCounter(): void
    {
        $autora = $this->person('autora');
        $lectora = $this->person('lectora');

        $postId = $this->publish($autora['token'], 'Algo');
        $commentId = $this->comment($postId, $lectora['token'], '¡Enhorabuena!');

        $replyId = $this->reply($commentId, $autora['token'], 'Gracias');

        $this->replies($commentId, $autora['token']);
        self::assertSame([$replyId], $this->ids());
        self::assertSame($commentId, $this->payload()['comments'][0]['parentCommentId']);

        $this->comments($postId, $autora['token']);
        self::assertSame(1, $this->payload()['comments'][0]['replyCount']);
    }

    /**
     * **`RN-2`.** Responder a una respuesta la cuelga del mismo raíz, no de
     * la respuesta: el hilo se queda plano y se puede paginar.
     */
    public function testReplyingToAReplyHangsFromTheSameRoot(): void
    {
        $autora = $this->person('autora');
        $lectora = $this->person('lectora');

        $postId = $this->publish($autora['token'], 'Algo');
        $commentId = $this->comment($postId, $lectora['token'], 'Primero');

        $primera = $this->reply($commentId, $autora['token'], 'Respondo');
        $segunda = $this->reply($primera, $lectora['token'], 'Y yo a ti');

        $this->replies($commentId, $autora['token']);

        self::assertSame([$primera, $segunda], $this->ids(), 'Las dos en el mismo hilo.');

        foreach ($this->payload()['comments'] as $reply) {
            self::assertSame($commentId, $reply['parentCommentId'], 'Ninguna cuelga de otra respuesta.');
        }
    }

    /**
     * Y la otra mitad: pedir «las respuestas de esta respuesta» devuelve las
     * del hilo entero. El cliente no tiene que saber cuál es el raíz.
     */
    public function testAskingForTheRepliesOfAReplyAnswersWithTheWholeThread(): void
    {
        $autora = $this->person('autora');
        $postId = $this->publish($autora['token'], 'Algo');
        $commentId = $this->comment($postId, $autora['token'], 'Primero');

        $primera = $this->reply($commentId, $autora['token'], 'Una');
        $segunda = $this->reply($commentId, $autora['token'], 'Dos');

        $this->replies($primera, $autora['token']);

        self::assertSame([$primera, $segunda], $this->ids());
    }

    /**
     * Un hilo se lee **en el orden en que se dijo**. Del revés obligaría a
     * leer hacia arriba para entender a qué contesta cada cosa.
     */
    public function testAThreadIsReadOldestFirst(): void
    {
        $autora = $this->person('autora');
        $postId = $this->publish($autora['token'], 'Algo');
        $commentId = $this->comment($postId, $autora['token'], 'Primero');

        $una = $this->reply($commentId, $autora['token'], 'Una');
        $dos = $this->reply($commentId, $autora['token'], 'Dos');
        $tres = $this->reply($commentId, $autora['token'], 'Tres');

        $this->replies($commentId, $autora['token'], '?limit=2');
        self::assertSame([$una, $dos], $this->ids());

        $this->replies($commentId, $autora['token'], '?limit=2&cursor='.$this->payload()['pageInfo']['nextCursor']);
        self::assertSame([$tres], $this->ids());
    }

    /**
     * `RN-3` y `RN-4`: la mención a quien se responde viaja con la
     * respuesta, y **se puede no mandar**. Es una comodidad del compositor,
     * no una obligación.
     */
    public function testTheMentionTravelsWithTheReplyAndIsOptional(): void
    {
        $autora = $this->person('autora', 'Ana García');
        $juanjo = $this->person('juanjo', 'Juanjo Estévez');

        $postId = $this->publish($autora['token'], 'Algo');
        $commentId = $this->comment($postId, $juanjo['token'], 'Qué bien');

        $conMencion = $this->replyWith($commentId, $autora['token'], 'Juanjo Estévez, gracias', [
            ['userId' => $juanjo['userId'], 'position' => 0],
        ]);
        $sinMencion = $this->reply($commentId, $autora['token'], 'Y otra cosa');

        $this->replies($commentId, $autora['token']);
        $porId = [];

        foreach ($this->payload()['comments'] as $reply) {
            $porId[(string) $reply['commentId']] = $reply;
        }

        self::assertSame($juanjo['userId'], $porId[$conMencion]['mentions'][0]['userId']);
        self::assertSame([], $porId[$sinMencion]['mentions'], 'La mención precargada se puede borrar.');
    }

    /**
     * `RN-5`: valen las mismas reglas que comentar. Un comentario no tiene
     * audiencia propia, hereda entera la de su publicación, así que
     * responder no es una puerta trasera a una conversación ajena.
     */
    public function testSomebodyExcludedByTheAudienceCannotReply(): void
    {
        $autora = $this->person('autora');
        $extrana = $this->person('extrana');

        $postId = $this->publish($autora['token'], 'Solo para los míos', 'FOLLOWERS');
        $commentId = $this->comment($postId, $autora['token'], 'Me comento');

        $this->post(\sprintf('/api/v1/comments/%s/replies', $commentId), $extrana['token'], ['body' => 'Hola']);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $this->replies($commentId, $extrana['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * `RN-7`: eliminar el comentario raíz elimina sus respuestas. Lo
     * contrario dejaría respuestas contestando a una pregunta que nadie
     * puede leer.
     */
    public function testDeletingTheRootTakesItsRepliesWithIt(): void
    {
        $autora = $this->person('autora');
        $postId = $this->publish($autora['token'], 'Algo');
        $commentId = $this->comment($postId, $autora['token'], 'Primero');

        $this->reply($commentId, $autora['token'], 'Una');
        $this->reply($commentId, $autora['token'], 'Dos');

        self::assertSame(3, $this->counterOf($postId, $autora['token']), 'El comentario y sus dos respuestas.');

        $this->client->request('DELETE', \sprintf('/api/v1/comments/%s', $commentId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$autora['token'],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->replies($commentId, $autora['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        self::assertSame(0, $this->counterOf($postId, $autora['token']), 'Y el contador baja por los tres.');
    }

    public function testDeletingAReplyLowersTheCounterOfItsComment(): void
    {
        $autora = $this->person('autora');
        $postId = $this->publish($autora['token'], 'Algo');
        $commentId = $this->comment($postId, $autora['token'], 'Primero');
        $replyId = $this->reply($commentId, $autora['token'], 'Una');

        $this->client->request('DELETE', \sprintf('/api/v1/comments/%s', $replyId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$autora['token'],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->comments($postId, $autora['token']);
        self::assertSame(0, $this->payload()['comments'][0]['replyCount']);
    }

    public function testAnUnactivatedAccountCannotReply(): void
    {
        $autora = $this->person('autora');
        $postId = $this->publish($autora['token'], 'Algo');
        $commentId = $this->comment($postId, $autora['token'], 'Primero');

        $token = $this->signedInWithoutActivating('nueva');

        $this->post(\sprintf('/api/v1/comments/%s/replies', $commentId), $token, ['body' => 'Hola']);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    /**
     * El hecho lleva **las dos personas a las que avisar**: quien escribió el
     * comentario y quien escribió la publicación. Sin `parentAuthorId`,
     * `Notification` tendría que preguntarle a `Community` quién es cada
     * cual, que es justo la llamada que un hecho evita.
     */
    public function testReplyingAnnouncesBothPeopleToTell(): void
    {
        $autora = $this->person('autora');
        $lectora = $this->person('lectora');

        $postId = $this->publish($autora['token'], 'Algo');
        $commentId = $this->comment($postId, $lectora['token'], 'Primero');

        $this->reply($commentId, $autora['token'], 'Gracias');

        $anunciado = $this->lastAnnouncementOf('PostCommented');

        self::assertSame($autora['userId'], $anunciado['postAuthorId']);
        self::assertSame($autora['userId'], $anunciado['commentAuthorId']);
        self::assertSame($lectora['userId'], $anunciado['parentAuthorId']);
    }

    private function reply(string $commentId, string $token, string $body): string
    {
        return $this->replyWith($commentId, $token, $body, []);
    }

    /**
     * @param list<array<string, mixed>> $mentions
     */
    private function replyWith(string $commentId, string $token, string $body, array $mentions): string
    {
        $this->post(\sprintf('/api/v1/comments/%s/replies', $commentId), $token, [
            'body' => $body,
            'mentions' => $mentions,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->capture();

        return (string) $this->payload()['commentId'];
    }

    private function replies(string $commentId, string $token, string $query = ''): void
    {
        $this->client->request('GET', \sprintf('/api/v1/comments/%s/replies%s', $commentId, $query), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
    }

    private function comments(string $postId, string $token): void
    {
        $this->client->request('GET', \sprintf('/api/v1/posts/%s/comments', $postId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        self::assertResponseIsSuccessful();
    }

    private function comment(string $postId, string $token, string $body): string
    {
        $this->post(\sprintf('/api/v1/posts/%s/comments', $postId), $token, ['body' => $body]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->capture();

        return (string) $this->payload()['commentId'];
    }

    private function publish(string $token, string $body, string $audience = 'EVERYONE'): string
    {
        $this->post('/api/v1/posts', $token, ['body' => $body, 'audience' => $audience]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->capture();

        return (string) $this->payload()['postId'];
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
}
