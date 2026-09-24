<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\User;

use Doctrine\ORM\EntityManagerInterface;
use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cambiar el nombre de usuario (`FEAT-USR-034`).
 *
 * **Lo que se defiende aquí no es el cambio: es lo que pasa con el nombre que
 * se deja.** No queda libre. Se reserva como alias de la cuenta durante 30
 * días, y la razón principal no es que los enlaces ya compartidos sigan
 * funcionando —que también— sino que **nadie pueda ocupar el nombre y heredar
 * el tráfico dirigido a otra persona**.
 *
 * De ahí el resto: el plazo de 30 días existe porque cada cambio bloquea un
 * nombre, y recuperar el propio se permite fuera de plazo porque
 * arrepentirse es el caso más previsible de todos.
 *
 * El tiempo se simula envejeciendo las filas, que es lo mismo que esperar un
 * mes y cabe en una prueba.
 */
final class ChangeUsernameTest extends EconomyScenario
{
    public function testChangingTheNameAssignsItAndReservesTheOldOne(): void
    {
        $person = $this->person('persona');

        $this->change($person['token'], 'nombrenuevo');

        self::assertResponseIsSuccessful();
        self::assertSame('nombrenuevo', $this->payload()['username']);
        self::assertSame($person['username'], $this->payload()['previousUsername']);

        $expiry = new \DateTimeImmutable((string) $this->payload()['aliasExpiresAt']);
        self::assertEqualsWithDelta(
            (new \DateTimeImmutable('+30 days'))->getTimestamp(),
            $expiry->getTimestamp(),
            60,
            'El alias caduca 30 días después.',
        );

        self::assertSame([$person['username']], $this->aliasesOf($person['userId']));
    }

    /**
     * Los dos nombres llevan al mismo perfil durante el mes, y la respuesta
     * dice cuál es el de ahora para que el cliente sustituya la URL.
     */
    public function testDuringThatMonthTheOldNameStillLeadsToTheSameProfile(): void
    {
        $person = $this->person('persona');

        $this->change($person['token'], 'nombrenuevo');

        $this->byUsername($person['username']);
        self::assertResponseIsSuccessful();
        self::assertSame($person['userId'], $this->payload()['userId']);
        self::assertSame('ALIAS', $this->payload()['resolvedVia']);
        self::assertSame('nombrenuevo', $this->payload()['canonicalUsername']);

        $this->byUsername('nombrenuevo');
        self::assertResponseIsSuccessful();
        self::assertSame('USERNAME', $this->payload()['resolvedVia']);
    }

    /**
     * **La razón de ser del alias.** Sin esto, cambiar de nombre sería
     * regalarle a quien esté mirando el nombre por el que otras personas
     * buscan a alguien.
     */
    public function testNobodyElseCanTakeTheNameWhileTheAliasHolds(): void
    {
        $person = $this->person('persona');
        $otra = $this->person('otra');

        $this->change($person['token'], 'nombrenuevo');

        $this->change($otra['token'], $person['username']);

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame('USERNAME_TAKEN', $this->payload()['code']);
    }

    /**
     * Un nombre en uso y uno retenido por un alias responden **lo mismo**, a
     * propósito: distinguirlos contaría que alguien tuvo ese nombre y lo dejó
     * hace menos de un mes, que es información sobre una persona y no sobre
     * la disponibilidad de una palabra.
     */
    public function testAnAliasAndANameInUseAnswerExactlyTheSame(): void
    {
        $person = $this->person('persona');
        $otra = $this->person('otra');

        $this->change($person['token'], 'nombrenuevo');

        $this->change($otra['token'], $person['username']);
        $porAlias = $this->payload();

        $this->change($otra['token'], 'nombrenuevo');
        $enUso = $this->payload();

        self::assertSame($enUso['code'], $porAlias['code']);
        self::assertSame($enUso['detail'], $porAlias['detail']);
        self::assertSame($enUso['status'], $porAlias['status']);
    }

