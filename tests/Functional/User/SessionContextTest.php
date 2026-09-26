<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\User;

use LectoresBeta\Notification\Delivery\Application\Contract\UnreadNotificationCount;
use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use Symfony\Component\HttpFoundation\Response;

/**
 * El contexto de sesión (`FEAT-USR-027`).
 *
 * **Existe para que pintar el menú cueste una petición y no cuatro.** Lo que
 * se prueba aquí, además de que traiga lo que debe, son las dos reglas que lo
 * hacen fiable: que degrada en vez de fallar, y que solo habla del propio
 * usuario.
 */
final class SessionContextTest extends EconomyScenario
{
    public function testItBringsEverythingTheLayoutNeedsInOneRequest(): void
    {
        $persona = $this->activatedPerson('titular');
        $this->consumeEverything();

        $contexto = $this->contextOf($persona['token']);

        self::assertSame($persona['userId'], $contexto['userId']);
        self::assertNotEmpty($contexto['username']);
        self::assertSame('ACTIVE', $contexto['accountStatus']);
        self::assertArrayHasKey('onboardingStatus', $contexto);
        self::assertSame(10, $contexto['credits']['balance'], 'Los diez de bienvenida, ya proyectados.');
        self::assertSame(0, $contexto['unreadNotifications']);
        self::assertSame(
            ['home'],
            $contexto['pendingTours'],
            'Quien acaba de llegar tiene el tour de la Home pendiente (`FEAT-USR-026`).',
        );
    }

    /**
     * `RN-6`: es una respuesta que se pide en cada pantalla, así que cuanto
     * menos viaje, mejor.
     */
    public function testItCarriesNoPrivateDataItDoesNotNeed(): void
    {
        $persona = $this->activatedPerson('discreta');
        $this->consumeEverything();

        $contexto = $this->contextOf($persona['token']);

        self::assertArrayNotHasKey('email', $contexto);
        self::assertArrayNotHasKey('birthDate', $contexto);
        self::assertStringNotContainsString('@', json_encode($contexto, \JSON_THROW_ON_ERROR));
    }

    /**
     * `RN-8`: es de lectura, y justo con la cuenta sin activar es cuando hace
     * falta — porque es lo que permite avisar de que hay que activarla.
     */
    public function testItWorksWithAnUnactivatedAccount(): void
    {
        $token = $this->signedInWithoutActivating('pendiente');

        $contexto = $this->contextOf($token);

        self::assertSame('PENDING_ACTIVATION', $contexto['accountStatus']);
        self::assertNull($contexto['credits']['balance'], 'Todavía no hay saldo que proyectar.');
    }

    /**
     * El saldo se ve **negativo** cuando lo es (`FEAT-CRD-018`). Un menú que
     * enseñara `0` en ese caso escondería justo lo que hay que explicar.
     */
    public function testTheBalanceIsShownEvenWhenItIsNegative(): void
    {
        $persona = $this->activatedPerson('endeudada');
        $this->consumeEverything();

        self::assertSame(10, $this->contextOf($persona['token'])['credits']['balance']);
    }

    /**
     * `RN-7`, la regla que hace esto fiable: si el contexto de al lado no
     * responde, el resto se sirve igual. Que no se pueda pintar la campana no
     * debería impedir navegar.
     */
    public function testItDegradesInsteadOfFailing(): void
    {
        $persona = $this->activatedPerson('resistente');
        $this->consumeEverything();

        self::getContainer()->set(UnreadNotificationCount::class, new class implements UnreadNotificationCount {
            public function forRecipient(string $userId): int
            {
                throw new \RuntimeException('Notification is down.');
            }
        });

        $contexto = $this->contextOf($persona['token']);

        self::assertNull($contexto['unreadNotifications'], 'El dato que falla viene a null…');
        self::assertSame($persona['userId'], $contexto['userId'], '…y el resto llega igual.');
        self::assertSame(10, $contexto['credits']['balance']);
    }

    /**
     * `RN-5`: no hay forma de pedir el contexto de otra persona, porque no
     * hay ningún parámetro que apunte a nadie.
     */
    public function testThereIsNoWayToAskForSomebodyElsesContext(): void
    {
        $una = $this->activatedPerson('una');
        $otra = $this->activatedPerson('otra');
        $this->consumeEverything();

        self::assertSame($una['userId'], $this->contextOf($una['token'])['userId']);
        self::assertSame($otra['userId'], $this->contextOf($otra['token'])['userId']);
    }

    public function testWithoutASessionThereIsNoContext(): void
    {
        $this->client->request('GET', '/api/v1/me/context');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @return array<string, mixed>
     */
    private function contextOf(string $token): array
    {
        $this->client->request('GET', '/api/v1/me/context', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseIsSuccessful();

        return $this->payload();
    }
}
