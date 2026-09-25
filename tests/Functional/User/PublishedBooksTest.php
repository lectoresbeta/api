<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\User;

use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use Symfony\Component\HttpFoundation\Response;

/**
 * La bibliografía del autor (`FEAT-USR-029`).
 *
 * **Lo que estas pruebas defienden antes que nada es que una obra publicada
 * no es una `Work`**: comparten la palabra «obra» y nada más. La de aquí está
 * editada y a la venta fuera, no tiene contenido en la plataforma, no se
 * puede leer ni corregir, no mueve créditos y no cuenta como relato.
 *
 * Si algún día alguien las junta «porque son casi lo mismo», estos casos son
 * los que se pondrán rojos.
 */
final class PublishedBooksTest extends EconomyScenario
{
    /**
     * `RN-6`: **solo el título es obligatorio**. Quien cita una obra
     * descatalogada no tiene editorial que poner ni enlace al que mandar a
     * nadie, y exigírselos le dejaría fuera una obra que existió.
     */
    public function testATitleIsEnoughToAddABook(): void
    {
        $autora = $this->activatedPerson('autora');

        $book = $this->add($autora['token'], ['title' => 'Cuentos de la mesa camilla']);

        self::assertSame('Cuentos de la mesa camilla', $book['title']);
        self::assertNull($book['publisher']);
        self::assertNull($book['publicationYear']);
        self::assertNull($book['purchaseUrl']);
        self::assertFalse($book['purchaseUrlIsExternal']);
        self::assertNull($book['coverUrl'], 'RN-10: sin portada, y el marcador lo pone la interfaz.');
    }

    /**
     * `RN-2`: es contenido público. Aparece en el perfil que ven los demás,
     * **sin sesión**, porque acreditar una trayectoria es enseñarla.
     */
    public function testTheBibliographyIsPublic(): void
    {
        $autora = $this->activatedPerson('autora');
        $this->add($autora['token'], ['title' => 'La primera']);

        $this->client->request('GET', $this->bibliographyOf($autora['userId']));

        self::assertResponseIsSuccessful('Sin cabecera de sesión.');
        self::assertSame('La primera', $this->payload()['publishedBooks'][0]['title']);
    }

    /**
     * `RN-9`: **la editorial no se valida**. El ejemplo del propio diseño
     * dice «Amazon», que no es un sello sino una plataforma de
     * autopublicación, y validarla contra un catálogo dejaría fuera justo al
     * autor que más usa este campo.
     */
    public function testAnythingGoesAsAPublisher(): void
    {
        $autora = $this->activatedPerson('autora');

        foreach (['Amazon', 'Edición del autor', 'Tusquets'] as $publisher) {
            $book = $this->add($autora['token'], ['title' => 'Obra de '.$publisher, 'publisher' => $publisher]);
            self::assertSame($publisher, $book['publisher']);
        }
    }

