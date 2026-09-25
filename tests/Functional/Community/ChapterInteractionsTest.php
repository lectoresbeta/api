<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Community;

use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use Symfony\Component\HttpFoundation\Response;

/**
 * La barra social bajo el texto de un capítulo (`FEAT-COM-036`).
 *
 * > **Comentar un capítulo no es corregirlo.**
 * >
 * > Es la distinción más importante de esta pantalla y la que la
 * > documentación de este producto confundió durante meses. Un comentario es
 * > libre, caben los que sean, y **no mueve un solo crédito**; la corrección
 * > es el cuestionario del autor, hay una por lector y capítulo, y sí los
 * > mueve. Hay una prueba aquí dedicada a eso y no a otra cosa.
 *
 * Lo demás son las reglas de siempre con contenido inédito: **no se comenta
 * ni se apoya lo que no se puede leer**, y el error es el mismo que si el
 * capítulo no existiera.
 */
final class ChapterInteractionsTest extends EconomyScenario
{
    /**
     * `RN-1`, `RN-2`: apoyar es alternable e idempotente en las dos
     * direcciones, y la cabecera lo cuenta.
     */
    public function testALikeTogglesAndRepeatingItChangesNothing(): void
    {
        [$autora, $lectora, $chapterId] = $this->aPublicChapter();

        self::assertSame(1, $this->like($lectora['token'], $chapterId));
        self::assertSame(1, $this->like($lectora['token'], $chapterId), 'Repetirlo no suma.');

        self::assertSame(1, $this->engagement($autora['token'], $chapterId)['likeCount']);

        self::assertSame(0, $this->unlike($lectora['token'], $chapterId));
        self::assertSame(0, $this->unlike($lectora['token'], $chapterId), 'Y quitarlo dos veces no baja de cero.');
    }

    /**
     * `R-1`: las cifras son **del capítulo**, y `likedByViewer` es de quien
     * mira, para que el corazón salga resaltado sin una segunda petición.
     */
    public function testTheHeaderCountsBelongToTheChapterAndTheHeartToTheViewer(): void
    {
        [$autora, $lectora, $chapterId] = $this->aPublicChapter();

        $this->like($lectora['token'], $chapterId);
        $this->comment($lectora['token'], $chapterId, 'Me ha encantado el ritmo.');

        $mirado = $this->engagement($lectora['token'], $chapterId);
        self::assertSame(1, $mirado['likeCount']);
        self::assertSame(1, $mirado['commentCount']);
        self::assertTrue($mirado['likedByViewer']);

        self::assertFalse(
            $this->engagement($autora['token'], $chapterId)['likedByViewer'],
            'La autora no lo ha apoyado.',
        );
        self::assertArrayNotHasKey('readCount', $mirado, 'Qué cuenta como lectura sigue sin definirse (`H-3`).');
    }

    /**
     * **La prueba que da nombre a la ficha**: comentar no mueve créditos.
     *
     * Se comprueba por los dos lados que importan — el saldo no se mueve, y
     * `Credits` no recibe nada que pudiera interpretar.
     */
    public function testCommentingMovesNoCredits(): void
    {
        [$autora, $lectora, $chapterId] = $this->aPublicChapter();

        $antesAutora = $this->balanceOf($autora['userId']);
        $antesLectora = $this->balanceOf($lectora['userId']);

        $this->comment($lectora['token'], $chapterId, 'Esto no es una corrección.');
        $this->consumeEverything();

        self::assertSame($antesAutora, $this->balanceOf($autora['userId']), 'A la autora no le cuesta nada.');
        self::assertSame($antesLectora, $this->balanceOf($lectora['userId']), 'Y quien comenta no cobra.');
    }

    /**
     * `RN-4`: los hilos son **planos**. Responder a una respuesta cuelga del
     * comentario raíz, y el cliente no necesita saber cuál es.
     */
    public function testThreadsAreFlattenedToOneLevel(): void
    {
        [$autora, $lectora, $chapterId] = $this->aPublicChapter();

        $raiz = $this->comment($lectora['token'], $chapterId, 'Pues yo creo que…');
        $respuesta = $this->comment($autora['token'], $chapterId, 'Gracias por leerlo', $raiz);
        $tercero = $this->comment($lectora['token'], $chapterId, 'A ti', $respuesta);

        $replies = $this->repliesOf($lectora['token'], $raiz);

        self::assertSame([$respuesta, $tercero], array_column($replies, 'commentId'));
        self::assertSame(
            [$raiz, $raiz],
            array_column($replies, 'parentCommentId'),
            'La respuesta a una respuesta cuelga del raíz.',
        );
    }

