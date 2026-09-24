<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Community;

use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use Symfony\Component\HttpFoundation\Response;

/**
 * Seguir a un autor (`FEAT-COM-010`), el primer endpoint de `Community`.
 *
 * Lo que más se defiende aquí es la **idempotencia**, que no es una
 * comodidad: el botón «Seguir» se pulsa dos veces por error, la red repite
 * una petición, y en los tres sitios donde aparece la acción —perfil,
 * onboarding y Home— es la misma. Si seguir dos veces fallara, el cliente
 * tendría que recordar el estado para no equivocarse, y lo que el usuario
 * pidió —seguirle— ya se cumple.
 *
 * Y lo segundo: **seguir no concede nada**. Lo que cambia son las audiencias,
 * y eso se prueba donde viven, en `PrivacySettingsTest` y `PublicProfileTest`.
 */
final class AuthorSubscriptionTest extends EconomyScenario
{
    public function testFollowingSomebodyAndAskingAboutIt(): void
    {
        $autora = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');

        $this->subscription($lectora['token'], $autora['userId']);
        self::assertResponseIsSuccessful();
        self::assertFalse($this->payload()['subscribed'], 'Antes de nada, no le sigue.');
        self::assertNull($this->payload()['subscribedAt']);

        $this->follow($lectora['token'], $autora['userId']);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->subscription($lectora['token'], $autora['userId']);
        self::assertTrue($this->payload()['subscribed']);
        self::assertNotNull($this->payload()['subscribedAt']);
    }

    /**
     * `RN-1`. Seguir a quien ya sigues deja el mundo igual: ni una segunda
     * fila, ni un segundo hecho, ni una respuesta distinta.
     */
    public function testFollowingTwiceChangesNothing(): void
    {
        $autora = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');

        $this->follow($lectora['token'], $autora['userId']);
        $primera = $this->client->getResponse()->getStatusCode();

        $this->follow($lectora['token'], $autora['userId']);

        self::assertSame($primera, $this->client->getResponse()->getStatusCode());

        // Recogido de la cola y no del transporte, que se vacía con cada
        // petición: lo que se afirma es que en total hubo **un** hecho.
        self::assertCount(1, $this->queued('AuthorSubscribed'), 'Un solo hecho.');
    }

    /**
     * `RN-4`: el estado que se pedía —no seguirle— ya se cumple, así que no
     * hay nada que devolver como error.
     */
    public function testUnfollowingSomebodyYouDoNotFollowIsNotAFailure(): void
    {
        $autora = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');

        $this->unfollow($lectora['token'], $autora['userId']);

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertSame([], $this->announced('AuthorUnsubscribed'), 'Y no se anuncia nada: no ha cambiado nada.');
    }

    public function testUnfollowingUndoesIt(): void
    {
        $autora = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');

        $this->follow($lectora['token'], $autora['userId']);
        $this->unfollow($lectora['token'], $autora['userId']);

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->subscription($lectora['token'], $autora['userId']);
        self::assertFalse($this->payload()['subscribed']);
    }

    /**
     * `RN-8`: volver a seguir es una suscripción nueva con su fecha nueva. No
     * hay histórico, y no hace falta: lo que importa es si ahora le sigue.
     */
    public function testFollowingAgainAfterUnfollowingWorks(): void
    {
        $autora = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');

        $this->follow($lectora['token'], $autora['userId']);
        $this->unfollow($lectora['token'], $autora['userId']);
        $this->follow($lectora['token'], $autora['userId']);

        $this->subscription($lectora['token'], $autora['userId']);
        self::assertTrue($this->payload()['subscribed']);
    }

