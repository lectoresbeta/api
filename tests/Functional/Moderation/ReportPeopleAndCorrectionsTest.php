<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Moderation;

use Doctrine\Persistence\ManagerRegistry;
use LectoresBeta\Feedback\Correction\Domain\Repository\CorrectionRepository;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\CorrectionId;
use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use Symfony\Component\HttpFoundation\Response;

/**
 * Las dos puertas de entrada a la moderación: denunciar una corrección
 * abusiva (`FEAT-FBK-009`) y denunciar a una persona (`FEAT-COM-035`).
 *
 * **Las dos desembocan en `FEAT-MOD-001`**, que ya estaba construido, y esa
 * es la mitad de la ficha: no hay una tubería de denuncias por cada sitio
 * desde el que se denuncia. Lo que cambia es qué se comprueba antes de
 * registrar, porque las dos se prestan a abusos distintos.
 *
 * Denunciar una corrección **devuelve créditos si se estima**, así que el
 * botón es también una forma de no pagar. De ahí que solo lo abra el autor de
 * la obra, y solo sobre lo que ya ha podido leer.
 *
 * Denunciar a una persona no devuelve nada, pero sí gasta cupo, así que lo
 * que hay que impedir es que se use para otra cosa: contra uno mismo, o
 * contra quien no existe.
 */
final class ReportPeopleAndCorrectionsTest extends EconomyScenario
{
    /**
     * `FEAT-FBK-009` `RN-1`: el autor de la obra denuncia la corrección que
     * ha recibido, y queda registrada.
     */
    public function testTheAuthorCanReportACorrectionTheyReceived(): void
    {
        [$autora, , $correctionId] = $this->aDeliveredCorrection();
        $this->readIt($autora['token'], $correctionId);

        $this->claim($autora['token'], 'CORRECTION', $correctionId, 'NO_VALUE');

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertSame('PENDING', $this->payload()['status']);
    }

