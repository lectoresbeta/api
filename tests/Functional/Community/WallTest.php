<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Community;

use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

/**
 * Publicar y el muro (`FEAT-COM-002`, `FEAT-COM-001`).
 *
 * Las dos mitades de lo mismo, y por eso se prueban juntas: sin el muro,
 * publicar es escribir en un sitio que nadie mira, y **la audiencia es una
 * regla de privacidad que solo se puede defender donde se lee**.
 *
 * Lo que se vigila aquí es `RN-8`: en cuanto una consulta del muro olvide el
 * filtro, lo escrito para un círculo cerrado aparece ante todos, que es
 * exactamente la sorpresa que hace que la gente deje de publicar.
 */
final class WallTest extends EconomyScenario
{
    public function testPublishingATextPutsItOnTheWall(): void
    {
        $autora = $this->person('autora', 'Ana García');

        $postId = $this->publish($autora['token'], ['body' => 'Hoy he terminado el tercer capítulo 🎉']);

        $this->wall($autora['token']);
        self::assertSame($postId, $this->payload()['posts'][0]['postId']);
        self::assertSame('Hoy he terminado el tercer capítulo 🎉', $this->payload()['posts'][0]['body']);
        self::assertSame('TEXT', $this->payload()['posts'][0]['format']);
        self::assertSame('EVERYONE', $this->payload()['posts'][0]['audience']);
    }

    /**
     * `RN-2`: el autor es quien tiene la sesión. Aceptar un `authorId` del
     * cuerpo sería dejar publicar en nombre de otro.
     */
    public function testTheAuthorIsWhoeverHasTheSession(): void
    {
        $autora = $this->person('autora');
        $otra = $this->person('otra');

        $this->publish($autora['token'], ['body' => 'Mío', 'authorId' => $otra['userId']]);

        $this->wall($autora['token']);
        self::assertSame($autora['userId'], $this->payload()['posts'][0]['author']['userId']);
    }

    /**
     * `RN-4`: texto plano. Lo que se guarda acaba dentro de una tarjeta junto
     * al nombre de quien lo escribió, así que nada que un cliente pueda
     * interpretar sobrevive a la escritura. Los emojis sí: son texto.
     */
    public function testTheTextIsStoredAsPlainTextAndKeepsItsEmoji(): void
    {
        $autora = $this->person('autora');

        $this->publish($autora['token'], ['body' => '<script>alert(1)</script>Hola 👋 <b>mundo</b>']);

        $this->wall($autora['token']);
        $body = (string) $this->payload()['posts'][0]['body'];

        self::assertStringNotContainsString('<script>', $body);
        self::assertStringNotContainsString('<b>', $body);
        self::assertStringContainsString('Hola 👋', $body);
        self::assertStringContainsString('mundo', $body);
    }

    public function testAnEmptyPostIsRefused(): void
    {
        $autora = $this->person('autora');

        $this->post($autora['token'], []);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('EMPTY_POST', $this->payload()['code']);
    }

