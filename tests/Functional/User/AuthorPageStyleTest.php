<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\User;

use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

/**
 * Personalizar la página de autor (`FEAT-USR-016`).
 *
 * `U-5` preguntaba si esto tenía límites —«temas cerrados o CSS libre»— y
 * anotaba el motivo: riesgo de seguridad si es libre. Lo que estas pruebas
 * defienden es la respuesta: **un catálogo cerrado y nada más**.
 *
 * Si algún día alguien acepta aquí un hexadecimal «porque es solo un color»,
 * o una propiedad CSS «porque es inofensiva», estos casos son los que se
 * ponen rojos. Un color libre acaba interpolado en un atributo `style`, y ahí
 * una cadena que no sea un color es una inyección por la puerta de atrás.
 */
final class AuthorPageStyleTest extends EconomyScenario
{
    /** `RN-3`: sin haber elegido, el tema de siempre. */
    public function testWithoutChoosingItIsTheDefault(): void
    {
        $autora = $this->activatedPerson('autora');

        $this->readStyle($autora['token']);

        self::assertResponseIsSuccessful();
        self::assertSame('CLASSIC', $this->payload()['theme']);
        self::assertSame('SLATE', $this->payload()['accentColour']);
    }

    public function testTheAuthorPicksAThemeAndAColour(): void
    {
        $autora = $this->activatedPerson('autora');

        $this->writeStyle($autora['token'], ['theme' => 'MIDNIGHT', 'accentColour' => 'PLUM']);

        self::assertSame('MIDNIGHT', $this->payload()['theme']);
        self::assertSame('PLUM', $this->payload()['accentColour']);
    }

    /** Un campo ausente no pisa el otro: la pantalla mueve un selector cada vez. */
    public function testMovingOneSelectorDoesNotTouchTheOther(): void
    {
        $autora = $this->activatedPerson('autora');
        $this->writeStyle($autora['token'], ['theme' => 'MIDNIGHT', 'accentColour' => 'PLUM']);

        $this->writeStyle($autora['token'], ['accentColour' => 'AMBER']);

        self::assertSame('MIDNIGHT', $this->payload()['theme']);
        self::assertSame('AMBER', $this->payload()['accentColour']);
    }

    /**
     * **La prueba de `U-5`.** Ni CSS, ni un hexadecimal, ni una URL: lo único
     * que se acepta es un código del catálogo.
     */
    public function testNeitherCssNorAColourValueIsAccepted(): void
    {
        $autora = $this->activatedPerson('autora');

        $peligros = [
            ['accentColour' => '#ff0000'],
            ['accentColour' => 'red;position:fixed'],
            ['accentColour' => 'url(https://tercero.example/pixel)'],
            ['theme' => 'body{display:none}'],
            ['theme' => '@import url(https://tercero.example/x.css)'],
            ['theme' => 'midnight'],
            ['theme' => ''],
        ];

        foreach ($peligros as $body) {
            $this->put($autora['token'], $body);

            self::assertResponseStatusCodeSame(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                json_encode($body, \JSON_THROW_ON_ERROR),
            );
            self::assertContains($this->payload()['code'], ['UNKNOWN_AUTHOR_PAGE_THEME', 'UNKNOWN_ACCENT_COLOUR']);
        }

        // Y no ha cambiado nada por el camino.
        $this->readStyle($autora['token']);
        self::assertSame('CLASSIC', $this->payload()['theme']);
    }

    /**
     * `RN-7`: el estilo es contenido público y viaja **dentro del perfil**, no
     * en una petición aparte. La página de autor es el perfil (`P-5`).
     */
    public function testTheStyleTravelsInsideThePublicProfile(): void
    {
        $autora = $this->activatedPerson('autora');
        $this->writeStyle($autora['token'], ['theme' => 'PARCHMENT', 'accentColour' => 'FOREST']);

        $this->client->request('GET', '/api/v1/users/'.$autora['userId']);

        self::assertResponseIsSuccessful();
        self::assertSame('PARCHMENT', $this->payload()['theme']);
        self::assertSame('FOREST', $this->payload()['accentColour']);
    }

    /** Y quien no ha elegido nada también sale con valores, no con huecos. */
    public function testAProfileThatNeverChoseStillCarriesAStyle(): void
    {
        $autora = $this->activatedPerson('autora');

        $this->client->request('GET', '/api/v1/users/'.$autora['userId']);

        self::assertSame('CLASSIC', $this->payload()['theme']);
        self::assertSame('SLATE', $this->payload()['accentColour']);
    }

    /** El estilo es de cada autor. */
    public function testEachAuthorHasTheirOwn(): void
    {
        $una = $this->activatedPerson('una');
        $otra = $this->activatedPerson('otra');

        $this->writeStyle($una['token'], ['theme' => 'INK']);

        $this->readStyle($otra['token']);
        self::assertSame('CLASSIC', $this->payload()['theme']);
    }

    // ------------------------------------------------------------- el fondo

    /**
     * `RN-4`: el fondo es la portada de la cuenta, que existía desde
     * `FEAT-USR-014` y **nunca tuvo endpoint**. Esto le pone la puerta.
     */
    public function testTheAuthorUploadsABackground(): void
    {
        $autora = $this->activatedPerson('autora');

        $this->uploadCover($autora['token'], $this->jpeg(1200, 400));

        self::assertResponseIsSuccessful();
        $coverUrl = (string) $this->payload()['coverUrl'];
        self::assertStringStartsWith('/api/v1/media/covers/', $coverUrl);

        // Y se ve en el perfil público, sin sesión.
        $this->client->request('GET', '/api/v1/users/'.$autora['userId']);
        self::assertSame($coverUrl, $this->payload()['coverUrl']);

        // Y se sirve: `covers/` es una carpeta pública, como los avatares.
        $this->client->request('GET', $coverUrl);
        self::assertResponseIsSuccessful();
    }