    /**
     * La lista de primer nivel **no mezcla las respuestas**, y cuenta cuántas
     * cuelgan de cada una.
     */
    public function testTheListCarriesOnlyTopLevelCommentsWithTheirReplyCount(): void
    {
        [$autora, $lectora, $chapterId] = $this->aPublicChapter();

        $raiz = $this->comment($lectora['token'], $chapterId, 'Primero');
        $this->comment($autora['token'], $chapterId, 'Una respuesta', $raiz);

        $comentarios = $this->commentsOf($lectora['token'], $chapterId);

        self::assertCount(1, $comentarios);
        self::assertSame($raiz, $comentarios[0]['commentId']);
        self::assertSame(1, $comentarios[0]['replyCount']);
        self::assertTrue($comentarios[0]['mine']);
        self::assertSame(2, $this->engagement($lectora['token'], $chapterId)['commentCount'], 'El del capítulo cuenta las dos.');
    }

    /**
     * **La regla que impide el agujero**: no se comenta ni se apoya lo que no
     * se puede leer, y el error es el mismo que si no existiera.
     */
    public function testYouCannotTouchAChapterYouCannotRead(): void
    {
        $autora = $this->activatedPerson('autora');
        $extranya = $this->activatedPerson('extranya');

        // Sin publicar: una obra en borrador no existe para nadie más.
        $workId = $this->createWork($autora['token'], 'Inédita');
        $chapterId = $this->addChapter($workId, $autora['token'], words: 900);

        foreach ([['PUT', '/like'], ['GET', '/comments'], ['GET', '/engagement']] as [$method, $suffix]) {
            $this->client->request($method, \sprintf('/api/v1/chapters/%s%s', $chapterId, $suffix), server: [
                'HTTP_AUTHORIZATION' => 'Bearer '.$extranya['token'],
            ]);
            self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND, $method.$suffix);
            self::assertSame('CHAPTER_NOT_FOUND', $this->payload()['code']);
        }

