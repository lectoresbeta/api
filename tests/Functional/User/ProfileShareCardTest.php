<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\User;

use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

/**
 * Compartir el perfil fuera (`FEAT-USR-032`).
 *
 * La tercera tarjeta de previsualización, y la que más cuidado pide: las
 * otras dos hablan de contenido y esta habla de una persona.
 *
 * Lo que se defiende es que **no abre nada**: la dirección es la canónica de
 * siempre, y solo hay tarjeta para un perfil que cualquiera podía ver ya. Si
 * algún día alguien reescribe la comprobación de privacidad aquí en lugar de
 * pedir el perfil sin espectador, estos casos son los que se ponen rojos.
 */
final class ProfileShareCardTest extends EconomyScenario
{
    public function testTheCardCarriesTheCanonicalAddressAndTheProfile(): void
    {
        $autora = $this->activatedPerson('autora');
        $this->editProfile($autora['token'], [
            'name' => 'Ana García',
            'description' => 'Escribo novela negra en Lavapiés.',
        ]);

        $this->share($autora['userId']);

        self::assertResponseIsSuccessful();
        self::assertSame('http://localhost/usuarios/'.$autora['userId'], $this->payload()['url']);
        self::assertSame('Ana García', $this->payload()['title']);
        self::assertSame('Escribo novela negra en Lavapiés.', $this->payload()['description']);
        self::assertNull($this->payload()['imageUrl'], 'Sin avatar no se inventa una imagen.');
    }

    /**
     * `RN-1`: **no se genera ningún enlace.** No hay token que pueda circular
     * por una red social, y no hay nada que revocar.
     */
    public function testTheAddressCarriesNoToken(): void
    {
        $autora = $this->activatedPerson('autora');

        $this->share($autora['userId']);

        $url = (string) $this->payload()['url'];
        self::assertStringNotContainsString('?', $url);
        self::assertStringNotContainsString('token', $url);
    }

    /** No hace falta sesión: quien pregunta es un rastreador. */
    public function testItAnswersWithoutASession(): void
    {
        $autora = $this->activatedPerson('autora');

        $this->share($autora['userId']);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Cache-Control', 'max-age=300, public');
    }

    /**
     * **`RN-2`, y es la regla que protege a quien cerró su perfil.** Un
     * rastreador no sigue a nadie, así que `FOLLOWERS` le responde lo mismo
     * que `NOBODY`.
     */
    public function testAProfileThatIsNotOpenToEveryoneHasNoCard(): void
    {
        foreach (['FOLLOWERS', 'NOBODY'] as $visibility) {
            $persona = $this->activatedPerson('cerrada'.strtolower($visibility));
            $this->putAs('/api/v1/me/privacy-settings', $persona['token'], ['profileVisibility' => $visibility]);

            $this->share($persona['userId']);

            self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND, $visibility);
        }
    }

    /**
     * Y ni siquiera su titular obtiene la tarjeta de su perfil cerrado. No es
     * un descuido: la tarjeta existe para que la pinte alguien de fuera, y
     * devolvérsela sería decirle que se puede compartir algo que nadie va a
     * poder abrir.
     */
    public function testNotEvenItsOwnerGetsTheCardOfAClosedProfile(): void
    {
        $persona = $this->activatedPerson('cerrada');
        $this->putAs('/api/v1/me/privacy-settings', $persona['token'], ['profileVisibility' => 'NOBODY']);

        $this->client->request('GET', '/api/v1/users/'.$persona['userId'].'/share', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$persona['token'],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /** `RN-3`: lo que no existe y lo que no se enseña responden igual. */
    public function testAProfileThatDoesNotExistAnswersTheSame(): void
    {
        foreach (['0192b1f0-0000-7000-8000-000000000000', 'no-es-un-uuid'] as $userId) {
            $this->share($userId);

            self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND, $userId);
        }
    }

    /** `RN-4`: quien no ha terminado el onboarding todavía no tiene nombre. */
    public function testWithoutANameTheTitleIsTheUsername(): void
    {
        $autora = $this->activatedPerson('autora');

        $this->client->request('GET', '/api/v1/users/'.$autora['userId']);
        $username = (string) $this->payload()['username'];

        $this->share($autora['userId']);

        self::assertSame('@'.$username, $this->payload()['title']);
    }

    /** Una biografía larga se recorta: es una previsualización. */
    public function testALongBioIsTrimmed(): void
    {
        $autora = $this->activatedPerson('autora');
        $this->editProfile($autora['token'], [
            'name' => 'Ana García',
            'description' => str_repeat('a', 300),
        ]);

        $this->share($autora['userId']);

        $description = (string) $this->payload()['description'];
        self::assertSame(201, mb_strlen($description));
        self::assertStringEndsWith('…', $description);
    }

    /**
     * `RN-5`. En el resto de la API el avatar viaja relativo; en una
     * `og:image` eso es una imagen rota, porque quien la lee no sabe contra
     * qué origen habla.
     */
    public function testTheAvatarTravelsAbsolute(): void
    {
        $autora = $this->activatedPerson('autora');
        $this->uploadAvatar($autora['token']);

        $this->share($autora['userId']);

        $imageUrl = (string) $this->payload()['imageUrl'];
        self::assertStringStartsWith('http://localhost/api/v1/media/', $imageUrl);
    }

    private function share(string $userId): void
    {
        $this->client->request('GET', '/api/v1/users/'.$userId.'/share');
    }

    /**
     * @param array<string, mixed> $body
     */
    private function editProfile(string $token, array $body): void
    {
        $this->client->request('PATCH', '/api/v1/me/profile', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode($body, \JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        $this->capture();
    }

    private function uploadAvatar(string $token): void
    {
        $image = imagecreatetruecolor(400, 400);
        self::assertNotFalse($image);

        for ($x = 0; $x < 400; $x += 40) {
            $colour = imagecolorallocate($image, ($x * 7) % 255, ($x * 13) % 255, 90);
            imagefilledrectangle($image, $x, 0, $x + 20, 400, false === $colour ? 0 : $colour);
        }

        ob_start();
        imagejpeg($image, null, 85);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        $path = tempnam(sys_get_temp_dir(), 'avatar');
        self::assertNotFalse($path);
        file_put_contents($path, $bytes);

        $this->client->request(
            'PUT',
            '/api/v1/me/profile/avatar',
            files: ['image' => new UploadedFile($path, 'foto.jpg', 'image/jpeg', test: true)],
            server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token],
        );

        self::assertResponseIsSuccessful();
        $this->capture();
    }
}