    public function testATextLongerThanTheMaximumIsRefused(): void
    {
        $autora = $this->person('autora');

        $this->post($autora['token'], ['body' => str_repeat('a', 5001)]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * Dos adjuntos se rechazan en vez de quedarse con el primero: quien manda
     * dos cree que va a publicar dos, y elegir por él le enseñaría el
     * resultado cuando ya no puede cambiarlo.
     */
    public function testTwoAttachmentsAreRefused(): void
    {
        $autora = $this->person('autora');
        $workId = $this->publishedWork($autora);

        $this->post($autora['token'], [
            'body' => 'Las dos cosas',
            'linkUrl' => 'https://example.org/algo',
            'workId' => $workId,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('TOO_MANY_ATTACHMENTS', $this->payload()['code']);
    }

    /**
     * **La regla que protege a quien pulsa.** `javascript:` no es un enlace,
     * es un botón que alguien ajeno escribió dentro de la tarjeta de otro.
     */
    public function testALinkThatIsNotHttpIsRefused(): void
    {
        $autora = $this->person('autora');

        $this->post($autora['token'], ['linkUrl' => 'javascript:alert(1)']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->post($autora['token'], ['linkUrl' => 'https://example.org/un-articulo']);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
    }

    /**
     * `RN-7`: un relato se guarda **por identificador**, no por URL. La
     * tarjeta se pinta con los datos vivos de la obra.
     */
    public function testAWorkIsAttachedByItsIdentifier(): void
    {
        $autora = $this->person('autora');
        $workId = $this->publishedWork($autora);

        $this->publish($autora['token'], ['body' => 'Os presento mi relato', 'workId' => $workId]);

        $this->wall($autora['token']);
        self::assertSame('WORK', $this->payload()['posts'][0]['format']);
        self::assertSame($workId, $this->payload()['posts'][0]['workId']);
    }

    /**
     * `RN-14`: no se promociona lo que no se puede abrir. El borrador de otra
     * persona no existe para quien publica.
     */
    public function testSomebodyElsesDraftCannotBePromoted(): void
    {
        $autora = $this->person('autora');
        $otra = $this->person('otra');

        $borrador = $this->createWork($otra['token'], 'Lo que no he enseñado');

        $this->post($autora['token'], ['body' => 'Mirad esto', 'workId' => $borrador]);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertSame('WORK_NOT_FOUND', $this->payload()['code']);
    }

    /**
     * **El criterio de aceptación que justifica que el muro exista.** Una
     * publicación para seguidores no la ve quien no sigue, y el filtro es de
     * servidor: no llega al cliente para que la esconda.
     */
    public function testAFollowersPostIsNotServedToSomebodyWhoDoesNotFollow(): void
    {
        $autora = $this->person('autora');
        $seguidora = $this->person('seguidora');
        $extrana = $this->person('extrana');

        $this->follow($seguidora['token'], $autora['userId']);

        $publica = $this->publish($autora['token'], ['body' => 'Para cualquiera', 'audience' => 'EVERYONE']);
        $cerrada = $this->publish($autora['token'], ['body' => 'Solo para los míos', 'audience' => 'FOLLOWERS']);

        $this->wall($seguidora['token']);
        self::assertSame([$cerrada, $publica], $this->ids(), 'Quien sigue ve las dos.');

        $this->wall($extrana['token']);
        self::assertSame([$publica], $this->ids(), 'Quien no sigue solo ve la abierta.');

        self::assertStringNotContainsString(
            'Solo para los míos',
            (string) $this->client->getResponse()->getContent(),
            'Y el texto no llega al cliente siquiera.',
        );
    }

    public function testTheAuthorAlwaysSeesTheirOwnRestrictedPost(): void
    {
        $autora = $this->person('autora');

        $cerrada = $this->publish($autora['token'], ['body' => 'Solo para los míos', 'audience' => 'FOLLOWERS']);

        $this->wall($autora['token']);
        self::assertSame([$cerrada], $this->ids(), 'No hace falta seguirse a uno mismo.');
    }

    /**
     * `RN-7` del muro: el bloqueo es unilateral en la intención y
     * **bidireccional en el efecto**, así que ninguno de los dos ve al otro.
     */
    public function testABlockHidesThePostsBothWays(): void
    {
        $una = $this->person('una');
        $otra = $this->person('otra');

        $suya = $this->publish($otra['token'], ['body' => 'Algo']);
        $mia = $this->publish($una['token'], ['body' => 'Otra cosa']);

        $this->client->request('PUT', \sprintf('/api/v1/users/%s/block', $otra['userId']), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$una['token'],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->capture();
        $this->consumeEverything();

        $this->wall($una['token']);
        self::assertNotContains($suya, $this->ids());

        $this->wall($otra['token']);
        self::assertNotContains($mia, $this->ids());
    }

    public function testTheAuthorEditsTheirOwnTextAndItIsMarkedAsEdited(): void
    {
        $autora = $this->person('autora');
        $postId = $this->publish($autora['token'], ['body' => 'Primera versión']);

        $this->wall($autora['token']);
        self::assertFalse($this->payload()['posts'][0]['edited']);

        $this->edit($postId, $autora['token'], 'Segunda versión');
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->wall($autora['token']);
        self::assertSame('Segunda versión', $this->payload()['posts'][0]['body']);
        self::assertTrue($this->payload()['posts'][0]['edited']);
    }

    /**
     * Editar no es una puerta para ampliar la audiencia: eso haría aparecer
     * ante todos algo escrito para un círculo cerrado.
     */
    public function testEditingDoesNotChangeTheAudience(): void
    {
        $autora = $this->person('autora');
        $extrana = $this->person('extrana');

        $postId = $this->publish($autora['token'], ['body' => 'Solo para los míos', 'audience' => 'FOLLOWERS']);

        $this->client->request('PATCH', \sprintf('/api/v1/posts/%s', $postId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$autora['token'],
        ], content: json_encode(['body' => 'Sigue siendo para los míos', 'audience' => 'EVERYONE'], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->wall($extrana['token']);
        self::assertSame([], $this->ids(), 'La audiencia no se toca al editar.');
    }

    public function testNobodyEditsOrDeletesSomebodyElsesPost(): void
    {
        $autora = $this->person('autora');
        $otra = $this->person('otra');

        $postId = $this->publish($autora['token'], ['body' => 'Mío']);

        $this->edit($postId, $otra['token'], 'Tuyo ya no');
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND, 'Y `404`, no `403`: un permiso denegado confirmaría que existe.');

        $this->client->request('DELETE', \sprintf('/api/v1/posts/%s', $postId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$otra['token'],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testADeletedPostIsGoneForEverybodyIncludingItsAuthor(): void
    {
        $autora = $this->person('autora');
        $postId = $this->publish($autora['token'], ['body' => 'Me arrepiento']);

        $this->client->request('DELETE', \sprintf('/api/v1/posts/%s', $postId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$autora['token'],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->wall($autora['token']);
        self::assertSame([], $this->ids(), 'No hay papelera: «eliminar» significa lo que la gente cree.');

        $this->edit($postId, $autora['token'], 'Recuperada');
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * `RN-10`: el hecho lleva la audiencia, porque `Notification` no debe
     * avisar a quien no puede abrir lo que se le anuncia.
     */
    public function testPublishingAnnouncesTheFactWithItsAudience(): void
    {
        $autora = $this->person('autora');

        $this->post($autora['token'], ['body' => 'Algo', 'audience' => 'FOLLOWERS']);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->capture();
        $anunciado = $this->lastAnnouncementOf('PostPublished');

        self::assertSame('FOLLOWERS', $anunciado['audience']);
        self::assertSame($autora['userId'], $anunciado['authorId']);
        self::assertArrayNotHasKey('body', $anunciado, 'Un aviso no necesita el cuerpo de lo publicado.');
    }

    public function testPublishingMovesNoCredits(): void
    {
        $autora = $this->person('autora');
        $antes = $this->balanceOf($autora['userId']);

        $this->publish($autora['token'], ['body' => 'Algo']);

        self::assertSame($antes, $this->balanceOf($autora['userId']));
    }

    public function testAnUnactivatedAccountCannotPublish(): void
    {
        $token = $this->signedInWithoutActivating('nueva');

        $this->post($token, ['body' => 'Hola']);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame('ACCOUNT_NOT_ACTIVATED', $this->payload()['code']);
    }

    /**
     * Leer no es escribir: el muro se sirve con la cuenta sin activar, que es
     * además lo que esa persona necesita para entender dónde ha entrado.
     */
    public function testAnUnactivatedAccountCanReadTheWall(): void
    {
        $token = $this->signedInWithoutActivating('nueva');

        $this->wall($token);

        self::assertResponseIsSuccessful();
    }

    public function testWithoutASessionThereIsNoWall(): void
    {
        $this->client->request('GET', '/api/v1/posts');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testAManipulatedCursorIsRefused(): void
    {
        $autora = $this->person('autora');

        $this->client->request('GET', '/api/v1/posts?cursor=lo-que-sea', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$autora['token'],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('INVALID_CURSOR', $this->payload()['code']);
    }

    public function testTheWallIsPagedNewestFirst(): void
    {
        $autora = $this->person('autora');

        $uno = $this->publish($autora['token'], ['body' => 'Uno']);
        $dos = $this->publish($autora['token'], ['body' => 'Dos']);
        $tres = $this->publish($autora['token'], ['body' => 'Tres']);

        $this->wall($autora['token'], '?limit=2');
        self::assertSame([$tres, $dos], $this->ids());
        self::assertTrue($this->payload()['pageInfo']['hasNextPage']);

        $this->wall($autora['token'], '?limit=2&cursor='.$this->payload()['pageInfo']['nextCursor']);
        self::assertSame([$uno], $this->ids());
        self::assertFalse($this->payload()['pageInfo']['hasNextPage']);
    }

    /**
     * Cada tarjeta lleva el autor **resuelto** (`RN-6`): una lista de
     * identificadores obligaría al cliente a una petición por tarjeta.
     */
    public function testEachCardCarriesItsAuthorResolved(): void
    {
        $autora = $this->person('autora', 'Ana García');
        $this->publish($autora['token'], ['body' => 'Algo']);

        $this->wall($autora['token']);
        $author = $this->payload()['posts'][0]['author'];

        self::assertSame($autora['userId'], $author['userId']);
        self::assertSame('Ana García', $author['name']);
        self::assertSame($autora['username'], $author['username']);
    }

    /**
     * `RN-5`: la imagen **se reescribe al guardarla** y pierde sus metadatos,
     * igual que el avatar. Los de una foto llevan escrito **dónde se tomó**, y
     * publicar una foto en un muro no puede significar publicar la dirección
     * de casa de quien la hizo.
     */
    public function testAnAttachedImageLosesItsExifMetadata(): void
    {
        $autora = $this->person('autora');

        $this->client->request('POST', '/api/v1/posts', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$autora['token'],
        ], files: ['image' => $this->upload($this->jpegWithExif(900, 600))], parameters: [
            'body' => 'Mirad dónde escribo',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->capture();

        $this->wall($autora['token']);
        self::assertSame('IMAGE', $this->payload()['posts'][0]['format']);

        $url = (string) $this->payload()['posts'][0]['imageUrl'];
        self::assertNotSame('', $url);

        $this->client->request('GET', $url, server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$autora['token'],
        ]);
        self::assertResponseIsSuccessful();

        $stored = (string) $this->client->getResponse()->getContent();
        self::assertStringNotContainsString('GPSLatitude', $stored);
        self::assertStringNotContainsString('Exif', $stored);
    }

    /**
     * Y la puerta que esa decisión cierra: la imagen de una publicación para
     * seguidores **no se sirve** a quien no la sigue, ni sabiendo su
     * dirección. Si estuviera en la carpeta pública, bastaría con la URL.
     */
    public function testTheImageOfARestrictedPostIsNotServedToAStranger(): void
    {
        $autora = $this->person('autora');
        $extrana = $this->person('extrana');

        $this->client->request('POST', '/api/v1/posts', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$autora['token'],
        ], files: ['image' => $this->upload($this->jpeg(600, 400))], parameters: [
            'body' => 'Solo para los míos',
            'audience' => 'FOLLOWERS',
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->capture();

        $postId = (string) $this->payload()['postId'];
        $url = \sprintf('/api/v1/posts/%s/image', $postId);

        $this->client->request('GET', $url, server: ['HTTP_AUTHORIZATION' => 'Bearer '.$autora['token']]);
        self::assertResponseIsSuccessful('Su autora sí la ve.');

        $this->client->request('GET', $url, server: ['HTTP_AUTHORIZATION' => 'Bearer '.$extrana['token']]);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $this->client->request('GET', $url);
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    private function upload(string $bytes): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'post');
        self::assertNotFalse($path);
        file_put_contents($path, $bytes);

        return new UploadedFile($path, 'foto.jpg', 'image/jpeg', test: true);
    }

    /**
     * @param int<1, max> $width
     * @param int<1, max> $height
     */
    private function jpeg(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        self::assertNotFalse($image);

        for ($x = 0; $x < $width; $x += 40) {
            $colour = imagecolorallocate($image, ($x * 7) % 255, ($x * 13) % 255, 90);
            imagefilledrectangle($image, $x, 0, $x + 20, $height, false === $colour ? 0 : $colour);
        }

        ob_start();
        imagejpeg($image, null, 85);

        return (string) ob_get_clean();
    }

    /**
     * Un JPEG con un bloque EXIF de verdad. Se inserta a mano porque GD no
     * escribe metadatos: lo que hay que probar es que el servidor los quita,
     * no que PHP los ponga.
     *
     * @param int<1, max> $width
     * @param int<1, max> $height
     */
    private function jpegWithExif(int $width, int $height): string
    {
        $jpeg = $this->jpeg($width, $height);
        $exif = "Exif\0\0".'II*'."\0".str_repeat('GPSLatitude 41.3874 GPSLongitude 2.1686 ', 4);
        $segment = "\xFF\xE1".pack('n', \strlen($exif) + 2).$exif;

        return substr($jpeg, 0, 2).$segment.substr($jpeg, 2);
    }

    /**
     * @param array<string, string> $body
     */
    private function publish(string $token, array $body): string
    {
        $this->post($token, $body);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->capture();

        return (string) $this->payload()['postId'];
    }

    /**
     * @param array<string, string> $body
     */
    private function post(string $token, array $body): void
    {
        $this->client->request('POST', '/api/v1/posts', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode($body, \JSON_THROW_ON_ERROR));
    }

    private function edit(string $postId, string $token, string $body): void
    {
        $this->client->request('PATCH', \sprintf('/api/v1/posts/%s', $postId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(['body' => $body], \JSON_THROW_ON_ERROR));
    }

    private function wall(string $token, string $query = ''): void
    {
        $this->client->request('GET', '/api/v1/posts'.$query, server: [
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
}
