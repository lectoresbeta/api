<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Moderation;

use LectoresBeta\Moderation\ModeratorRole\Domain\Enum\ModeratorLevel;
use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use Symfony\Component\HttpFoundation\Response;

/**
 * El catálogo de sanciones (`FEAT-MOD-006`).
 *
 * Cuatro familias y nada más. Un catálogo corto es una virtud: cuantos más
 * matices tenga, más difícil es que dos moderadores sancionen lo mismo de la
 * misma manera.
 *
 * Lo que estas pruebas vigilan es la frontera: **`Moderation` registra la
 * sanción y `User` la aplica**. Si `Moderation` marcara la cuenta
 * directamente habría dos dueños del estado del usuario, y el día que
 * discreparan no habría forma de saber cuál manda.
 */
final class SanctionTest extends EconomyScenario
{
    /**
     * `MOD-27`: la suspensión parcial **deja entrar y leer**, y solo cierra
     * las escrituras. Que pueda entrar no es una concesión menor: es lo que
     * le permite leer la sanción, entender por qué la tiene y ver cuándo
     * termina.
     */
    public function testAPartialSuspensionLeavesReadingOpenAndClosesWriting(): void
    {
        $moderador = $this->moderator('mod');
        $sancionada = $this->activatedPerson('sancionada');

        $this->impose($moderador['token'], [
            'userId' => $sancionada['userId'],
            'type' => 'PARTIAL_SUSPENSION',
            'duration' => 'ONE_WEEK',
            'reason' => 'Comentarios ofensivos repetidos',
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->consumeEverything();

        // Sigue entrando y leyendo.
        $this->client->request('GET', '/api/v1/me/profile', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$sancionada['token'],
        ]);
        self::assertResponseIsSuccessful();

        // Y no escribe.
        $this->client->request('POST', '/api/v1/works', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$sancionada['token'],
        ], content: json_encode(['title' => 'Lo que sea'], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame('ACCOUNT_NOT_ACTIVATED', $this->payload()['code']);
    }

    /**
     * `RN-7b`: una cuenta expulsada **no autentica**, y `MOD-26`: conserva
     * sus datos. No se puede a la vez borrar a alguien y recordarlo para
     * impedirle volver.
     */
    public function testAnExpulsionCloncesTheDoorAndKeepsTheAccount(): void
    {
        $moderador = $this->moderator('mod');
        $expulsada = $this->activatedPerson('expulsada');

        $this->impose($moderador['token'], [
            'userId' => $expulsada['userId'],
            'type' => 'EXPULSION',
            'reason' => 'Suplantación de identidad',
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->consumeEverything();

        $this->client->request('GET', '/api/v1/me/profile', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$expulsada['token'],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        self::assertSame('BLOCKED', $this->statusOf($expulsada['userId']), 'Y no `DELETED`: los datos se conservan.');
    }

    public function testAFullSuspensionAlsoClosesTheDoor(): void
    {
        $moderador = $this->moderator('mod');
        $suspendida = $this->activatedPerson('suspendida');

        $this->impose($moderador['token'], [
            'userId' => $suspendida['userId'],
            'type' => 'FULL_SUSPENSION',
            'reason' => 'Se investiga una denuncia grave',
        ]);
        $this->consumeEverything();

        $this->client->request('GET', '/api/v1/me/profile', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$suspendida['token'],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        self::assertSame('SUSPENDED', $this->statusOf($suspendida['userId']));
    }

    /**
     * Un aviso **no tiene efecto funcional**. Queda registrado, que es
     * exactamente lo que se quería de él: es lo que hace que la reincidencia
     * pese.
     */
    public function testAWarningChangesNothingButIsRecorded(): void
    {
        $moderador = $this->moderator('mod');
        $avisada = $this->activatedPerson('avisada');

        $this->impose($moderador['token'], [
            'userId' => $avisada['userId'],
            'type' => 'WARNING',
            'reason' => 'Primer aviso por el tono',
        ]);
        $this->consumeEverything();

        $this->client->request('POST', '/api/v1/works', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$avisada['token'],
        ], content: json_encode(['title' => 'Sigo escribiendo'], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertSame('ACTIVE', $this->statusOf($avisada['userId']));
    }

    /**
     * `RN-7`: **cualquiera se puede levantar, incluida la expulsión**. Al no
     * anonimizar, no destruye nada que impida volver atrás.
     */
    public function testAnExpulsionCanBeLiftedAndTheAccountComesBack(): void
    {
        $moderador = $this->moderator('mod');
        $expulsada = $this->activatedPerson('expulsada');

        $this->impose($moderador['token'], [
            'userId' => $expulsada['userId'],
            'type' => 'EXPULSION',
            'reason' => 'Error de apreciación',
        ]);
        $sanctionId = (string) $this->payload()['sanctionId'];
        $this->consumeEverything();

        $this->lift($sanctionId, $moderador['token'], 'Se revisó y no procedía');
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->consumeEverything();

        self::assertSame('ACTIVE', $this->statusOf($expulsada['userId']));
    }

    /**
     * Levantar lo ya levantado **sí es un error aquí**, al revés que en otras
     * operaciones idempotentes del proyecto: es un acto administrativo que se
     * registra y se comunica, y hacerlo dos veces dejaría en el historial
     * algo que no ocurrió.
     */
    public function testLiftingTwiceIsRefused(): void
    {
        $moderador = $this->moderator('mod');
        $sancionada = $this->activatedPerson('sancionada');

        $this->impose($moderador['token'], [
            'userId' => $sancionada['userId'],
            'type' => 'WARNING',
            'reason' => 'Un aviso',
        ]);
        $sanctionId = (string) $this->payload()['sanctionId'];
        $this->capture();

        $this->lift($sanctionId, $moderador['token'], 'Retirado');
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->capture();

        $this->lift($sanctionId, $moderador['token'], 'Otra vez');
        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame('SANCTION_NOT_IN_FORCE', $this->payload()['code']);
    }

    /**
     * `RN-1` y `RN-4`: **el motivo es obligatorio**. Al usuario se le
     * comunica, y una sanción que no se entiende no corrige nada: solo hace
     * que la persona se vaya.
     */
    public function testASanctionWithoutAReasonIsRefused(): void
    {
        $moderador = $this->moderator('mod');
        $alguien = $this->activatedPerson('alguien');

        $this->impose($moderador['token'], [
            'userId' => $alguien['userId'],
            'type' => 'WARNING',
            'reason' => '   ',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('SANCTION_REASON_REQUIRED', $this->payload()['code']);
    }

    /**
     * Una suspensión parcial **siempre lleva plazo**: sin él sería una total
     * con otro nombre, y la total es una decisión distinta que se toma a
     * conciencia.
     */
    public function testAPartialSuspensionWithoutADurationIsRefused(): void
    {
        $moderador = $this->moderator('mod');
        $alguien = $this->activatedPerson('alguien');

        $this->impose($moderador['token'], [
            'userId' => $alguien['userId'],
            'type' => 'PARTIAL_SUSPENSION',
            'reason' => 'Un motivo',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('SANCTION_DURATION_REQUIRED', $this->payload()['code']);
    }

    /**
     * `RN-6`: **ninguna sanción mueve créditos**. Devolver el crédito repara
     * al perjudicado; la sanción corrige al infractor. Una reclamación puede
     * producir lo primero sin lo segundo, y al revés.
     */
    public function testNoSanctionMovesCredits(): void
    {
        $moderador = $this->moderator('mod');
        $sancionada = $this->activatedPerson('sancionada');
        // Los créditos de bienvenida se abonan al consumir la activación; sin
        // esto, el «antes» sería el de una cuenta que todavía no los tiene.
        $this->consumeEverything();
        $antes = $this->balanceOf($sancionada['userId']);

        $this->impose($moderador['token'], [
            'userId' => $sancionada['userId'],
            'type' => 'PARTIAL_SUSPENSION',
            'duration' => 'THREE_DAYS',
            'reason' => 'Un motivo',
        ]);
        $this->consumeEverything();

        self::assertSame($antes, $this->balanceOf($sancionada['userId']));
    }

    /**
     * Queda en el registro de auditoría **con la identidad de quien la
     * impuso**: un poder que se ejerce sin dejar nombre es un poder que nadie
     * puede revisar después.
     */
    public function testEverySanctionLeavesItsTraceInTheAuditLog(): void
    {
        $admin = $this->moderator('admin', ModeratorLevel::ADMIN);
        $sancionada = $this->activatedPerson('sancionada');

        $this->impose($admin['token'], [
            'userId' => $sancionada['userId'],
            'type' => 'WARNING',
            'reason' => 'El tono de los comentarios',
        ]);
        $this->consumeEverything();

        $this->client->request('GET', '/api/v1/admin/audit-log?action=SANCTION_IMPOSED', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$admin['token'],
        ]);
        self::assertResponseIsSuccessful();

        $entrada = $this->payload()['entries'][0];
        self::assertSame($admin['userId'], $entrada['actorId']);
        self::assertSame($sancionada['userId'], $entrada['targetId']);
        self::assertSame('El tono de los comentarios', $entrada['reason']);
    }

    public function testOnlyModerationCanSanction(): void
    {
        $cualquiera = $this->activatedPerson('cualquiera');
        $otra = $this->activatedPerson('otra');

        $this->impose($cualquiera['token'], [
            'userId' => $otra['userId'],
            'type' => 'WARNING',
            'reason' => 'Porque sí',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    /**
     * El hecho lleva **tipo, motivo y hasta cuándo**, que es lo que
     * `Notification` necesita para contárselo a la persona.
     */
    public function testTheFactCarriesWhatTheUserHasToBeTold(): void
    {
        $moderador = $this->moderator('mod');
        $sancionada = $this->activatedPerson('sancionada');

        $this->impose($moderador['token'], [
            'userId' => $sancionada['userId'],
            'type' => 'PARTIAL_SUSPENSION',
            'duration' => 'THREE_DAYS',
            'reason' => 'Comentarios ofensivos',
        ]);

        $anunciado = $this->lastAnnouncementOf('SanctionImposed');

        self::assertSame($sancionada['userId'], $anunciado['userId']);
        self::assertSame('PARTIAL_SUSPENSION', $anunciado['type']);
        self::assertSame('Comentarios ofensivos', $anunciado['reason']);
        self::assertNotNull($anunciado['expiresAt']);
    }

    /**
     * @param array<string, string> $body
     */
    private function impose(string $token, array $body): void
    {
        $this->client->request('POST', '/api/v1/admin/sanctions', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode($body, \JSON_THROW_ON_ERROR));
    }

    private function lift(string $sanctionId, string $token, string $reason): void
    {
        $this->client->request('POST', \sprintf('/api/v1/admin/sanctions/%s/lift', $sanctionId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(['reason' => $reason], \JSON_THROW_ON_ERROR));
    }

    private function statusOf(string $userId): string
    {
        /** @var \Doctrine\ORM\EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');

        return (string) $entityManager->getConnection()->fetchOne(
            'SELECT status FROM user_ctx.account WHERE id = :user',
            ['user' => $userId],
        );
    }
}
