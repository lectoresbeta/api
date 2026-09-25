<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Domain\Service;

use LectoresBeta\Notification\Delivery\Domain\Enum\NotificationKind;

/**
 * Qué dice cada aviso (`FEAT-NOT-002` `RN-7`).
 *
 * **La campana no necesita esto y el correo sí.** A la campana se le mandan
 * `kind` y `payload` y la frase la compone el cliente, que es lo correcto:
 * así se traduce, se acorta y se cambia sin desplegar el servidor. Un correo
 * no tiene cliente que la componga.
 *
 * Lo que sale de aquí es **un asunto y una línea**, construidos con lo que el
 * aviso ya guarda —nombres, títulos, cifras—. Nunca con contenido: ni una
 * línea de una obra, de una corrección o de un mensaje (`RN-4`). Quien recibe
 * el correo tiene que saber qué ha pasado y entrar a verlo, no leerlo en el
 * buzón.
 *
 * Es un servicio de dominio y no una plantilla porque la decisión de qué se
 * cuenta y qué no es de negocio. Cómo se maqueta sí es de infraestructura, y
 * por eso de aquí sale texto y no HTML.
 */
final class NotificationPhrase
{
    /**
     * @param array<string, scalar|null> $payload
     */
    public function subjectOf(NotificationKind $kind, array $payload): string
    {
        $who = self::actor($payload);
        $work = self::work($payload);

        return match ($kind) {
            NotificationKind::CORRECTION_RECEIVED => null === $work
                ? 'Has recibido una corrección'
                : \sprintf('Has recibido una corrección de «%s»', $work),
            NotificationKind::CORRECTION_REPLIED => 'Han contestado a tu corrección',
            NotificationKind::CORRECTION_UNLOCKED => 'Ya puedes leer las correcciones que tenías retenidas',
            NotificationKind::CORRECTION_CLOSED => 'Se ha cerrado una corrección tuya',
            NotificationKind::CHAPTER_COMMENT => null === $work
                ? 'Han comentado un texto tuyo'
                : \sprintf('Han comentado «%s»', $work),
            NotificationKind::MENTION => null === $who
                ? 'Te han mencionado'
                : \sprintf('%s te ha mencionado', $who),
            NotificationKind::ACCESS_REQUESTED => 'Alguien quiere ser lector beta de una obra tuya',
            NotificationKind::ACCESS_REQUEST_RESOLVED => 'Ya hay respuesta a tu solicitud de lectura',
            NotificationKind::BETA_READER_INVITATION => 'Te han invitado a leer una obra',
            NotificationKind::BETA_READER_ACCESS_REVOKED => 'Se ha retirado tu acceso a una obra',
            NotificationKind::WRITING_BUDDY_PROPOSED => 'Te proponen ser writing buddy',
            NotificationKind::SUBSCRIBED_AUTHOR_PUBLISHED => null === $who
                ? 'Hay algo nuevo de alguien a quien sigues'
                : \sprintf('%s ha publicado algo nuevo', $who),
            NotificationKind::BALANCE_WENT_NEGATIVE => 'Tu saldo de créditos está en negativo',
            NotificationKind::CLAIM_RESOLVED => 'Hay resolución de tu reclamación',
            NotificationKind::REACTIVATION_OFFER => 'Han corregido un texto tuyo',
            default => 'Tienes un aviso en Lectores Beta',
        };
    }

    /**
     * El cuerpo, en una o dos frases.
     *
     * Varias dicen explícitamente **qué hacer a continuación**, y no es
     * relleno: un aviso que cuenta un problema y no la salida es un aviso que
     * hace que alguien se vaya. El del saldo negativo es el caso claro —salir
     * de la deuda es corregir a otros, y no se le ocurre a nadie solo—.
     *
     * @param array<string, scalar|null> $payload
     */
    public function bodyOf(NotificationKind $kind, array $payload): string
    {
        $who = self::actor($payload) ?? 'Alguien';
        $work = self::work($payload);
        $about = null === $work ? 'un texto tuyo' : \sprintf('«%s»', $work);

        return match ($kind) {
            NotificationKind::CORRECTION_RECEIVED => \sprintf(
                '%s ha terminado de corregir %s. Entra a leerla cuando quieras.',
                $who,
                $about,
            ),
            NotificationKind::CORRECTION_REPLIED => \sprintf(
                '%s ha contestado a la corrección que le dejaste.',
                $who,
            ),
            NotificationKind::CORRECTION_UNLOCKED => 'Has vuelto a saldo positivo, así que las correcciones que te llegaron retenidas ya están abiertas.',
            NotificationKind::CORRECTION_CLOSED => \sprintf('Se ha cerrado una corrección de %s.', $about),
            NotificationKind::CHAPTER_COMMENT => \sprintf('%s ha dejado un comentario en %s.', $who, $about),
            NotificationKind::MENTION => \sprintf('%s te ha mencionado en una publicación.', $who),
            NotificationKind::ACCESS_REQUESTED => \sprintf(
                '%s quiere leer %s como lector beta. Puedes aceptar o rechazar la solicitud.',
                $who,
                $about,
            ),
            NotificationKind::ACCESS_REQUEST_RESOLVED => 'Ya hay respuesta a la solicitud de lectura que enviaste.',
            NotificationKind::BETA_READER_INVITATION => \sprintf('%s te ha invitado a leer %s.', $who, $about),
            NotificationKind::BETA_READER_ACCESS_REVOKED => \sprintf('Ya no tienes acceso a %s.', $about),
            NotificationKind::WRITING_BUDDY_PROPOSED => \sprintf('%s te propone ser su writing buddy.', $who),
            NotificationKind::SUBSCRIBED_AUTHOR_PUBLISHED => \sprintf('%s ha publicado %s.', $who, $about),
            NotificationKind::BALANCE_WENT_NEGATIVE => \sprintf(
                'Te has quedado con %s créditos. No hay nada que pagar: corrigiendo a otras personas vuelves a positivo, y las correcciones que te lleguen mientras tanto te esperan.',
                self::number($payload, 'balance'),
            ),
            NotificationKind::CLAIM_RESOLVED => 'Ya hay una resolución de la reclamación que presentaste.',
            NotificationKind::REACTIVATION_OFFER => \sprintf(
                '%s ha corregido %s mientras estabas fuera. Te faltan %s créditos para poder leerla, y se consiguen corrigiendo.',
                $who,
                $about,
                self::number($payload, 'creditsNeeded'),
            ),
            default => 'Entra en Lectores Beta para verlo.',
        };
    }

    /**
     * @param array<string, scalar|null> $payload
     */
    private static function actor(array $payload): ?string
    {
        $name = $payload['actorName'] ?? $payload['actorUsername'] ?? null;

        return \is_string($name) && '' !== $name ? $name : null;
    }

    /**
     * @param array<string, scalar|null> $payload
     */
    private static function work(array $payload): ?string
    {
        $title = $payload['workTitle'] ?? null;

        return \is_string($title) && '' !== $title ? $title : null;
    }

    /**
     * @param array<string, scalar|null> $payload
     */
    private static function number(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;

        return \is_int($value) ? (string) abs($value) : 'algunos';
    }
}
