<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Moderation;

use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use Symfony\Component\HttpFoundation\Response;

/**
 * El backoffice de usuarios (`FEAT-MOD-005`).
 *
 * **El backoffice mira, no posee**, y esta prueba defiende las tres formas en
 * que eso se concreta: que la ficha se componga sin que `Moderation` lea una
 * sola tabla ajena, que mover créditos sea **ordenar** y no ajustar, y que
 * mirar deje traza igual que cambiar.
 */
final class BackofficeTest extends EconomyScenario
{
    /**
     * Buscar por correo es el caso real: quien atiende a una persona tiene su
     * dirección porque se la ha escrito ella.
     */
    public function testAModeratorFindsAnAccountByItsEmail(): void
    {
        $moderador = $this->moderator('moderadora');
        $persona = $this->activatedPerson('buscada');

        $this->search($moderador['token'], $this->address('buscada'));

        self::assertResponseIsSuccessful();
        $encontradas = $this->payload()['users'];
        self::assertCount(1, $encontradas);
        self::assertSame($persona['userId'], $encontradas[0]['userId']);
        self::assertSame($this->address('buscada'), $encontradas[0]['email']);
        self::assertStringContainsString(
            'no-store',
            (string) $this->client->getResponse()->headers->get('Cache-Control'),
            'Lleva direcciones de correo.',
        );
    }

    /**
     * `RN-1`: **también las consultas**. Un backoffice donde mirar es
     * invisible es un backoffice donde se puede curiosear.
     */
    public function testLookingIsAudited(): void
    {
        $admin = $this->administrator('jefa');
        $persona = $this->activatedPerson('mirada');

        $this->search($admin['token'], 'mirada');
        $this->sheet($admin['token'], $persona['userId']);

        $acciones = $this->auditActions($admin['token']);

        self::assertContains('USERS_SEARCHED', $acciones);
        self::assertContains('USER_SHEET_READ', $acciones);
    }

    /**
     * La ficha se compone de cuatro contextos y **ninguno se lee por su
     * tabla**: `User` contesta la identidad, `Work` los relatos, `Feedback`
     * las correcciones, y las reclamaciones y sanciones son de casa.
     */
    public function testTheSheetComposesWhatEachContextOwns(): void
    {
        [$autora, $lectora] = $this->aDeliveredCorrection();
        $moderador = $this->moderator('moderadora');

        $this->sheet($moderador['token'], $lectora['userId']);

        self::assertResponseIsSuccessful();
        $ficha = $this->payload();

        self::assertSame($lectora['userId'], $ficha['account']['userId']);
        self::assertSame('ACTIVE', $ficha['account']['status']);
        self::assertSame(1, $ficha['counters']['corrections'], 'De `Feedback`.');
        self::assertSame([], $ficha['sanctions']);

        $this->sheet($moderador['token'], $autora['userId']);
        self::assertSame(1, $this->payload()['counters']['works'], 'De `Work`.');
    }

