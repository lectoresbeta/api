<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Unit\User;

use LectoresBeta\User\Preferences\Domain\Enum\PrivacyAudience;
use LectoresBeta\Work\Manuscript\Domain\Enum\BetaReaderAccessMode;
use PHPUnit\Framework\TestCase;

/**
 * El ajuste de privacidad del perfil es un **techo** sobre la modalidad de
 * cada obra (`FEAT-USR-038` `S-14`): el perfil pone el máximo, la obra puede
 * bajarlo.
 *
 * Si la obra pudiera ganarle, endurecer el ajuste global sería creer que se
 * cierra una puerta que sigue abierta en cada obra publicada como `PUBLIC`.
 */
final class PrivacyAudienceTest extends TestCase
{
    public function testEveryoneIsTheMostOpenSetting(): void
    {
        self::assertTrue(PrivacyAudience::EVERYONE->isAtLeastAsOpenAs(PrivacyAudience::FOLLOWERS));
        self::assertTrue(PrivacyAudience::FOLLOWERS->isAtLeastAsOpenAs(PrivacyAudience::NOBODY));
        self::assertFalse(PrivacyAudience::NOBODY->isAtLeastAsOpenAs(PrivacyAudience::FOLLOWERS));
    }

    public function testTheNarrowerOfTwoSettingsWins(): void
    {
        self::assertSame(
            PrivacyAudience::FOLLOWERS,
            PrivacyAudience::EVERYONE->narrowest(PrivacyAudience::FOLLOWERS),
        );

        self::assertSame(
            PrivacyAudience::NOBODY,
            PrivacyAudience::NOBODY->narrowest(PrivacyAudience::EVERYONE),
        );
    }

    /**
     * Una obra `PUBLIC` bajo un perfil restrictivo **no deja comentar a
     * cualquiera**: la restricción del perfil alcanza a todas las obras.
     *
     * Los dos ejes se guardan aparte y la autorización evalúa los dos. Nada
     * reescribe la modalidad de cada obra al cambiar el ajuste: hacerlo
     * impediría volver atrás, porque al relajar el perfil ya nadie sabría qué
     * tenía antes cada obra.
     */
    public function testAProfileCeilingReachesEveryWork(): void
    {
        $delPerfil = PrivacyAudience::FOLLOWERS;
        $deLaObra = $this->audienceOf(BetaReaderAccessMode::PUBLIC);

        self::assertSame(PrivacyAudience::FOLLOWERS, $delPerfil->narrowest($deLaObra));
    }

    /**
     * Y al revés: una obra `PRIVATE` sigue siendo privada aunque el perfil lo
     * permita todo. Gana siempre el más estrecho de los dos.
     */
    public function testAStricterWorkIsNotLoosenedByAnOpenProfile(): void
    {
        $delPerfil = PrivacyAudience::EVERYONE;
        $deLaObra = $this->audienceOf(BetaReaderAccessMode::PRIVATE);

        self::assertSame($deLaObra, $delPerfil->narrowest($deLaObra));
    }

    /**
     * Cómo se lee la modalidad de una obra en la escala del perfil. Vive en
     * el test porque la traducción es una decisión de autorización que
     * todavía no tiene sitio propio.
     */
    private function audienceOf(BetaReaderAccessMode $mode): PrivacyAudience
    {
        return match ($mode) {
            BetaReaderAccessMode::PUBLIC => PrivacyAudience::EVERYONE,
            BetaReaderAccessMode::ON_REQUEST => PrivacyAudience::FOLLOWERS,
            BetaReaderAccessMode::PRIVATE => PrivacyAudience::NOBODY,
        };
    }
}
