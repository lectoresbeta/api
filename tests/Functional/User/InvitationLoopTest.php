<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\User;

use LectoresBeta\Credits\Account\Domain\ValueObject\UserId as CreditsUserId;
use LectoresBeta\Credits\Referral\Domain\Repository\ReferralRepository;
use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use LectoresBeta\User\Invitation\Application\Handler\InvitePersonToThePlatformHandler;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Email as MimeEmail;

/**
 * El bucle de invitación entero (`FEAT-USR-018`, `FEAT-NOT-007`,
 * `FEAT-CRD-005`).
 *
 * Tres fichas y **un solo mecanismo**: invitar, el correo, el alta con el
 * enlace, la primera corrección, el abono. Probarlas por separado dejaría sin
 * comprobar justo lo que las une, que es el recorrido de un token y un par de
 * identificadores a través de tres contextos que no se conocen.
 *
 * Lo que más defienden estas pruebas, por orden:
 *
 * - que **invitar no diga quién tiene cuenta**;
 * - que el **token no viaje en la cola**, sino que se pida al mandar;
 * - que el abono llegue **con la primera corrección y no antes**, que es todo
 *   el diseño antifraude (`decision:0006`, regla 6);
 * - y que llegue **una sola vez**.
 */
final class InvitationLoopTest extends EconomyScenario
{
    /**
     * El recorrido completo, de una punta a la otra.
     *
     * `RN-1` de las tres fichas a la vez: se invita, sale un correo con un
     * enlace, quien lo usa queda apuntado, y **al entregar su primera
     * corrección** el invitador cobra cinco créditos.
     */
    public function testTheWholeLoopFromInvitingToBeingPaid(): void
    {
        $invitadora = $this->activatedPerson('invitadora');
        $this->consumeEverything();
        $balanceInicial = $this->balanceOf($invitadora['userId']);

        $invitada = $this->address('invitada');
        $token = $this->inviteAndReadTheLink($invitadora['token'], $invitada);

        self::assertNotNull($token, 'El correo trae un enlace con token.');
        self::assertNull(
            $this->referralOf($invitadora['userId']),
            'Antes del alta no hay nada apuntado en `Credits`.',
        );

        // El alta con el enlace: apunta el par y **no paga**.
        $lectora = $this->activatedPerson('invitada', $token);
        $this->consumeEverything();

        self::assertSame(
            $invitadora['userId'],
            $this->referralOf($lectora['userId']),
            'El par queda apuntado al registrarse.',
        );
        self::assertSame(
            $balanceInicial,
            $this->balanceOf($invitadora['userId']),
            'Y registrarse no mueve un solo crédito: pagar aquí valdría lo que cuesta un correo desechable.',
        );

        // Y la primera corrección de la invitada, que es lo que paga.
        $this->aCorrectionDeliveredBy($lectora);

        self::assertSame(
            $balanceInicial + 5,
            $this->balanceOf($invitadora['userId']),
            'Cinco créditos, cuando el invitado entrega trabajo de verdad.',
        );

        $abono = $this->lastAnnouncementOf('CreditsAdded');
        self::assertSame('INVITATION_REWARD', $abono['reason']);
        self::assertSame($invitadora['userId'], $abono['userId']);
    }

    /**
     * `FEAT-USR-018` `RN-2`: invitar a una dirección que **ya tiene cuenta**
     * responde exactamente igual que invitar a una que no, y no manda nada.
     *
     * Es la prueba que impide que el formulario de invitar se convierta en un
     * comprobador de quién está dentro, que es justo lo que el alta y la
     * recuperación de contraseña se cuidan de no decir.
     */
    public function testInvitingSaysNothingAboutWhoHasAnAccount(): void
    {
        $invitadora = $this->activatedPerson('invitadora');
        $yaDentro = $this->activatedPerson('yadentro');

        $this->invite($invitadora['token'], $this->address('nadie'));
        $aNadie = $this->client->getResponse();
        $this->consumeEverything();
        self::assertCount(1, self::getMailerMessages(), 'A quien no tiene cuenta le llega su invitación.');

        $this->invite($invitadora['token'], $this->address('yadentro'));
        $aQuienEsta = $this->client->getResponse();
        $this->consumeEverything();

        self::assertSame($aNadie->getStatusCode(), $aQuienEsta->getStatusCode());
        self::assertSame($aNadie->getContent(), $aQuienEsta->getContent());
        self::assertCount(
            0,
            self::getMailerMessages(),
            'La diferencia está donde no se ve: al que ya tiene cuenta no le llega nada.',
        );

        self::assertNotSame('', $yaDentro['userId']);
    }

