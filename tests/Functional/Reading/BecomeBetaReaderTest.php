<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Reading;

use Doctrine\ORM\EntityManagerInterface;
use LectoresBeta\Reading\BetaReaderAccess\Application\Contract\BetaReaderAccessCheck;
use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use Symfony\Component\HttpFoundation\Response;

/**
 * Convertirse en lector beta al empezar a corregir (`FEAT-RDG-001`).
 *
 * No hay endpoint que probar: esta funcionalidad **es el efecto en `Reading`**
 * de un hecho que ocurre en `Feedback`. Lo que se comprueba, por tanto, es
 * que después de empezar una corrección existe un acceso que antes no estaba,
 * y que sigue existiendo cuando el autor cierra la puerta por la que se
 * entró.
 */
final class BecomeBetaReaderTest extends EconomyScenario
{
    public function testStartingACorrectionMakesYouABetaReader(): void
    {
        [, $reader, $chapterId, $workId] = $this->aWorkOpenForCorrection();

        self::assertFalse($this->isBetaReader($workId, $reader['userId']));

        $this->start($chapterId, $reader['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->consumeEverything();

        self::assertTrue($this->isBetaReader($workId, $reader['userId']));
    }

    /**
     * `RN-2`: un acceso vivo por par (lector, obra). Corregir cinco capítulos
     * no convierte a nadie en lector beta cinco veces, y reentregar el hecho
     * tampoco.
     */
    public function testCorrectingSeveralChaptersGrantsOneAccess(): void
    {
        [$author, $reader, $primero, $workId] = $this->aWorkOpenForCorrection();
        $segundo = $this->addChapter($workId, $author['token'], words: 900);

        $this->start($primero, $reader['token']);
        $this->start($segundo, $reader['token']);

        $this->consumeEverything();
        $this->consumeEverything();

        self::assertCount(1, $this->accessesOf($workId, $reader['userId']));
    }

    /**
     * `RN-4` y `RN-6`: se concede **al empezar**, y sobrevive a que el autor
     * restrinja la obra. Es lo que impide que a quien está escribiendo se le
     * evapore el trabajo a mitad.
     */
    public function testTheWorkCanBeRestrictedAndWhoeverIsCorrectingKeepsGoing(): void
    {
        [$author, $reader, $chapterId, $workId] = $this->aWorkOpenForCorrection();

        $this->start($chapterId, $reader['token']);
        $this->consumeEverything();

        // La autora se arrepiente de tenerla abierta a cualquiera.
        $this->accessMode($workId, $author['token'], 'PRIVATE');

        self::assertTrue($this->isBetaReader($workId, $reader['userId']));

        // Y quien estaba corrigiendo termina y cobra.
        $questionId = $this->firstQuestionId($chapterId, $reader['token']);
        $this->submit($chapterId, $reader['token'], [$questionId => $this->words(60)]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->consumeEverything();
        self::assertSame(13, $this->balanceOf($reader['userId']));
    }

    /**
     * La consecuencia que hace que el acceso sirva de algo: con la obra
     * restringida, **el lector beta la lee y un desconocido no**.
     */
    public function testARestrictedWorkIsReadableByItsBetaReadersOnly(): void
    {
        [$author, $reader, $chapterId, $workId] = $this->aWorkOpenForCorrection();

        $this->start($chapterId, $reader['token']);
        $this->consumeEverything();

        $this->accessMode($workId, $author['token'], 'PRIVATE');

        $this->read($chapterId, $reader['token']);
        self::assertResponseIsSuccessful();

        $extraño = $this->activatedPerson('desconocida');
        $this->read($chapterId, $extraño['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * `RN-5`: quien pulsa «descartar» está diciendo que no va a hacerlo.
     * Dejarle acceso permanente a obra inédita por haber abierto un panel
     * sería regalar lectura a cambio de nada.
     */
    public function testWalkingAwayExplicitlyTakesTheAccessWithIt(): void
    {
        [, $reader, $chapterId, $workId] = $this->aWorkOpenForCorrection();

        $this->start($chapterId, $reader['token']);
        $this->consumeEverything();
        self::assertTrue($this->isBetaReader($workId, $reader['userId']));

        $this->discardDraft($chapterId, $reader['token']);
        $this->consumeEverything();

        self::assertFalse($this->isBetaReader($workId, $reader['userId']));
    }

    /**
     * Pero quien ya entregó algo se lo ganó: descartar el borrador de otro
     * capítulo no se lo quita.
     */
    public function testWhoeverDeliveredSomethingKeepsTheAccess(): void
    {
        [$author, $reader, $primero, $workId] = $this->aWorkOpenForCorrection();
        $segundo = $this->addChapter($workId, $author['token'], words: 900);

        $questionId = $this->firstQuestionId($primero, $reader['token']);
        $this->submit($primero, $reader['token'], [$questionId => $this->words(60)]);
        $this->consumeEverything();

        // Empieza el segundo capítulo y se echa atrás.
        $this->start($segundo, $reader['token']);
        $this->discardDraft($segundo, $reader['token']);
        $this->consumeEverything();

        self::assertTrue($this->isBetaReader($workId, $reader['userId']), 'Ya había trabajado en esta obra.');
    }

    /**
     * @return array{0: array{token: string, userId: string}, 1: array{token: string, userId: string}, 2: string, 3: string}
     */
    private function aWorkOpenForCorrection(): array
    {
        $author = $this->activatedPerson('autora');
        $reader = $this->activatedPerson('lectora');

        $workId = $this->createWork($author['token']);
        $chapterId = $this->addChapter($workId, $author['token'], words: 1200);
        $this->saveQuestionnaire($workId, $author['token'], [
            ['statement' => '¿Cómo funciona el ritmo?', 'minWords' => 50],
        ]);

        $this->accessMode($workId, $author['token'], 'PUBLIC');
        $this->changeStatus($workId, $author['token'], 'PUBLISHED');
        $this->changeStatus($workId, $author['token'], 'IN_CORRECTION');

        return [$author, $reader, $chapterId, $workId];
    }

    private function isBetaReader(string $workId, string $readerId): bool
    {
        /** @var BetaReaderAccessCheck $accesses */
        $accesses = self::getContainer()->get(BetaReaderAccessCheck::class);

        return $accesses->hasAccessTo($workId, $readerId);
    }

    /**
     * Toda la historia de accesos de ese par, viva o no: es lo que distingue
     * «uno vivo» de «uno en total».
     *
     * @return list<array<string, mixed>>
     */
    private function accessesOf(string $workId, string $readerId): array
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');

        /** @var list<array<string, mixed>> $rows */
        $rows = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT id FROM reading_ctx.beta_reader_access WHERE work_id = :work AND reader_id = :reader',
            ['work' => $workId, 'reader' => $readerId],
        );

        return $rows;
    }

    private function read(string $chapterId, string $token): void
    {
        $this->client->request('GET', \sprintf('/api/v1/chapters/%s', $chapterId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
    }

    private function start(string $chapterId, string $token): void
    {
        $this->client->request('POST', \sprintf('/api/v1/chapters/%s/corrections/start', $chapterId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        $this->capture();
    }

    private function discardDraft(string $chapterId, string $token): void
    {
        $this->client->request('DELETE', \sprintf('/api/v1/chapters/%s/correction/draft', $chapterId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->capture();
    }

    private function firstQuestionId(string $chapterId, string $token): string
    {
        $this->client->request('GET', \sprintf('/api/v1/chapters/%s/questionnaire', $chapterId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        self::assertResponseIsSuccessful();

        return (string) $this->payload()['questions'][0]['questionId'];
    }

    /**
     * @param array<string, string> $answers
     */
    private function submit(string $chapterId, string $token, array $answers): void
    {
        $body = [];

        foreach ($answers as $questionId => $text) {
            $body[] = ['questionId' => $questionId, 'text' => $text];
        }

        $this->client->request('POST', \sprintf('/api/v1/chapters/%s/corrections', $chapterId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(['answers' => $body], \JSON_THROW_ON_ERROR));

        $this->capture();
    }

    private function changeStatus(string $workId, string $token, string $status): void
    {
        $this->client->request('PUT', \sprintf('/api/v1/works/%s/status', $workId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(['status' => $status], \JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        $this->capture();
    }

    private function accessMode(string $workId, string $token, string $mode): void
    {
        $this->client->request('PUT', \sprintf('/api/v1/works/%s/access-mode', $workId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(['accessMode' => $mode], \JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        $this->capture();
    }

    private function words(int $count): string
    {
        return implode(' ', array_fill(0, $count, 'palabra'));
    }
}
