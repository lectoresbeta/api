<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Feedback;

use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use Symfony\Component\HttpFoundation\Response;

/**
 * Corregir un capítulo (`FEAT-FBK-003`).
 *
 * Es la funcionalidad central del producto y la única que mueve créditos, así
 * que este test recorre el ciclo entero tal y como lo vive la gente: una
 * autora abre su obra a corrección, una lectora la corrige, y los dos saldos
 * se mueven.
 */
final class CorrectionTest extends EconomyScenario
{
    /**
     * El recorrido completo, y lo que importa al final: **el trabajo de la
     * lectora se paga**.
     */
    public function testAReaderCorrectsAChapterAndBothBalancesMove(): void
    {
        [$author, $reader, $chapterId] = $this->aWorkOpenForCorrection();

        $this->panel($chapterId, $reader['token']);

        self::assertResponseIsSuccessful();
        self::assertSame('NOT_STARTED', $this->payload()['status']);

        $questions = $this->payload()['questions'];
        self::assertCount(1, $questions);
        self::assertSame('', $questions[0]['answer']);

        $this->start($chapterId, $reader['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->submit($chapterId, $reader['token'], [
            $questions[0]['questionId'] => $this->words(60),
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertSame('SUBMITTED', $this->payload()['status']);

        // La respuesta no lleva ninguna cifra de créditos: el abono es
        // asíncrono y lo decide otro contexto.
        self::assertSame(['correctionId', 'status'], array_keys($this->payload()));

        $this->consumeEverything();

        // 1.200 palabras y 50 exigidas: 2 + 1.
        self::assertSame(7, $this->balanceOf($author['userId']));
        self::assertSame(13, $this->balanceOf($reader['userId']));
    }

    /**
     * `R-4`: en una obra `PUBLIC` empezar **es** el permiso. No hay solicitud
     * previa, porque el autor ya dijo que sí al elegir la modalidad y una
     * espera justo ahí es donde el producto pierde a la gente.
     */
    public function testInAPublicWorkNobodyHasToAskFirst(): void
    {
        [, $reader, $chapterId] = $this->aWorkOpenForCorrection();

        $this->start($chapterId, $reader['token']);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
    }

    /**
     * Y en una que no es `PUBLIC`, quien no tiene acceso concedido no pasa
     * **ni ve las preguntas**: los enunciados nombran personajes y suelen
     * adelantar el final.
     */
    public function testWithoutAccessToARestrictedWorkThereIsNoQuestionnaireEither(): void
    {
        [$author, $reader, $chapterId, $workId] = $this->aWorkOpenForCorrection();

        $this->accessMode($workId, $author['token'], 'ON_REQUEST');

        $this->panel($chapterId, $reader['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame('NOT_A_BETA_READER', $this->payload()['code']);

        $this->start($chapterId, $reader['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testAnAuthorCannotCorrectTheirOwnWork(): void
    {
        [$author, , $chapterId] = $this->aWorkOpenForCorrection();

        $this->start($chapterId, $author['token']);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame('AUTHOR_CANNOT_CORRECT', $this->payload()['code']);
    }

    /**
     * `RN-5`: el mínimo de palabras es lo que el autor compró, y la primera
     * defensa contra cobrar por «ok, muy bueno».
     */
    public function testAnAnswerBelowTheMinimumIsRefusedAndSaysWhichQuestion(): void
    {
        [, $reader, $chapterId] = $this->aWorkOpenForCorrection();

        $questionId = $this->firstQuestionId($chapterId, $reader['token']);

        $this->submit($chapterId, $reader['token'], [$questionId => 'Ok, muy bueno.']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('ANSWER_TOO_SHORT', $this->payload()['code']);
        self::assertStringContainsString('question 1', (string) $this->payload()['detail']);
    }

    public function testAnUnansweredRequiredQuestionIsRefused(): void
    {
        [, $reader, $chapterId] = $this->aWorkOpenForCorrection();

        $this->submit($chapterId, $reader['token'], []);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('MISSING_REQUIRED_ANSWER', $this->payload()['code']);
    }

    /**
     * `RN-2` y `RN-3`: una por lectora y capítulo, e inmutable una vez
     * entregada. El autor ya ha pagado por ella.
     */
    public function testTheSameChapterCannotBeCorrectedTwiceByTheSameReader(): void
    {
        [$author, $reader, $chapterId] = $this->aWorkOpenForCorrection();

        $questionId = $this->firstQuestionId($chapterId, $reader['token']);
        $this->submit($chapterId, $reader['token'], [$questionId => $this->words(60)]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->submit($chapterId, $reader['token'], [$questionId => $this->words(60)]);

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame('CORRECTION_ALREADY_SUBMITTED', $this->payload()['code']);

        $this->consumeEverything();
        self::assertSame(7, $this->balanceOf($author['userId']), 'Un solo cargo.');
    }

    /**
     * Una obra que no está en corrección no se corrige, aunque se pueda leer.
     * Son dos puertas distintas y el autor abre la segunda a conciencia,
     * porque recibir correcciones le cuesta créditos.
     */
    public function testAWorkThatIsMerelyPublishedDoesNotTakeCorrections(): void
    {
        [$author, $reader, $chapterId, $workId] = $this->aWorkOpenForCorrection();

        $this->changeStatus($workId, $author['token'], 'PUBLISHED');

        $this->start($chapterId, $reader['token']);

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame('WORK_NOT_IN_CORRECTION', $this->payload()['code']);
    }

    /**
     * El hecho que cruza la frontera describe **lo ocurrido**, no sus
     * consecuencias: ni importes, ni una palabra de lo que la lectora
     * escribió. El texto es material privado entre dos personas.
     */
    public function testTheFactThatLeavesCarriesNoMoneyAndNoProse(): void
    {
        [, $reader, $chapterId] = $this->aWorkOpenForCorrection();

        $questionId = $this->firstQuestionId($chapterId, $reader['token']);
        $this->submit($chapterId, $reader['token'], [$questionId => 'Ryn '.$this->words(60)]);

        $delivered = $this->capture('FeedbackSubmitted');
        self::assertCount(1, $delivered);

        $body = $delivered[0]['body'];
        self::assertStringNotContainsString('Ryn', $body);

        /** @var array<string, mixed> $payload */
        $payload = json_decode($body, true, 512, \JSON_THROW_ON_ERROR);

        self::assertSame(1, $payload['questionnaireVersion']);
        self::assertArrayNotHasKey('amount', $payload);
        self::assertArrayNotHasKey('answers', $payload);
    }

    /**
     * Una cuenta sin activar no escribe nada, y corregir es escribir
     * (`FEAT-USR-025`).
     */
    public function testAnUnactivatedAccountCannotCorrect(): void
    {
        [, , $chapterId] = $this->aWorkOpenForCorrection();

        $token = $this->signedInWithoutActivating('pendiente');

        $this->start($chapterId, $token);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame('ACCOUNT_NOT_ACTIVATED', $this->payload()['code']);
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

    private function start(string $chapterId, string $token): void
    {
        $this->client->request('POST', \sprintf('/api/v1/chapters/%s/corrections/start', $chapterId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        $this->capture();
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