    /**
     * `FEAT-NOT-007` `RN-3`: **el token no viaja en el hecho.**.
     *
     * Es la regla de `AGENTS.md` que más caro sale saltarse: una credencial
     * viva en una cola que persiste, reintenta y aparca mensajes acaba en un
     * fichero de mensajes muertos que nadie considera secreto.
     */
    public function testTheFactTravelsWithoutTheToken(): void
    {
        $invitadora = $this->activatedPerson('invitadora');

        $this->invite($invitadora['token'], $this->address('invitada'));

        $hecho = $this->lastAnnouncementOf('PlatformInvitationSent');

        self::assertArrayNotHasKey('token', $hecho);
        self::assertArrayNotHasKey('tokenHash', $hecho);
        self::assertSame($invitadora['userId'], $hecho['inviterId']);
        self::assertNotSame('', (string) $hecho['invitationId']);
    }

    /**
     * `FEAT-USR-018` `RN-4`: el tope diario.
     *
     * Sin él, invitar es un canal de correo gratuito hacia direcciones ajenas
     * con el remitente de la plataforma: el tipo de cosa que quema un dominio
     * en una tarde.
     */
    public function testTheDailyCapRefusesTheOneTooMany(): void
    {
        $invitadora = $this->activatedPerson('invitadora');

        for ($i = 0; $i < InvitePersonToThePlatformHandler::DAILY_LIMIT; ++$i) {
            $this->invite($invitadora['token'], $this->address('destino'.$i));
            self::assertResponseStatusCodeSame(Response::HTTP_ACCEPTED);
        }

        $this->invite($invitadora['token'], $this->address('unamas'));
        self::assertResponseStatusCodeSame(Response::HTTP_TOO_MANY_REQUESTS);
    }

    /**
     * `FEAT-USR-018` `RN-3`: nadie se invita a sí mismo.
     */
    public function testInvitingYourselfIsRefused(): void
    {
        $invitadora = $this->activatedPerson('invitadora');

        $this->invite($invitadora['token'], $this->address('invitadora'));

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('CANNOT_INVITE_YOURSELF', $this->payload()['code']);
    }

