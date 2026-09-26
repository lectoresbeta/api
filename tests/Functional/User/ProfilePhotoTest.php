<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\User;

use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

/**
 * La foto de perfil (`FEAT-USR-037`).
 *
 * **La regla que no se puede saltar por comodidad es `RN-3`**: la imagen se
 * reescribe siempre en el servidor, venga como venga. Es tentador confiar en
 * una foto que el navegador ya ha recortado y guardarla tal cual; eso
 * publicaría **las coordenadas de dónde se tomó**. Aquí se prueba con una
 * imagen que lleva EXIF de verdad, comprobando que lo guardado no lo
 * conserva.
 *
 * Y la segunda: se guardan **dos ficheros** —la recortada y la original— y al
 * borrar la foto **se van los dos**. Dejar la original huérfana es guardar
 * una imagen personal que su dueña cree haber borrado.
 */
final class ProfilePhotoTest extends EconomyScenario
{
    public function testUploadingAPhotoPutsItInTheProfile(): void
    {
        $person = $this->activatedPerson('persona');

        $this->upload($person['token'], $this->jpeg(800, 800));

        self::assertResponseIsSuccessful();
        $url = (string) $this->payload()['avatarUrl'];
        self::assertStringStartsWith('/api/v1/media/avatars/', $url);

        $this->myProfile($person['token']);
        self::assertSame($url, $this->payload()['avatarUrl'], 'Y es la que devuelve el perfil.');
    }

    /**
     * `RN-3`. La imagen guardada **no conserva los metadatos**, y da igual
     * que quien la subió la hubiera recortado antes: recortar y sanear son
     * cosas distintas.
     */
    public function testTheStoredImageKeepsNoExifMetadata(): void
    {
        $person = $this->activatedPerson('persona');
        $conExif = $this->jpegWithExif(600, 600);

        self::assertStringContainsString('GPSLatitude', $conExif, 'La de partida sí lo lleva.');

        $this->upload($person['token'], $conExif);
        self::assertResponseIsSuccessful();

        $guardada = $this->fetch((string) $this->payload()['avatarUrl']);

        self::assertStringNotContainsString('GPSLatitude', $guardada);
        self::assertStringNotContainsString('Exif', $guardada);
        self::assertSame('image/webp', $this->client->getResponse()->headers->get('Content-Type'));
    }

    /**
     * `RN-4`: lo guardado es cuadrado y de tamaño acotado. El recorte
     * circular del diseño es una máscara de presentación, no la imagen.
     */
    public function testTheStoredImageIsSquareAndResized(): void
    {
        $person = $this->activatedPerson('persona');

        $this->upload($person['token'], $this->jpeg(4000, 3000));
        self::assertResponseIsSuccessful('Una foto de 4000 × 3000 se acepta y se redimensiona.');

        $medidas = getimagesizefromstring($this->fetch((string) $this->payload()['avatarUrl']));

        self::assertNotFalse($medidas);
        self::assertSame($medidas[0], $medidas[1], 'Cuadrada.');
        self::assertLessThanOrEqual(360, $medidas[0]);
    }

    /**
     * `RN-2`: el tipo se decide **por el contenido**, no por la extensión ni
     * por el `Content-Type`, que los escribe quien sube el fichero.
     */
    public function testAFileThatIsNotAnImageIsRefusedEvenNamedJpg(): void
    {
        $person = $this->activatedPerson('persona');

        $this->upload($person['token'], 'esto no es una imagen, por mucho que se llame foto.jpg');

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('UNSUPPORTED_FILE_TYPE', $this->payload()['code']);
    }

