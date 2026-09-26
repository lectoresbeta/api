<?php

declare(strict_types=1);

namespace LectoresBeta\User\Preferences\Domain\Enum;

/**
 * El catálogo de lo que se puede configurar (`FEAT-USR-039`).
 *
 * **Solo lo silenciable.** Activación, restablecimiento de contraseña,
 * cambios de correo, sanciones, bloqueos de obra, avisos a moderadores y
 * comunicaciones legales no están aquí y no es un olvido: son **operativos**
 * y quedan fuera de estas preferencias, incluido el interruptor general
 * (`RN-3`). Un catálogo que los incluyera invitaría a apagarlos.
 *
 * Los nombres coinciden con los de `NotificationKind` en `Notification`, y
 * esa coincidencia es deliberada y no un acoplamiento: son dos preguntas
 * distintas —**qué se puede configurar** y **qué se entrega**— que se
 * responden en dos contextos, y compartir el vocabulario es lo que permite
 * cruzarlas sin una tabla de traducción que alguien tendría que mantener.
 *
 * Un tipo que se entrega y no está aquí toma el valor por defecto, que es
 * «activado» (`RN-4`): un aviso nuevo llega hasta que alguien decida que se
 * puede apagar, y no al revés.
 */
enum NotificationTopic: string
{
    // Actividad sobre lo que escribo.
    case CORRECTION_RECEIVED = 'CORRECTION_RECEIVED';
    case CORRECTION_CLOSED = 'CORRECTION_CLOSED';
    case CHAPTER_COMMENT = 'CHAPTER_COMMENT';

    /**
     * Ampliar un capítulo lo ha encarecido (`FEAT-CRD-016` `RN-9`).
     *
     * Solo campana: es una consecuencia de algo que el autor acaba de hacer,
     * y se entera mientras sigue en la pantalla donde lo hizo. Un correo por
     * cada guardado sería el que enseña a ignorar el remitente.
     */
    case CHAPTER_PRICE_INCREASED = 'CHAPTER_PRICE_INCREASED';
    case POST_REPLY = 'POST_REPLY';
    case MENTION = 'MENTION';

    // Mi actividad como corrector.
    case CORRECTION_RATED = 'CORRECTION_RATED';
    case CORRECTION_REPLIED = 'CORRECTION_REPLIED';
    case CORRECTION_UNLOCKED = 'CORRECTION_UNLOCKED';

    // Relación con otros.
    case SUBSCRIBED_AUTHOR_PUBLISHED = 'SUBSCRIBED_AUTHOR_PUBLISHED';
    case DIRECT_MESSAGE_RECEIVED = 'DIRECT_MESSAGE_RECEIVED';
    case ACCESS_REQUESTED = 'ACCESS_REQUESTED';
    case ACCESS_REQUEST_RESOLVED = 'ACCESS_REQUEST_RESOLVED';
    case BETA_READER_INVITATION = 'BETA_READER_INVITATION';
    case BETA_READER_ACCESS_REVOKED = 'BETA_READER_ACCESS_REVOKED';
    case WRITING_BUDDY_PROPOSED = 'WRITING_BUDDY_PROPOSED';

    /**
     * La resolución de una reclamación que presentó esta persona
     * (`FEAT-MOD-002`).
     *
     * **Faltaba**, y lo destapó la comprobación de catálogos de
     * `FEAT-NOT-002`: el aviso salía por correo y no había forma de apagarlo.
     * No es operativo —nadie se queda sin cuenta por no leerlo— así que le
     * toca casilla como a cualquier otro.
     */
    case CLAIM_RESOLVED = 'CLAIM_RESOLVED';

    // Créditos.
    case CREDITS_ADDED = 'CREDITS_ADDED';
    case CREDITS_SPENT = 'CREDITS_SPENT';
    case BALANCE_WENT_NEGATIVE = 'BALANCE_WENT_NEGATIVE';

    /**
     * El gancho de reactivación (`FEAT-CRD-019`).
     *
     * **Apagarlo es renunciar al mecanismo entero** (`RN-2d`, `RN-8`), no
     * solo al correo: sin aviso, un descubierto no es un gancho sino deuda a
     * espaldas de alguien. `Credits` escucha este ajuste y deja de
     * seleccionar a quien lo apaga.
     */
    case REACTIVATION_OFFER = 'REACTIVATION_OFFER';

    // Divulgación. No existen en la plataforma: no son actividad.
    case PLATFORM_UPDATES = 'PLATFORM_UPDATES';
    case USAGE_TIPS = 'USAGE_TIPS';

    /**
     * En qué canales existe este aviso.
     *
     * Sin `default` a propósito: un tipo nuevo tiene que declarar sus canales
     * o el análisis estático se queja, que es la única forma de que la lista
     * no envejezca a espaldas de nadie.
     *
     * @return list<NotificationChannel>
     */
    public function channels(): array
    {
        return match ($this) {
            // Nadie quiere un correo por cada mensaje directo ni por cada
            // respuesta a un comentario suyo: son de ritmo social.
            self::POST_REPLY,
            self::DIRECT_MESSAGE_RECEIVED,
            self::CORRECTION_RATED,
            self::CHAPTER_PRICE_INCREASED,
            self::CREDITS_ADDED,
            self::CREDITS_SPENT => [NotificationChannel::PLATFORM],

            // Y la divulgación es lo contrario: se lee en el buzón, no es
            // actividad que mirar en una campana.
            //
            // El gancho de reactivación va aquí por el mismo motivo y por uno
            // más: se le tiende a quien lleva meses sin entrar, así que una
            // campana que no va a mirar no es un aviso. Un solo canal hace
            // además que apagarlo sea inequívoco, que es lo que `Credits`
            // necesita para dejar de seleccionar a esa persona.
            self::REACTIVATION_OFFER,
            self::PLATFORM_UPDATES,
            self::USAGE_TIPS => [NotificationChannel::EMAIL],

            default => [NotificationChannel::EMAIL, NotificationChannel::PLATFORM],
        };
    }

    /**
     * Si viene activado de fábrica.
     *
     * **Todo sí, salvo las actualizaciones de la plataforma** (`RN-4`). Que
     * el valor por defecto sea explícito por tipo y no un booleano global es
     * lo que permite añadir un aviso nuevo sin migración: no hay fila hasta
     * que alguien cambia algo.
     */
    public function defaultEnabled(): bool
    {
        return self::PLATFORM_UPDATES !== $this;
    }

    public function admits(NotificationChannel $channel): bool
    {
        return \in_array($channel, $this->channels(), true);
    }
}
