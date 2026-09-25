<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Community;

use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use Symfony\Component\HttpFoundation\Response;

/**
 * El carrusel de obras recomendadas de la Home (`FEAT-COM-017`).
 *
 * **Es el catálogo, acotado a lo que le gusta a quien mira.** No hay una
 * segunda consulta ni una segunda fórmula: la misma que sirve la sección
 * «Leer», con los géneros de esa persona y un tope. La ficha original pedía
 * una proyección de obras dentro de `Community` (`RN-7`); hacerla habría
 * significado reescribir aquí la regla de visibilidad más peligrosa del
 * backend y copiar la fórmula de reparto de `decision:0008`.
 *
 * Las dos pruebas que importan son las dos que no enseñan nada: una obra en
 * borrador no aparece jamás, y ninguna tarjeta lleva texto de la obra.
 */
final class HomeRecommendationsTest extends EconomyScenario
{
    /**
     * `RN-1`: lo que sale encaja con los géneros elegidos en el onboarding.
     */
    public function testItRecommendsWorksOfTheGenresYouChose(): void
    {
        $lectora = $this->activatedPerson('lectora');
        $this->likes($lectora['token'], ['FANTASY', 'ADVENTURE', 'CRIME']);

        $autora = $this->activatedPerson('autora');
        $workId = $this->aCorrectableWork($autora, 'La ciudad de los pájaros', ['FANTASY']);

        $carrusel = $this->carousel($lectora['token']);

        self::assertTrue($carrusel['shouldDisplay']);
        self::assertContains($workId, $this->ids($carrusel));
    }

    /**
     * `RN-2`: **una obra en borrador no se recomienda jamás.**.
     *
     * Es la regla que protege el activo del producto. Un fallo aquí expone
     * obra inédita a quien no tiene acceso concedido, y por eso la
     * visibilidad se decide en `Work` y no se vuelve a decidir aquí.
     */
    public function testADraftIsNeverRecommended(): void
    {
        $lectora = $this->activatedPerson('lectora');
        $autora = $this->activatedPerson('autora');

        $workId = $this->createWork($autora['token'], 'Todavía en borrador');
        $this->addChapter($workId, $autora['token'], words: 900);
        $this->consumeEverything();

        self::assertNotContains($workId, $this->ids($this->carousel($lectora['token'])));
    }

    /**
     * `RN-3`: **nadie corrige lo suyo**, así que la obra propia no ocupa
     * sitio en la única pantalla donde se busca trabajo ajeno.
     */
    public function testYourOwnWorkIsNotRecommendedToYou(): void
    {
        $autora = $this->activatedPerson('autora');
        $workId = $this->aCorrectableWork($autora, 'Lo mío', ['FANTASY']);

        $ajena = $this->activatedPerson('ajena');

        self::assertNotContains($workId, $this->ids($this->carousel($autora['token'])));
        self::assertContains($workId, $this->ids($this->carousel($ajena['token'])), 'Pero a otra sí.');
    }

    /**
     * `RN-6`: **la tarjeta no lleva contenido de la obra**, solo su sinopsis.
     *
     * La otra mitad de lo que protege el activo: que una obra aparezca aquí
     * no significa que quien la ve pueda abrirla.
     */
    public function testTheCardCarriesNoTextOfTheWork(): void
    {
        $lectora = $this->activatedPerson('lectora');
        $autora = $this->activatedPerson('autora');
        $this->aCorrectableWork($autora, 'La obra recomendada', ['FANTASY']);

        $carrusel = $this->carousel($lectora['token']);
        self::assertNotEmpty($carrusel['works']);

        foreach ($carrusel['works'] as $work) {
            self::assertArrayNotHasKey('content', $work);
            self::assertArrayNotHasKey('chapters', $work);
            self::assertArrayNotHasKey('body', $work);
        }
    }

    /**
     * `RN-5`: el tiempo de lectura **se deriva** del número de palabras con
     * el ritmo fijo de `FEAT-WRK-013`. No lo introduce el autor.
     */
    public function testReadingTimeIsDerivedFromTheWordCount(): void
    {
        $lectora = $this->activatedPerson('lectora');
        $autora = $this->activatedPerson('autora');
        $this->aCorrectableWork($autora, 'La obra medida', ['FANTASY']);

        $work = $this->carousel($lectora['token'])['works'][0];

        self::assertGreaterThan(0, $work['readingMinutes']);
        self::assertArrayNotHasKey('wordCount', $work, 'Va calculado, no en crudo.');
    }

    /**
     * Segundo tramo de la cadena de relleno: sin obras de sus géneros, se
     * enseñan las que haya.
     *
     * Es preferible enseñar algo que no encaje del todo a dejar la Home
     * empezando por el muro, que es donde no hay nada que hacer si todavía no
     * sigues a nadie.
     */
    public function testWithoutWorksInYourGenresItFallsBackToAnyGenre(): void
    {
        $lectora = $this->activatedPerson('lectora');
        $this->likes($lectora['token'], ['HORROR', 'ROMANCE', 'HISTORICAL']);

        $autora = $this->activatedPerson('autora');
        $workId = $this->aCorrectableWork($autora, 'Una novela', ['FANTASY']);

        $carrusel = $this->carousel($lectora['token']);

        self::assertTrue($carrusel['shouldDisplay']);
        self::assertContains($workId, $this->ids($carrusel));
    }

