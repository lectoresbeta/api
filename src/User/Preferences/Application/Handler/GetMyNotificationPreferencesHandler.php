<?php

declare(strict_types=1);

namespace LectoresBeta\User\Preferences\Application\Handler;

use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Preferences\Application\DTO\NotificationPreferences;
use LectoresBeta\User\Preferences\Application\Query\GetMyNotificationPreferences;
use LectoresBeta\User\Preferences\Domain\Entity\NotificationPreference;
use LectoresBeta\User\Preferences\Domain\Enum\NotificationTopic;
use LectoresBeta\User\Preferences\Domain\Repository\NotificationPreferenceRepository;

/**
 * Lo que la pantalla de notificaciones necesita para abrirse
 * (`FEAT-USR-039`).
 *
 * Devuelve **el catálogo entero**, con el valor que cada casilla tiene ahora
 * mismo: el explícito si lo hay, el de fábrica si no. La pantalla no tiene
 * que saber cuáles existen ni cuáles vienen activadas.
 *
 * El interruptor general va aparte y no plegado en los valores, porque lo que
 * hace es **suspender** y no sobrescribir: con él encendido, las casillas
 * siguen diciendo lo que su dueño eligió, que es lo que le devolverá al
 * apagarlo.
 */
final readonly class GetMyNotificationPreferencesHandler
{
    public function __construct(private NotificationPreferenceRepository $preferences)
    {
    }

    public function __invoke(GetMyNotificationPreferences $query): NotificationPreferences
    {
        try {
            $owner = UserId::fromString($query->userId);
        } catch (InvalidValue) {
            return new NotificationPreferences(false, []);
        }

        $chosen = [];

        foreach ($this->preferences->ofUser($owner) as $preference) {
            $chosen[self::key($preference)] = $preference->isEnabled();
        }

        $topics = [];

        foreach (NotificationTopic::cases() as $topic) {
            $channels = [];

            foreach ($topic->channels() as $channel) {
                $channels[$channel->value] = $chosen[$topic->value.'|'.$channel->value] ?? $topic->defaultEnabled();
            }

            $topics[] = ['topic' => $topic->value, 'channels' => $channels];
        }

        return new NotificationPreferences(
            true === $this->preferences->settingsOf($owner)?->allMuted(),
            $topics,
        );
    }

    private static function key(NotificationPreference $preference): string
    {
        return $preference->topic()->value.'|'.$preference->channel()->value;
    }
}