    /**
     * `RN-2`. Es una invariante del agregado, no una comprobación del
     * controlador: da igual por qué puerta llegue.
     */
    public function testNobodyFollowsThemselves(): void
    {
        $lectora = $this->activatedPerson('lectora');

        $this->follow($lectora['token'], $lectora['userId']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('CANNOT_SUBSCRIBE_TO_YOURSELF', $this->payload()['code']);
    }

    /**
     * `RN-3`: se pregunta por el **contrato publicado** de `User`, no leyendo
     * sus tablas. Sin esa comprobación, un identificador inventado crearía
     * una suscripción a nadie y `User` proyectaría un seguidor de un autor
     * inexistente.
     */
    public function testFollowingSomebodyWhoDoesNotExistIsRefused(): void
    {
        $lectora = $this->activatedPerson('lectora');

        foreach (['0192f000-0000-7000-8000-000000000000', 'no-es-un-identificador'] as $desconocido) {
            $this->follow($lectora['token'], $desconocido);

            self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND, $desconocido);
            self::assertSame('USER_NOT_FOUND', $this->payload()['code'], $desconocido);
        }
    }

    /**
     * Preguntar por alguien que no existe responde `false`, no `404`: la
     * pregunta es sobre **mi** relación con él y «no le sigo» es cierto. Un
     * `404` contaría además si esa cuenta existe a cualquiera que pruebe
     * identificadores.
     */
    public function testAskingAboutSomebodyWhoDoesNotExistIsNotAnError(): void
    {
        $lectora = $this->activatedPerson('lectora');

        $this->subscription($lectora['token'], '0192f000-0000-7000-8000-000000000000');

        self::assertResponseIsSuccessful();
        self::assertFalse($this->payload()['subscribed']);
    }

    /**
     * `RN-6`. Los dos hechos, y con **los dos identificadores y nada más**:
     * quien los consume tiene su propia copia de las personas, y copiar aquí
     * un nombre solo añadiría un sitio donde envejece.
     */
    public function testBothHalvesAreAnnounced(): void
    {
        $autora = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');

        $this->follow($lectora['token'], $autora['userId']);

        $seguido = $this->lastAnnouncementOf('AuthorSubscribed');
        self::assertSame($lectora['userId'], $seguido['subscriberId']);
        self::assertSame($autora['userId'], $seguido['authorId']);
        self::assertSame(['subscriberId', 'authorId', 'subscribedAt'], array_keys($seguido));

        $this->unfollow($lectora['token'], $autora['userId']);

        $dejado = $this->lastAnnouncementOf('AuthorUnsubscribed');
        self::assertSame($lectora['userId'], $dejado['subscriberId']);
        self::assertSame($autora['userId'], $dejado['authorId']);
    }

    /**
     * `RN-5` y [`decision:0003`](../../../../docs/decisions/0003-write-operations-require-activated-account.md):
     * seguir es escribir, y además deja rastro en el perfil de otra persona.
     */
    public function testAnUnactivatedAccountCannotFollowButCanAsk(): void
    {
        $autora = $this->activatedPerson('autora');
        $token = $this->signedInWithoutActivating('pendiente');

        $this->follow($token, $autora['userId']);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame('ACCOUNT_NOT_ACTIVATED', $this->payload()['code']);

        $this->subscription($token, $autora['userId']);
        self::assertResponseIsSuccessful();
        self::assertFalse($this->payload()['subscribed']);
    }

    public function testWithoutASessionThereIsNobodyToFollow(): void
    {
        $autora = $this->activatedPerson('autora');
        $path = \sprintf('/api/v1/users/%s/subscription', $autora['userId']);

        foreach (['GET', 'PUT', 'DELETE'] as $method) {
            $this->client->request($method, $path);
            self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED, $method);
        }
    }

    private function follow(string $token, string $userId): void
    {
        $this->client->request('PUT', \sprintf('/api/v1/users/%s/subscription', $userId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        $this->capture();
    }

    private function unfollow(string $token, string $userId): void
    {
        $this->client->request('DELETE', \sprintf('/api/v1/users/%s/subscription', $userId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        $this->capture();
    }

    private function subscription(string $token, string $userId): void
    {
        $this->client->request('GET', \sprintf('/api/v1/users/%s/subscription', $userId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
    }
}