    /**
     * `RN-5`: ni una línea de obra inédita, ni el texto de una reclamación.
     * La ficha dice **qué** hay, no **qué dice**.
     */
    public function testTheSheetNeverCarriesAnybodysText(): void
    {
        [$autora, $lectora, $correctionId] = $this->aDeliveredCorrection();
        $moderador = $this->moderator('moderadora');

        $this->claimAbout($autora['token'], 'CORRECTION', $correctionId, 'Esto es un insulto, no una corrección.');

        $this->sheet($moderador['token'], $autora['userId']);

        $ficha = $this->payload();
        self::assertCount(1, $ficha['claimsFiled']);
        self::assertArrayNotHasKey('description', $ficha['claimsFiled'][0]);

        $encoded = json_encode($ficha, \JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('insulto', $encoded, 'El texto de la reclamación no sale.');
        self::assertStringNotContainsString('palabra', $encoded, 'Ni una línea de la corrección.');

        // Y quien la recibe la ve en la otra lista, que es una pregunta
        // distinta: quién se queja mucho y de quién se quejan mucho.
        $this->sheet($moderador['token'], $lectora['userId']);
        self::assertCount(1, $this->payload()['claimsReceived']);
        self::assertSame([], $this->payload()['claimsFiled']);
    }

    /**
     * `RN-3` y `RN-4`, que son lo que esta funcionalidad aporta de verdad al
     * sistema de créditos: **un movimiento nuevo, contabilizado como grifo**.
     *
     * Si se contara como transferencia, la invariante contable empezaría a
     * fallar y nadie sabría por qué.
     */
    public function testAnAdjustmentIsOrderedAndCreditsAppliesItAsATap(): void
    {
        $admin = $this->administrator('jefa');
        $persona = $this->activatedPerson('ajustada');
        $this->consumeEverything();

        $antes = (int) $this->balanceOf($persona['userId']);
        $emitidoAntes = $this->health($admin['token'])['invariant']['issued'];

        $this->adjust($admin['token'], $persona['userId'], 25, 'Compensación por una reclamación estimada.');

        self::assertResponseStatusCodeSame(Response::HTTP_ACCEPTED, 'Ordenado, no aplicado.');
        $this->capture();

        // Todavía no ha pasado nada: `Credits` no ha recibido el hecho.
        self::assertSame($antes, (int) $this->balanceOf($persona['userId']));

        $this->consumeEverything();

        self::assertSame($antes + 25, (int) $this->balanceOf($persona['userId']));

        $salud = $this->health($admin['token']);
        self::assertTrue($salud['invariant']['holds'], 'La invariante aguanta un ajuste manual.');
        self::assertSame($emitidoAntes + 25, $salud['invariant']['issued'], 'Es un grifo, no una transferencia.');
        self::assertSame(1, $salud['manualAdjustments']['count']);
        self::assertSame(25, $salud['manualAdjustments']['net']);
    }

    /**
     * Una absorción es emisión negativa, y puede dejar el saldo en rojo a
     * propósito: revertir un abono indebido es justamente eso.
     */
    public function testAnAdjustmentMayTakeCreditsAwayAndEvenGoNegative(): void
    {
        $admin = $this->administrator('jefa');
        $persona = $this->activatedPerson('ajustada');
        $this->consumeEverything();

        $this->adjust($admin['token'], $persona['userId'], -15, 'Reversión de un abono indebido.');
        $this->capture();
        $this->consumeEverything();

        self::assertLessThan(0, (int) $this->balanceOf($persona['userId']));
        self::assertTrue($this->health($admin['token'])['invariant']['holds']);
    }

    /**
     * `RN-6`: un `Moderator` no ajusta créditos. Es la única acción del
     * backoffice que crea o destruye valor de la nada.
     */
    public function testOnlyAnAdministratorMovesCredits(): void
    {
        $moderador = $this->moderator('moderadora');
        $persona = $this->activatedPerson('ajustada');

        $this->adjust($moderador['token'], $persona['userId'], 10, 'Lo que sea.');

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    /**
     * `RN-2`: ni una sanción ni un ajuste se aceptan sin motivo. Y cero no es
     * un ajuste: sería un apunte que no movió nada.
     */
    public function testAnAdjustmentNeedsAReasonAndAnAmount(): void
    {
        $admin = $this->administrator('jefa');
        $persona = $this->activatedPerson('ajustada');

        $this->adjust($admin['token'], $persona['userId'], 10, '   ');
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('REASON_REQUIRED', $this->payload()['code']);

        $this->adjust($admin['token'], $persona['userId'], 0, 'Un motivo cualquiera.');
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('AMOUNT_REQUIRED', $this->payload()['code']);

        $this->adjust($admin['token'], $persona['userId'], 5000, 'Un motivo cualquiera.');
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('ADJUSTMENT_TOO_LARGE', $this->payload()['code']);
    }

    /**
     * `RN-12` a `RN-15`: la reclamación registrada en nombre de otro queda
     * marcada, la ve su dueño, y **quien la registró no puede resolverla**.
     */
    public function testAClaimFiledOnBehalfIsMarkedAndItsRegistrarCannotResolveIt(): void
    {
        [$autora, , $correctionId] = $this->aDeliveredCorrection();
        $moderador = $this->moderator('moderadora');

        $this->client->request('POST', '/api/v1/admin/claims/on-behalf', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$moderador['token'],
        ], content: json_encode([
            'onBehalfOf' => $autora['userId'],
            'targetType' => 'CORRECTION',
            'targetId' => $correctionId,
            'reason' => 'OFFENSIVE',
            'description' => 'Me lo ha contado por correo.',
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $claimId = (string) $this->payload()['claimId'];

        // `RN-13`: es suya y la ve, aunque no la haya presentado ella.
        $this->client->request('GET', '/api/v1/me/claims', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$autora['token'],
        ]);
        self::assertResponseIsSuccessful();
        self::assertSame($claimId, $this->payload()['claims'][0]['claimId']);

        // `RN-12`: queda marcada y no se disfraza de ordinaria.
        $this->sheet($moderador['token'], $autora['userId']);
        self::assertTrue($this->payload()['claimsFiled'][0]['filedOnBehalf']);

        // `RN-14`: quien la redactó ya se ha formado una opinión.
        $this->client->request('POST', \sprintf('/api/v1/admin/claims/%s/review', $claimId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$moderador['token'],
        ], content: json_encode(['decision' => 'REJECTED', 'motivation' => 'No procede.'], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame('CLAIM_MODERATOR_IS_PARTY', $this->payload()['code'], 'La misma puerta que ya cerraba a las partes.');

        // Y otro moderador sí puede.
        $otra = $this->moderator('otra');
        $this->client->request('POST', \sprintf('/api/v1/admin/claims/%s/review', $claimId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$otra['token'],
        ], content: json_encode(['decision' => 'REJECTED', 'motivation' => 'No procede.'], \JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
    }

    /**
     * El backoffice entero es de moderación, y un usuario normal no lo abre.
     */
    public function testItIsClosedToEverybodyElse(): void
    {
        $cualquiera = $this->activatedPerson('cualquiera');

        $this->search($cualquiera['token'], 'lo que sea');
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $this->sheet($cualquiera['token'], $cualquiera['userId']);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    private function search(string $token, ?string $term): void
    {
        $this->client->request('GET', '/api/v1/admin/users?q='.urlencode($term ?? ''), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
    }

    private function sheet(string $token, string $userId): void
    {
        $this->client->request('GET', \sprintf('/api/v1/admin/users/%s', $userId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
    }

    private function adjust(string $token, string $userId, int $amount, string $reason): void
    {
        $this->client->request('POST', \sprintf('/api/v1/admin/users/%s/credit-adjustment', $userId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(['amount' => $amount, 'reason' => $reason], \JSON_THROW_ON_ERROR));
    }

    private function claimAbout(string $token, string $targetType, string $targetId, string $description): void
    {
        $this->client->request('POST', '/api/v1/claims', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode([
            'targetType' => $targetType,
            'targetId' => $targetId,
            'reason' => 'OFFENSIVE',
            'description' => $description,
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
    }

    /**
     * @return array<string, mixed>
     */
    private function health(string $token): array
    {
        $this->client->request('GET', '/api/v1/admin/credits/health', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        self::assertResponseIsSuccessful();

        return $this->payload();
    }

    /**
     * @return list<string>
     */
    private function auditActions(string $token): array
    {
        $this->client->request('GET', '/api/v1/admin/audit-log', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        self::assertResponseIsSuccessful();

        /** @var list<array{action: string}> $entries */
        $entries = $this->payload()['entries'];

        return array_map(static fn (array $entry): string => $entry['action'], $entries);
    }
}
