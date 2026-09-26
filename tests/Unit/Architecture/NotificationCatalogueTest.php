<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Unit\Architecture;

use LectoresBeta\Notification\Delivery\Domain\Enum\NotificationKind;
use LectoresBeta\User\Preferences\Domain\Enum\NotificationChannel;
use LectoresBeta\User\Preferences\Domain\Enum\NotificationTopic;
use PHPUnit\Framework\TestCase;

/**
 * Los dos catálogos de avisos tienen que decir lo mismo (`FEAT-NOT-002`).
 *
 * `Notification` tiene su `NotificationKind` y `User` tiene su
 * `NotificationTopic`, y **están separados a propósito**: son dos contextos y
 * ninguno importa el enum del otro. Se emparejan por el valor de la cadena,
 * sin tabla de traducción, porque una tabla de traducción es una tercera cosa
 * que hay que mantener.
 *
 * Eso deja un hueco que solo se puede cerrar comprobándolo: si un tipo dijera
 * aquí que sale por correo y allí no tuviera casilla de correo, saldría un
 * aviso que nadie puede apagar; y al revés, la pantalla ofrecería una casilla
 * que no hace nada.
 *
 * Es la misma solución que las rutas en YAML: una convención que solo vive en
 * un documento se incumple el día que alguien tiene prisa.
 */
final class NotificationCatalogueTest extends TestCase
{
    /**
     * Todo lo que sale por correo **se puede apagar**.
     *
     * Es la mitad que de verdad importa: un aviso sin interruptor es correo
     * que alguien recibe y no puede parar.
     */
    public function testEverythingThatIsEmailedCanBeSwitchedOff(): void
    {
        $withoutASwitch = [];

        foreach (NotificationKind::cases() as $kind) {
            if (!$kind->reachesInbox()) {
                continue;
            }

            $topic = NotificationTopic::tryFrom($kind->value);

            if (null === $topic || !$topic->admits(NotificationChannel::EMAIL)) {
                $withoutASwitch[] = $kind->value;
            }
        }

        self::assertSame([], $withoutASwitch, implode("\n", [
            'Estos avisos salen por correo y no tienen casilla de correo en Configuración.',
            'O se les añade en NotificationTopic, o dejan de salir en NotificationKind::reachesInbox().',
        ]));
    }

    /**
     * Y toda casilla de correo **apaga algo**.
     *
     * Una casilla que no hace nada es peor que no ofrecerla: promete un
     * control que no existe.
     *
     * Se exceptúan los tipos que **no son actividad** —las actualizaciones de
     * la plataforma y los consejos de uso—: no los provoca ningún hecho, así
     * que no tienen `NotificationKind` que los produzca, y aun así su casilla
     * tiene sentido el día que salga una campaña.
     */
    public function testEveryEmailSwitchTurnsSomethingOff(): void
    {
        $broadcast = [NotificationTopic::PLATFORM_UPDATES, NotificationTopic::USAGE_TIPS];
        $switchingNothing = [];

        foreach (NotificationTopic::cases() as $topic) {
            if (!$topic->admits(NotificationChannel::EMAIL) || \in_array($topic, $broadcast, true)) {
                continue;
            }

            $kind = NotificationKind::tryFrom($topic->value);

            if (null === $kind || !$kind->reachesInbox()) {
                $switchingNothing[] = $topic->value;
            }
        }

        self::assertSame([], $switchingNothing, implode("\n", [
            'Estas casillas de correo no apagan nada: ningún aviso sale por ese canal.',
            'O el tipo empieza a salir por correo, o la casilla deja de ofrecerse.',
        ]));
    }

    /**
     * Un aviso **operativo nunca sale por este camino** (`FEAT-NOT-002`
     * `RN-6`).
     *
     * Los operativos llevan cosas que un correo genérico no tiene —un enlace
     * de un solo uso, una dirección a la que recurrir— y tienen su propio
     * consumidor con su propio texto. Si alguno se colara aquí, su
     * destinatario recibiría dos correos, y el segundo sin el enlace.
     */
    public function testNoOperationalNoticeIsEmailedGenerically(): void
    {
        $leaking = array_values(array_map(
            static fn (NotificationKind $kind): string => $kind->value,
            array_filter(
                NotificationKind::cases(),
                static fn (NotificationKind $kind): bool => $kind->isOperational() && $kind->reachesInbox(),
            ),
        ));

        self::assertSame([], $leaking, 'Un operativo tiene su propio correo, y con dos llegarían dos.');
    }
}
