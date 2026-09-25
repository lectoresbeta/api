<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Feedback;

use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use Symfony\Component\HttpFoundation\Response;

/**
 * «Mis correcciones» (`FEAT-FBK-010`).
 *
 * La simétrica de «Mis reclamaciones», y existe por lo mismo: **corregir no
 * puede ser escribir en un buzón**.
 */
final class MyCorrectionsTest extends EconomyScenario
{
    public function testTheWriterSeesWhatTheyWroteAndWhatTheyEarned(): void
    {
        [, $lectora, $correctionId] = $this->aDeliveredCorrection();

        $this->mine($lectora['token']);
        self::assertResponseIsSuccessful();

        $mias = $this->payload()['corrections'];
        self::assertCount(1, $mias);
        self::assertSame($correctionId, $mias[0]['correctionId']);
        self::assertSame('SUBMITTED', $mias[0]['status']);
        self::assertSame('La obra corregida', $mias[0]['workTitle']);
        self::assertGreaterThan(0, $mias[0]['earnedCredits'], 'Y cuánto ganó, que es trabajo remunerado.');
        self::assertNull($mias[0]['helpful'], 'Sin valorar todavía.');
        self::assertFalse($mias[0]['replied']);
    }

    /**
     * `RN-2`: un borrador es trabajo empezado, y quien lo dejó a medias
     * necesita encontrarlo.
     */
    public function testDraftsAppearDistinguishedFromWhatWasDelivered(): void
    {
        $autora = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');

        $workId = $this->createWork($autora['token'], 'La obra a medias');
        $chapterId = $this->addChapter($workId, $autora['token'], words: 500);
        $this->saveQuestionnaire($workId, $autora['token'], [
            ['statement' => '¿Qué te ha parecido?', 'minWords' => 10],
        ]);
        $this->putAs(\sprintf('/api/v1/works/%s/access-mode', $workId), $autora['token'], ['accessMode' => 'PUBLIC']);
        $this->putAs(\sprintf('/api/v1/works/%s/status', $workId), $autora['token'], ['status' => 'PUBLISHED']);
        $this->putAs(\sprintf('/api/v1/works/%s/status', $workId), $autora['token'], ['status' => 'IN_CORRECTION']);
        $this->consumeEverything();

        $this->client->request('POST', \sprintf('/api/v1/chapters/%s/corrections/start', $chapterId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$lectora['token'],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->capture();
        $this->consumeEverything();

        $this->mine($lectora['token']);
        self::assertCount(1, $this->payload()['corrections']);
        self::assertSame('DRAFT', $this->payload()['corrections'][0]['status']);
        self::assertNull($this->payload()['corrections'][0]['submittedAt']);
        self::assertNull($this->payload()['corrections'][0]['earnedCredits'], 'Un borrador no ha cobrado nada.');

        $this->mine($lectora['token'], ['status' => 'SUBMITTED']);
        self::assertSame([], $this->payload()['corrections'], 'Y se puede filtrar.');
    }

    /**
     * `RN-5`: la valoración se muestra tal cual, también la negativa.
     * Ocultarla la dejaría sin función.
     */
    public function testTheWriterSeesWhatTheAuthorDidWithIt(): void
    {
        [$autora, $lectora, $correctionId] = $this->aDeliveredCorrection();

        $this->client->request('PUT', \sprintf('/api/v1/corrections/%s/rating', $correctionId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$autora['token'],
        ], content: json_encode(['helpful' => false], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->client->request('PUT', \sprintf('/api/v1/corrections/%s/reply', $correctionId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$autora['token'],
        ], content: json_encode(['body' => 'No me ha servido, y te cuento por qué.'], \JSON_THROW_ON_ERROR));
        $this->capture();
        $this->consumeEverything();

        $this->mine($lectora['token']);
        self::assertFalse($this->payload()['corrections'][0]['helpful']);
        self::assertTrue($this->payload()['corrections'][0]['replied']);
    }

    /**
     * Una reclamación estimada **retira lo cobrado**, y la lista no puede
     * seguir enseñando un ingreso que ya no existe.
     */
    public function testAnUpheldClaimTakesTheEarningOutOfTheList(): void
    {
        [$autora, $lectora, $correctionId] = $this->aDeliveredCorrection();

        $this->mine($lectora['token']);
        self::assertGreaterThan(0, $this->payload()['corrections'][0]['earnedCredits']);

        $this->upholdAClaimAbout($correctionId, $autora);

        $this->mine($lectora['token']);
        self::assertSame(0, $this->payload()['corrections'][0]['earnedCredits'], 'Se le retiró lo que cobró.');
    }

    public function testNobodyCanListSomebodyElsesCorrections(): void
    {
        [$autora, , $correctionId] = $this->aDeliveredCorrection();

        $this->mine($autora['token']);
        self::assertSame([], $this->payload()['corrections'], 'La autora no corrigió nada: su lista está vacía.');
        self::assertNotSame('', $correctionId);

        $this->client->request('GET', '/api/v1/me/corrections');
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @param array{token: string, userId: string} $autora
     */
    private function upholdAClaimAbout(string $correctionId, array $autora): void
    {
        $moderadora = $this->activatedPerson('moderadora');

        /** @var \LectoresBeta\Moderation\ModeratorRole\Application\Handler\SetModeratorRoleHandler $setRole */
        $setRole = self::getContainer()->get(\LectoresBeta\Moderation\ModeratorRole\Application\Handler\SetModeratorRoleHandler::class);
        $setRole(new \LectoresBeta\Moderation\ModeratorRole\Application\Command\SetModeratorRole(
            \LectoresBeta\Moderation\ModeratorRole\Application\Handler\SetModeratorRoleHandler::CONSOLE,
            $moderadora['userId'],
            \LectoresBeta\Moderation\ModeratorRole\Domain\Enum\ModeratorLevel::MODERATOR->value,
            fromConsole: true,
        ));

        $this->client->request('POST', '/api/v1/claims', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$autora['token'],
        ], content: json_encode([
            'targetType' => 'CORRECTION',
            'targetId' => $correctionId,
            'reason' => 'NO_VALUE',
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $claimId = (string) $this->payload()['claimId'];

        $this->client->request('POST', \sprintf('/api/v1/admin/claims/%s/review', $claimId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$moderadora['token'],
        ], content: json_encode([
            'decision' => 'UPHELD',
            'motivation' => 'No responde a ninguna de las preguntas.',
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
        $this->capture();
        $this->consumeEverything();
    }

    /**
     * @param array<string, string> $query
     */
    private function mine(string $token, array $query = []): void
    {
        $this->client->request('GET', '/api/v1/me/corrections', $query, server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        self::assertResponseIsSuccessful();
    }
}
