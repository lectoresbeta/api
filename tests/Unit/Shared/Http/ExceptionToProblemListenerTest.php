<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Unit\Shared\Http;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;
use LectoresBeta\Shared\Infrastructure\Http\Problem\ExceptionToProblemListener;
use LectoresBeta\Shared\Infrastructure\Http\Problem\ProblemFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * La salida única de errores de la API. Lo que se comprueba aquí no es el
 * formato: es que **nada interno la cruza**.
 */
final class ExceptionToProblemListenerTest extends TestCase
{
    public function testABusinessFailureKeepsItsCodeAndItsCategory(): void
    {
        $response = $this->handle(new TokenHasExpired());

        self::assertSame(410, $response['status']);
        self::assertSame('ACTIVATION_TOKEN_EXPIRED', $response['body']['code']);
        self::assertSame('application/problem+json', $response['contentType']);
        self::assertSame(
            'https://api.lectoresbeta.com/problems/activation-token-expired',
            $response['body']['type'],
        );
    }

    /**
     * El criterio de `AGENTS.md`: ni trazas, ni SQL, ni nombres de clase, ni
     * rutas de fichero. Ni siquiera el mensaje, que lo escribimos nosotros
     * pero suele llevar dentro el nombre de una tabla.
     */
    public function testAnUnexpectedExceptionLeaksNothing(): void
    {
        $response = $this->handle(new \RuntimeException(
            'SQLSTATE[42P01]: relation "user_ctx.account" does not exist in /app/src/Foo.php',
        ));

        $serialised = json_encode($response['body'], \JSON_THROW_ON_ERROR);

        self::assertSame(500, $response['status']);
        self::assertSame('INTERNAL_ERROR', $response['body']['code']);

        foreach (['SQLSTATE', 'user_ctx', '/app/src', 'RuntimeException'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $serialised);
        }
    }

    public function testAFrameworkHttpExceptionBecomesAProblemToo(): void
    {
        $response = $this->handle(new NotFoundHttpException('No route found for "GET /nope"'));

        self::assertSame(404, $response['status']);
        self::assertSame('NOT_FOUND', $response['body']['code']);
        self::assertStringNotContainsString('/nope', json_encode($response['body'], \JSON_THROW_ON_ERROR));
    }

    /**
     * @return array{status: int, contentType: string, body: array<string, mixed>}
     */
    private function handle(\Throwable $throwable): array
    {
        $listener = new ExceptionToProblemListener(new ProblemFactory(), new NullLogger());

        $event = new ExceptionEvent(
            $this->createStub(HttpKernelInterface::class),
            Request::create('/api/v1/auth/activate', 'POST'),
            HttpKernelInterface::MAIN_REQUEST,
            $throwable,
        );

        $listener($event);

        $response = $event->getResponse();
        self::assertNotNull($response);

        /** @var array<string, mixed> $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return [
            'status' => $response->getStatusCode(),
            'contentType' => (string) $response->headers->get('Content-Type'),
            'body' => $body,
        ];
    }
}

final class TokenHasExpired extends \DomainException implements BusinessFailure
{
    public function __construct()
    {
        parent::__construct('The activation link has expired.');
    }

    public function errorCode(): string
    {
        return 'ACTIVATION_TOKEN_EXPIRED';
    }

    public function kind(): FailureKind
    {
        return FailureKind::GONE;
    }
}
