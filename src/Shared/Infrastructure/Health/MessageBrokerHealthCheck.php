<?php

declare(strict_types=1);

namespace LectoresBeta\Shared\Infrastructure\Health;

use LectoresBeta\Shared\Domain\Health\CheckResult;
use LectoresBeta\Shared\Domain\Health\HealthCheck;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\Connection;

/**
 * Asks RabbitMQ whether it is there.
 *
 * It opens a connection and a channel, and declares nothing. `countMessages()`
 * would be the obvious probe and is the wrong one: the Symfony bridge
 * implements it with `declareQueue()`, so probing would **create** the queue
 * it is asking about. A health check has no business changing the thing it
 * inspects.
 *
 * Why RabbitMQ is worth checking at all: nothing in the API waits for it —
 * `decision:0006` removed the last case where one context blocked on another—
 * so a broker that is down does not break a single request. What it breaks is
 * everything that happens afterwards: the welcome credits are never paid, the
 * activation email is never sent, and the only visible symptom is a queue
 * that grows. That is precisely the kind of failure worth a probe.
 */
final readonly class MessageBrokerHealthCheck implements HealthCheck
{
    public function __construct(
        private string $transportDsn,
        private LoggerInterface $logger,
    ) {
    }

    public function name(): string
    {
        return 'message_broker';
    }

    public function run(): CheckResult
    {
        if (!str_starts_with($this->transportDsn, 'amqp')) {
            // In tests the transport is in-memory. Saying «down» would make
            // the suite depend on a broker; saying «up» would be a lie.
            return CheckResult::skipped(
                $this->name(),
                \sprintf('El transporte configurado no es AMQP (%s).', $this->scheme()),
            );
        }

        $startedAt = microtime(true);

        try {
            Connection::fromDsn($this->transportDsn)->channel();

            return CheckResult::up($this->name(), $this->elapsedMs($startedAt));
        } catch (\Throwable $failure) {
            $this->logger->error('El chequeo de salud de RabbitMQ ha fallado.', [
                'exception' => $failure,
            ]);

            return CheckResult::down($this->name(), $this->elapsedMs($startedAt));
        }
    }

    /**
     * Not `parse_url()`: PHP refuses `in-memory://` because of the hyphen,
     * even though RFC 3986 allows one in a scheme. Everything before `://`
     * is enough, and this string only ever reaches a health report.
     */
    private function scheme(): string
    {
        $separator = strpos($this->transportDsn, '://');

        return false === $separator
            ? 'desconocido'
            : substr($this->transportDsn, 0, $separator);
    }

    private function elapsedMs(float $startedAt): float
    {
        return round((microtime(true) - $startedAt) * 1000, 2);
    }
}
