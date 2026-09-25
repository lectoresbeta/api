<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Moderation;

use LectoresBeta\Moderation\Claim\Domain\ValueObject\PartyId;
use LectoresBeta\Moderation\ModeratorRole\Application\Command\SetModeratorRole;
use LectoresBeta\Moderation\ModeratorRole\Application\Handler\SetModeratorRoleHandler;
use LectoresBeta\Moderation\ModeratorRole\Domain\Enum\ModeratorLevel;
use LectoresBeta\Moderation\ModeratorRole\Domain\Repository\ModeratorRoleRepository;
use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use Symfony\Component\HttpFoundation\Response;

/**
 * El rol de moderación (`FEAT-MOD-004`) y el primer administrador
 * (`FEAT-MOD-012`).
 *
 * **La cuenta más valiosa de la plataforma para quien quiera atacarla**: el
 * backoffice lee obra inédita y datos personales de cualquier usuario. De ahí
 * que lo que se prueba aquí sea sobre todo quién **no** puede hacer qué.
 */
final class ModeratorRoleTest extends EconomyScenario
{
    /**
     * `RN-4`: perder el rol cierra el backoffice **en el acto**, sin esperar
     * a que caduque ningún token. Es la razón de que el token no lleve roles.
     */
    public function testLosingTheRoleClosesTheBackOfficeAtOnce(): void
    {
        $admin = $this->administrator('jefa');
        $persona = $this->activatedPerson('moderadora');

        $this->setRole($admin['token'], $persona['userId'], 'MODERATOR');
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        // Con el mismo token de antes de tener el rol: se resuelve contra la
        // base de datos en cada petición.
        $this->moderators($persona['token']);
        self::assertResponseIsSuccessful('Modera, y el token no ha cambiado.');

        $this->setRole($admin['token'], $persona['userId'], null);

        $this->moderators($persona['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN, 'Y deja de moderar, con ese mismo token.');
    }

    /**
     * `RN-1`: conceder roles es cosa de un `Admin` y no de cualquier
     * moderador. Si no, el primer moderador podría fabricar los demás.
     */
    public function testAModeratorCannotHandOutRoles(): void
    {
        $admin = $this->administrator('jefa');
        $moderadora = $this->activatedPerson('moderadora');
        $tercera = $this->activatedPerson('tercera');

        $this->setRole($admin['token'], $moderadora['userId'], 'MODERATOR');

        $this->setRole($moderadora['token'], $tercera['userId'], 'MODERATOR');
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testSomebodyWithoutTheRoleSeesNothing(): void
    {
        $persona = $this->activatedPerson('curiosa');

        $this->moderators($persona['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $this->client->request('GET', '/api/v1/admin/moderators');
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Nadie se toca su propio rol desde la API: concedérselo convertiría la
     * auditoría en un trámite, y quitárselo dejaría la plataforma sin
     * administrador por un descuido.
     */
    public function testNobodyChangesTheirOwnRoleThroughTheApi(): void
    {
        $admin = $this->administrator('jefa');

        $this->setRole($admin['token'], $admin['userId'], null);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('MODERATOR_ROLE_REFUSED', $this->payload()['code']);
    }

    /**
     * `FEAT-MOD-012` `RN-2`: una cuenta sin activar no ha demostrado todavía
     * que haya alguien detrás.
     */
    public function testAnAccountThatIsNotActivatedCannotModerate(): void
    {
        $admin = $this->administrator('jefa');

        $this->setRole($admin['token'], '11111111-1111-4111-8111-111111111111', 'MODERATOR');

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('MODERATOR_ROLE_REFUSED', $this->payload()['code']);
    }

    /**
     * `RN-3`: apagar el aviso no es renunciar al rol. Hay quien prefiere
     * entrar a la cola cuando puede.
     */
    public function testAModeratorCanSilenceTheAlertsWithoutLosingTheRole(): void
    {
        $admin = $this->administrator('jefa');
        $moderadora = $this->activatedPerson('moderadora');
        $this->setRole($admin['token'], $moderadora['userId'], 'MODERATOR');

        $this->client->request('PUT', '/api/v1/me/moderator-alerts', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$moderadora['token'],
        ], content: json_encode(['enabled' => false], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $fila = $this->rowOf($moderadora['userId']);
        self::assertSame('MODERATOR', $fila['level'], 'Sigue moderando…');
        self::assertFalse($fila['emailAlerts'], '…y en silencio.');
    }

    /**
     * Conceder el rol a quien lo tuvo **reactiva su ficha**, con su historia,
     * y vuelve a encender el aviso: quien recupera el rol vuelve al trabajo.
     */
    public function testGrantingItBackReactivatesTheSameRow(): void
    {
        $admin = $this->administrator('jefa');
        $moderadora = $this->activatedPerson('moderadora');

        $this->setRole($admin['token'], $moderadora['userId'], 'MODERATOR');
        $this->setRole($admin['token'], $moderadora['userId'], null);
        $this->setRole($admin['token'], $moderadora['userId'], 'ADMIN');

        $fila = $this->rowOf($moderadora['userId']);
        self::assertSame('ADMIN', $fila['level']);
        self::assertTrue($fila['emailAlerts']);

        $this->moderators($admin['token']);
        $ids = array_column($this->payload()['moderators'], 'userId');
        self::assertCount(\count(array_unique($ids)), $ids, 'Una sola ficha por cuenta.');
    }

    /**
     * El primer administrador se nombra por consola, que es el único camino
     * sin administrador detrás (`FEAT-MOD-012` `RN-6`).
     */
    public function testTheFirstAdministratorIsNamedFromTheConsole(): void
    {
        $persona = $this->activatedPerson('primera');

        $this->makeAdmin($persona['userId']);

        $this->moderators($persona['token']);
        self::assertResponseIsSuccessful();
    }

    /**
     * @return array{token: string, userId: string}
     */
    private function administrator(string $local): array
    {
        $admin = $this->activatedPerson($local);
        $this->makeAdmin($admin['userId']);

        return $admin;
    }

    /**
     * Lo que hace el comando de consola, por el mismo camino que él: saltando
     * la regla de «nadie se toca su propio rol», porque la primera vez no hay
     * ningún administrador que firme.
     */
    private function makeAdmin(string $userId): void
    {
        /** @var SetModeratorRoleHandler $setRole */
        $setRole = self::getContainer()->get(SetModeratorRoleHandler::class);
        $setRole(new SetModeratorRole(
            SetModeratorRoleHandler::CONSOLE,
            $userId,
            ModeratorLevel::ADMIN->value,
            fromConsole: true,
        ));
    }

    private function setRole(string $token, string $userId, ?string $level): void
    {
        $this->client->request('PUT', \sprintf('/api/v1/admin/users/%s/moderator-role', $userId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(['level' => $level], \JSON_THROW_ON_ERROR));
    }

    private function moderators(string $token): void
    {
        $this->client->request('GET', '/api/v1/admin/moderators', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
    }

    /**
     * @return array{level: string, emailAlerts: bool}
     */
    private function rowOf(string $userId): array
    {
        /** @var ModeratorRoleRepository $roles */
        $roles = self::getContainer()->get(ModeratorRoleRepository::class);
        $role = $roles->ofUser(PartyId::fromString($userId));

        self::assertNotNull($role);

        return ['level' => $role->level()->value, 'emailAlerts' => $role->wantsEmailAlerts()];
    }
}
