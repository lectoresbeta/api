<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\User;

use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use LectoresBeta\User\AuthorPage\Application\Handler\UpdateAuthorLinksHandler;
use Symfony\Component\HttpFoundation\Response;

/**
 * Las referencias de la página de autor (`FEAT-USR-015`).
 *
 * **La página de autor es el perfil**, y esa es la decisión de la ficha
 * (`P-5` y `S-5`, resueltas). El nombre, la biografía, la foto y la portada
 * ya viven en el perfil; las obras publicadas, en `FEAT-USR-029`. Lo único
 * que faltaba eran las referencias.
 *
 * Dos perfiles habrían sido dos textos que envejecen por separado y dos
 * sitios donde la misma persona se describe de forma distinta, y de ahí que
 * no haya un `GET /authors/{id}/page`: se leen donde se lee todo lo demás.
 */
final class AuthorPageLinksTest extends EconomyScenario
{
    /**
     * `RN-1`: se guardan y se leen **en el perfil**, en el orden en que su
     * dueño las puso.
     */
    public function testTheyAreSavedAndReadOnTheProfile(): void
    {
        $autora = $this->activatedPerson('autora');

        $this->save($autora['token'], [
            ['label' => 'Mi web', 'url' => 'https://ejemplo.com'],
            ['label' => 'Mi blog', 'url' => 'https://blog.ejemplo.com'],
        ]);
        self::assertResponseIsSuccessful();

        $links = $this->profileOf($autora['userId'], $autora['token'])['links'];

        self::assertSame(
            [['label' => 'Mi web', 'url' => 'https://ejemplo.com'],
                ['label' => 'Mi blog', 'url' => 'https://blog.ejemplo.com']],
            $links,
        );
    }

    /**
     * `RN-2`: **se sustituyen enteras.** Lo que el formulario manda es «estas
     * son mis referencias», no «he añadido una».
     */
    public function testTheyAreReplacedWholesale(): void
    {
        $autora = $this->activatedPerson('autora');

        $this->save($autora['token'], [['label' => 'Mi web', 'url' => 'https://ejemplo.com']]);
        $this->save($autora['token'], [['label' => 'Otra', 'url' => 'https://otra.com']]);

        $links = $this->profileOf($autora['userId'], $autora['token'])['links'];

        self::assertCount(1, $links);
        self::assertSame('Otra', $links[0]['label']);
    }

    /**
     * Y la lista vacía las quita todas, que es lo que significa.
     */
    public function testAnEmptyListClearsThem(): void
    {
        $autora = $this->activatedPerson('autora');

        $this->save($autora['token'], [['label' => 'Mi web', 'url' => 'https://ejemplo.com']]);
        $this->save($autora['token'], []);

        self::assertSame([], $this->profileOf($autora['userId'], $autora['token'])['links']);
    }

    /**
     * `RN-3`: **solo `http` y `https`.**.
     *
     * Es la regla de seguridad de la ficha: la dirección la escribe un
     * usuario y la pulsa cualquiera que abra su perfil. Un `javascript:` sería
     * algo que ejecuta lo que escribió un extraño.
     */
    public function testOnlyHttpAndHttpsAreAccepted(): void
    {
        $autora = $this->activatedPerson('autora');

        foreach (['javascript:alert(1)', 'data:text/html,<script>', 'file:///etc/passwd', 'no-es-una-url'] as $url) {
            $this->save($autora['token'], [['label' => 'Trampa', 'url' => $url]]);

            self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
            self::assertSame('INVALID_AUTHOR_LINK', $this->payload()['code']);
        }
    }

    /**
     * `RN-4`: **una referencia sin etiqueta se rechaza**, no se rellena con
     * su dirección.
     *
     * Un enlace que no dice a dónde lleva es el que nadie pulsa, o el que se
     * pulsa por error; y poner la URL como texto visible invita justamente a
     * disfrazar el destino.
     */
    public function testAReferenceWithoutALabelIsRefused(): void
    {
        $autora = $this->activatedPerson('autora');

        $this->save($autora['token'], [['label' => '   ', 'url' => 'https://ejemplo.com']]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('AUTHOR_LINK_WITHOUT_LABEL', $this->payload()['code']);
    }

    /**
     * La etiqueta se **sanea**, como cualquier texto que escribe alguien y se
     * pinta a otro. El marcado se retira en vez de rechazarse: decirle a
     * quien pegó desde otro sitio que «su texto lleva HTML» no ayuda a nadie.
     */
    public function testTheLabelIsStrippedOfMarkup(): void
    {
        $autora = $this->activatedPerson('autora');

        $this->save($autora['token'], [
            ['label' => 'Mi <script>alert(1)</script>web', 'url' => 'https://ejemplo.com'],
        ]);
        self::assertResponseIsSuccessful();

        $label = $this->profileOf($autora['userId'], $autora['token'])['links'][0]['label'];

        self::assertStringNotContainsString('<', $label);
        self::assertStringNotContainsString('script', $label);
    }

    /**
     * `RN-5`: hay un tope. Un perfil con veinte enlaces deja de ser una
     * página de autor y pasa a ser un directorio de enlaces, que es otra cosa
     * y atrae a quien quiere justamente eso.
     */
    public function testThereIsALimit(): void
    {
        $autora = $this->activatedPerson('autora');

        $muchos = [];

        for ($i = 0; $i <= UpdateAuthorLinksHandler::MAX_LINKS; ++$i) {
            $muchos[] = ['label' => 'Enlace '.$i, 'url' => 'https://ejemplo.com/'.$i];
        }

        $this->save($autora['token'], $muchos);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('TOO_MANY_AUTHOR_LINKS', $this->payload()['code']);
    }

    /**
     * `RN-6`: las referencias son **públicas**, como el resto del perfil: un
     * visitante sin sesión las ve.
     */
    public function testTheyArePublicLikeTheRestOfTheProfile(): void
    {
        $autora = $this->activatedPerson('autora');
        $this->save($autora['token'], [['label' => 'Mi web', 'url' => 'https://ejemplo.com']]);

        $this->client->request('GET', \sprintf('/api/v1/users/%s', $autora['userId']));
        self::assertResponseIsSuccessful();

        self::assertSame('Mi web', $this->payload()['links'][0]['label']);
    }

    /**
     * Cada quien edita las suyas: no hay forma de tocar las de otro, porque
     * la ruta es `/me`.
     */
    public function testItRequiresASession(): void
    {
        $this->client->request('PUT', '/api/v1/me/author-links', server: ['CONTENT_TYPE' => 'application/json'], content: '{"links":[]}');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @param list<array{label: string, url: string}> $links
     */
    private function save(string $token, array $links): void
    {
        $this->client->request('PUT', '/api/v1/me/author-links', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(['links' => $links], \JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, mixed>
     */
    private function profileOf(string $userId, string $token): array
    {
        $this->client->request('GET', \sprintf('/api/v1/users/%s', $userId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        self::assertResponseIsSuccessful();

        return $this->payload();
    }
}
