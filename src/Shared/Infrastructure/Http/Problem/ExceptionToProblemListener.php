<?php

declare(strict_types=1);

namespace LectoresBeta\Shared\Infrastructure\Http\Problem;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * The single place where an exception becomes an API response.
 *
 * Centralising it is what makes «do not expose stack traces or internal
 * details» enforceable: there is one exit, so there is one thing to get
 * right. A controller that caught its own exceptions would be a second exit,
 * and the first one to forget is the one that leaks.
 *
 * It only acts on requests it should: a non-API path —the health probes— is
 * left to Symfony.
 */
#[AsEventListener(event: KernelEvents::EXCEPTION, priority: 0)]
final readonly class ExceptionToProblemListener
{
    public function __construct(
        private ProblemFactory $problems,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $throwable = $event->getThrowable();

        if ($throwable instanceof BusinessFailure) {
            // An expected outcome, not an incident: logged at info so that a
            // wall of `422`s never buries a real failure.
            $this->logger->info('Business rule refused the request.', [
                'code' => $throwable->errorCode(),
            ]);

            $event->setResponse($this->problems->fromBusinessFailure($throwable));

            return;
        }

        if ($throwable instanceof HttpExceptionInterface) {
            $event->setResponse($this->problems->fromStatus(
                $throwable->getStatusCode(),
                self::codeForStatus($throwable->getStatusCode()),
                'The request could not be completed.',
            ));

            return;
        }

        // Anything else is a bug. The detail goes to the log, where it is
        // ours; the caller gets a `500` and nothing else.
        $this->logger->error('Unhandled exception.', ['exception' => $throwable]);

        $event->setResponse($this->problems->unexpected());
    }

    private static function codeForStatus(int $status): string
    {
        return match ($status) {
            400 => 'BAD_REQUEST',
            401 => 'UNAUTHENTICATED',
            403 => 'FORBIDDEN',
            404 => 'NOT_FOUND',
            405 => 'METHOD_NOT_ALLOWED',
            415 => 'UNSUPPORTED_MEDIA_TYPE',
            429 => 'TOO_MANY_REQUESTS',
            default => ProblemFactory::UNEXPECTED,
        };
    }
}
