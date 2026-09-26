<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Unit\Work;

use LectoresBeta\Work\Manuscript\Domain\Enum\ContentWarning;
use LectoresBeta\Work\Manuscript\Domain\Exception\InvalidContentRating;
use LectoresBeta\Work\Manuscript\Domain\Service\ContentRatingPolicy;
use PHPUnit\Framework\TestCase;

/**
 * Qué es una clasificación de contenido válida (`FEAT-WRK-017`).
 *
 * Las dos reglas que se defienden aquí existen por la misma razón: la
 * declaración del autor es lo que después le protege de una reclamación y lo
 * que le hace responsable si engaña. Una que el sistema complete por su
 * cuenta no sirve para ninguna de las dos cosas.
 */
final class ContentRatingPolicyTest extends TestCase
{
    private ContentRatingPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new ContentRatingPolicy();
    }

    public function testTheLabelsComeFromTheClosedCatalogue(): void
    {
        self::assertSame(
            [ContentWarning::SELF_HARM, ContentWarning::STRONG_LANGUAGE],
            $this->policy->warnings(['self_harm', 'STRONG_LANGUAGE']),
        );
    }

    /**
     * **Se nombra la que falla.** Descartarla en silencio dejaría la obra
     * etiquetada de forma distinta a como su autor cree, y el autor responde
     * de eso: etiquetar mal es reclamable.
     */
    public function testAnInventedLabelIsRefusedByName(): void
    {
        try {
            $this->policy->warnings(['SELF_HARM', 'CONTENIDO_PERTURBADOR']);
            self::fail('Una etiqueta que no existe no puede pasar.');
        } catch (InvalidContentRating $refusal) {
            self::assertSame('UNKNOWN_CONTENT_WARNING', $refusal->errorCode());
            self::assertStringContainsString('CONTENIDO_PERTURBADOR', $refusal->getMessage());
        }
    }

    public function testRepeatingALabelDeclaresItOnce(): void
    {
        self::assertSame(
            [ContentWarning::SEXUAL_CONTENT],
            $this->policy->warnings(['SEXUAL_CONTENT', 'sexual_content']),
        );
    }

    /**
     * Declarar las cinco es legal: no hace daño a nadie y solo le cuesta
     * lectores a su autor. Es lo contrario del tope de temáticas, que existe
     * porque esas **añaden** obras a una búsqueda.
     */
    public function testDeclaringEverythingIsAllowed(): void
    {
        self::assertCount(5, $this->policy->warnings(array_map(
            static fn (ContentWarning $warning): string => $warning->value,
            ContentWarning::cases(),
        )));
    }

    public function testNoLabelsAtAllIsAPerfectlyGoodDeclaration(): void
    {
        self::assertSame([], $this->policy->warnings([]));
    }

    /**
     * **La regla que importa.** Omitir el indicador no significa «apta para
     * menores»: significa que nadie lo ha dicho, y dar por supuesto lo más
     * permisivo es justo el error que esta funcionalidad existe para evitar.
     */
    public function testNobodySayingIsNotTheSameAsSayingNo(): void
    {
        self::assertFalse($this->policy->audience(false));
        self::assertTrue($this->policy->audience(true));

        $this->expectException(InvalidContentRating::class);
        $this->policy->audience(null);
    }
}