    /**
     * `RN-5`: el enlace de compra sale de la plataforma, y por eso se valida
     * aquí en vez de confiar en que el cliente lo pinte con cuidado. Un
     * `javascript:` no es un enlace: es un botón que alguien ajeno escribió.
     */
    public function testAMalformedPurchaseLinkIsRefused(): void
    {
        $autora = $this->activatedPerson('autora');

        foreach (['javascript:alert(1)', 'no-es-una-url', 'ftp://algun.sitio/libro', 'http://'] as $link) {
            $this->client->request('POST', '/api/v1/me/published-books', server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$autora['token'],
            ], content: json_encode(['title' => 'Con enlace raro', 'purchaseUrl' => $link], \JSON_THROW_ON_ERROR));

            self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY, $link);
            self::assertSame('INVALID_PURCHASE_URL', $this->payload()['code'], $link);
        }

        $book = $this->add($autora['token'], [
            'title' => 'Con enlace bueno',
            'purchaseUrl' => 'https://www.amazon.es/dp/0000000000',
        ]);

        self::assertSame('https://www.amazon.es/dp/0000000000', $book['purchaseUrl']);
        self::assertTrue($book['purchaseUrlIsExternal'], 'Y viene marcado como saliente.');
    }

    /**
     * `RN-3`, la regla que impide que este concepto contamine el resto: una
     * obra publicada **no se lee, ni se corrige, ni se pide acceso a ella**.
     *
     * Se comprueba por donde se rompería de verdad: su identificador no vale
     * como el de una obra en ninguno de los endpoints de `Work`, `Reading` ni
     * `Feedback`. En cuanto algo «publicado» admitiera correcciones habría
     * que decidir si cuesta créditos, y el modelo entero se tambalearía.
     */
    public function testAPublishedBookIsNotAWorkAnywhere(): void
    {
        $autora = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');
        $book = $this->add($autora['token'], ['title' => 'La que no se lee aquí']);
        $id = $book['publishedBookId'];

        $this->client->request('GET', \sprintf('/api/v1/works/%s', $id), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$lectora['token'],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND, 'No es una obra que leer.');

        $this->client->request('POST', \sprintf('/api/v1/works/%s/access-requests', $id), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$lectora['token'],
        ], content: '{}');
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND, 'Ni a la que pedir acceso.');

        $this->client->request('POST', \sprintf('/api/v1/chapters/%s/corrections', $id), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$lectora['token'],
        ], content: '{"answers": []}');
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND, 'Ni un capítulo que corregir.');
    }

    /**
     * `RN-4` y `P-17`: añadirla no mueve créditos y **no cuenta como
     * relato**. Son conceptos distintos, y el contador del perfil es donde se
     * nota si alguien los junta.
     */
    public function testAddingABookMovesNothingElse(): void
    {
        $autora = $this->activatedPerson('autora');
        $this->consumeEverything();

        $antes = $this->profileOf($autora['userId']);

        $this->add($autora['token'], ['title' => 'La que no cuenta']);

        $despues = $this->profileOf($autora['userId']);

        self::assertSame($antes['counters']['works'], $despues['counters']['works'], 'P-17: no es un relato.');
        self::assertSame(10, $this->balanceOf($autora['userId']), 'RN-4: sigue con los diez de bienvenida.');
    }

    /**
     * `RN-1`: una obra publicada la gestiona **solo su dueño**.
     *
     * Responde `403` y no `404`, al revés que casi todo lo demás: la obra se
     * enseña en un perfil abierto, así que fingir que no existe sería
     * mentirle a quien la acaba de ver, sin ocultarle nada.
     */
    public function testNobodyElseEditsOrDeletesYourBibliography(): void
    {
        $autora = $this->activatedPerson('autora');
        $otra = $this->activatedPerson('otra');
        $book = $this->add($autora['token'], ['title' => 'Mía']);
        $path = \sprintf('/api/v1/me/published-books/%s', $book['publishedBookId']);

        $this->client->request('PATCH', $path, server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$otra['token'],
        ], content: json_encode(['title' => 'Tuya'], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame('NOT_YOUR_PUBLISHED_BOOK', $this->payload()['code']);

        $this->client->request('DELETE', $path, server: ['HTTP_AUTHORIZATION' => 'Bearer '.$otra['token']]);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $this->client->request('GET', $this->bibliographyOf($autora['userId']));
        self::assertCount(1, $this->payload()['publishedBooks'], 'Y sigue donde estaba.');
    }

    /**
     * `RN-8` y [`decision:0003`](../../../../docs/decisions/0003-write-operations-require-activated-account.md):
     * añadir es escribir, y escribir exige la cuenta activada.
     */
    public function testAnUnactivatedAccountCannotAddAnything(): void
    {
        $token = $this->signedInWithoutActivating('pendiente');

        $this->client->request('POST', '/api/v1/me/published-books', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(['title' => 'Antes de activar'], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame('ACCOUNT_NOT_ACTIVATED', $this->payload()['code']);
    }

    /**
     * `PATCH`: lo que no se manda se queda como estaba, y mandar `null`
     * borra. La diferencia la hace la presencia de la clave, no su valor,
     * porque quitar el enlace de una obra descatalogada y cambiarle el año
     * son cosas distintas.
     */
    public function testEditingLeavesAloneWhatWasNotSent(): void
    {
        $autora = $this->activatedPerson('autora');
        $book = $this->add($autora['token'], [
            'title' => 'La editada',
            'publisher' => 'Amazon',
            'publicationYear' => 2019,
            'purchaseUrl' => 'https://www.amazon.es/dp/1111111111',
        ]);

        $solo = $this->patch($autora['token'], $book['publishedBookId'], ['publicationYear' => 2020]);

        self::assertSame(2020, $solo['publicationYear']);
        self::assertSame('Amazon', $solo['publisher'], 'Lo que no se mandó sigue ahí.');
        self::assertSame('https://www.amazon.es/dp/1111111111', $solo['purchaseUrl']);

        $sin = $this->patch($autora['token'], $book['publishedBookId'], ['purchaseUrl' => null]);

        self::assertNull($sin['purchaseUrl'], 'Y mandar null sí lo borra.');
        self::assertFalse($sin['purchaseUrlIsExternal']);
        self::assertSame('Amazon', $sin['publisher']);
    }

    /**
     * `RN-7` y `P-15`: **manda el autor, y hasta que decide algo manda el año
     * descendente**.
     *
     * Las dos mitades se sostienen porque colocar la obra nueva no reordena
     * las que ya estaban: reordenar por año en cada alta desharía en silencio
     * lo que el autor acababa de arrastrar.
     */
    public function testTheAuthorOrdersTheListAndTheYearDecidesUntilThen(): void
    {
        $autora = $this->activatedPerson('autora');

        $this->add($autora['token'], ['title' => 'Vieja', 'publicationYear' => 2001]);
        $this->add($autora['token'], ['title' => 'Nueva', 'publicationYear' => 2024]);
        $this->add($autora['token'], ['title' => 'Intermedia', 'publicationYear' => 2015]);
        $this->add($autora['token'], ['title' => 'Sin fecha']);

        self::assertSame(
            ['Nueva', 'Intermedia', 'Vieja', 'Sin fecha'],
            $this->titlesOf($autora['userId']),
            'Año descendente, y la que no sabe de cuándo es, al final.',
        );

        // Y ahora manda el autor.
        $vieja = $this->bookNamed($autora['userId'], 'Vieja');
        $this->patch($autora['token'], $vieja['publishedBookId'], ['position' => 0]);

        self::assertSame(['Vieja', 'Nueva', 'Intermedia', 'Sin fecha'], $this->titlesOf($autora['userId']));

        // Cambiarle el año a una obra ya colocada no la mueve de sitio: eso
        // sería el sistema deshaciendo lo que la persona acaba de hacer.
        $this->patch($autora['token'], $vieja['publishedBookId'], ['publicationYear' => 1999]);

        self::assertSame(['Vieja', 'Nueva', 'Intermedia', 'Sin fecha'], $this->titlesOf($autora['userId']));
    }

    /**
     * Quitar una obra recoloca las que quedan. Una lista con un número
     * saltado funciona hasta que alguien añade otra y dos acaban compartiendo
     * posición, y entonces el orden empieza a cambiar solo entre dos lecturas
     * iguales.
     */
    public function testRemovingOneClosesTheGap(): void
    {
        $autora = $this->activatedPerson('autora');
        $this->add($autora['token'], ['title' => 'Primera', 'publicationYear' => 2024]);
        $this->add($autora['token'], ['title' => 'Segunda', 'publicationYear' => 2020]);
        $this->add($autora['token'], ['title' => 'Tercera', 'publicationYear' => 2010]);

        $segunda = $this->bookNamed($autora['userId'], 'Segunda');

        $this->client->request('DELETE', \sprintf('/api/v1/me/published-books/%s', $segunda['publishedBookId']), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$autora['token'],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->client->request('GET', $this->bibliographyOf($autora['userId']));
        $quedan = $this->payload()['publishedBooks'];

        self::assertSame(['Primera', 'Tercera'], array_column($quedan, 'title'));
        self::assertSame([0, 1], array_column($quedan, 'position'), 'Sin huecos.');
    }

    /**
     * `P-16`: un perfil no es un catálogo. El tope existe para que la
     * bibliografía no se convierta en un tablón de anuncios.
     */
    public function testAProfileHoldsOnlySoManyBooks(): void
    {
        $autora = $this->activatedPerson('autora');

        for ($i = 0; $i < 50; ++$i) {
            $this->add($autora['token'], ['title' => 'Obra '.$i]);
        }

        $this->client->request('POST', '/api/v1/me/published-books', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$autora['token'],
        ], content: json_encode(['title' => 'La que sobra'], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame('PUBLISHED_BOOK_LIMIT_REACHED', $this->payload()['code']);
    }

    /**
     * Un año imposible se rechaza. El tope está para atrapar el error de
     * teclado —un `19` o un `202`—, no para discutirle a nadie su
     * bibliografía: por eso llega hasta el año que viene, que es cuando se
     * anuncia un libro que todavía no ha salido.
     */
    public function testAnImplausibleYearIsRefusedAndNextYearIsNot(): void
    {
        $autora = $this->activatedPerson('autora');

        foreach ([202, 1200, (int) date('Y') + 5] as $year) {
            $this->client->request('POST', '/api/v1/me/published-books', server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$autora['token'],
            ], content: json_encode(['title' => 'Del año '.$year, 'publicationYear' => $year], \JSON_THROW_ON_ERROR));

            self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY, (string) $year);
        }

        $book = $this->add($autora['token'], [
            'title' => 'La que sale el año que viene',
            'publicationYear' => (int) date('Y') + 1,
        ]);

        self::assertSame((int) date('Y') + 1, $book['publicationYear']);
    }

    /**
     * La portada: se sube, **se reescribe siempre** y pierde sus metadatos.
     *
     * Que una portada de libro parezca un dato inocuo no cambia el fichero
     * que llega: lo que lleva dentro una imagen es lo que lleva, y los
     * metadatos de una foto llevan dónde se tomó.
     */
    public function testACoverIsRewrittenAndLosesItsMetadata(): void
    {
        $autora = $this->activatedPerson('autora');
        $book = $this->add($autora['token'], ['title' => 'La ilustrada']);

        $conMetadatos = $this->jpegWithMetadata();
        self::assertStringContainsString('Calle Falsa 123', $conMetadatos, 'La imagen de partida sí los lleva.');

        $subida = $this->uploadCover($autora['token'], $book['publishedBookId'], $conMetadatos);

        self::assertNotNull($subida['coverUrl']);

        $this->client->request('GET', (string) $subida['coverUrl']);
        self::assertResponseIsSuccessful('Y se sirve en abierto: aparece en un perfil sin sesión.');

        $servida = (string) $this->client->getResponse()->getContent();
        self::assertStringNotContainsString('Calle Falsa 123', $servida, 'Reescrita, sin metadatos.');
    }

    /**
     * `RN-10`: la portada es opcional, y quitarla deja la obra donde estaba.
     */
    public function testACoverCanBeRemovedWithoutLosingTheBook(): void
    {
        $autora = $this->activatedPerson('autora');
        $book = $this->add($autora['token'], ['title' => 'La que se queda sin portada']);
        $subida = $this->uploadCover($autora['token'], $book['publishedBookId'], $this->jpegWithMetadata());

        $url = (string) $subida['coverUrl'];

        $this->client->request('DELETE', \sprintf('/api/v1/me/published-books/%s/cover', $book['publishedBookId']), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$autora['token'],
        ]);

        self::assertResponseIsSuccessful();
        self::assertNull($this->payload()['coverUrl']);
        self::assertSame('La que se queda sin portada', $this->payload()['title'], 'La obra sigue.');

        $this->client->request('GET', $url);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND, 'Y el fichero ya no está.');
    }

    /**
     * Un perfil restringido no tiene bibliografía que enseñar
     * (`FEAT-USR-038`).
     *
     * Si esta lista contestara por su cuenta sería un camino lateral para
     * confirmar que una cuenta existe justo cuando su titular ha pedido que
     * no se sepa. Su dueña sí se ve, que es lo que permite deshacer el
     * ajuste.
     */
    public function testARestrictedProfileHasNoBibliographyToShow(): void
    {
        $autora = $this->activatedPerson('autora');
        $this->add($autora['token'], ['title' => 'La escondida']);

        $this->client->request('PUT', '/api/v1/me/privacy-settings', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$autora['token'],
        ], content: json_encode(['profileVisibility' => 'NOBODY'], \JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();

        $this->client->request('GET', $this->bibliographyOf($autora['userId']));
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $this->client->request('GET', $this->bibliographyOf($autora['userId']), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$autora['token'],
        ]);
        self::assertResponseIsSuccessful('Su dueña se sigue viendo, o no podría deshacerlo.');
        self::assertCount(1, $this->payload()['publishedBooks']);
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    private function add(string $token, array $body): array
    {
        $this->client->request('POST', '/api/v1/me/published-books', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode($body, \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        return $this->payload();
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    private function patch(string $token, string $bookId, array $body): array
    {
        $this->client->request('PATCH', \sprintf('/api/v1/me/published-books/%s', $bookId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode($body, \JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();

        return $this->payload();
    }

    /**
     * @return array<string, mixed>
     */
    private function uploadCover(string $token, string $bookId, string $bytes): array
    {
        $path = tempnam(sys_get_temp_dir(), 'cover').'.jpg';
        file_put_contents($path, $bytes);

        $this->client->request(
            'PUT',
            \sprintf('/api/v1/me/published-books/%s/cover', $bookId),
            files: ['cover' => new \Symfony\Component\HttpFoundation\File\UploadedFile($path, 'portada.jpg', 'image/jpeg', test: true)],
            server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token],
        );

        self::assertResponseIsSuccessful();

        return $this->payload();
    }

    /**
     * Un JPEG de verdad con un comentario dentro, que es lo que hace de
     * metadato para esta prueba: si el servidor no reescribiera la imagen,
     * ese texto seguiría ahí.
     */
    private function jpegWithMetadata(): string
    {
        $image = imagecreatetruecolor(600, 900);
        self::assertNotFalse($image);

        ob_start();
        imagejpeg($image, null, 90);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        // Un segmento de comentario (`0xFFFE`) justo después de la cabecera.
        $comment = 'Calle Falsa 123';
        $segment = "\xFF\xFE".pack('n', \strlen($comment) + 2).$comment;

        return substr($bytes, 0, 2).$segment.substr($bytes, 2);
    }

    /**
     * @return list<string>
     */
    private function titlesOf(string $userId): array
    {
        $this->client->request('GET', $this->bibliographyOf($userId));
        self::assertResponseIsSuccessful();

        /** @var list<string> $titles */
        $titles = array_column($this->payload()['publishedBooks'], 'title');

        return $titles;
    }

    /**
     * @return array<string, mixed>
     */
    private function bookNamed(string $userId, string $title): array
    {
        $this->client->request('GET', $this->bibliographyOf($userId));
        self::assertResponseIsSuccessful();

        foreach ($this->payload()['publishedBooks'] as $book) {
            if ($title === $book['title']) {
                return $book;
            }
        }

        self::fail(\sprintf('No hay ninguna obra titulada «%s».', $title));
    }

    /**
     * @return array<string, mixed>
     */
    private function profileOf(string $userId): array
    {
        $this->client->request('GET', \sprintf('/api/v1/users/%s', $userId));
        self::assertResponseIsSuccessful();

        return $this->payload();
    }

    private function bibliographyOf(string $userId): string
    {
        return \sprintf('/api/v1/users/%s/published-books', $userId);
    }
}
