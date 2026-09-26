<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\User;

use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Account\Domain\ValueObject\Username;
use LectoresBeta\User\Profile\Application\Service\PurgeExpiredAliases;
use LectoresBeta\User\Profile\Domain\Entity\UsernameAlias;
use LectoresBeta\User\Profile\Domain\Enum\UsernameAliasReason;
use LectoresBeta\User\Profile\Domain\Repository\UsernameAliasRepository;
use Symfony\Component\HttpFoundation\Response;

/**
 * La purga de alias caducados (`FEAT-USR-036`).
 *
 * **Lo que esta prueba defiende no es lo que el comando hace, sino lo que
 * *no* hace falta que haga**: un alias caducado ya ha dejado de resolver y ya
 * ha dejado de ocupar su nombre **antes** de que nadie lo borre.
 *
 * Si la disponibilidad de un nombre dependiera de que el comando hubiera
 * pasado, un fallo del programador mantendría nombres bloqueados sin que
 * nadie se enterase, y el sistema daría respuestas distintas según la hora
 * del día. Los dos primeros casos son los que lo demuestran.
 */
final class PurgeExpiredAliasesTest extends EconomyScenario
{
    /**
     * **Antes de purgar**, un alias caducado ya devuelve `404` al resolver.
     */
    public function testAnExpiredAliasLeadsNowhereBeforeAnybodyPurgesIt(): void
    {
        $persona = $this->activatedPerson('persona');
        $this->anExpiredAlias('nombreviejo', $persona['userId']);

        $this->client->request('GET', '/api/v1/profiles/nombreviejo');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertTrue($this->aliasExists('nombreviejo'), 'Y su fila sigue ahí, esperando.');
    }

    /**
     * **Antes de purgar**, el nombre ya está disponible para quien lo quiera.
     */
    public function testAnExpiredAliasNoLongerHoldsItsNameBeforeBeingPurged(): void
    {
        $persona = $this->activatedPerson('persona');
        $this->anExpiredAlias('nombreviejo', $persona['userId']);

        $otra = $this->activatedPerson('otra');
        $this->client->request('PUT', '/api/v1/me/username', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$otra['token'],
        ], content: json_encode(['username' => 'nombreviejo'], \JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful('Caducado es libre, lo haya borrado alguien o no.');
    }

    /**
     * `RN-1` y `RN-2`: borra lo caducado y no toca lo vigente.
     */
    public function testItPurgesWhatExpiredAndLeavesTheRestAlone(): void
    {
        $persona = $this->activatedPerson('persona');
        $this->anExpiredAlias('caducado', $persona['userId']);
        $this->aLiveAlias('vigente', $persona['userId']);

        self::assertSame(1, $this->purge());

        self::assertFalse($this->aliasExists('caducado'), 'RN-4c: la fila desaparece.');
        self::assertTrue($this->aliasExists('vigente'));
    }

    /**
     * `RN-4b`: da igual de dónde venga el alias. Lo único que mira es su
     * fecha.
     */
    public function testItTreatsBothOriginsAlike(): void
    {
        $persona = $this->activatedPerson('persona');
        $this->anExpiredAlias('porcambio', $persona['userId'], UsernameAliasReason::USERNAME_CHANGED);
        $this->anExpiredAlias('porborrado', null, UsernameAliasReason::ACCOUNT_DELETED);

        self::assertSame(2, $this->purge());

        self::assertFalse($this->aliasExists('porcambio'));
        self::assertFalse($this->aliasExists('porborrado'), 'Sin cuenta detrás y se borra igual.');
    }

    /**
     * `RN-3`: ejecutarlo dos veces no produce error ni efecto adicional.
     */
    public function testRunningItTwiceDoesNothingTheSecondTime(): void
    {
        $persona = $this->activatedPerson('persona');
        $this->anExpiredAlias('caducado', $persona['userId']);

        self::assertSame(1, $this->purge());
        self::assertSame(0, $this->purge());
    }

    /**
     * `--dry-run` cuenta y no borra. Sirve para mirar antes de tocar, que es
     * lo que alguien hace la primera vez que lo ejecuta en producción.
     */
    public function testADryRunCountsAndChangesNothing(): void
    {
        $persona = $this->activatedPerson('persona');
        $this->anExpiredAlias('caducado', $persona['userId']);

        self::assertSame(1, $this->purge(dryRun: true));
        self::assertTrue($this->aliasExists('caducado'), 'Sigue ahí.');

        self::assertSame(1, $this->purge(), 'Y se borra cuando se pide de verdad.');
    }

    /**
     * `RN-4`: no toca ninguna cuenta. El nombre en uso de la persona sigue
     * siendo el suyo.
     */
    public function testItNeverTouchesAUsernameInUse(): void
    {
        $persona = $this->activatedPerson('persona');
        $this->anExpiredAlias('caducado', $persona['userId']);

        $this->client->request('GET', '/api/v1/me/profile', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$persona['token'],
        ]);
        self::assertResponseIsSuccessful();
        $antes = (string) $this->payload()['username'];

        $this->purge();

        $this->client->request('GET', '/api/v1/me/profile', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$persona['token'],
        ]);
        self::assertSame($antes, $this->payload()['username']);
    }

    private function purge(bool $dryRun = false): int
    {
        /** @var PurgeExpiredAliases $purge */
        $purge = self::getContainer()->get(PurgeExpiredAliases::class);

        return $purge($dryRun);
    }

    private function anExpiredAlias(
        string $username,
        ?string $userId,
        UsernameAliasReason $reason = UsernameAliasReason::USERNAME_CHANGED,
    ): void {
        $this->saveAlias($username, $userId, $reason, new \DateTimeImmutable('-31 days'));
    }

    private function aLiveAlias(string $username, string $userId): void
    {
        $this->saveAlias($username, $userId, UsernameAliasReason::USERNAME_CHANGED, new \DateTimeImmutable('-1 day'));
    }

    /**
     * La reserva dura 30 días, así que un alias nacido hace 31 ya está
     * caducado y uno nacido ayer sigue vigente. Se construye por donde lo
     * construye la aplicación —los dos constructores con nombre— para que la
     * prueba no invente una fila que el dominio nunca produciría.
     */
    private function saveAlias(
        string $username,
        ?string $userId,
        UsernameAliasReason $reason,
        \DateTimeImmutable $bornAt,
    ): void {
        /** @var UsernameAliasRepository $aliases */
        $aliases = self::getContainer()->get(UsernameAliasRepository::class);

        $alias = UsernameAliasReason::ACCOUNT_DELETED === $reason
            ? UsernameAlias::afterAccountDeletion(Username::fromString($username), $bornAt)
            : UsernameAlias::afterRename(
                Username::fromString($username),
                UserId::fromString($userId ?? throw new \LogicException('Un cambio de nombre tiene dueño.')),
                $bornAt,
            );

        $aliases->save($alias);

        $this->flush();
    }

    private function aliasExists(string $username): bool
    {
        /** @var UsernameAliasRepository $aliases */
        $aliases = self::getContainer()->get(UsernameAliasRepository::class);

        return null !== $aliases->ofUsername(Username::fromString($username));
    }

    private function flush(): void
    {
        /** @var \Doctrine\Persistence\ManagerRegistry $registry */
        $registry = self::getContainer()->get('doctrine');
        $registry->getManager()->flush();
    }
}
