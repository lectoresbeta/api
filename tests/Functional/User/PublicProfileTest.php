<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\User;

use Doctrine\Persistence\ManagerRegistry;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use LectoresBeta\User\Account\Domain\Repository\UserRepository;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Account\Domain\ValueObject\Username;
use LectoresBeta\User\Profile\Domain\Entity\UsernameAlias;
use LectoresBeta\User\Profile\Domain\Repository\UsernameAliasRepository;
use Symfony\Component\HttpFoundation\Response;

/**
 * El perfil ajeno (`FEAT-USR-014`) y cómo se llega a él (`FEAT-USR-035`).
 *
 * Lo que se defiende aquí es lo que el perfil **recorta**: nunca sale un
 * correo ni una fecha de nacimiento, una cuenta eliminada no tiene perfil, y
 * quien ha restringido el suyo responde lo mismo que alguien que no existe —
 * porque un `403` confirmaría que la cuenta está ahí, y ese ajuste existe
 * precisamente para no ser encontrado.
 */
final class PublicProfileTest extends EconomyScenario
{
    public function testAProfileShowsWhatIsPublicAndNothingElse(): void
    {
        $person = $this->namedPerson('persona', 'Ana García');

        $this->byId($person['userId']);

        self::assertResponseIsSuccessful();
        self::assertSame('Ana García', $this->payload()['name']);
        self::assertSame($person['userId'], $this->payload()['userId']);
        self::assertSame('USER_ID', $this->payload()['resolvedVia']);

        self::assertSame(
            ['userId', 'username', 'canonicalUsername', 'resolvedVia', 'name', 'description', 'avatarUrl', 'coverUrl'],
            array_keys($this->payload()),
        );
    }

    /**
     * `RN-1`: nunca el correo ni la fecha de nacimiento. Son datos privados y
     * no salen de su contexto ni siquiera hacia su propia API.
     */
    public function testAProfileNeverCarriesPrivateData(): void
    {
        $person = $this->namedPerson('persona', 'Ana García');

        $this->byId($person['userId']);

        $body = json_encode($this->payload(), \JSON_THROW_ON_ERROR);

        self::assertStringNotContainsString('@', $body, 'Ni rastro de un correo.');
        self::assertStringNotContainsString('1990', $body, 'Ni de una fecha de nacimiento.');
    }

    /**
     * **Público**: un perfil sin restringir es una URL que se comparte, y
     * exigir sesión para abrirla haría inútil compartirla.
     */
    public function testAProfileOpensWithoutASession(): void
    {
        $person = $this->namedPerson('persona', 'Ana García');

        $this->client->request('GET', \sprintf('/api/v1/users/%s', $person['userId']));

        self::assertResponseIsSuccessful();
    }

    public function testAProfileAlsoResolvesByUsername(): void
    {
        $person = $this->namedPerson('persona', 'Ana García');

        $this->byUsername($person['username']);

        self::assertResponseIsSuccessful();
        self::assertSame($person['userId'], $this->payload()['userId']);
        self::assertSame('USERNAME', $this->payload()['resolvedVia']);
        self::assertSame($person['username'], $this->payload()['canonicalUsername']);
    }

    /**
     * **Lo que mantiene vivos los enlaces compartidos** (`FEAT-USR-035`): un
     * nombre que su titular ya no usa sigue llevando a su perfil mientras el
     * alias esté vigente, y la respuesta dice cuál es el nombre de ahora para
     * que el cliente sustituya la URL.
     */
    public function testAnAliasStillLeadsToItsOwner(): void
    {
        $person = $this->namedPerson('persona', 'Ana García');
        $this->aliasFor($person['userId'], 'elnombredeantes', expired: false);

        $this->byUsername('elnombredeantes');

        self::assertResponseIsSuccessful();
        self::assertSame($person['userId'], $this->payload()['userId']);
        self::assertSame('ALIAS', $this->payload()['resolvedVia']);
        self::assertSame('elnombredeantes', $this->payload()['username']);
        self::assertSame($person['username'], $this->payload()['canonicalUsername'], 'Y dice cuál es el de ahora.');
    }

    /**
     * `RN-2`, la regla crítica: **solo resuelven los alias vigentes**. Si se
     * comprobara que la fila existe en lugar de que siga en plazo, un enlace
     * caducado funcionaría durante días — justo lo que el mes de reserva
     * pretende acotar.
     */
    public function testAnExpiredAliasLeadsNowhereEvenThoughItsRowIsStillThere(): void
    {
        $person = $this->namedPerson('persona', 'Ana García');
        $this->aliasFor($person['userId'], 'caducado', expired: true);

        $this->byUsername('caducado');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertSame('PROFILE_NOT_FOUND', $this->payload()['code']);
    }

