<?php

declare(strict_types=1);

namespace LectoresBeta\User\Preferences\Application\Handler;

use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Preferences\Application\Command\UpdateNotificationPreferences;
use LectoresBeta\User\Preferences\Application\Contract\NotificationChoices;
use LectoresBeta\User\Preferences\Domain\Entity\NotificationPreference;
use LectoresBeta\User\Preferences\Domain\Entity\UserNotificationSettings;
use LectoresBeta\User\Preferences\Domain\Enum\NotificationChannel;
use LectoresBeta\User\Preferences\Domain\Enum\NotificationTopic;
use LectoresBeta\User\Preferences\Domain\Event\NotificationPreferencesChanged;
use LectoresBeta\User\Preferences\Domain\Event\ReactivationOfferChoiceChanged;
use LectoresBeta\User\Preferences\Domain\Exception\NotificationPreferenceRefused;
use LectoresBeta\User\Preferences\Domain\Repository\NotificationPreferenceRepository;
use LectoresBeta\User\Profile\Domain\Exception\ProfileNotFound;

/**
 * Guardar qué avisos quiere recibir alguien (`FEAT-USR-039`).
 *
 * **El interruptor general y las casillas se guardan por separado** (`RN-2`),
 * y esa separación es toda la funcionalidad: el interruptor suspende y no
 * sobrescribe, así que apagarlo devuelve a su dueño la configuración que
 * tenía. Guardarlo como «todas las casillas a `false`» habría hecho imposible
 * devolvérsela.
 *
 * Es un `PUT` parcial a propósito: llega lo que se ha tocado y no el
 * catálogo entero. Una pantalla con veinte avisos y dos canales envía dos
 * campos al pulsar una casilla, no cuarenta.
 *
 * **Un canal que ese aviso no admite se rechaza** en vez de guardarse: nadie
 * recibe un correo por cada mensaje directo por mucho que active la casilla,
 * y aceptarlo en silencio sería prometérselo.
 */
final readonly class UpdateNotificationPreferencesHandler
{
    public function __construct(
        private NotificationPreferenceRepository $preferences,
        private NotificationChoices $choices,
        private TransactionalSession $session,
        private EventPublisher $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(UpdateNotificationPreferences $command): void
    {
        try {
            $owner = UserId::fromString($command->userId);
        } catch (InvalidValue) {
            throw ProfileNotFound::create();
        }

        $now = $this->clock->now();
        $changed = [];
        $toSave = [];

        foreach ($command->choices as $choice) {
            $topic = NotificationTopic::tryFrom($choice['topic'])
                ?? throw NotificationPreferenceRefused::unknownTopic($choice['topic']);
            $channel = NotificationChannel::tryFrom($choice['channel'])
                ?? throw NotificationPreferenceRefused::unknownChannel($choice['channel']);

            if (!$topic->admits($channel)) {
                throw NotificationPreferenceRefused::channelNotAvailable($topic->value, $channel->value);
            }

            $existing = $this->preferences->one($owner, $topic, $channel);

            if (null === $existing) {
                $toSave[] = new NotificationPreference($owner, $topic, $channel, $choice['enabled'], $now);
            } else {
                $existing->set($choice['enabled'], $now);
                $toSave[] = $existing;
            }

            $changed[] = $topic->value;
        }

        $settings = null;

        if (null !== $command->allMuted) {
            $settings = $this->preferences->settingsOf($owner) ?? new UserNotificationSettings($owner, $now);
            $settings->muteEverything($command->allMuted, $now);
        }

        $this->session->execute(function () use ($toSave, $settings): void {
            foreach ($toSave as $preference) {
                $this->preferences->save($preference);
            }

            if (null !== $settings) {
                $this->preferences->saveSettings($settings);
            }
        });

        if ([] === $changed && null === $command->allMuted) {
            // Nada que contar: un `PUT` vacío es una pantalla que se guardó
            // sin tocar nada.
            return;
        }

        $this->events->publish(new NotificationPreferencesChanged(
            EventId::generate(),
            $owner->value(),
            array_values(array_unique($changed)),
            $command->allMuted,
            $now,
        ));

        $this->announceReactivationChoice($owner, $changed, null !== $command->allMuted, $now);
    }

    /**
     * El gancho de reactivación se apaga desde esta misma pantalla, y
     * apagarlo renuncia al mecanismo entero y no solo al correo
     * (`FEAT-CRD-019` `RN-2d`, `RN-8`).
     *
     * Se anuncia **la respuesta efectiva**, no la casilla: quien silencia
     * todo ha renunciado aunque la casilla siga marcada, y hacérselo deducir
     * a `Credits` sería repartir esta regla entre dos contextos.
     *
     * @param list<string> $changed
     */
    private function announceReactivationChoice(
        UserId $owner,
        array $changed,
        bool $touchedMute,
        \DateTimeImmutable $now,
    ): void {
        if (!$touchedMute && !\in_array(NotificationTopic::REACTIVATION_OFFER->value, $changed, true)) {
            return;
        }

        $this->events->publish(new ReactivationOfferChoiceChanged(
            EventId::generate(),
            $owner->value(),
            $this->choices->allows(
                $owner->value(),
                NotificationTopic::REACTIVATION_OFFER->value,
                NotificationChannel::EMAIL->value,
            ),
            $now,
        ));
    }
}
