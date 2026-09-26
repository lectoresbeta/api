<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Community;

use Doctrine\Persistence\ManagerRegistry;
use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use LectoresBeta\User\Account\Domain\Repository\UserRepository;
use LectoresBeta\User\Account\Domain\ValueObject\Email;
use Symfony\Component\HttpFoundation\Response;

/**
 * La cuenta con la que habla la plataforma (`FEAT-COM-038`).
 *
 * **Es una cuenta normal con una marca**, y esa decisión es toda la ficha.
 * Un tipo de publicación sin autor habría dejado una tarjeta que el muro
 * tendría que pintar de otra manera, y con ella una rama nueva en cada
 * consulta que hoy resuelve el autor.
 *
 * La consecuencia que más importa: **el muro no ha cambiado ni una línea**.
 * Una publicación de la plataforma llega a todo el mundo sin seguir a nadie
 * por la regla que ya existía desde `FEAT-COM-001` —lo compone la audiencia,
 * no el seguimiento—, y se comenta, se apoya y se repostea como cualquier
 * otra.
 */
final class PlatformAccountTest extends EconomyScenario
{
    /**
     * `RN-1`: lo que publica la plataforma llega a quien **no sigue a
     * nadie**, que es lo que responde a `H-8` de la Home.
     */
    public function testWhatThePlatformSaysReachesSomebodyWhoFollowsNobody(): void
    {
        $admin = $this->administrator('admin');
        $plataforma = $this->thePlatformAccount();
        $reciencita = $this->activatedPerson('reciencita');

        $postId = $this->publishAsPlatform($admin['token'], 'Bienvenidos a Lectores Beta');

        $this->client->request('GET', '/api/v1/posts', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$reciencita['token'],
        ]);
        self::assertResponseIsSuccessful();

        $found = null;

        foreach ($this->payload()['posts'] as $post) {
            if ($postId === $post['postId']) {
                $found = $post;
            }
        }

        self::assertNotNull($found, 'Sin seguir a nadie, la plataforma se ve igual.');
        self::assertSame($plataforma, $found['author']['userId'], 'Y firma la cuenta, no quien la administra.');
    }

    /**
     * `RN-4`: **quien administra firma la acción, no la tarjeta.** El autor
     * que se ve es la cuenta institucional.
     */
    public function testTheAdminIsNotTheAuthor(): void
    {
        $admin = $this->administrator('admin');
        $this->thePlatformAccount();

        $postId = $this->publishAsPlatform($admin['token'], 'Un aviso');

        $this->client->request('GET', '/api/v1/me/posts', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$admin['token'],
        ]);
        self::assertResponseIsSuccessful();

        $ids = array_map(
            static fn (array $post): string => (string) $post['postId'],
            $this->payload()['posts'],
        );

        self::assertNotContains($postId, $ids, 'No aparece en el muro de quien lo publicó.');
    }

    /**
     * `RN-5`: se comenta, se apoya y se repostea **como cualquier otra**. Es
     * lo que se gana modelándola como una cuenta.
     */
    public function testItBehavesLikeAnyOtherPost(): void
    {
        $admin = $this->administrator('admin');
        $this->thePlatformAccount();
        $lectora = $this->activatedPerson('lectora');

        $postId = $this->publishAsPlatform($admin['token'], 'Contadnos qué os parece');

        $this->client->request('POST', \sprintf('/api/v1/posts/%s/comments', $postId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$lectora['token'],
        ], content: json_encode(['body' => 'Me parece bien'], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->capture();

        $this->client->request('PUT', \sprintf('/api/v1/posts/%s/like', $postId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$lectora['token'],
        ]);
        self::assertResponseIsSuccessful();
        $this->capture();

        $this->client->request('POST', \sprintf('/api/v1/posts/%s/repost', $postId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$lectora['token'],
        ]);
        self::assertResponseIsSuccessful();
    }

    /**
     * `RN-3`: sin cuenta designada, se dice con claridad.
     *
     * Es un estado normal de una instalación recién puesta en marcha, no una
     * avería: a quien opera le falta ejecutar un comando, y un error genérico
     * le haría buscar en el sitio equivocado.
     */
    public function testWithoutADesignatedAccountItSaysSo(): void
    {
        $admin = $this->administrator('admin');

        $this->client->request('POST', '/api/v1/admin/platform-posts', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$admin['token'],
        ], content: json_encode(['body' => 'Un aviso'], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame('NO_PLATFORM_ACCOUNT', $this->payload()['code']);
    }

    /**
     * `RN-6`: **hablar con la voz de la plataforma no es moderar.** Un
     * moderador no llega, y una persona cualquiera tampoco.
     */
    public function testOnlyAnAdministratorMaySpeakForThePlatform(): void
    {
        $this->thePlatformAccount();
        $moderadora = $this->moderator('moderadora');
        $cualquiera = $this->activatedPerson('cualquiera');

        foreach ([$moderadora['token'], $cualquiera['token']] as $token) {
            $this->client->request('POST', '/api/v1/admin/platform-posts', server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ], content: json_encode(['body' => 'Un aviso'], \JSON_THROW_ON_ERROR));

            self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        }

        $this->client->request('POST', '/api/v1/admin/platform-posts');
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * `RN-7`: la audiencia **no se acepta del cuerpo**. Un anuncio de la
     * plataforma para seguidores no significa nada, porque a la cuenta
     * institucional no se la sigue para enterarse.
     */
    public function testTheAudienceCannotBeNarrowed(): void
    {
        $admin = $this->administrator('admin');
        $this->thePlatformAccount();

        $this->client->request('POST', '/api/v1/admin/platform-posts', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$admin['token'],
        ], content: json_encode([
            'body' => 'Un aviso',
            'audience' => 'FOLLOWERS',
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $postId = (string) $this->payload()['postId'];
        $this->capture();

        // Quien no sigue a la cuenta institucional: con `FOLLOWERS` no lo
        // vería, y lo ve, porque la audiencia la pone el servidor.
        $extrana = $this->activatedPerson('extrana');

        $this->client->request('GET', '/api/v1/posts', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$extrana['token'],
        ]);
        self::assertResponseIsSuccessful();

        $ids = array_map(
            static fn (array $post): string => (string) $post['postId'],
            $this->payload()['posts'],
        );

        self::assertContains($postId, $ids, 'Llega igual: la audiencia la pone el servidor.');
    }

    /**
     * Designa una cuenta como institucional, por el mismo camino que el
     * comando de consola.
     */
    private function thePlatformAccount(): string
    {
        $persona = $this->activatedPerson('lectoresbeta');

        /** @var UserRepository $users */
        $users = self::getContainer()->get(UserRepository::class);
        $account = $users->ofEmail(Email::fromString($this->address('lectoresbeta')));
        self::assertNotNull($account);
        $account->beInstitutional(true, new \DateTimeImmutable());

        /** @var ManagerRegistry $doctrine */
        $doctrine = self::getContainer()->get('doctrine');
        $doctrine->getManager()->flush();

        return $persona['userId'];
    }

    private function publishAsPlatform(string $token, string $body): string
    {
        $this->client->request('POST', '/api/v1/admin/platform-posts', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(['body' => $body], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->capture();

        return (string) $this->payload()['postId'];
    }
}
