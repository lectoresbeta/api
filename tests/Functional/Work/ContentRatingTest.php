<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Work;

use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use Symfony\Component\HttpFoundation\Response;

/**
 * La clasificación de contenido sensible (`FEAT-WRK-017`).
 *
 * Lo que hace distinta a esta funcionalidad es que la declaración funciona en
 * los dos sentidos: **bien etiquetar protege al autor y mal etiquetar le hace
 * responsable**. El sistema deja de castigar el contenido difícil y pasa a
 * castigar el engaño, que es lo correcto en una plataforma literaria — la
 * literatura incómoda tiene derecho a existir; lo que no lo tiene es aparecer
 * sin avisar.
 *
 * La mitad de esa simetría vive en `Moderation`, que no existe todavía. Lo
 * que sí se puede defender aquí es lo que la hace posible: que la declaración
 * sea explícita, completa y visible antes de abrir la obra.
 */
final class ContentRatingTest extends EconomyScenario
{
    public function testAnAuthorDeclaresWhatTheirWorkContains(): void
    {
        $author = $this->activatedPerson('autora');
        $workId = $this->createWork($author['token'], 'La ciudad de los pájaros');

        $this->classify($workId, $author['token'], true, ['SELF_HARM', 'STRONG_LANGUAGE']);

        self::assertResponseIsSuccessful();
        self::assertTrue($this->payload()['adultsOnly']);
        self::assertSame(['SELF_HARM', 'STRONG_LANGUAGE'], $this->payload()['contentWarnings']);
    }

    /**
     * `RN-1`: se declara al publicar y se puede cambiar después. El `PUT`
     * sustituye la clasificación entera, como el modo de acceso o las
     * temáticas.
     */
    public function testReclassifyingReplacesTheWholeDeclaration(): void
    {
        $author = $this->activatedPerson('autora');
        $workId = $this->createWork($author['token'], 'La ciudad de los pájaros');

        $this->classify($workId, $author['token'], true, ['GRAPHIC_VIOLENCE']);
        $this->classify($workId, $author['token'], false, []);

        self::assertResponseIsSuccessful();
        self::assertFalse($this->payload()['adultsOnly']);
        self::assertSame([], $this->payload()['contentWarnings'], 'Ninguna etiqueta es una declaración legítima.');
    }

