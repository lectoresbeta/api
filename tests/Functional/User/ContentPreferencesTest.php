<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\User;

use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use Symfony\Component\HttpFoundation\Response;

/**
 * Las preferencias de contenido sensible (`FEAT-USR-043`).
 *
 * La otra mitad de [`FEAT-WRK-017`](../../../docs/features/work/FEAT-WRK-017-content-rating.md):
 * el autor declara qué hay y el lector decide qué quiere.
 *
 * Lo que se defiende aquí es `RN-2`, que no es una formalidad: **el filtrado
 * ocurre en el servidor**. Un filtro de cliente significa que el contenido
 * viaja hasta el navegador de quien pidió no verlo, y para material sensible
 * eso no sirve de nada.
 */
final class ContentPreferencesTest extends EconomyScenario
{
    /**
     * `RN-3`: por defecto no se filtra nada, y eso no es «no ha contestado».
     * No hay nada que contestar hasta que alguien quiera excluir algo.
     */
    public function testByDefaultNothingIsExcluded(): void
    {
        $lectora = $this->activatedPerson('lectora');

        $this->mine($lectora['token']);

        self::assertResponseIsSuccessful();
        self::assertSame([], $this->payload()['excludedWarnings']);
    }

    public function testWhatIsChosenComesBack(): void
    {
        $lectora = $this->activatedPerson('lectora');

        $this->choose($lectora['token'], ['SELF_HARM', 'STRONG_LANGUAGE']);

        self::assertResponseIsSuccessful();
        self::assertSame(['SELF_HARM', 'STRONG_LANGUAGE'], $this->payload()['excludedWarnings']);

        $this->mine($lectora['token']);
        self::assertSame(['SELF_HARM', 'STRONG_LANGUAGE'], $this->payload()['excludedWarnings']);
    }

    /**
     * El `PUT` sustituye la lista entera, como la clasificación de una obra:
     * esto es lo que no quiero ver, y lo que no esté aquí ya no cuenta.
     */
    public function testChoosingAgainReplacesTheWholeList(): void
    {
        $lectora = $this->activatedPerson('lectora');

        $this->choose($lectora['token'], ['SELF_HARM', 'STRONG_LANGUAGE']);
        $this->choose($lectora['token'], ['SEXUAL_CONTENT']);

        self::assertSame(['SEXUAL_CONTENT'], $this->payload()['excludedWarnings']);
    }

    public function testEverythingCanBeUnexcludedAgain(): void
    {
        $lectora = $this->activatedPerson('lectora');

        $this->choose($lectora['token'], ['SELF_HARM']);
        $this->choose($lectora['token'], []);

        self::assertSame([], $this->payload()['excludedWarnings'], 'La lista vacía es una decisión, no un olvido.');
    }