    /**
     * **`RN-4`, y es la regla de seguridad del fondo.** La imagen se reescribe
     * siempre: guardarla tal cual publicaría los metadatos EXIF, y una foto de
     * fondo puede llevar las coordenadas de dónde se tomó.
     */
    public function testTheBackgroundIsRewrittenAndLosesItsMetadata(): void
    {
        $autora = $this->activatedPerson('autora');
        $conExif = $this->jpegWithExif(800, 300);

        self::assertStringContainsString('GPSLatitude', $conExif, 'El fichero de partida lleva metadatos de verdad.');

        $this->uploadCover($autora['token'], $conExif);
        self::assertResponseIsSuccessful();

        $this->client->request('GET', (string) $this->payload()['coverUrl']);
        self::assertResponseIsSuccessful();

        $servido = (string) $this->client->getResponse()->getContent();
        self::assertStringNotContainsString('GPSLatitude', $servido);
    }

    /** Subir otro sustituye al anterior, y el anterior deja de existir. */
    public function testANewBackgroundReplacesTheOldOne(): void
    {
        $autora = $this->activatedPerson('autora');

        $this->uploadCover($autora['token'], $this->jpeg(1200, 400));
        $primero = (string) $this->payload()['coverUrl'];

        $this->uploadCover($autora['token'], $this->jpeg(900, 300));
        $segundo = (string) $this->payload()['coverUrl'];

        self::assertNotSame($primero, $segundo);

        $this->client->request('GET', $primero);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND, 'El sustituido no se queda huérfano en el almacén.');
    }

    /** `RN-6`: quitarlo devuelve la página al tema sin imagen, y es idempotente. */
    public function testRemovingTheBackgroundIsIdempotent(): void
    {
        $autora = $this->activatedPerson('autora');
        $this->uploadCover($autora['token'], $this->jpeg(1200, 400));
        $coverUrl = (string) $this->payload()['coverUrl'];

        $this->deleteCover($autora['token']);
        $this->deleteCover($autora['token']);

        $this->client->request('GET', '/api/v1/users/'.$autora['userId']);
        self::assertNull($this->payload()['coverUrl']);

        $this->client->request('GET', $coverUrl);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * Se distingue «no es una imagen» de «es una imagen que no se puede leer»:
     * llevan a cosas distintas —elegir otro fichero, o volver a exportarlo—.
     */
    public function testWhatIsNotAnImageIsRefusedSayingSo(): void
    {
        $autora = $this->activatedPerson('autora');

        $this->putCover($autora['token'], 'esto no es una imagen', 'fondo.jpg');
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('UNSUPPORTED_COVER_TYPE', $this->payload()['code']);

        $this->client->request('PUT', '/api/v1/me/profile/cover', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$autora['token'],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('COVER_REQUIRED', $this->payload()['code']);
    }

    public function testABackgroundOverTheLimitIsRefused(): void
    {
        $autora = $this->activatedPerson('autora');

        $this->putCover($autora['token'], str_repeat('a', 5 * 1024 * 1024), 'enorme.jpg');

        self::assertResponseStatusCodeSame(Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        self::assertSame('COVER_TOO_LARGE', $this->payload()['code']);
    }

    public function testWithoutASessionNothingIsCustomised(): void
    {
        foreach ([
            ['GET', '/api/v1/me/author-page-style'],
            ['PUT', '/api/v1/me/author-page-style'],
            ['PUT', '/api/v1/me/profile/cover'],
            ['DELETE', '/api/v1/me/profile/cover'],
        ] as [$method, $path]) {
            $this->client->request($method, $path);

            self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED, $method.' '.$path);
        }
    }

    private function readStyle(string $token): void
    {
        $this->client->request('GET', '/api/v1/me/author-page-style', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function writeStyle(string $token, array $body): void
    {
        $this->put($token, $body);

        self::assertResponseIsSuccessful();
    }

    /**
     * @param array<string, mixed> $body
     */
    private function put(string $token, array $body): void
    {
        $this->client->request('PUT', '/api/v1/me/author-page-style', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode($body, \JSON_THROW_ON_ERROR));
    }

    private function uploadCover(string $token, string $bytes): void
    {
        $this->putCover($token, $bytes, 'fondo.jpg');
    }

    private function putCover(string $token, string $bytes, string $name): void
    {
        $path = tempnam(sys_get_temp_dir(), 'cover');
        self::assertNotFalse($path);
        file_put_contents($path, $bytes);

        $this->client->request(
            'PUT',
            '/api/v1/me/profile/cover',
            files: ['image' => new UploadedFile($path, $name, 'image/jpeg', test: true)],
            server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token],
        );

        $this->capture();
    }

    private function deleteCover(string $token): void
    {
        $this->client->request('DELETE', '/api/v1/me/profile/cover', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->capture();
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
        $payload = 'Exif'."\0\0".'GPSLatitude 41.3874 GPSLongitude 2.1686';
        $length = \strlen($payload) + 2;
        $segment = "\xFF\xE1".\chr($length >> 8).\chr($length & 0xFF).$payload;

        return substr($jpeg, 0, 2).$segment.substr($jpeg, 2);
    }
}