    /**
     * `FEAT-USR-018` `RN-5`: reinvitar a quien ya invitaste **no crea una
     * segunda invitación**. La primera sigue valiendo, y dos correos iguales
     * son spam con buena intención.
     */
    public function testInvitingTheSamePersonTwiceSendsOneEmail(): void
    {
        $invitadora = $this->activatedPerson('invitadora');
        $invitada = $this->address('invitada');

        $this->invite($invitadora['token'], $invitada);
        $this->consumeEverything();
        self::assertCount(1, self::getMailerMessages());

        $this->invite($invitadora['token'], $invitada);
        self::assertResponseStatusCodeSame(Response::HTTP_ACCEPTED);
        $this->consumeEverything();

        self::assertCount(0, self::getMailerMessages(), 'La segunda no manda nada: la primera sigue valiendo.');

        $this->client->request('GET', '/api/v1/me/invitations', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$invitadora['token'],
        ]);
        self::assertCount(1, $this->payload()['invitations']);
    }

    /**
     * `FEAT-USR-001` `RN-13`: un token inválido **no impide el alta**.
     *
     * Quien llega con un enlace viejo viene a registrarse, no a canjear nada,
     * y rechazar su cuenta por eso sería castigarle por un detalle que no
     * controla.
     */
    public function testAnInvalidInvitationTokenDoesNotBlockTheRegistration(): void
    {
        $lectora = $this->activatedPerson('llegatarde', 'esto-no-es-un-token');
        $this->consumeEverything();

        self::assertNotSame('', $lectora['userId']);
        self::assertNull($this->referralOf($lectora['userId']));
    }

    /**
     * `FEAT-CRD-005` `RN-3`: **una vez por persona invitada, para siempre.**.
     *
     * La segunda corrección de la invitada llega con un evento distinto y
     * legítimo, así que deduplicar por identificador de evento no la cubre.
     * Lo que la cubre es la marca en la fila del par.
     */
    public function testTheRewardIsPaidOnceHoweverManyCorrectionsFollow(): void
    {
        $invitadora = $this->activatedPerson('invitadora');
        $token = $this->inviteAndReadTheLink($invitadora['token'], $this->address('invitada'));
        self::assertNotNull($token);

        $lectora = $this->activatedPerson('invitada', $token);
        $this->consumeEverything();

        $balance = $this->balanceOf($invitadora['userId']);

        $this->aCorrectionDeliveredBy($lectora);
        $trasLaPrimera = $this->balanceOf($invitadora['userId']);

        $this->aCorrectionDeliveredBy($lectora, 'La segunda obra');
        $trasLaSegunda = $this->balanceOf($invitadora['userId']);

        self::assertSame($balance + 5, $trasLaPrimera);
        self::assertSame($trasLaPrimera, $trasLaSegunda, 'La segunda corrección ya no paga.');
    }

    /**
     * `FEAT-CRD-005` `RN-4`: el mismo hecho lo escuchan **dos reglas** de
     * `Credits`, y una no puede cerrárselo a la otra.
     *
     * Con el identificador del evento a secas como clave de deduplicación, la
     * primera en procesar `FeedbackSubmitted` marcaría el hecho como visto y
     * la segunda no llegaría nunca (`FEAT-CRD-011` `RN-4`). Aquí se comprueba
     * que el cobro de la corrección **y** la recompensa ocurren los dos.
     */
    public function testTheChargeAndTheRewardBothHappenOnTheSameFact(): void
    {
        $invitadora = $this->activatedPerson('invitadora');
        $token = $this->inviteAndReadTheLink($invitadora['token'], $this->address('invitada'));
        self::assertNotNull($token);

        $lectora = $this->activatedPerson('invitada', $token);
        $this->consumeEverything();

        $antesInvitadora = $this->balanceOf($invitadora['userId']);
        $antesLectora = $this->balanceOf($lectora['userId']);

        $this->aCorrectionDeliveredBy($lectora);

        self::assertSame($antesInvitadora + 5, $this->balanceOf($invitadora['userId']));
        self::assertGreaterThan(
            $antesLectora,
            (int) $this->balanceOf($lectora['userId']),
            'Y quien corrigió cobra su corrección, que es la otra regla sobre el mismo hecho.',
        );
    }

    /**
     * «Mis invitaciones» (`FEAT-USR-018` `RN-7`): qué mandé y en qué quedó.
     *
     * **No dice si se cobró por ella.** Lo que se pagó y a quién es asunto de
     * `Credits`, y preguntárselo para pintar esta lista sería abrir una
     * puerta entre contextos para un adorno.
     */
    public function testMyInvitationsShowWhatWasSentAndWhetherItWasAccepted(): void
    {
        $invitadora = $this->activatedPerson('invitadora');
        $invitada = $this->address('invitada');
        $token = $this->inviteAndReadTheLink($invitadora['token'], $invitada);
        self::assertNotNull($token);

        $this->client->request('GET', '/api/v1/me/invitations', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$invitadora['token'],
        ]);
        self::assertResponseIsSuccessful();

        /** @var list<array<string, mixed>> $antes */
        $antes = $this->payload()['invitations'];
        self::assertCount(1, $antes);
        self::assertSame($invitada, $antes[0]['email']);
        self::assertFalse($antes[0]['accepted']);
        self::assertNull($antes[0]['acceptedAt']);
        self::assertArrayNotHasKey('token', $antes[0]);

        $this->activatedPerson('invitada', $token);
        $this->consumeEverything();

        $this->client->request('GET', '/api/v1/me/invitations', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$invitadora['token'],
        ]);

        /** @var list<array<string, mixed>> $despues */
        $despues = $this->payload()['invitations'];
        self::assertTrue($despues[0]['accepted']);
        self::assertNotNull($despues[0]['acceptedAt']);
    }

    /**
     * Sin sesión no se invita, y no se ve la lista de nadie: a quién invita
     * alguien es de las cosas más privadas que guarda la plataforma.
     */
    public function testInvitingAndListingRequireASession(): void
    {
        $this->client->request('POST', '/api/v1/invitations', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(
            ['email' => $this->address('invitada')],
            \JSON_THROW_ON_ERROR,
        ));
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        $this->client->request('GET', '/api/v1/me/invitations');
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Una dirección mal escrita se rechaza antes de tocar nada.
     */
    public function testAMalformedAddressIsRefused(): void
    {
        $invitadora = $this->activatedPerson('invitadora');

        $this->invite($invitadora['token'], 'esto-no-es-un-correo');

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertNotSame('CANNOT_INVITE_YOURSELF', $this->payload()['code'] ?? null);
    }

    private function invite(string $token, string $email): void
    {
        $this->client->request('POST', '/api/v1/invitations', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(['email' => $email], \JSON_THROW_ON_ERROR));

        $this->capture();
    }

    /**
     * Invita y saca del correo el token del enlace, que es el único sitio
     * donde existe en claro.
     */
    private function inviteAndReadTheLink(string $token, string $email): ?string
    {
        $this->invite($token, $email);
        self::assertResponseStatusCodeSame(Response::HTTP_ACCEPTED);
        $this->consumeEverything();

        foreach (array_reverse(self::getMailerMessages()) as $message) {
            if (!$message instanceof MimeEmail || $email !== ($message->getTo()[0] ?? null)?->getAddress()) {
                continue;
            }

            if (1 === preg_match('/invitacion=([^\s"<]+)/', (string) $message->getTextBody(), $found)) {
                return rawurldecode($found[1]);
            }
        }

        return null;
    }

    /**
     * Quién invitó a esta persona, según la proyección de `Credits`.
     */
    private function referralOf(string $userId): ?string
    {
        /** @var ReferralRepository $referrals */
        $referrals = self::getContainer()->get(ReferralRepository::class);

        return $referrals->ofInvitee(CreditsUserId::fromString($userId))?->inviterId()->value();
    }

    /**
     * Una corrección entregada **por esta persona**, que es el hecho del que
     * cuelga la recompensa.
     *
     * @param array{token: string, userId: string} $reader
     */
    private function aCorrectionDeliveredBy(array $reader, string $title = 'La obra corregida'): void
    {
        $autora = $this->activatedPerson('autora'.substr(md5($title), 0, 6));

        $workId = $this->createWork($autora['token'], $title);
        $chapterId = $this->addChapter($workId, $autora['token'], words: 900);
        $this->saveQuestionnaire($workId, $autora['token'], [
            ['statement' => '¿Cómo funciona el ritmo?', 'minWords' => 10],
        ]);
        $this->putAs(\sprintf('/api/v1/works/%s/access-mode', $workId), $autora['token'], ['accessMode' => 'PUBLIC']);
        $this->putAs(\sprintf('/api/v1/works/%s/status', $workId), $autora['token'], ['status' => 'PUBLISHED']);
        $this->putAs(\sprintf('/api/v1/works/%s/status', $workId), $autora['token'], ['status' => 'IN_CORRECTION']);
        $this->consumeEverything();

        $this->client->request('POST', \sprintf('/api/v1/chapters/%s/corrections/start', $chapterId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$reader['token'],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->capture();
        $this->consumeEverything();

        $this->client->request('GET', \sprintf('/api/v1/chapters/%s/questionnaire', $chapterId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$reader['token'],
        ]);
        /** @var list<array{questionId: string}> $questions */
        $questions = $this->payload()['questions'];

        $this->client->request('POST', \sprintf('/api/v1/chapters/%s/corrections', $chapterId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$reader['token'],
        ], content: json_encode([
            'answers' => array_map(
                static fn (array $question): array => [
                    'questionId' => $question['questionId'],
                    'text' => implode(' ', array_fill(0, 60, 'palabra')),
                ],
                $questions,
            ),
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->capture();
        $this->consumeEverything();
    }
}
