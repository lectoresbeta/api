<?php

declare(strict_types=1);

namespace LectoresBeta\User\Preferences\Domain\Entity;

use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Preferences\Domain\Enum\NotificationChannel;
use LectoresBeta\User\Preferences\Domain\Enum\NotificationTopic;

/**
 * Una decisión explícita sobre un aviso y un canal (`FEAT-USR-039`).
 *
 * **Una fila por preferencia cambiada, no una columna por tipo.** Es lo que
 * permite que un aviso nuevo no exija migración ni despliegue del cliente: lo
 * que no tiene fila toma su valor por defecto, y ese valor lo declara el
 * propio tipo.
 *
 * Una tabla con una columna por aviso habría envejecido al primer tipo nuevo,
 * y el catálogo de esta funcionalidad ya trae veinte.
 */
class NotificationPreference
{
    private string $userId;

    /**
     * Guardados como texto y no como enumerado, porque son parte de la clave
     * primaria y el mapeo XML no admite enumerados ahí. El tipo sigue siendo
     * el enumerado para todo el que los use: la conversión vive en los dos
     * accesores y en ningún otro sitio.
     */
    private string $topic;

    private string $channel;

    private bool $enabled;

    private \DateTimeImmutable $updatedAt;

    public function __construct(
        UserId $userId,
        NotificationTopic $topic,
        NotificationChannel $channel,
        bool $enabled,
        \DateTimeImmutable $now,
    ) {
        $this->userId = $userId->value();
        $this->topic = $topic->value;
        $this->channel = $channel->value;
        $this->enabled = $enabled;
        $this->updatedAt = $now;
    }

    public function topic(): NotificationTopic
    {
        return NotificationTopic::from($this->topic);
    }

    public function channel(): NotificationChannel
    {
        return NotificationChannel::from($this->channel);
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function set(bool $enabled, \DateTimeImmutable $now): void
    {
        $this->enabled = $enabled;
        $this->updatedAt = $now;
    }
}