        $this->client->request('POST', \sprintf('/api/v1/chapters/%s/comments', $chapterId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$extranya['token'],
        ], content: json_encode(['body' => 'Hola'], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * Y un capítulo **oculto** de una obra publicada tampoco, aunque la obra
     * sí se vea. La autora sigue viendo el suyo.
     */
    public function testAHiddenChapterIsOnlyItsAuthorsToComment(): void
    {
        [$autora, $lectora, $chapterId] = $this->aPublicChapter();

        $this->putAs(\sprintf('/api/v1/chapters/%s/visibility', $chapterId), $autora['token'], [
            'visibility' => 'HIDDEN',
        ]);

        $this->client->request('PUT', \sprintf('/api/v1/chapters/%s/like', $chapterId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$lectora['token'],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        self::assertSame(1, $this->like($autora['token'], $chapterId), 'La autora lee lo suyo.');
    }

    /**
     * El techo de audiencia del perfil (`FEAT-USR-038` `RN-2`): quien cierra
     * sus comentarios cierra también los de sus capítulos.
     *
     * **Pero no esconde la conversación que ya existe** ni la cifra de
     * apoyos: cerrar el buzón no es borrar lo dicho.
     */
    public function testClosingCommentsStopsNewOnesButHidesNothing(): void
    {
        [$autora, $lectora, $chapterId] = $this->aPublicChapter();

        $this->comment($lectora['token'], $chapterId, 'Dicho antes de que cerrara');
        $this->like($lectora['token'], $chapterId);

        $this->putAs('/api/v1/me/privacy-settings', $autora['token'], [
            'profileVisibility' => 'EVERYONE',
            'commentPermission' => 'NOBODY',
            'messagePermission' => 'EVERYONE',
        ]);
        $this->consumeEverything();

        $this->client->request('POST', \sprintf('/api/v1/chapters/%s/comments', $chapterId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$lectora['token'],
        ], content: json_encode(['body' => 'Y esto ya no'], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame('COMMENTS_NOT_ACCEPTED', $this->payload()['code']);

        self::assertCount(1, $this->commentsOf($lectora['token'], $chapterId), 'Lo dicho sigue dicho.');
        self::assertSame(1, $this->engagement($lectora['token'], $chapterId)['likeCount']);
        self::assertSame(0, $this->unlike($lectora['token'], $chapterId), 'Y lo suyo lo puede retirar.');
    }

    /**
     * `RN-6`: retirar un comentario raíz se lleva sus respuestas, y el
     * contador baja por todo lo que se va.
     */
    public function testDeletingARootTakesItsRepliesAndTheCount(): void
    {
        [$autora, $lectora, $chapterId] = $this->aPublicChapter();

        $raiz = $this->comment($lectora['token'], $chapterId, 'Un hilo');
        $this->comment($autora['token'], $chapterId, 'Una respuesta', $raiz);
        $this->comment($autora['token'], $chapterId, 'Y otra', $raiz);

        self::assertSame(3, $this->engagement($lectora['token'], $chapterId)['commentCount']);

        $this->client->request('DELETE', \sprintf('/api/v1/chapter-comments/%s', $raiz), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$lectora['token'],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        self::assertSame([], $this->commentsOf($lectora['token'], $chapterId));
        self::assertSame(0, $this->engagement($lectora['token'], $chapterId)['commentCount']);
    }

    /**
     * Y solo el propio: el de otra persona responde `404`, igual que uno que
     * no existe.
     */
    public function testYouOnlyDeleteYourOwnComment(): void
    {
        [$autora, $lectora, $chapterId] = $this->aPublicChapter();

        $ajeno = $this->comment($lectora['token'], $chapterId, 'Mío');

        $this->client->request('DELETE', \sprintf('/api/v1/chapter-comments/%s', $ajeno), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$autora['token'],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * `RN-7`: comentar avisa al autor de la obra, y responder avisa además a
     * quien escribió el comentario. Nadie se avisa a sí mismo.
     */
    public function testTheAuthorIsToldAndSoIsWhoeverIsAnswered(): void
    {
        [$autora, $lectora, $chapterId] = $this->aPublicChapter();

        $raiz = $this->comment($lectora['token'], $chapterId, 'Qué buen capítulo');
        $this->consumeEverything();

        self::assertContains('CHAPTER_COMMENT', $this->kindsOf($autora['token']));
        self::assertSame([], $this->kindsOf($lectora['token']), 'Quien comenta ya sabe que ha comentado.');

        $tercera = $this->activatedPerson('tercera');
        $this->comment($tercera['token'], $chapterId, 'Coincido', $raiz);
        $this->consumeEverything();

        self::assertContains('POST_REPLY', $this->kindsOf($lectora['token']));
    }

    /**
     * Un comentario vacío o interminable no sale, y el error lo dice: es lo
     * único que quien escribe puede arreglar.
     */
    public function testTheBodyHasToBeAComment(): void
    {
        [, $lectora, $chapterId] = $this->aPublicChapter();

        foreach (['', '   ', str_repeat('a', 1001)] as $body) {
            $this->client->request('POST', \sprintf('/api/v1/chapters/%s/comments', $chapterId), server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$lectora['token'],
            ], content: json_encode(['body' => $body], \JSON_THROW_ON_ERROR));

            self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY, var_export($body, true));
        }
    }

    public function testWithoutASessionThereIsNothingToTouch(): void
    {
        [, , $chapterId] = $this->aPublicChapter();

        $this->client->request('PUT', \sprintf('/api/v1/chapters/%s/like', $chapterId));
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Una obra pública y publicada, con un capítulo que cualquiera puede
     * leer.
     *
     * @return array{0: array{token: string, userId: string}, 1: array{token: string, userId: string}, 2: string}
     */
    private function aPublicChapter(): array
    {
        $autora = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');

        $workId = $this->createWork($autora['token'], 'La ciudad de los pájaros');
        $chapterId = $this->addChapter($workId, $autora['token'], words: 900);
        $this->putAs(\sprintf('/api/v1/works/%s/access-mode', $workId), $autora['token'], ['accessMode' => 'PUBLIC']);
        $this->putAs(\sprintf('/api/v1/works/%s/status', $workId), $autora['token'], ['status' => 'PUBLISHED']);
        $this->consumeEverything();

        return [$autora, $lectora, $chapterId];
    }

    private function like(string $token, string $chapterId): int
    {
        return $this->toggle('PUT', $token, $chapterId);
    }

    private function unlike(string $token, string $chapterId): int
    {
        return $this->toggle('DELETE', $token, $chapterId);
    }

    private function toggle(string $method, string $token, string $chapterId): int
    {
        $this->client->request($method, \sprintf('/api/v1/chapters/%s/like', $chapterId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseIsSuccessful();
        $this->capture();

        return (int) $this->payload()['likeCount'];
    }

    private function comment(string $token, string $chapterId, string $body, ?string $parentCommentId = null): string
    {
        $this->client->request('POST', \sprintf('/api/v1/chapters/%s/comments', $chapterId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(
            array_filter(['body' => $body, 'parentCommentId' => $parentCommentId]),
            \JSON_THROW_ON_ERROR,
        ));

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->capture();

        return (string) $this->payload()['commentId'];
    }

    /**
     * @return array<string, mixed>
     */
    private function engagement(string $token, string $chapterId): array
    {
        $this->client->request('GET', \sprintf('/api/v1/chapters/%s/engagement', $chapterId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        self::assertResponseIsSuccessful();

        return $this->payload();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function commentsOf(string $token, string $chapterId): array
    {
        $this->client->request('GET', \sprintf('/api/v1/chapters/%s/comments', $chapterId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        self::assertResponseIsSuccessful();

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->payload()['comments'];

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function repliesOf(string $token, string $commentId): array
    {
        $this->client->request('GET', \sprintf('/api/v1/chapter-comments/%s/replies', $commentId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        self::assertResponseIsSuccessful();

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->payload()['comments'];

        return $rows;
    }

    /**
     * @return list<string>
     */
    private function kindsOf(string $token): array
    {
        $this->client->request('GET', '/api/v1/me/notifications', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        self::assertResponseIsSuccessful();

        /** @var list<array{kind: string}> $data */
        $data = $this->payload()['data'];

        return array_map(static fn (array $row): string => $row['kind'], $data);
    }
}