    /**
     * Una etiqueta inventada **se nombra y no se guarda nada**.
     *
     * Aceptarla sería la peor manera de fallar en algo que la gente configura
     * precisamente para no llevarse un disgusto: no filtraría nada y
     * parecería que sí.
     */
    public function testAnInventedLabelIsRefusedByNameAndNothingIsStored(): void
    {
        $lectora = $this->activatedPerson('lectora');

        $this->choose($lectora['token'], ['SELF_HARM', 'CONTENIDO_PERTURBADOR']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('UNKNOWN_CONTENT_WARNING', $this->payload()['code']);
        self::assertStringContainsString('CONTENIDO_PERTURBADOR', (string) $this->payload()['detail']);

        $this->mine($lectora['token']);
        self::assertSame([], $this->payload()['excludedWarnings'], 'No se guarda a medias.');
    }

    /**
     * **`RN-2`, y es la regla que da sentido a todo lo demás.** Lo excluido
     * no llega al cliente ni cuenta en el total: no se esconde en la
     * interfaz.
     *
     * Y se aplica **sin pedirlo en la petición**: el catálogo no lleva
     * ningún filtro, es lo que esta persona decidió hace tiempo en su
     * configuración.
     */
    public function testTheStoredChoiceFiltersTheCatalogueWithoutAskingForIt(): void
    {
        $author = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');

        $dura = $this->published($author, 'Lo que duele', ['SELF_HARM']);
        $suave = $this->published($author, 'Un día de campo', []);

        $this->catalogue($lectora['token']);
        self::assertSame(2, $this->payload()['total'], 'Antes de elegir nada, están las dos.');
        self::assertContains($dura, $this->listed());

        $this->choose($lectora['token'], ['SELF_HARM']);

        $this->catalogue($lectora['token']);
        self::assertSame([$suave], $this->listed());
        self::assertSame(1, $this->payload()['total'], 'El total refleja el filtro, no el catálogo entero.');
    }

    /**
     * `RN-1`: son **del usuario**. Lo que excluya una persona no le quita
     * nada a otra, y en particular no le quita nada a quien no ha elegido.
     */
    public function testOneReadersChoiceDoesNotFilterAnybodyElsesCatalogue(): void
    {
        $author = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');
        $otra = $this->activatedPerson('otra');

        $dura = $this->published($author, 'Lo que duele', ['SELF_HARM']);

        $this->choose($lectora['token'], ['SELF_HARM']);

        $this->catalogue($otra['token']);
        self::assertContains($dura, $this->listed());
    }

    /**
     * `RN-7`: no se comparten con los autores. Un autor no debe poder deducir
     * cuánta audiencia pierde por etiquetar bien, porque con ese número
     * tendría un incentivo directo para etiquetar mal.
     *
     * No hay puerta que preguntar al revés: la única operación que existe es
     * «las mías», y responde las de quien la pide.
     */
    public function testNobodyReadsSomebodyElsesPreferences(): void
    {
        $lectora = $this->activatedPerson('lectora');
        $autora = $this->activatedPerson('autora');

        $this->choose($lectora['token'], ['SELF_HARM']);

        $this->mine($autora['token']);
        self::assertSame([], $this->payload()['excludedWarnings']);
    }

    public function testWithoutASessionThereIsNothing(): void
    {
        $this->client->request('GET', '/api/v1/me/content-preferences');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    private function mine(string $token): void
    {
        $this->client->request('GET', '/api/v1/me/content-preferences', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
    }

    /**
     * @param list<string> $warnings
     */
    private function choose(string $token, array $warnings): void
    {
        $this->client->request('PUT', '/api/v1/me/content-preferences', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(['excludedWarnings' => $warnings], \JSON_THROW_ON_ERROR));

        $this->capture();
    }

    /**
     * @param array{token: string, userId: string} $author
     * @param list<string>                         $warnings
     */
    private function published(array $author, string $title, array $warnings): string
    {
        $workId = $this->createWork($author['token'], $title);
        $this->addChapter($workId, $author['token'], words: 600);

        $this->client->request('PUT', \sprintf('/api/v1/works/%s/content-rating', $workId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$author['token'],
        ], content: json_encode(['adultsOnly' => false, 'contentWarnings' => $warnings], \JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
        $this->capture();

        $this->put(\sprintf('/api/v1/works/%s/access-mode', $workId), $author['token'], ['accessMode' => 'PUBLIC']);
        $this->put(\sprintf('/api/v1/works/%s/status', $workId), $author['token'], ['status' => 'PUBLISHED']);

        return $workId;
    }

    private function catalogue(string $token): void
    {
        $this->client->request('GET', '/api/v1/works', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseIsSuccessful();
    }

    /**
     * @return list<string>
     */
    private function listed(): array
    {
        $found = array_map(
            static fn (array $work): string => (string) $work['workId'],
            $this->payload()['works'],
        );

        sort($found);

        return $found;
    }

    /**
     * @param array<string, string> $body
     */
    private function put(string $path, string $token, array $body): void
    {
        $this->client->request('PUT', $path, server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode($body, \JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        $this->capture();
    }
}