    /**
     * `RN-1`. El límite no es una defensa contra el abuso añadida a última
     * hora: cada cambio bloquea un nombre 30 días, así que sin él una sola
     * cuenta podría retener tantos como quisiera.
     *
     * Es un `429` **con la fecha**, y esa es la diferencia con un conflicto:
     * un cliente que no pueda saberla dejará el formulario activo y permitirá
     * reintentar mañana, y pasado.
     */
    public function testASecondChangeBeforeThirtyDaysIsRefusedAndSaysWhen(): void
    {
        $person = $this->person('persona');
        $this->change($person['token'], 'nombrenuevo');

        $this->change($person['token'], 'otronombre');

        self::assertResponseStatusCodeSame(Response::HTTP_TOO_MANY_REQUESTS);
        self::assertSame('USERNAME_CHANGE_TOO_SOON', $this->payload()['code']);

        $disponible = new \DateTimeImmutable((string) $this->payload()['availableOn']);
        self::assertEqualsWithDelta(
            (new \DateTimeImmutable('+30 days'))->getTimestamp(),
            $disponible->getTimestamp(),
            60,
        );

        $this->myProfile($person['token']);
        self::assertSame('nombrenuevo', $this->payload()['username'], 'No se ha cambiado nada.');
    }

    /**
     * Y pasado el mes se puede otra vez. La fecha que el error anunciaba no
     * era decorativa.
     */
    public function testOnceThirtyDaysHavePassedTheNameCanChangeAgain(): void
    {
        $person = $this->person('persona');
        $this->change($person['token'], 'nombrenuevo');

        $this->ageBy($person['userId'], '31 days');

        $this->change($person['token'], 'otronombre');

        self::assertResponseIsSuccessful();
        self::assertSame('otronombre', $this->payload()['username']);
    }

    /**
     * `RN-6`: pasado el mes el nombre deja de resolver y vuelve a estar
     * disponible **aunque su fila siga ahí**. La purga (`FEAT-USR-036`) pasa
     * cuando pasa; la caducidad se comprueba al mirar.
     */
    public function testOnceTheAliasExpiresTheNameStopsResolvingAndIsFreeAgain(): void
    {
        $person = $this->person('persona');
        $otra = $this->person('otra');

        $this->change($person['token'], 'nombrenuevo');
        $abandonado = $person['username'];

        $this->ageBy($person['userId'], '31 days');

        $this->byUsername($abandonado);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $this->change($otra['token'], $abandonado);
        self::assertResponseIsSuccessful();
        self::assertSame($abandonado, $this->payload()['username']);

        self::assertSame([], $this->aliasesOf($person['userId']), 'La fila caducada ya no reserva nada.');
        self::assertSame([$otra['username']], $this->aliasesOf($otra['userId']), 'Y ahora el reservado es el suyo.');
    }

    /**
     * `RN-1b`. Arrepentirse de un cambio es el caso más previsible de esta
     * funcionalidad: mientras el alias siga vigente el nombre **sigue siendo
     * suyo**, así que no tiene que esperar por él.
     *
     * `RN-11`: al recuperarlo su fila desaparece —vuelve a ser el nombre de
     * la cuenta, y no puede ser las dos cosas— y el nombre abandonado ocupa
     * su lugar. Sigue habiendo un solo alias vigente.
     */
    public function testReclaimingYourOwnLiveAliasNeedsNoWaiting(): void
    {
        $person = $this->person('persona');
        $original = $person['username'];

        $this->change($person['token'], 'nombrenuevo');
        $this->change($person['token'], $original);

        self::assertResponseIsSuccessful();
        self::assertSame($original, $this->payload()['username']);
        self::assertSame('nombrenuevo', $this->payload()['previousUsername']);

        self::assertSame(['nombrenuevo'], $this->aliasesOf($person['userId']), 'Se permutan.');

        $this->byUsername($original);
        self::assertResponseIsSuccessful();
        self::assertSame('USERNAME', $this->payload()['resolvedVia']);

        $this->byUsername('nombrenuevo');
        self::assertResponseIsSuccessful();
        self::assertSame('ALIAS', $this->payload()['resolvedVia']);
    }