    /**
     * `FEAT-FBK-009` `RN-2`: **solo el autor de la obra.**.
     *
     * Ni quien la escribió ni un extraño. Una corrección ajena responde lo
     * mismo que una inexistente: que exista ya es información sobre una obra
     * que no es suya.
     */
    public function testNobodyElseCanReportThatCorrection(): void
    {
        [$autora, $lectora, $correctionId] = $this->aDeliveredCorrection();
        $this->readIt($autora['token'], $correctionId);
        $extrana = $this->activatedPerson('extrana');

        foreach ([$lectora['token'], $extrana['token']] as $token) {
            $this->claim($token, 'CORRECTION', $correctionId, 'NO_VALUE');
            self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * `FEAT-FBK-009` `RN-3`: **solo lo que ya se ha podido leer.**.
     *
     * Cierra una vía sutil: reclamar a ciegas una corrección retenida por
     * descubierto (`FEAT-CRD-018`), solo para no pagarla. Sin esta puerta,
     * quien no puede pagar denunciaría lo que no ha visto.
     *
     * La retención se provoca **bloqueando la corrección directamente**, que
     * es lo que hace el descubierto: llegar ahí por la economía exigiría
     * conceder un cupo de descubierto, y lo que esta prueba defiende es la
     * puerta de la reclamación, no el camino hasta ella.
     */
    public function testACorrectionYouHaveNotBeenAbleToReadCannotBeReported(): void
    {
        [$autora, , $correctionId] = $this->aDeliveredCorrection();

        /** @var CorrectionRepository $corrections */
        $corrections = self::getContainer()->get(CorrectionRepository::class);
        $correction = $corrections->ofId(CorrectionId::fromString($correctionId));
        self::assertNotNull($correction);
        $correction->lock(new \DateTimeImmutable());

        /** @var ManagerRegistry $doctrine */
        $doctrine = self::getContainer()->get('doctrine');
        $doctrine->getManager()->flush();

        $this->claim($autora['token'], 'CORRECTION', $correctionId, 'NO_VALUE');

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame('CLAIM_CORRECTION_NOT_READ', $this->payload()['code']);
    }

    /**
     * `FEAT-COM-035` `RN-1`: cualquiera puede denunciar a una persona, con
     * uno de los motivos del catálogo.
     */
    public function testAnybodyCanReportSomebodyElse(): void
    {
        $quien = $this->activatedPerson('quien');
        $senalada = $this->activatedPerson('senalada');

        $this->claim($quien['token'], 'USER', $senalada['userId'], 'HARASSMENT');

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
    }

    /**
     * `FEAT-COM-035` `RN-3`: **nadie se denuncia a sí mismo.**.
     *
     * Gastaría el cupo de quien la presenta y el tiempo de quien la lee, y no
     * hay desenlace posible que signifique algo.
     */
    public function testYouCannotReportYourself(): void
    {
        $quien = $this->activatedPerson('quien');

        $this->claim($quien['token'], 'USER', $quien['userId'], 'HARASSMENT');

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('CANNOT_CLAIM_AGAINST_YOURSELF', $this->payload()['code']);
    }

    /**
     * `FEAT-COM-035` `RN-4`: a quien no existe tampoco, y **responde lo mismo
     * que un objeto no reclamable**.
     *
     * Decir «esa cuenta no existe» convertiría el formulario de denunciar en
     * un comprobador de quién está en la plataforma, que es justo lo que el
     * alta y la recuperación de contraseña se cuidan de no decir.
     */
    public function testReportingSomebodyWhoIsNotThereSaysNothingAboutIt(): void
    {
        $quien = $this->activatedPerson('quien');

        $this->claim($quien['token'], 'USER', '0192f000-0000-7000-8000-000000000000', 'HARASSMENT');
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $inexistente = $this->payload()['code'];

        $this->claim($quien['token'], 'USER', 'esto-no-es-un-uuid', 'HARASSMENT');
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        self::assertSame($inexistente, $this->payload()['code'], 'La diferencia está donde no se ve.');
    }

    /**
     * `RN-5` de las dos: **una por persona y objeto.** Diez denuncias de
     * alguien sobre lo mismo no son diez señales, son la misma repetida.
     */
    public function testReportingTheSamePersonTwiceReturnsTheFirstClaim(): void
    {
        $quien = $this->activatedPerson('quien');
        $senalada = $this->activatedPerson('senalada');

        $this->claim($quien['token'], 'USER', $senalada['userId'], 'HARASSMENT');
        $primera = (string) $this->payload()['claimId'];

        $this->claim($quien['token'], 'USER', $senalada['userId'], 'SPAM');

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertSame($primera, $this->payload()['claimId']);
        self::assertTrue($this->payload()['alreadyExisted']);
    }

    /**
     * `RN-6` de las dos: **ninguna produce ningún efecto sobre lo
     * denunciado.** No oculta nada, no congela nada y no avisa al señalado.
     *
     * Es la regla que separa denunciar de censurar: registrar que alguien
     * pide que se mire algo no es haberlo mirado.
     */
    public function testReportingProducesNoEffectOnTheTarget(): void
    {
        $quien = $this->activatedPerson('quien');
        $senalada = $this->activatedPerson('senalada');
        $postId = $this->publish($senalada['token'], 'Sigo publicando');

        $this->claim($quien['token'], 'USER', $senalada['userId'], 'HARASSMENT');
        $this->consumeEverything();

        // Sigue publicando y su publicación sigue en el muro.
        $this->publish($senalada['token'], 'Y sigo');

        $this->client->request('GET', '/api/v1/posts', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$quien['token'],
        ]);
        self::assertResponseIsSuccessful();

        $ids = array_map(
            static fn (array $post): string => (string) $post['postId'],
            $this->payload()['posts'],
        );

        self::assertContains($postId, $ids);
        self::assertSame([], $this->kindsOfNotice($senalada['token']), 'Y no se entera.');
    }

    /**
     * Las dos exigen sesión: denunciar sin identificarse no es denunciar, es
     * dejar una nota anónima que nadie puede valorar.
     */
    public function testBothRequireASession(): void
    {
        $this->client->request('POST', '/api/v1/claims', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'targetType' => 'USER',
            'targetId' => '0192f000-0000-7000-8000-000000000000',
            'reason' => 'HARASSMENT',
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    private function readIt(string $token, string $correctionId): void
    {
        $this->client->request('GET', \sprintf('/api/v1/corrections/%s', $correctionId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        $this->capture();
        $this->consumeEverything();
    }

    private function claim(string $token, string $targetType, string $targetId, string $reason): void
    {
        $this->client->request('POST', '/api/v1/claims', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode([
            'targetType' => $targetType,
            'targetId' => $targetId,
            'reason' => $reason,
        ], \JSON_THROW_ON_ERROR));

        $this->capture();
    }

    private function publish(string $token, string $body): string
    {
        $this->client->request('POST', '/api/v1/posts', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(['body' => $body], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->capture();

        return (string) $this->payload()['postId'];
    }

    /**
     * @return list<string>
     */
    private function kindsOfNotice(string $token): array
    {
        $this->client->request('GET', '/api/v1/me/notifications', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        self::assertResponseIsSuccessful();

        /** @var list<array{kind: string}> $data */
        $data = $this->payload()['data'];

        return array_values(array_map(
            static fn (array $notice): string => (string) $notice['kind'],
            $data,
        ));
    }
}