    /**
     * **La regla que justifica la asimetría del cuerpo.** La lista vacía es
     * una declaración —«no contiene nada de esto»—; omitir el indicador de
     * público no significa «apta para menores», significa que nadie lo ha
     * dicho.
     */
    public function testNotSayingWhoTheWorkIsForIsRefused(): void
    {
        $author = $this->activatedPerson('autora');
        $workId = $this->createWork($author['token'], 'La ciudad de los pájaros');

        $this->client->request('PUT', \sprintf('/api/v1/works/%s/content-rating', $workId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$author['token'],
        ], content: json_encode(['contentWarnings' => ['SELF_HARM']], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('AUDIENCE_NOT_DECLARED', $this->payload()['code']);
    }

    /**
     * Una etiqueta inventada **se nombra**, y no se guarda nada a medias: el
     * autor responde de lo que ha declarado, así que tiene que saber qué se
     * ha entendido.
     */
    public function testAnInventedLabelIsRefusedByNameAndNothingIsStored(): void
    {
        $author = $this->activatedPerson('autora');
        $workId = $this->createWork($author['token'], 'La ciudad de los pájaros');

        $this->classify($workId, $author['token'], true, ['SELF_HARM', 'CONTENIDO_PERTURBADOR']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('UNKNOWN_CONTENT_WARNING', $this->payload()['code']);
        self::assertStringContainsString('CONTENIDO_PERTURBADOR', (string) $this->payload()['detail']);

        self::assertSame([], $this->warningsOf($workId, $author['token']), 'No se guarda a medias.');
    }

    public function testNobodyClassifiesSomebodyElsesWork(): void
    {
        $author = $this->activatedPerson('autora');
        $otra = $this->activatedPerson('otra');

        $workId = $this->createWork($author['token'], 'La ciudad de los pájaros');

        $this->classify($workId, $otra['token'], true, ['SELF_HARM']);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * `RN-8`, que es la regla que hace útil todo lo demás: una advertencia
     * que solo aparece cuando ya estás leyendo no advierte de nada. Va en la
     * tarjeta **y** en la cabecera.
     */
    public function testTheLabelsAreVisibleBeforeOpeningTheWork(): void
    {
        $author = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');

        $workId = $this->published($author, 'La ciudad de los pájaros', ['SELF_HARM']);

        $this->catalogue($lectora['token']);
        self::assertSame(['SELF_HARM'], $this->payload()['works'][0]['contentWarnings']);

        self::assertSame(['SELF_HARM'], $this->warningsOf($workId, $lectora['token']));
    }

    /**
     * **`RN-10`: el filtro quita, no busca.** Quien pide no ver autolesión no
     * la ve, y lo excluido **no llega al cliente** ni cuenta en el total: no
     * se esconde en la interfaz (`FEAT-WRK-012` `RN-9`).
     */
    public function testTheReaderExcludesWhatTheyDoNotWantToSee(): void
    {
        $author = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');

        $dura = $this->published($author, 'Lo que duele', ['SELF_HARM']);
        $suave = $this->published($author, 'Un día de campo', []);
        $mixta = $this->published($author, 'Las dos cosas', ['SELF_HARM', 'STRONG_LANGUAGE']);

        $this->catalogue($lectora['token'], ['excludeContentWarnings' => ['SELF_HARM']]);

        self::assertResponseIsSuccessful();
        self::assertSame(1, $this->payload()['total'], 'El total refleja el filtro, no el catálogo entero.');

        $encontradas = array_map(
            static fn (array $work): string => (string) $work['workId'],
            $this->payload()['works'],
        );

        self::assertSame([$suave], $encontradas);
        self::assertNotContains($dura, $encontradas);
        self::assertNotContains($mixta, $encontradas, 'Basta con que lleve **una** de las rechazadas.');
    }

    /**
     * Al revés que una temática desconocida, que es una búsqueda vacía: la
     * lista es cerrada, así que un valor que no está es una errata. Y el
     * error va en la dirección peligrosa — ignorarlo enseñaría justo lo que
     * se ha pedido no ver.
     */
    public function testAnInventedLabelInTheFilterIsRefusedInsteadOfIgnored(): void
    {
        $lectora = $this->activatedPerson('lectora');

        $this->catalogue($lectora['token'], ['excludeContentWarnings' => ['LO_QUE_SEA']]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('UNKNOWN_FILTER_VALUE', $this->payload()['code']);
    }

    /**
     * `RN-5`: cambiar la clasificación no toca las correcciones en curso.
     * Quien empezó a corregir sigue pudiendo entregar.
     */
    public function testChangingTheRatingDoesNotDisturbACorrectionInFlight(): void
    {
        $author = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');

        $workId = $this->published($author, 'La ciudad de los pájaros', []);
        $this->saveQuestionnaire($workId, $author['token'], [
            ['statement' => '¿Cómo funciona el ritmo?', 'minWords' => 50],
        ]);
        $this->put(\sprintf('/api/v1/works/%s/status', $workId), $author['token'], ['status' => 'IN_CORRECTION']);
        $this->consumeEverything();

        $chapterId = $this->firstChapterOf($workId, $lectora['token']);

        $this->client->request('POST', \sprintf('/api/v1/chapters/%s/corrections/start', $chapterId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$lectora['token'],
        ]);
        self::assertResponseIsSuccessful();
        $this->capture();

        // El autor reclasifica en mitad de la corrección.
        $this->classify($workId, $author['token'], false, ['STRONG_LANGUAGE']);
        self::assertResponseIsSuccessful();

        $this->client->request('GET', \sprintf('/api/v1/chapters/%s/questionnaire', $chapterId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$lectora['token'],
        ]);
        self::assertResponseIsSuccessful();

        $questionId = (string) $this->payload()['questions'][0]['questionId'];

        $this->client->request('POST', \sprintf('/api/v1/chapters/%s/corrections', $chapterId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$lectora['token'],
        ], content: json_encode([
            'answers' => [['questionId' => $questionId, 'text' => implode(' ', array_fill(0, 60, 'palabra'))]],
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
    }

    /**
     * El catálogo de etiquetas es público, como el de temáticas: hace falta
     * en pantallas que se ven sin sesión.
     */
    public function testTheCatalogueOfLabelsIsPublic(): void
    {
        $this->client->request('GET', '/api/v1/content-warnings');

        self::assertResponseIsSuccessful();
        self::assertSame(
            ['SEXUAL_CONTENT', 'GRAPHIC_VIOLENCE', 'SELF_HARM', 'SUBSTANCE_USE', 'STRONG_LANGUAGE'],
            $this->payload()['contentWarnings'],
        );
    }

    /**
     * @param array{token: string, userId: string} $author
     * @param list<string>                         $warnings
     */
    private function published(array $author, string $title, array $warnings): string
    {
        $workId = $this->createWork($author['token'], $title);
        $this->addChapter($workId, $author['token'], words: 600);
        $this->classify($workId, $author['token'], false, $warnings);
        self::assertResponseIsSuccessful();

        $this->put(\sprintf('/api/v1/works/%s/access-mode', $workId), $author['token'], ['accessMode' => 'PUBLIC']);
        $this->put(\sprintf('/api/v1/works/%s/status', $workId), $author['token'], ['status' => 'PUBLISHED']);

        return $workId;
    }

    /**
     * @param list<string> $warnings
     */
    private function classify(string $workId, string $token, bool $adultsOnly, array $warnings): void
    {
        $this->client->request('PUT', \sprintf('/api/v1/works/%s/content-rating', $workId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode([
            'adultsOnly' => $adultsOnly,
            'contentWarnings' => $warnings,
        ], \JSON_THROW_ON_ERROR));

        $this->capture();
    }

    /**
     * @return list<string>
     */
    private function warningsOf(string $workId, string $token): array
    {
        $this->client->request('GET', \sprintf('/api/v1/works/%s', $workId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseIsSuccessful();

        /** @var list<string> $warnings */
        $warnings = $this->payload()['contentWarnings'];

        return $warnings;
    }

    private function firstChapterOf(string $workId, string $token): string
    {
        $this->client->request('GET', \sprintf('/api/v1/works/%s', $workId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        self::assertResponseIsSuccessful();

        return (string) $this->payload()['chapters'][0]['chapterId'];
    }

    /**
     * @param array<string, list<string>|string> $filters
     */
    private function catalogue(string $token, array $filters = []): void
    {
        $this->client->request('GET', '/api/v1/works?'.http_build_query($filters), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
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
