<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Work;

use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use Symfony\Component\HttpFoundation\Response;

/**
 * Retirar una obra (`FEAT-WRK-006`).
 *
 * El botón dice «Eliminar» y lo que ocurre es un **archivado**. La razón está
 * en lo que cuelga de una obra: correcciones pagadas, reclamaciones resueltas
 * y movimientos de crédito que la citan. Borrarla destruiría el trabajo de
 * otras personas y la prueba de lo que se decidió.
 */
final class ArchiveWorkTest extends EconomyScenario
{
    public function testArchivingHidesItFromEverybodyButItsAuthor(): void
    {
        $autora = $this->activatedPerson('autora');
        $curiosa = $this->activatedPerson('curiosa');
        $workId = $this->publishedWork($autora, 'La que se retira');

        $this->work($workId, $curiosa['token']);
        self::assertResponseIsSuccessful('Antes de retirarla, cualquiera la lee.');

        $this->archive($workId, $autora['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->capture();
        $this->consumeEverything();

        $this->work($workId, $curiosa['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND, 'Deja de existir para el resto.');

        $this->work($workId, $autora['token']);
        self::assertResponseIsSuccessful('Su autora la sigue viendo.');
    }

    /**
     * `RN-2`: la confirmación la comprueba el servidor, no solo la pantalla.
     */
    public function testWithoutAnExplicitConfirmationNothingHappens(): void
    {
        $autora = $this->activatedPerson('autora');
        $workId = $this->publishedWork($autora, 'La que no se retira sola');

        $this->client->request('DELETE', \sprintf('/api/v1/works/%s', $workId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$autora['token'],
        ], content: '{}');

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('CONFIRMATION_REQUIRED', $this->payload()['code']);

        $this->work($workId, $this->activatedPerson('curiosa')['token']);
        self::assertResponseIsSuccessful('Y sigue publicada.');
    }

    /**
     * `RN-5` y `RN-6`: lo entregado se conserva, los accesos se retiran.
     */
    public function testDeliveredCorrectionsSurviveAndAccessesAreRevoked(): void
    {
        [$autora, $lectora, $correctionId, $workId] = $this->aDeliveredCorrection();

        $this->archive($workId, $autora['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->capture();
        $this->consumeEverything();

        $this->client->request('GET', \sprintf('/api/v1/corrections/%s', $correctionId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$autora['token'],
        ]);
        self::assertResponseIsSuccessful('La corrección pagada se sigue leyendo.');

        $this->client->request('GET', \sprintf('/api/v1/corrections/%s', $correctionId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$lectora['token'],
        ]);
        self::assertResponseIsSuccessful('Y quien la escribió tampoco la pierde.');

        $this->client->request('GET', \sprintf('/api/v1/works/%s/beta-readers', $workId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$autora['token'],
        ]);
        self::assertResponseIsSuccessful();
        self::assertSame([], $this->payload()['data'], 'Los accesos se retiran: no hay nada que leer.');
    }

    /**
     * `RN-4`: una obra retirada no admite correcciones nuevas.
     */
    public function testAnArchivedWorkTakesNoCorrections(): void
    {
        $autora = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');

        $workId = $this->createWork($autora['token'], 'La que se cierra');
        $chapterId = $this->addChapter($workId, $autora['token'], words: 400);
        $this->saveQuestionnaire($workId, $autora['token'], [
            ['statement' => '¿Qué te ha parecido?', 'minWords' => 10],
        ]);
        $this->putAs(\sprintf('/api/v1/works/%s/access-mode', $workId), $autora['token'], ['accessMode' => 'PUBLIC']);
        $this->putAs(\sprintf('/api/v1/works/%s/status', $workId), $autora['token'], ['status' => 'PUBLISHED']);
        $this->putAs(\sprintf('/api/v1/works/%s/status', $workId), $autora['token'], ['status' => 'IN_CORRECTION']);
        $this->consumeEverything();

        $this->archive($workId, $autora['token']);
        $this->capture();
        $this->consumeEverything();

        $this->client->request('POST', \sprintf('/api/v1/chapters/%s/corrections/start', $chapterId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$lectora['token'],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * `RN-7`: vuelve a borrador, nunca publicada. Reabrir la puerta es otra
     * decisión.
     */
    public function testRestoringBringsItBackAsADraft(): void
    {
        $autora = $this->activatedPerson('autora');
        $curiosa = $this->activatedPerson('curiosa');
        $workId = $this->publishedWork($autora, 'La que vuelve');

        $this->archive($workId, $autora['token']);
        $this->capture();
        $this->consumeEverything();

        $this->restore($workId, $autora['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->work($workId, $autora['token']);
        self::assertSame('DRAFT', $this->payload()['status']);

        $this->work($workId, $curiosa['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND, 'En borrador tampoco la ve nadie más.');

        $this->restore($workId, $autora['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT, 'Y no se recupera dos veces.');
    }

    /**
     * `RN-8`: sería la vía para hacer desaparecer contenido reclamado.
     */
    public function testABlockedWorkCannotBeArchived(): void
    {
        $autora = $this->activatedPerson('autora');
        $workId = $this->publishedWork($autora, 'La reclamada');

        $this->blockWork($workId);

        $this->archive($workId, $autora['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame('WORK_BLOCKED', $this->payload()['code']);
    }

    /**
     * `RN-10`: para el resto del mundo no existe, así que no cuenta.
     */
    public function testAnArchivedWorkDoesNotCountInTheProfile(): void
    {
        $autora = $this->activatedPerson('autora');
        $this->publishedWork($autora, 'La que se queda');
        $retirada = $this->publishedWork($autora, 'La que se va');

        self::assertSame(2, $this->worksCounterOf($autora['token']));

        $this->archive($retirada, $autora['token']);
        $this->capture();
        $this->consumeEverything();

        self::assertSame(1, $this->worksCounterOf($autora['token']));
    }

    public function testNobodyElseArchivesSomebodyElsesWork(): void
    {
        $autora = $this->activatedPerson('autora');
        $extrana = $this->activatedPerson('extrana');
        $workId = $this->publishedWork($autora, 'La ajena');

        $this->archive($workId, $extrana['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $this->client->request('DELETE', \sprintf('/api/v1/works/%s', $workId));
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    private function worksCounterOf(string $token): int
    {
        $this->client->request('GET', '/api/v1/me/profile', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        self::assertResponseIsSuccessful();

        return (int) $this->payload()['counters']['works'];
    }

    private function blockWork(string $workId): void
    {
        /** @var \LectoresBeta\Work\Manuscript\Domain\Repository\WorkRepository $works */
        $works = self::getContainer()->get(\LectoresBeta\Work\Manuscript\Domain\Repository\WorkRepository::class);
        $work = $works->ofId(\LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId::fromString($workId));
        self::assertNotNull($work);

        $work->block(new \DateTimeImmutable());
        $works->save($work);

        /** @var \Doctrine\ORM\EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        $entityManager->flush();
    }

    private function archive(string $workId, string $token): void
    {
        $this->client->request('DELETE', \sprintf('/api/v1/works/%s', $workId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(['confirm' => true], \JSON_THROW_ON_ERROR));
    }

    private function restore(string $workId, string $token): void
    {
        $this->client->request('POST', \sprintf('/api/v1/works/%s/restore', $workId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
    }

    private function work(string $workId, string $token): void
    {
        $this->client->request('GET', \sprintf('/api/v1/works/%s', $workId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
    }
}