    /**
     * `RN-9`: el alias de una cuenta eliminada **bloquea el nombre y nunca
     * resuelve**. No hay perfil al que llevar.
     */
    public function testTheAliasOfADeletedAccountBlocksTheNameAndLeadsNowhere(): void
    {
        $this->aliasOfADeletedAccount('sefue');

        $this->byUsername('sefue');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * `RN-7`: una cuenta eliminada está anonimizada. No queda nada que
     * enseñar, solo un identificador al que siguen apuntando correcciones y
     * movimientos de créditos.
     */
    public function testADeletedAccountHasNoProfile(): void
    {
        $person = $this->namedPerson('persona', 'Ana García');
        $this->anonymise($person['userId']);

        $this->byId($person['userId']);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * **El techo de `FEAT-USR-038`, que es lo que esta funcionalidad hace por
     * fin verdad.** Hasta ahora `profileVisibility` en `NOBODY` solo retiraba
     * a alguien del buscador; ahora su perfil responde igual que el de
     * alguien que no existe.
     */
    public function testARestrictedProfileAnswersLikeOneThatDoesNotExist(): void
    {
        $person = $this->namedPerson('persona', 'Ana García');

        $this->byId($person['userId']);
        self::assertResponseIsSuccessful('Antes se veía.');

        $this->restrict($person['token'], 'NOBODY');

        $this->byId($person['userId']);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertSame('PROFILE_NOT_FOUND', $this->payload()['code']);

        // Y por nombre, igual: no hay forma de confirmar que la cuenta está.
        $this->byUsername($person['username']);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * Su titular **siempre se ve a sí mismo**. Esconderle su propio perfil
     * sería absurdo, y es lo que permite deshacer el ajuste: quien lo cierra
     * sigue entrando a abrirlo.
     */
    public function testTheOwnerStillSeesTheirOwnRestrictedProfile(): void
    {
        $person = $this->namedPerson('persona', 'Ana García');
        $this->restrict($person['token'], 'NOBODY');

        $this->byId($person['userId'], $person['token']);

        self::assertResponseIsSuccessful();
        self::assertSame('Ana García', $this->payload()['name']);
    }

    /**
     * `FOLLOWERS` oculta hoy igual que `NOBODY`, y es la respuesta correcta
     * mientras nadie pueda seguir a nadie: el conjunto de seguidores está
     * vacío.
     */
    public function testFollowersHidesTheProfileWhileNobodyCanFollow(): void
    {
        $person = $this->namedPerson('persona', 'Ana García');
        $this->restrict($person['token'], 'FOLLOWERS');

        $this->byId($person['userId']);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testAnIdentifierThatIsNotOneAnswersLikeAProfileThatIsNotThere(): void
    {
        foreach (['lo-que-sea', '0192f000-0000-7000-8000-000000000000'] as $userId) {
            $this->byId($userId);

            self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND, $userId);
            self::assertSame('PROFILE_NOT_FOUND', $this->payload()['code']);
        }
    }

    public function testANameNobodyUsesAnswersTheSame(): void
    {
        $this->byUsername('nadieseallamaasi');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * `RN-8`: una cuenta sin activar puede consultar perfiles. Es solo
     * lectura, y bloquearla ahí no protege nada.
     */
    public function testAnUnactivatedAccountMayReadProfiles(): void
    {
        $person = $this->namedPerson('persona', 'Ana García');
        $sinActivar = $this->signedInWithoutActivating('pendiente');

        $this->byId($person['userId'], $sinActivar);

        self::assertResponseIsSuccessful();
    }

    /**
     * @return array{token: string, userId: string, username: string}
     */
    private function namedPerson(string $local, string $name): array
    {
        $person = $this->activatedPerson($local);

        $this->client->request('PUT', '/api/v1/me/onboarding/profile', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$person['token'],
        ], content: json_encode(['name' => $name, 'birthDate' => '1990-05-17'], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->capture();

        $this->client->request('GET', '/api/v1/me/onboarding', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$person['token'],
        ]);
        self::assertResponseIsSuccessful();

        return [...$person, 'username' => (string) $this->payload()['username']];
    }

    private function byId(string $userId, ?string $token = null): void
    {
        $this->client->request('GET', \sprintf('/api/v1/users/%s', $userId), server: null === $token
            ? []
            : ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);
    }

    private function byUsername(string $username, ?string $token = null): void
    {
        $this->client->request('GET', \sprintf('/api/v1/profiles/%s', $username), server: null === $token
            ? []
            : ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);
    }

    private function restrict(string $token, string $audience): void
    {
        $this->client->request('PUT', '/api/v1/me/privacy-settings', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(['profileVisibility' => $audience], \JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        $this->capture();
    }

    /**
     * El alias se escribe directamente aunque cambiarse de nombre ya tenga
     * endpoint (`FEAT-USR-034`): lo que esta prueba defiende es la
     * resolución, y hacerla pasar por el cambio la ataría a sus reglas —el
     * plazo de 30 días, el alias caducado— que aquí no vienen a cuento.
     */
    private function aliasFor(string $userId, string $username, bool $expired): void
    {
        $this->storeAlias(UsernameAlias::afterRename(
            Username::fromString($username),
            UserId::fromString($userId),
            new \DateTimeImmutable($expired ? '-40 days' : 'now'),
        ));
    }

    private function aliasOfADeletedAccount(string $username): void
    {
        $this->storeAlias(UsernameAlias::afterAccountDeletion(
            Username::fromString($username),
            new \DateTimeImmutable(),
        ));
    }

    private function storeAlias(UsernameAlias $alias): void
    {
        /** @var UsernameAliasRepository $aliases */
        $aliases = self::getContainer()->get(UsernameAliasRepository::class);
        $aliases->save($alias);

        /** @var ManagerRegistry $doctrine */
        $doctrine = self::getContainer()->get('doctrine');
        $doctrine->getManager()->flush();
    }

    private function anonymise(string $userId): void
    {
        /** @var UserRepository $users */
        $users = self::getContainer()->get(UserRepository::class);

        try {
            $user = $users->ofId(UserId::fromString($userId));
        } catch (InvalidValue) {
            self::fail('No existe esa cuenta.');
        }

        self::assertNotNull($user);
        $user->anonymise(new \DateTimeImmutable());
        $users->save($user);

        /** @var ManagerRegistry $doctrine */
        $doctrine = self::getContainer()->get('doctrine');
        $doctrine->getManager()->flush();
    }
}