    /**
     * `RN-1`. **El límite lo aplica el servidor**: que el modal anuncie 2 MB
     * es una cortesía, no un control.
     */
    public function testAFileOverTwoMegabytesIsRefused(): void
    {
        $person = $this->activatedPerson('persona');

        $this->upload($person['token'], str_repeat('a', 2 * 1024 * 1024 + 1));

        self::assertResponseStatusCodeSame(Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        self::assertSame('FILE_TOO_LARGE', $this->payload()['code']);
    }

    /**
     * `RN-7`: el nombre del fichero se descarta. No se usa como ruta ni
     * aparece en la respuesta — un nombre de fichero puede decir mucho más de
     * lo que su dueño cree.
     */
    public function testTheOriginalFileNameNeverShowsUp(): void
    {
        $person = $this->activatedPerson('persona');

        $this->upload($person['token'], $this->jpeg(400, 400), name: 'vacaciones-en-casa-de-mama.jpg');

        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString('vacaciones', json_encode($this->payload(), \JSON_THROW_ON_ERROR));
    }

    /**
     * `RN-4c`: se guardan las dos. La original es material de trabajo del
     * editor, y sin ella «Editar» solo podría recortar hacia dentro de 360
     * píxeles, degradando la foto en cada pasada.
     */
    public function testTheOriginalIsKeptForTheEditorAndIsOnlyItsOwners(): void
    {
        $person = $this->activatedPerson('persona');
        $otra = $this->activatedPerson('otra');

        $this->upload($person['token'], $this->jpeg(400, 400), original: $this->jpeg(1200, 900));
        self::assertResponseIsSuccessful();

        $this->original($person['token']);
        self::assertResponseIsSuccessful();

        $medidas = getimagesizefromstring((string) $this->client->getResponse()->getContent());
        self::assertNotFalse($medidas);
        self::assertGreaterThan(360, $medidas[0], 'No es la recortada: es la grande.');

        // Y no es de nadie más. Ni siquiera hay una URL que compartir.
        $this->original($otra['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $this->client->request('GET', '/api/v1/me/profile/avatar/original');
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * La original **no se sirve por la puerta pública** aunque se conozca su
     * clave: vive en otra carpeta, y el endpoint abierto solo sirve avatares.
     * Confiar únicamente en que la clave es impredecible dejaría la puerta
     * abierta el día que una se filtre por un log.
     */
    public function testTheOriginalIsNotReachableThroughThePublicMediaEndpoint(): void
    {
        $person = $this->activatedPerson('persona');
        $this->upload($person['token'], $this->jpeg(400, 400), original: $this->jpeg(1200, 900));

        $clave = $this->storedKeyOf($person['userId'], 'avatar_original_url');
        self::assertStringStartsWith('originals/', $clave);

        $this->client->request('GET', '/api/v1/media/'.$clave);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * `RN-8`: subir otra reemplaza, y la anterior **deja de ser accesible**.
     * No se queda huérfana en el almacén para siempre.
     */
    public function testANewPhotoReplacesTheOldOneAndTheOldOneStopsExisting(): void
    {
        $person = $this->activatedPerson('persona');

        $this->upload($person['token'], $this->jpeg(400, 400));
        $primera = (string) $this->payload()['avatarUrl'];

        $this->upload($person['token'], $this->jpeg(500, 500));
        $segunda = (string) $this->payload()['avatarUrl'];

        self::assertNotSame($primera, $segunda);

        $this->client->request('GET', $primera);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND, 'La anterior ya no está.');

        $this->client->request('GET', $segunda);
        self::assertResponseIsSuccessful();
    }

    /**
     * Reencuadrar manda **solo la recortada**: la original ya está guardada y
     * volver a subirla sería mandar por la red algo que no ha cambiado. La
     * original no se toca.
     */
    public function testReframingKeepsTheSameOriginal(): void
    {
        $person = $this->activatedPerson('persona');

        $this->upload($person['token'], $this->jpeg(400, 400), original: $this->jpeg(1200, 900));
        $original = $this->storedKeyOf($person['userId'], 'avatar_original_url');

        $this->upload($person['token'], $this->jpeg(420, 420), crop: ['scale' => 1.4, 'rotation' => 90]);
        self::assertResponseIsSuccessful();

        self::assertSame($original, $this->storedKeyOf($person['userId'], 'avatar_original_url'));

        $this->original($person['token']);
        self::assertResponseIsSuccessful('Y sigue ahí.');
    }

    /**
     * `F-10`: el encuadre se guarda para reabrir el editor donde se dejó, y
     * **no se aplica nunca** — el recorte ya lo hizo el navegador. Va solo en
     * el perfil de su dueño, no en el que ven los demás.
     */
    public function testTheCropIsRememberedForItsOwnerAndNobodyElse(): void
    {
        $person = $this->activatedPerson('persona');

        $this->upload($person['token'], $this->jpeg(400, 400), crop: ['scale' => 1.5, 'rotation' => 90, 'offsetX' => -12]);

        $this->myProfile($person['token']);

        /** @var array<string, float|int> $crop */
        $crop = $this->payload()['avatarCrop'];
        ksort($crop);

        // Ordenado antes de comparar: se guarda como `jsonb`, que **no
        // conserva el orden de las claves**. Un cliente que dependiera de él
        // se llevaría una sorpresa, y la prueba no debe depender tampoco.
        self::assertSame(['offsetX' => -12, 'rotation' => 90, 'scale' => 1.5], $crop);

        $this->client->request('GET', \sprintf('/api/v1/users/%s', $person['userId']));
        self::assertResponseIsSuccessful();
        self::assertArrayNotHasKey('avatarCrop', $this->payload(), 'El perfil ajeno no enseña el material del editor.');
    }

    /**
     * `RN-12`: eliminar **borra los dos ficheros**, y `RN-13`: hacerlo dos
     * veces no es un error.
     */
    public function testDeletingTakesBothFilesAwayAndIsIdempotent(): void
    {
        $person = $this->activatedPerson('persona');

        $this->upload($person['token'], $this->jpeg(400, 400), original: $this->jpeg(1200, 900));
        $avatar = (string) $this->payload()['avatarUrl'];

        $this->delete($person['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->myProfile($person['token']);
        self::assertNull($this->payload()['avatarUrl'], 'Vuelve al avatar por defecto, que no es un fichero de nadie.');

        $this->client->request('GET', $avatar);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $this->original($person['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND, 'Y la original tampoco está.');

        $this->delete($person['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT, 'Borrar lo que no hay no es un fallo.');
    }

    /**
     * `RN-11`: se anuncia, **con la recortada y nunca con la original**, para
     * que los read models que copian el avatar se actualicen.
     */
    public function testChangingThePhotoIsAnnounced(): void
    {
        $person = $this->activatedPerson('persona');

        $this->upload($person['token'], $this->jpeg(400, 400), original: $this->jpeg(1200, 900));

        $anunciado = $this->lastAnnouncementOf('UserProfileUpdated');

        self::assertSame($person['userId'], $anunciado['userId']);
        self::assertSame($this->payload()['avatarUrl'], $anunciado['avatarUrl']);
        self::assertStringNotContainsString('originals/', (string) $anunciado['avatarUrl']);
    }

    public function testDeletingItIsAnnouncedToo(): void
    {
        $person = $this->activatedPerson('persona');
        $this->upload($person['token'], $this->jpeg(400, 400));

        $this->delete($person['token']);

        self::assertNull($this->lastAnnouncementOf('UserProfileUpdated')['avatarUrl']);
    }

    /**
     * `RN-9` y `decision:0003`: es escritura, y además es la imagen que
     * representa a alguien en toda la plataforma.
     */
    public function testAnUnactivatedAccountCannotChangeItsPhoto(): void
    {
        $token = $this->signedInWithoutActivating('pendiente');

        $this->upload($token, $this->jpeg(400, 400));
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame('ACCOUNT_NOT_ACTIVATED', $this->payload()['code']);

        $this->delete($token);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testWithoutASessionThereIsNoPhotoToChange(): void
    {
        $this->client->request('PUT', '/api/v1/me/profile/avatar');
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        $this->client->request('DELETE', '/api/v1/me/profile/avatar');
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Sin sesión **sí** se ve una foto: aparece en perfiles públicos, y
     * exigir sesión para pintarla haría inútil compartir el perfil.
     */
    public function testAPhotoIsVisibleWithoutASession(): void
    {
        $person = $this->activatedPerson('persona');
        $this->upload($person['token'], $this->jpeg(400, 400));

        $this->client->request('GET', (string) $this->payload()['avatarUrl']);

        self::assertResponseIsSuccessful();
        self::assertSame('image/webp', $this->client->getResponse()->headers->get('Content-Type'));
    }

    /**
     * @param array<string, float|int>|null $crop
     */
    private function upload(string $token, string $image, ?string $original = null, ?array $crop = null, string $name = 'foto.jpg'): void
    {
        $files = ['image' => $this->fileOf($image, $name)];

        if (null !== $original) {
            $files['original'] = $this->fileOf($original, 'original.jpg');
        }

        $this->client->request(
            'PUT',
            '/api/v1/me/profile/avatar',
            parameters: null === $crop ? [] : ['crop' => json_encode($crop, \JSON_THROW_ON_ERROR)],
            files: $files,
            server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token],
        );

        $this->capture();
    }

    private function delete(string $token): void
    {
        $this->client->request('DELETE', '/api/v1/me/profile/avatar', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        $this->capture();
    }

    private function original(string $token): void
    {
        $this->client->request('GET', '/api/v1/me/profile/avatar/original', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
    }

    private function myProfile(string $token): void
    {
        $this->client->request('GET', '/api/v1/me/profile', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
    }

    private function fetch(string $url): string
    {
        $this->client->request('GET', $url);

        self::assertResponseIsSuccessful();

        return (string) $this->client->getResponse()->getContent();
    }

    private function fileOf(string $contents, string $name): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'avatar');
        self::assertNotFalse($path);
        file_put_contents($path, $contents);

        // `test: true` evita la comprobación de «subido por HTTP», que en un
        // test no puede cumplirse. El `Content-Type` que se declara aquí es
        // justo el que el servidor no debe creerse.
        return new UploadedFile($path, $name, 'image/jpeg', test: true);
    }

    /**
     * @param int<1, max> $width
     * @param int<1, max> $height
     */
    private function jpeg(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        self::assertNotFalse($image);

        // Con algo de color: una imagen de un solo tono comprime tanto que
        // dejaría de parecerse a una foto de verdad.
        for ($x = 0; $x < $width; $x += 40) {
            $colour = imagecolorallocate($image, ($x * 7) % 255, ($x * 13) % 255, 90);
            imagefilledrectangle($image, $x, 0, $x + 20, $height, false === $colour ? 0 : $colour);
        }

        ob_start();
        imagejpeg($image, null, 85);

        return (string) ob_get_clean();
    }

    /**
     * Un JPEG con un bloque EXIF de verdad, con etiquetas de geolocalización
     * reconocibles. Se inserta a mano porque GD no escribe metadatos: lo que
     * hay que probar es que el servidor los quita, no que PHP los ponga.
     */
    /**
     * @param int<1, max> $width
     * @param int<1, max> $height
     */
    private function jpegWithExif(int $width, int $height): string
    {
        $jpeg = $this->jpeg($width, $height);
        $exif = "Exif\0\0".'II*'."\0".str_repeat('GPSLatitude 41.3874 GPSLongitude 2.1686 ', 4);
        $segment = "\xFF\xE1".pack('n', \strlen($exif) + 2).$exif;

        // Justo después del marcador de inicio de imagen.
        return substr($jpeg, 0, 2).$segment.substr($jpeg, 2);
    }

    private function storedKeyOf(string $userId, string $column): string
    {
        /** @var \Doctrine\ORM\EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');

        /** @var string|null $key */
        $key = $entityManager->getConnection()->fetchOne(
            \sprintf('SELECT %s FROM user_ctx.account WHERE id = :user', $column),
            ['user' => $userId],
        ) ?: null;

        self::assertNotNull($key);

        return $key;
    }
}
