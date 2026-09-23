<?php

declare(strict_types=1);

namespace LectoresBeta\Shared\Infrastructure\Messenger;

use LectoresBeta\Shared\Domain\Event\IntegrationEvent;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * What an integration event looks like on the wire.
 *
 * Messenger's own serializer writes the PHP class name into a header, which
 * means the consumer needs that exact class — and a bounded context that
 * imports another one's class has stopped being isolated. So the wire format
 * here is deliberately class-free: a name, an identifier, a timestamp and a
 * flat payload.
 *
 * ```text
 * headers: X-Event-Name: AccountActivated
 *          X-Event-Id:   0199...   X-Occurred-At: 2026-09-23T10:00:00+00:00
 * body:    {"userId":"0199...","activatedAt":"2026-09-23T10:00:00+00:00"}
 * ```
 *
 * Renaming a class is then a refactor and not a breaking change, which is the
 * property that matters: the contract is the name of the fact, and the name
 * of the fact is business language.
 */
final readonly class IntegrationEventSerializer implements SerializerInterface
{
    private const NAME = 'X-Event-Name';
    private const ID = 'X-Event-Id';
    private const OCCURRED_AT = 'X-Occurred-At';

    public function __construct(private IncomingEventRegistry $registry)
    {
    }

    public function encode(Envelope $envelope): array
    {
        $event = $envelope->getMessage();

        if (!$event instanceof IntegrationEvent) {
            throw new \LogicException(\sprintf('Only integration events travel on this transport; got %s.', get_debug_type($event)));
        }

        return [
            'body' => json_encode($event->payload(), \JSON_THROW_ON_ERROR),
            'headers' => [
                self::NAME => $event->eventName(),
                self::ID => $event->eventId(),
                self::OCCURRED_AT => $event->occurredAt()->format(\DATE_ATOM),
                'Content-Type' => 'application/json',
            ],
        ];
    }

    public function decode(array $encodedEnvelope): Envelope
    {
        $headers = $encodedEnvelope['headers'] ?? [];
        $name = $headers[self::NAME] ?? null;

        if (!\is_string($name) || '' === $name) {
            throw new MessageDecodingFailedException('The message carries no event name.');
        }

        $classes = $this->registry->classesFor($name);

        if ([] === $classes) {
            // Not an error: most facts are of no interest to this
            // application. Refusing loudly would send every unrelated event
            // to the failure queue.
            throw new MessageDecodingFailedException(\sprintf('Nothing subscribes to %s.', $name));
        }

        $payload = self::decodePayload($encodedEnvelope['body'] ?? '');
        $rawId = $headers[self::ID] ?? null;
        $eventId = \is_string($rawId) ? $rawId : '';
        $occurredAt = self::occurredAt($headers[self::OCCURRED_AT] ?? null);

        if ('' === $eventId) {
            throw new MessageDecodingFailedException(\sprintf('%s carries no event id, so it cannot be deduplicated.', $name));
        }

        // One fact, one message, even when several contexts subscribe: each
        // class is handled in turn and each deduplicates on its own.
        $first = $classes[0];

        return new Envelope($first::fromPayload($eventId, $occurredAt, $payload));
    }

    /**
     * @return array<string, string|int|float|bool|null>
     */
    private static function decodePayload(mixed $body): array
    {
        if (!\is_string($body) || '' === $body) {
            return [];
        }

        try {
            $decoded = json_decode($body, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new MessageDecodingFailedException('The payload is not valid JSON.', 0, $e);
        }

        if (!\is_array($decoded)) {
            throw new MessageDecodingFailedException('The payload is not an object.');
        }

        $payload = [];

        foreach ($decoded as $key => $value) {
            if (!\is_string($key) || (null !== $value && !\is_scalar($value))) {
                throw new MessageDecodingFailedException('An integration event payload must be flat scalars.');
            }

            $payload[$key] = $value;
        }

        return $payload;
    }

    private static function occurredAt(mixed $raw): \DateTimeImmutable
    {
        if (!\is_string($raw) || '' === $raw) {
            throw new MessageDecodingFailedException('The message carries no timestamp.');
        }

        $moment = \DateTimeImmutable::createFromFormat(\DATE_ATOM, $raw);

        if (false === $moment) {
            throw new MessageDecodingFailedException('The timestamp is not a valid RFC 3339 instant.');
        }

        return $moment;
    }
}
