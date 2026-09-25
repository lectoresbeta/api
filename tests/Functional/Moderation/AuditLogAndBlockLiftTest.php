<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Moderation;

use LectoresBeta\Moderation\ModeratorRole\Application\Command\SetModeratorRole;
use LectoresBeta\Moderation\ModeratorRole\Application\Handler\SetModeratorRoleHandler;
use LectoresBeta\Moderation\ModeratorRole\Domain\Enum\ModeratorLevel;
use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use Symfony\Component\HttpFoundation\Response;

/**
 * El registro de auditoría (`FEAT-MOD-007`) y levantar un bloqueo
 * (`FEAT-MOD-003` `RN-7`).
 *
 * Las dos cosas van juntas porque son la misma idea: **el poder que se ejerce
 * sobre los usuarios tiene que poder revisarse y deshacerse**. Hasta aquí el
 * registro se escribía y nadie podía leerlo, y un bloqueo era definitivo de
 * hecho aunque la regla dijera lo contrario.
 */
final class AuditLogAndBlockLiftTest extends EconomyScenario
{
    public function testOnlyAnAdminReadsTheAuditLog(): void
    {
        $moderadora = $this->moderator('moderadora', ModeratorLevel::MODERATOR);
        $administradora = $this->moderator('administradora', ModeratorLevel::ADMIN);
        $persona = $this->activatedPerson('persona');

        $this->auditLog($persona['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $this->auditLog($moderadora['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN, 'Un moderador no audita a sus compañeros.');

        $this->auditLog($administradora['token']);
        self::assertResponseIsSuccessful();

        $this->client->request('GET', '/api/v1/admin/audit-log');
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * `RN-4` y `RN-6`: la entrada lleva quién, y lleva la motivación — que es
     * material interno del expediente y no sale por ninguna otra puerta.
     */
    public function testADecisionLeavesItsAuthorAndItsMotivation(): void
    {
        $administradora = $this->moderator('administradora', ModeratorLevel::ADMIN);
        $autora = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');
        $workId = $this->publishedWork($autora, 'La reclamada');

        $claimId = $this->claim($lectora['token'], 'WORK', $workId);
        $this->review($administradora['token'], $claimId, 'UPHELD', 'La escena incumple las normas.');
        self::assertResponseIsSuccessful();
        $this->capture();
        $this->consumeEverything();

        $this->auditLog($administradora['token'], ['action' => 'CLAIM_REVIEWED']);
        self::assertResponseIsSuccessful();

        $entrada = $this->payload()['entries'][0];
        self::assertSame($administradora['userId'], $entrada['actorId']);
        self::assertSame('CLAIM', $entrada['targetType']);
        self::assertSame($claimId, $entrada['targetId']);
        self::assertSame('La escena incumple las normas.', $entrada['reason']);
        self::assertSame('UPHELD', $entrada['payload']['decision']);

        $this->auditLog($administradora['token'], ['actorId' => $autora['userId']]);
        self::assertSame([], $this->payload()['entries'], 'Y se filtra por quien actuó.');
    }

    /**
     * `RN-7`: hasta ahora un bloqueo era definitivo **de hecho**. El modelo
     * admitía deshacerlo y no había por dónde pedirlo.
     */
    public function testAModeratorCanLiftABlockAndItComesBack(): void
    {
        $moderadora = $this->moderator('moderadora', ModeratorLevel::MODERATOR);
        $autora = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');
        $curiosa = $this->activatedPerson('curiosa');
        $workId = $this->publishedWork($autora, 'La que vuelve');

        $claimId = $this->claim($lectora['token'], 'WORK', $workId);
        $this->review($moderadora['token'], $claimId, 'UPHELD', 'Parecía incumplir las normas.');
        $this->capture();
        $this->consumeEverything();

        $this->work($workId, $curiosa['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND, 'Bloqueada, no existe para el resto.');

        $this->lift($moderadora['token'], 'WORK', $workId, 'Revisado con calma: no incumple nada.');
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->capture();
        $this->consumeEverything();

        $this->work($workId, $curiosa['token']);
        self::assertResponseIsSuccessful('Y vuelve.');

        $this->work($workId, $autora['token']);
        self::assertFalse($this->payload()['blocked']);
    }

    public function testLiftingABlockNeedsAWrittenMotivation(): void
    {
        $moderadora = $this->moderator('moderadora', ModeratorLevel::MODERATOR);
        $autora = $this->activatedPerson('autora');

        $this->lift($moderadora['token'], 'WORK', $this->publishedWork($autora, 'Cualquiera'), '   ');

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('CLAIM_MOTIVATION_REQUIRED', $this->payload()['code']);
    }

    /**
     * Y el levantamiento queda anotado: deshacer una decisión necesita
     * explicarse todavía más que tomarla.
     */
    public function testLiftingABlockIsAudited(): void
    {
        $administradora = $this->moderator('administradora', ModeratorLevel::ADMIN);
        $autora = $this->activatedPerson('autora');
        $workId = $this->publishedWork($autora, 'La levantada');

        $this->lift($administradora['token'], 'WORK', $workId, 'Nos equivocamos.');
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->capture();
        $this->consumeEverything();

        $this->auditLog($administradora['token'], ['action' => 'MODERATION_BLOCK_LIFTED']);
        self::assertCount(1, $this->payload()['entries']);
        self::assertSame('Nos equivocamos.', $this->payload()['entries'][0]['reason']);
    }

    /**
     * `FEAT-MOD-003` `RN-4`: que pierda el trabajo es inevitable; que se
     * entere al intentar entregarlo, no.
     */
    public function testWhoeverWasCorrectingIsToldWhenTheWorkIsBlocked(): void
    {
        $moderadora = $this->moderator('moderadora', ModeratorLevel::MODERATOR);
        $autora = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');

        $workId = $this->createWork($autora['token'], 'La que se bloquea a medias');
        $chapterId = $this->addChapter($workId, $autora['token'], words: 400);
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

        $sinLeerAntes = $this->unreadCountOf($lectora['token']);

        $otra = $this->activatedPerson('otra');
        $claimId = $this->claim($otra['token'], 'WORK', $workId);
        $this->review($moderadora['token'], $claimId, 'UPHELD', 'Contenido inapropiado.');
        $this->capture();
        $this->consumeEverything();

        self::assertSame(
            $sinLeerAntes + 1,
            $this->unreadCountOf($lectora['token']),
            'Quien estaba corrigiendo se entera por un aviso, no por un error al entregar.',
        );
    }

    private function unreadCountOf(string $token): int
    {
        $this->client->request('GET', '/api/v1/me/notifications/unread-count', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        self::assertResponseIsSuccessful();

        return (int) $this->payload()['unreadCount'];
    }

    /**
     * @return array{token: string, userId: string}
     */
    private function moderator(string $local, ModeratorLevel $level): array
    {
        $persona = $this->activatedPerson($local);

        /** @var SetModeratorRoleHandler $setRole */
        $setRole = self::getContainer()->get(SetModeratorRoleHandler::class);
        $setRole(new SetModeratorRole(
            SetModeratorRoleHandler::CONSOLE,
            $persona['userId'],
            $level->value,
            fromConsole: true,
        ));

        return $persona;
    }

    private function claim(string $token, string $targetType, string $targetId): string
    {
        $this->client->request('POST', '/api/v1/claims', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode([
            'targetType' => $targetType,
            'targetId' => $targetId,
            'reason' => 'OFFENSIVE',
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        return (string) $this->payload()['claimId'];
    }

    private function review(string $token, string $claimId, string $decision, string $motivation): void
    {
        $this->client->request('POST', \sprintf('/api/v1/admin/claims/%s/review', $claimId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(['decision' => $decision, 'motivation' => $motivation], \JSON_THROW_ON_ERROR));
    }

    private function lift(string $token, string $targetType, string $targetId, string $motivation): void
    {
        $this->client->request('POST', '/api/v1/admin/moderation-blocks/lift', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode([
            'targetType' => $targetType,
            'targetId' => $targetId,
            'motivation' => $motivation,
        ], \JSON_THROW_ON_ERROR));
    }

    /**
     * @param array<string, string> $query
     */
    private function auditLog(string $token, array $query = []): void
    {
        $this->client->request('GET', '/api/v1/admin/audit-log', $query, server: [
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
