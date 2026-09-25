<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Work;

use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use Symfony\Component\HttpFoundation\Response;

/**
 * Editar una obra y sus capítulos (`FEAT-WRK-005`).
 *
 * Lo que se prueba aquí no es que se pueda guardar texto —eso es lo fácil—
 * sino **qué pasa con lo que alguien ya leyó**. Una corrección que habla de
 * un párrafo que ha dejado de existir no es que envejezca: deja de tener
 * sentido, y el autor pagó por ella.
 */
final class EditWorkAndChapterTest extends EconomyScenario
{
    public function testTheAuthorEditsTitleSynopsisAndText(): void
    {
        $autora = $this->activatedPerson('autora');
        $workId = $this->createWork($autora['token'], 'Título viejo');
        $chapterId = $this->addChapter($workId, $autora['token'], words: 400);

        $this->editWork($workId, $autora['token'], ['title' => 'Título nuevo', 'synopsis' => 'Una sinopsis.']);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->editChapter($chapterId, $autora['token'], [
            'title' => 'Capítulo uno',
            'contentHtml' => '<p>'.implode(' ', array_fill(0, 500, 'nueva')).'</p>',
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->work($workId, $autora['token']);
        self::assertSame('Título nuevo', $this->payload()['title']);
        self::assertSame('Una sinopsis.', $this->payload()['synopsis']);
        self::assertSame(500, $this->payload()['wordCount'], 'Y el recuento lo recalcula el servidor.');

        $this->chapter($chapterId, $autora['token']);
        self::assertSame('Capítulo uno', $this->payload()['title']);
        self::assertStringContainsString('nueva', $this->payload()['content']);
    }

    /**
     * `RN-2`: sin nadie leyendo, editar no archiva nada. Un autor que teclea
     * y guarda veinte veces antes de abrir su obra no deja veinte copias.
     */
    public function testEditingWhatNobodyHasReadKeepsTheSameVersion(): void
    {
        [$autora, $chapterId, $correctionId] = $this->aChapterBeingCorrected(startCorrection: false);

        $this->editChapter($chapterId, $autora['token'], ['contentHtml' => '<p>Otra cosa distinta del todo.</p>']);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->editChapter($chapterId, $autora['token'], ['contentHtml' => '<p>Y otra más.</p>']);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        self::assertSame(1, $this->versionOf($chapterId), 'Nadie lo ha leído: sigue siendo la primera.');
        self::assertSame('', $correctionId);
    }

    /**
     * El corazón de la funcionalidad: **quien está corrigiendo sigue viendo
     * el texto que empezó a leer**, y la corrección que entrega habla de él.
     */
    public function testEditingWhatSomebodyIsCorrectingArchivesTheTextTheyRead(): void
    {
        [$autora, $chapterId, $correctionId, $lectora] = $this->aDeliveredCorrectionOnAWork();

        self::assertSame(1, $this->versionOf($chapterId));

        $this->editChapter($chapterId, $autora['token'], [
            'contentHtml' => '<p>'.implode(' ', array_fill(0, 300, 'reescrito')).'</p>',
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertSame(2, $this->versionOf($chapterId), 'Alguien lo había leído: se archiva y sube.');

        // El capítulo, hoy, dice otra cosa.
        $this->chapter($chapterId, $autora['token']);
        self::assertStringContainsString('reescrito', $this->payload()['content']);

        // La corrección sigue apuntando al texto sobre el que se escribió.
        $this->chapterTextOf($correctionId, $autora['token']);
        self::assertResponseIsSuccessful();
        self::assertFalse($this->payload()['isCurrentVersion'], 'Y se dice que no es el de ahora.');
        self::assertSame(1, $this->payload()['version']);
        self::assertStringNotContainsString('reescrito', $this->payload()['contentHtml']);

        $this->chapterTextOf($correctionId, $lectora['token']);
        self::assertResponseIsSuccessful('Quien corrigió ve lo mismo.');
        self::assertSame(1, $this->payload()['version']);
    }

    /**
     * `RN-5`: editar reprecia el capítulo. Ese cálculo existía desde el
     * primer día y no se había ejecutado nunca, porque el texto no podía
     * cambiar.
     */
    public function testEditingRepricesTheChapter(): void
    {
        $autora = $this->activatedPerson('autora');
        $workId = $this->createWork($autora['token'], 'La que crece');
        $chapterId = $this->addChapter($workId, $autora['token'], words: 400);
        $this->saveQuestionnaire($workId, $autora['token'], [
            ['statement' => '¿Qué te ha parecido?', 'minWords' => 30],
        ]);
        $this->consumeEverything();

        $precioInicial = $this->priceOf($chapterId);

        $this->editChapter($chapterId, $autora['token'], [
            'contentHtml' => '<p>'.implode(' ', array_fill(0, 8000, 'palabra')).'</p>',
        ]);
        $this->capture();
        $this->consumeEverything();

        self::assertGreaterThan($precioInicial, $this->priceOf($chapterId), 'Más texto, más vale corregirlo.');
    }

    /**
     * `RN-10`: guardar sin tocar no es una edición. Es lo más frecuente que
     * hace un editor de texto.
     */
    public function testSavingWithoutChangesArchivesNothingAndAnnouncesNothing(): void
    {
        [$autora, $chapterId] = $this->aDeliveredCorrectionOnAWork();

        $this->chapter($chapterId, $autora['token']);
        $mismoTexto = (string) $this->payload()['content'];

        $this->editChapter($chapterId, $autora['token'], ['contentHtml' => $mismoTexto]);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        self::assertSame(1, $this->versionOf($chapterId), 'No archiva.');
        self::assertSame([], $this->capture('ChapterContentUpdated'), 'Y no anuncia nada.');
    }

    public function testNobodyElseCanEdit(): void
    {
        $autora = $this->activatedPerson('autora');
        $extrana = $this->activatedPerson('extrana');
        $workId = $this->createWork($autora['token'], 'La obra de otra');
        $chapterId = $this->addChapter($workId, $autora['token'], words: 300);

        $this->editWork($workId, $extrana['token'], ['title' => 'Mía ahora']);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertSame('WORK_NOT_FOUND', $this->payload()['code']);

        $this->editChapter($chapterId, $extrana['token'], ['contentHtml' => '<p>Mío.</p>']);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $this->client->request('PUT', \sprintf('/api/v1/chapters/%s', $chapterId));
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * `RN-7`: el contenido reclamado se conserva tal cual, porque es justo lo
     * que puede hacer falta si alguien discute la decisión.
     */
    public function testABlockedChapterCannotBeEdited(): void
    {
        $autora = $this->activatedPerson('autora');
        $workId = $this->createWork($autora['token'], 'La obra reclamada');
        $chapterId = $this->addChapter($workId, $autora['token'], words: 300);

        $this->blockChapter($chapterId);

        $this->editChapter($chapterId, $autora['token'], ['contentHtml' => '<p>Quitemos lo que denunciaron.</p>']);

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame('CHAPTER_BLOCKED', $this->payload()['code']);
    }

    public function testAnEmptyChapterIsRefused(): void
    {
        $autora = $this->activatedPerson('autora');
        $workId = $this->createWork($autora['token'], 'La que no se vacía');
        $chapterId = $this->addChapter($workId, $autora['token'], words: 300);

        $this->editChapter($chapterId, $autora['token'], ['contentHtml' => '<div><img src="x"></div>']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @return array{0: array{token: string, userId: string}, 1: string, 2: string, 3: array{token: string, userId: string}}
     */
    private function aDeliveredCorrectionOnAWork(): array
    {
        [$autora, $lectora, $correctionId, $workId] = $this->aDeliveredCorrection();

        $this->work($workId, $autora['token']);
        $chapterId = (string) $this->payload()['chapters'][0]['chapterId'];

        return [$autora, $chapterId, $correctionId, $lectora];
    }

    /**
     * @return array{0: array{token: string, userId: string}, 1: string, 2: string}
     */
    private function aChapterBeingCorrected(bool $startCorrection): array
    {
        $autora = $this->activatedPerson('autora');
        $workId = $this->createWork($autora['token'], 'La obra sin lectores');
        $chapterId = $this->addChapter($workId, $autora['token'], words: 400);
        $this->consumeEverything();

        self::assertFalse($startCorrection, 'Este montaje es el de «nadie ha leído».');

        return [$autora, $chapterId, ''];
    }

    /**
     * Lo que hace `Work` al estimarse una reclamación sobre un capítulo
     * (`FEAT-MOD-003`), por el camino corto: aquí se prueba qué ocurre
     * **después**, no cómo se llega.
     */
    private function blockChapter(string $chapterId): void
    {
        /** @var \LectoresBeta\Work\Chapter\Domain\Repository\ChapterRepository $chapters */
        $chapters = self::getContainer()->get(\LectoresBeta\Work\Chapter\Domain\Repository\ChapterRepository::class);
        $chapter = $chapters->ofId(\LectoresBeta\Work\Chapter\Domain\ValueObject\ChapterId::fromString($chapterId));
        self::assertNotNull($chapter);

        $chapter->block(new \DateTimeImmutable());
        $chapters->save($chapter);

        /** @var \Doctrine\ORM\EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        $entityManager->flush();
    }

    private function versionOf(string $chapterId): int
    {
        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');

        return (int) $connection->fetchOne(
            'SELECT version FROM work_ctx.chapter WHERE id = :id',
            ['id' => $chapterId],
        );
    }

    private function priceOf(string $chapterId): int
    {
        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');

        return (int) $connection->fetchOne(
            'SELECT price FROM credits_ctx.chapter_price WHERE chapter_id = :id',
            ['id' => $chapterId],
        );
    }

    /**
     * @param array<string, mixed> $body
     */
    private function editWork(string $workId, string $token, array $body): void
    {
        $this->client->request('PATCH', \sprintf('/api/v1/works/%s', $workId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode($body, \JSON_THROW_ON_ERROR));
    }

    /**
     * @param array<string, mixed> $body
     */
    private function editChapter(string $chapterId, string $token, array $body): void
    {
        $this->client->request('PUT', \sprintf('/api/v1/chapters/%s', $chapterId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode($body, \JSON_THROW_ON_ERROR));
    }

    private function work(string $workId, string $token): void
    {
        $this->client->request('GET', \sprintf('/api/v1/works/%s', $workId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        self::assertResponseIsSuccessful();
    }

    private function chapter(string $chapterId, string $token): void
    {
        $this->client->request('GET', \sprintf('/api/v1/chapters/%s', $chapterId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        self::assertResponseIsSuccessful();
    }

    private function chapterTextOf(string $correctionId, string $token): void
    {
        $this->client->request('GET', \sprintf('/api/v1/corrections/%s/chapter-text', $correctionId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
    }
}
