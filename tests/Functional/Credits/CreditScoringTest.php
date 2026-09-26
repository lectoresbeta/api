<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Credits;

use LectoresBeta\Credits\Pricing\Domain\Service\ChapterPricing;
use LectoresBeta\Tests\Functional\Support\EconomyScenario;

/**
 * «Ver puntuación de créditos» (`FEAT-CRD-015`), el destino del botón
 * izquierdo del modal de `FEAT-CRD-014`.
 *
 * **La prueba que da sentido a la ficha es la última**: las cifras que se
 * enseñan son las mismas que cobra el motor. Si esta pantalla se escribiera
 * en el cliente, o con los números a mano aquí, un día diría una cosa y el
 * cobro haría otra — y quien lo descubriría es alguien que esperaba cobrar
 * distinto.
 */
final class CreditScoringTest extends EconomyScenario
{
    /**
     * `RN-1`: público y sin sesión. Son las reglas de la casa, y quien se
     * está planteando registrarse tiene derecho a leerlas antes.
     */
    public function testItIsPublic(): void
    {
        $this->client->request('GET', '/api/v1/credits/scoring');

        self::assertResponseIsSuccessful();
        self::assertNotEmpty($this->payload()['earning']);
    }

    /**
     * `RN-2`: trae lo que se gana y lo que cuesta, con las cifras vigentes.
     */
    public function testItExplainsEarningAndSpending(): void
    {
        $this->client->request('GET', '/api/v1/credits/scoring');
        $scoring = $this->payload();

        self::assertSame(10, $scoring['earning']['welcomeGrant']);
        self::assertSame(5, $scoring['earning']['invitationReward']);
        self::assertSame(10, $scoring['earning']['rewardedInvitationLimit']);

        self::assertSame(ChapterPricing::MIN_PRICE, $scoring['spending']['minPrice']);
        self::assertSame(ChapterPricing::MAX_PRICE, $scoring['spending']['maxPrice']);
        self::assertSame(ChapterPricing::WORDS_PER_READING_CREDIT, $scoring['spending']['wordsPerReadingCredit']);
        self::assertSame(ChapterPricing::WORDS_PER_WRITING_CREDIT, $scoring['spending']['wordsPerWritingCredit']);
    }

    /**
     * `RN-3`: dice **cuándo se paga**, sin ambigüedad.
     *
     * La maqueta del modal sugería que los créditos se gastan al poner la
     * obra en corrección; `decision:0006` y `FEAT-CRD-006` establecen que se
     * pagan por cada corrección entregada. Aquí manda el código, y esta
     * prueba es lo que impide que la contradicción vuelva.
     */
    public function testItSaysWhenCreditsAreActuallyCharged(): void
    {
        $this->client->request('GET', '/api/v1/credits/scoring');

        self::assertSame('FEEDBACK_DELIVERED', $this->payload()['spending']['chargedOn']);
    }

    /**
     * `RN-4`: **no hay una cifra de «lo que se gana corrigiendo»**, porque
     * una corrección mueve créditos en vez de crearlos: el autor paga
     * exactamente lo que el corrector cobra.
     *
     * Decirlo así evita que alguien busque un número que no existe.
     */
    public function testCorrectingPaysExactlyWhatItCosts(): void
    {
        $this->client->request('GET', '/api/v1/credits/scoring');

        self::assertTrue($this->payload()['earning']['correctionPaysWhatItCosts']);
    }

    /**
     * `RN-5`: el ejemplo **lo calcula el motor de verdad**.
     *
     * Es la prueba que sostiene la ficha entera: se comprueba contra la misma
     * clase que cobra, no contra un número escrito a mano. Si la fórmula
     * cambia, el ejemplo cambia solo y esta prueba sigue pasando; si alguien
     * reprodujera la fórmula en la pantalla, fallaría.
     */
    public function testTheExampleIsComputedByTheRealEngine(): void
    {
        $this->client->request('GET', '/api/v1/credits/scoring');
        $example = $this->payload()['example'];

        /** @var ChapterPricing $pricing */
        $pricing = self::getContainer()->get(ChapterPricing::class);

        self::assertSame(
            $pricing->priceOf($example['words'], $example['requiredWords']),
            $example['price'],
        );
    }

    /**
     * Se puede cachear en público: la respuesta es idéntica para todo el
     * mundo y solo cambia cuando cambia la configuración de la economía.
     */
    public function testItCanBeCachedPublicly(): void
    {
        $this->client->request('GET', '/api/v1/credits/scoring');

        $cacheControl = (string) $this->client->getResponse()->headers->get('Cache-Control');

        self::assertStringContainsString('public', $cacheControl);
        self::assertStringContainsString('max-age=', $cacheControl);
    }
}