    /**
     * Recuperar **esquiva el plazo pero lo renueva**, y el plazo renovado
     * vale para **cualquier** cambio: uno normal y otra recuperación.
     *
     * La segunda mitad es la que cuesta, y esta prueba existe por ella. Cada
     * recuperación deja como alias el nombre que se abandona, así que sin una
     * regla explícita la vuelta siguiente volvería a ser una recuperación y
     * el plazo no se aplicaría nunca: se podría alternar entre dos nombres
     * indefinidamente, rompiendo en cada vuelta los enlaces que el plazo
     * existe para proteger.
     */
    public function testAfterReclaimingThereAreAnotherThirtyDaysToWait(): void
    {
        $person = $this->person('persona');
        $original = $person['username'];

        $this->change($person['token'], 'nombrenuevo');
        $this->change($person['token'], $original);
        self::assertResponseIsSuccessful();

        // Un cambio cualquiera: el plazo corriente.
        $this->change($person['token'], 'otronombre');
        self::assertResponseStatusCodeSame(Response::HTTP_TOO_MANY_REQUESTS);
        self::assertSame('USERNAME_CHANGE_TOO_SOON', $this->payload()['code']);

        // Y volver a lo anterior, que sigue siendo un alias propio vigente:
        // deshacer un «deshacer» ya es alternar, y no se permite.
        $this->change($person['token'], 'nombrenuevo');
        self::assertResponseStatusCodeSame(Response::HTTP_TOO_MANY_REQUESTS);
        self::assertSame('USERNAME_CHANGE_TOO_SOON', $this->payload()['code']);

        self::assertSame($original, $this->currentUsername($person['token']), 'Sigue con el recuperado.');
    }

    /**
     * Pasado el mes sí, claro: lo que se bloquea es el vaivén, no el derecho
     * a cambiar de nombre.
     */
    public function testOnceThatMonthPassesChangingWorksAgainAfterAReclaim(): void
    {
        $person = $this->person('persona');
        $original = $person['username'];

        $this->change($person['token'], 'nombrenuevo');
        $this->change($person['token'], $original);

        $this->ageBy($person['userId'], '31 days');

        $this->change($person['token'], 'otronombre');

        self::assertResponseIsSuccessful();
        self::assertSame('otronombre', $this->payload()['username']);
    }

    /**
     * `RN-12`: la excepción es para **alias propios**. El de otra persona es
     * un nombre ocupado como cualquier otro, y responde como tal.
     */
    public function testReclaimingSomebodyElsesAliasIsJustATakenName(): void
    {
        $person = $this->person('persona');
        $otra = $this->person('otra');

        $this->change($person['token'], 'nombrenuevo');

        $this->change($otra['token'], $person['username']);

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame('USERNAME_TAKEN', $this->payload()['code']);
        self::assertSame([$person['username']], $this->aliasesOf($person['userId']), 'Sigue siendo suyo.');
    }

    /**
     * `RN-12`, la otra mitad: un alias propio **caducado** ya no es suyo de
     * ninguna forma especial, así que recuperarlo es un cambio normal y el
     * plazo se aplica entero.
     */
    public function testAnExpiredOwnAliasGivesNoRightToSkipTheLimit(): void
    {
        $person = $this->person('persona');
        $original = $person['username'];

        $this->change($person['token'], 'nombrenuevo');
        $this->expireAliasesOf($person['userId']);

        $this->change($person['token'], $original);

        self::assertResponseStatusCodeSame(Response::HTTP_TOO_MANY_REQUESTS);
        self::assertSame('USERNAME_CHANGE_TOO_SOON', $this->payload()['code']);
    }

    /**
     * `FEAT-USR-033` `RN-5`. Un nombre reservado **sí** se distingue de uno
     * ocupado: ahí no hay nadie a quien proteger, y quien lo pide merece
     * saber que no es cuestión de esperar.
     */
    public function testAReservedNameIsRefusedAndSaysSo(): void
    {
        $person = $this->person('persona');

        $this->change($person['token'], 'soporte');

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('USERNAME_RESERVED', $this->payload()['code']);
    }

