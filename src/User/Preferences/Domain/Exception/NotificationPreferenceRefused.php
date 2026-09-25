<?php

declare(strict_types=1);

namespace LectoresBeta\User\Preferences\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureDetails;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * Ese ajuste no existe (`FEAT-USR-039`).
 */
final class NotificationPreferenceRefused extends \DomainException implements BusinessFailure, FailureDetails
{
    /**
     * @param array<string, scalar> $details
     */
    private function __construct(
        private readonly string $failureCode,
        private readonly array $details,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function unknownTopic(string $topic): self
    {
        return new self('UNKNOWN_NOTIFICATION_TOPIC', ['topic' => $topic], 'That notification topic does not exist.');
    }

    public static function unknownChannel(string $channel): self
    {
        return new self('UNKNOWN_NOTIFICATION_CHANNEL', ['channel' => $channel], 'That channel does not exist.');
    }

    /**
     * Un aviso que no existe en ese canal no se puede configurar ahí, y
     * aceptarlo en silencio sería prometer algo que nunca va a llegar: nadie
     * recibe un correo por cada mensaje directo por mucho que active la
     * casilla.
     */
    public static function channelNotAvailable(string $topic, string $channel): self
    {
        return new self(
            'CHANNEL_NOT_AVAILABLE_FOR_TOPIC',
            ['topic' => $topic, 'channel' => $channel],
            'That notification does not exist on that channel.',
        );
    }

    public function failureDetails(): array
    {
        return $this->details;
    }

    public function errorCode(): string
    {
        return $this->failureCode;
    }

    public function kind(): FailureKind
    {
        return FailureKind::INVALID;
    }
}