    /**
     * Y tercer tramo: **sin nada que corregir, la sección no se enseña**.
     *
     * La decisión la toma el servidor y no el cliente (`RN-10` de
     * `FEAT-COM-016`, mismo patrón): con el umbral en dos sitios, uno se
     * queda atrás.
     */
    public function testWithNothingToCorrectTheSectionIsNotShown(): void
    {
        $lectora = $this->activatedPerson('lectora');

        $carrusel = $this->carousel($lectora['token']);

        self::assertFalse($carrusel['shouldDisplay']);
        self::assertSame('NOTHING_TO_CORRECT_YET', $carrusel['reason']);
        self::assertSame([], $carrusel['works']);
    }

    /**
     * Una obra publicada pero **no abierta a corrección** no se recomienda:
     * el carrusel existe para que alguien empiece a corregir, y una obra que
     * no lo admite ocupa el sitio más valioso de la pantalla sin llevar a
     * ninguna parte.
     */
    public function testAWorkNotOpenForCorrectionIsNotRecommended(): void
    {
        $lectora = $this->activatedPerson('lectora');
        $autora = $this->activatedPerson('autora');

        $workId = $this->createWork($autora['token'], 'Publicada y cerrada');
        $this->addChapter($workId, $autora['token'], words: 900);
        $this->putAs(\sprintf('/api/v1/works/%s/status', $workId), $autora['token'], ['status' => 'PUBLISHED']);
        $this->consumeEverything();

        self::assertNotContains($workId, $this->ids($this->carousel($lectora['token'])));
    }

    /**
     * La tarjeta lleva **la insignia de créditos** (`FEAT-CRD-013`): lo que
     * quien entre ahora puede ganar con seguridad.
     */
    public function testTheCardCarriesTheCreditBadge(): void
    {
        $lectora = $this->activatedPerson('lectora');
        $autora = $this->activatedPerson('autora');
        $this->aCorrectableWork($autora, 'La obra con insignia', ['FANTASY']);

        $work = $this->carousel($lectora['token'])['works'][0];

        self::assertArrayHasKey('credits', $work);
        self::assertGreaterThan(0, $work['credits']);
    }

    /**
     * **Funciona con la cuenta sin activar**: es solo lectura, y la
     * restricción de `FEAT-USR-025` es sobre escribir. Quien acaba de
     * registrarse tiene que poder ver qué hay antes de confirmar su correo.
     */
    public function testItWorksWithoutActivatingTheAccount(): void
    {
        $sinActivar = $this->signedInWithoutActivating('reciente');

        $this->client->request('GET', '/api/v1/home/recommended-works', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$sinActivar,
        ]);

        self::assertResponseIsSuccessful();
    }

    /**
     * Sin sesión no hay carrusel: sin saber quién mira no se sabe qué le
     * gusta, ni si tiene edad, ni qué ha pedido no ver.
     */
    public function testItRequiresASession(): void
    {
        $this->client->request('GET', '/api/v1/home/recommended-works');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @param array{token: string, userId: string} $author
     * @param list<string>                         $genres
     */
    private function aCorrectableWork(array $author, string $title, array $genres): string
    {
        $workId = $this->createWork($author['token'], $title);
        $this->addChapter($workId, $author['token'], words: 900);
        $this->saveQuestionnaire($workId, $author['token'], [
            ['statement' => '¿Cómo funciona el ritmo?', 'minWords' => 10],
        ]);
        $this->putAs(\sprintf('/api/v1/works/%s/genres', $workId), $author['token'], ['genres' => $genres]);
        $this->putAs(\sprintf('/api/v1/works/%s/status', $workId), $author['token'], ['status' => 'PUBLISHED']);
        $this->putAs(\sprintf('/api/v1/works/%s/status', $workId), $author['token'], ['status' => 'IN_CORRECTION']);
        $this->consumeEverything();

        return $workId;
    }

    /**
     * @param list<string> $genres
     */
    private function likes(string $token, array $genres): void
    {
        $this->putAs('/api/v1/me/literary-preferences', $token, ['genres' => $genres]);
        $this->consumeEverything();
    }

    /**
     * @return array<string, mixed>
     */
    private function carousel(string $token): array
    {
        $this->client->request('GET', '/api/v1/home/recommended-works', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        self::assertResponseIsSuccessful();

        return $this->payload();
    }

    /**
     * @param array<string, mixed> $carousel
     *
     * @return list<string>
     */
    private function ids(array $carousel): array
    {
        /** @var list<array{workId: string}> $works */
        $works = $carousel['works'];

        return array_values(array_map(static fn (array $work): string => $work['workId'], $works));
    }
}