    public function testTheFormatIsTheSameAsTheAssignedOne(): void
    {
        $person = $this->person('persona');

        foreach (['ab', 'Con Mayúsculas', 'con-guion', str_repeat('a', 31)] as $invalido) {
            $this->change($person['token'], $invalido);

            self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY, $invalido);
            self::assertSame('INVALID_VALUE', $this->payload()['code'], $invalido);
        }
    }

    /**
     * Pedir el nombre que ya se tiene **no es un cambio**: guardar dos veces
     * el formulario sin tocar este campo no puede costar treinta días.
     */
    public function testAskingForTheNameYouAlreadyHaveCostsNothing(): void
    {
        $person = $this->person('persona');

        $this->change($person['token'], $person['username']);

        self::assertResponseIsSuccessful();
        self::assertSame($person['username'], $this->payload()['username']);
        self::assertNull($this->payload()['previousUsername']);
        self::assertNull($this->payload()['aliasExpiresAt'], 'Nadie es alias de sí mismo.');
        self::assertSame([], $this->aliasesOf($person['userId']));

        // Y el cupo sigue entero.
        $this->change($person['token'], 'nombrenuevo');
        self::assertResponseIsSuccessful();
    }

    /**
     * `changeableOn` va **siempre**, también cuando no ha habido cambio, y va
     * además en la lectura que abre la pantalla: un formulario que solo puede
     * saber si está bloqueado fallando es un formulario que falla.
     */
    public function testTheScreenCanTellWhenItWillBePossibleWithoutFailingFirst(): void
    {
        $person = $this->person('persona');

        $this->myProfile($person['token']);
        self::assertLessThanOrEqual(
            (new \DateTimeImmutable())->getTimestamp() + 5,
            (new \DateTimeImmutable((string) $this->payload()['usernameChangeableOn']))->getTimestamp(),
            'Quien nunca lo ha cambiado puede hacerlo ya.',
        );

        $this->change($person['token'], 'nombrenuevo');
        $trasElCambio = new \DateTimeImmutable((string) $this->payload()['changeableOn']);

        $this->myProfile($person['token']);
        self::assertSame(
            $trasElCambio->getTimestamp(),
            (new \DateTimeImmutable((string) $this->payload()['usernameChangeableOn']))->getTimestamp(),
            'La misma fecha en los dos sitios.',
        );
    }

    /**
     * `RN-8`, que es lo que hace que todo esto sea seguro: el nombre de
     * usuario es **presentación y enrutado, nunca identidad**. Si algo lo
     * usara como clave, se rompería con el primer cambio.
     */
    public function testChangingTheNameDoesNotChangeWhoYouAre(): void
    {
        $person = $this->person('persona');

        $this->change($person['token'], 'nombrenuevo');

        $this->myProfile($person['token']);
        self::assertSame($person['userId'], $this->payload()['userId'], 'El mismo identificador.');

        $this->client->request('GET', \sprintf('/api/v1/users/%s', $person['userId']));
        self::assertResponseIsSuccessful();
        self::assertSame('nombrenuevo', $this->payload()['username'], 'Y el perfil de siempre, con el nombre nuevo.');
    }

    /**
     * `RN-10`. Lleva los dos nombres y la caducidad: el viejo para encontrar
     * lo que hay que actualizar, el nuevo para escribirlo, y la fecha para
     * saber hasta cuándo un enlace antiguo seguirá llevando a alguna parte.
     */
    public function testTheChangeIsAnnounced(): void
    {
        $person = $this->person('persona');

        $this->change($person['token'], 'nombrenuevo');

        $anunciado = $this->lastAnnouncementOf('UsernameChanged');

        self::assertSame($person['userId'], $anunciado['userId']);
        self::assertSame($person['username'], $anunciado['previousUsername']);
        self::assertSame('nombrenuevo', $anunciado['newUsername']);
        self::assertNotEmpty($anunciado['aliasExpiresAt']);
    }

    /**
     * Recuperar publica **el mismo evento**: para quien lo consume es un
     * cambio más, y darle dos hechos para la misma consecuencia solo le
     * obligaría a tratarlos igual.
     */
    public function testReclaimingAnnouncesTheSameFact(): void
    {
        $person = $this->person('persona');
        $original = $person['username'];

        $this->change($person['token'], 'nombrenuevo');
        $this->change($person['token'], $original);

        $anunciado = $this->lastAnnouncementOf('UsernameChanged');

        self::assertSame('nombrenuevo', $anunciado['previousUsername']);
        self::assertSame($original, $anunciado['newUsername']);
    }

    /**
     * `RN-9` y [`decision:0003`](../../../../docs/decisions/0003-write-operations-require-activated-account.md):
     * el nombre de usuario es lo que aparece en cada comentario y en cada
     * URL, y dejar elegirlo a una cuenta cuyo correo nadie ha verificado es
     * exactamente lo que esa regla evita.
     */
    public function testAnUnactivatedAccountCannotChangeItsUsername(): void
    {
        $token = $this->signedInWithoutActivating('pendiente');

        $this->change($token, 'nombrenuevo');

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame('ACCOUNT_NOT_ACTIVATED', $this->payload()['code']);
    }

    public function testWithoutASessionThereIsNoNameToChange(): void
    {
        $this->client->request('PUT', '/api/v1/me/username', server: [
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode(['username' => 'nombrenuevo'], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @return array{token: string, userId: string, username: string}
     */
    private function person(string $local): array
    {
        $person = $this->activatedPerson($local);

        $this->myProfile($person['token']);
        self::assertResponseIsSuccessful();

        return [...$person, 'username' => (string) $this->payload()['username']];
    }

    private function change(string $token, string $username): void
    {
        $this->client->request('PUT', '/api/v1/me/username', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(['username' => $username], \JSON_THROW_ON_ERROR));

        $this->capture();
    }

    private function currentUsername(string $token): string
    {
        $this->myProfile($token);
        self::assertResponseIsSuccessful();

        return (string) $this->payload()['username'];
    }

    private function myProfile(string $token): void
    {
        $this->client->request('GET', '/api/v1/me/profile', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
    }

    private function byUsername(string $username): void
    {
        $this->client->request('GET', \sprintf('/api/v1/profiles/%s', $username));
    }

    /**
     * Los alias vigentes de alguien, que con los plazos de hoy son como mucho
     * uno — pero el modelo no asume ese máximo (`RN-7`), así que se miran
     * todos.
     *
     * @return list<string>
     */
    private function aliasesOf(string $userId): array
    {
        /** @var list<array{username: string}> $rows */
        $rows = $this->entityManager()->getConnection()->fetchAllAssociative(
            'SELECT username FROM user_ctx.username_alias WHERE user_id = :user AND expires_at > now() ORDER BY username',
            ['user' => $userId],
        );

        return array_map(static fn (array $row): string => $row['username'], $rows);
    }

    /**
     * Envejecer la cuenta y sus alias es lo mismo que esperar un mes, y cabe
     * en una prueba. No hay reloj falso: la fecha se escribe donde está.
     */
    private function ageBy(string $userId, string $interval): void
    {
        $connection = $this->entityManager()->getConnection();

        $connection->executeStatement(
            \sprintf('UPDATE user_ctx.account SET username_changed_at = username_changed_at - INTERVAL \'%s\' WHERE id = :user', $interval),
            ['user' => $userId],
        );
        $connection->executeStatement(
            \sprintf('UPDATE user_ctx.username_alias SET created_at = created_at - INTERVAL \'%1$s\', expires_at = expires_at - INTERVAL \'%1$s\' WHERE user_id = :user', $interval),
            ['user' => $userId],
        );

        $this->entityManager()->clear();
    }

    /**
     * Caducar solo los alias, dejando la cuenta recién cambiada: es el caso
     * que separa «este nombre fue mío» de «este nombre sigue siendo mío».
     */
    private function expireAliasesOf(string $userId): void
    {
        $this->entityManager()->getConnection()->executeStatement(
            "UPDATE user_ctx.username_alias SET expires_at = now() - INTERVAL '1 day' WHERE user_id = :user",
            ['user' => $userId],
        );

        $this->entityManager()->clear();
    }

    private function entityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');

        return $entityManager;
    }
}
