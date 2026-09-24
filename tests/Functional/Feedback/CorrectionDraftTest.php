<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Feedback;

use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use Symfony\Component\HttpFoundation\Response;

/**
 * El borrador de corrección (`FEAT-FBK-011`).
 *
 * Existe por una razón muy concreta: una corrección seria de una obra larga
 * no se escribe de una sentada, y sin borrador el lector escribe en otro
 * sitio y pega, o pierde el trabajo. Las dos cosas empeoran el feedback, que
 * es el producto.
 */
final class CorrectionDraftTest extends EconomyScenario
{
    public function testWhatIsSavedComesBackWhenThePanelIsReopened(): void
    {
        [, $reader, $chapterId] = $this->aWorkOpenForCorrection();

        $questionId = $this->firstQuestionId($chapterId, $reader['token']);

        $this->saveDraft($chapterId, $reader['token'], [$questionId => 'Voy por aquí']);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED, 'El primer guardado empieza la corrección.');

        $this->panel($chapterId, $reader['token']);

        self::assertSame('DRAFT', $this->payload()['status']);
        self::assertSame('Voy por aquí', $this->payload()['questions'][0]['answer']);
    }

    /**
     * `RN-1`: uno por capítulo. Guardar de nuevo sobrescribe, no acumula.
     */
    public function testSavingTwiceLeavesOneDraft(): void
    {
        [, $reader, $chapterId] = $this->aWorkOpenForCorrection();

        $questionId = $this->firstQuestionId($chapterId, $reader['token']);

        $this->saveDraft($chapterId, $reader['token'], [$questionId => 'Primera versión']);
        $primero = $this->payload()['correctionId'];

        $this->saveDraft($chapterId, $reader['token'], [$questionId => 'Segunda versión']);

        self::assertResponseStatusCodeSame(Response::HTTP_OK, 'Ya estaba empezada.');
        self::assertSame($primero, $this->payload()['correctionId']);

        $this->panel($chapterId, $reader['token']);
        self::assertSame('Segunda versión', $this->payload()['questions'][0]['answer']);
    }

    /**
     * `RN-2`: está a medias por definición. Rechazar un borrador por corto
     * sería impedir guardar.
     */
    public function testADraftIsNotMeasuredAgainstWhatTheAuthorAsked(): void
    {
        [, $reader, $chapterId] = $this->aWorkOpenForCorrection();

        $questionId = $this->firstQuestionId($chapterId, $reader['token']);

        // Tres palabras, cuando el mínimo son cincuenta.
        $this->saveDraft($chapterId, $reader['token'], [$questionId => 'Todavía no sé']);

        self::assertResponseIsSuccessful();

        // Y enviarlo así sigue sin colar.
        $this->submit($chapterId, $reader['token'], [$questionId => 'Todavía no sé']);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * El techo sí, y no para medir calidad: para no almacenar texto sin
     * límite.
     */
    public function testTheCeilingIsCheckedEvenInADraft(): void
    {
        [, $reader, $chapterId] = $this->aWorkOpenForCorrection();

        $questionId = $this->firstQuestionId($chapterId, $reader['token']);

        $this->saveDraft($chapterId, $reader['token'], [$questionId => $this->words(500)]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('ANSWER_TOO_LONG', $this->payload()['code']);
    }

    /**
     * `RN-5`: enviar es una transición, no una copia. El borrador deja de
     * existir como tal.
     */
    public function testSendingTurnsTheDraftIntoTheCorrection(): void
    {
        [, $reader, $chapterId] = $this->aWorkOpenForCorrection();

        $questionId = $this->firstQuestionId($chapterId, $reader['token']);

        $this->saveDraft($chapterId, $reader['token'], [$questionId => $this->words(60)]);
        $borrador = $this->payload()['correctionId'];

        $this->submit($chapterId, $reader['token'], [$questionId => $this->words(60)]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertSame($borrador, $this->payload()['correctionId'], 'La misma corrección, en otro estado.');

        $this->panel($chapterId, $reader['token']);
        self::assertSame('SUBMITTED', $this->payload()['status']);
    }

    /**
     * **Descartar sí es un hecho de negocio**, y esto es lo que lo demuestra:
     * suelta la cotización del precio y con ella el sitio que ocupaba en el
     * capítulo (`FEAT-CRD-009` `RN-8`).
     */
    public function testDiscardingReleasesTheSlotItHadTaken(): void
    {
        [, $reader, $chapterId] = $this->aWorkOpenForCorrection();

        $questionId = $this->firstQuestionId($chapterId, $reader['token']);
        $this->saveDraft($chapterId, $reader['token'], [$questionId => 'A medias']);

        $this->discardDraft($chapterId, $reader['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $descartes = $this->queued('CorrectionDraftDiscarded');
        self::assertCount(1, $descartes);

        /** @var array<string, mixed> $payload */
        $payload = json_decode($descartes[0]['body'], true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame($chapterId, $payload['chapterId']);

        // Y el panel vuelve a estar en blanco.
        $this->panel($chapterId, $reader['token']);
        self::assertSame('NOT_STARTED', $this->payload()['status']);
        self::assertSame('', $this->payload()['questions'][0]['answer']);
    }

    public function testDiscardingWhatIsNotThereIsNotAnError(): void
    {
        [, $reader, $chapterId] = $this->aWorkOpenForCorrection();

        $this->discardDraft($chapterId, $reader['token']);

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }

    /**
     * `RN-3` de `FEAT-FBK-003`: entregada es inmutable, porque el autor ya ha
     * pagado por ella.
     */
    public function testADeliveredCorrectionCannotBeDiscarded(): void
    {
        [, $reader, $chapterId] = $this->aWorkOpenForCorrection();

        $questionId = $this->firstQuestionId($chapterId, $reader['token']);
        $this->submit($chapterId, $reader['token'], [$questionId => $this->words(60)]);

        $this->discardDraft($chapterId, $reader['token']);

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame('CORRECTION_ALREADY_SUBMITTED', $this->payload()['code']);
    }

    /**
     * `RN-4`: el autor no ve el borrador ni sabe que existe. Si lo supiera,
     * tendría información sobre una crítica que todavía no ha recibido, y
     * quien la escribe sentiría la presión de enviarla.
     */
    public function testTheAuthorCannotReachSomebodyElsesDraft(): void
    {
        [$author, $reader, $chapterId] = $this->aWorkOpenForCorrection();

        $questionId = $this->firstQuestionId($chapterId, $reader['token']);
        $this->saveDraft($chapterId, $reader['token'], [$questionId => 'Lo que opino en realidad']);

        // El autor ni siquiera puede abrir el panel de su propia obra.
        $this->panel($chapterId, $author['token']);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame('AUTHOR_CANNOT_CORRECT', $this->payload()['code']);
    }

    /**
     * `RN-6`: si el autor reescribe el cuestionario, el lector tiene derecho
     * a enterarse antes de seguir. Lo que responde sigue siendo la versión
     * con la que empezó.
     */
    public function testTheReaderIsToldWhenTheQuestionnaireMovedUnderThem(): void
    {
        [$author, $reader, $chapterId, $workId] = $this->aWorkOpenForCorrection();

        $questionId = $this->firstQuestionId($chapterId, $reader['token']);
        $this->saveDraft($chapterId, $reader['token'], [$questionId => 'A medias']);
        self::assertFalse($this->payload()['questionnaireVersionChanged']);

        $this->saveQuestionnaire($workId, $author['token'], [
            ['statement' => 'Otra cosa distinta', 'minWords' => 50],
        ]);

        $this->saveDraft($chapterId, $reader['token'], []);

        self::assertTrue($this->payload()['questionnaireVersionChanged']);
        self::assertSame(1, $this->payload()['questionnaireVersion'], 'Sigue respondiendo la versión con la que empezó.');
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
            ['statement' => '¿Cómo funciona el ritmo?', 'minWords' => 50, 'maxWords' => 400],
        ]);

        $this->accessMode($workId, $author['token'], 'PUBLIC');
        $this->changeStatus($workId, $author['token'], 'PUBLISHED');
        $this->changeStatus($workId, $author['token'], 'IN_CORRECTION');

        return [$author, $reader, $chapterId, $workId];
    }

    /**
     * @param array<string, string> $answers
     */
    private function saveDraft(string $chapterId, string $token, array $answers): void
    {
        $this->client->request('PUT', \sprintf('/api/v1/chapters/%s/correction/draft', $chapterId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(['answers' => $this->asList($answers)], \JSON_THROW_ON_ERROR));

        $this->capture();
    }

    private function discardDraft(string $chapterId, string $token): void
    {
        $this->client->request('DELETE', \sprintf('/api/v1/chapters/%s/correction/draft', $chapterId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        $this->capture();
    }

    private function firstQuestionId(string $chapterId, string $token): string
    {
        $this->panel($chapterId, $token);
        self::assertResponseIsSuccessful();

        return (string) $this->payload()['questions'][0]['questionId'];
    }

    private function panel(string $chapterId, string $token): void
    {
        $this->client->request('GET', \sprintf('/api/v1/chapters/%s/questionnaire', $chapterId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
    }

    /**
     * @param array<string, string> $answers
     */
    private function submit(string $chapterId, string $token, array $answers): void
    {
        $this->client->request('POST', \sprintf('/api/v1/chapters/%s/corrections', $chapterId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(['answers' => $this->asList($answers)], \JSON_THROW_ON_ERROR));

        $this->capture();
    }

    /**
     * @param array<string, string> $answers
     *
     * @return list<array{questionId: string, text: string}>
     */
    private function asList(array $answers): array
    {
        $body = [];

        foreach ($answers as $questionId => $text) {
            $body[] = ['questionId' => $questionId, 'text' => $text];
        }

        return $body;
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
