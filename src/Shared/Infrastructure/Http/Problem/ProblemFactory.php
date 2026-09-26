<?php

declare(strict_types=1);

namespace LectoresBeta\Shared\Infrastructure\Http\Problem;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureDetails;
use LectoresBeta\Shared\Domain\Exception\FailureKind;
use LectoresBeta\Shared\Domain\Exception\RetryAfter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Turns anything thrown into an RFC 9457 response.
 *
 * The rule this class exists to keep is
 * [`docs/api/conventions/errors.md`](../../../../../docs/api/conventions/errors.md):
 * **nothing internal crosses the boundary.** No stack traces, no SQL, no
 * class names, no file paths. The message of an unexpected exception is not
 * shown either — it is written by us, but it routinely contains a table name
 * or a path, and deciding case by case is how those leak.
 */
final readonly class ProblemFactory
{
    public const UNEXPECTED = 'INTERNAL_ERROR';
    private const TYPE_PREFIX = 'https://api.lectoresbeta.com/problems/';

    public function fromBusinessFailure(BusinessFailure $failure): JsonResponse
    {
        $response = $this->build(
            self::statusFor($failure->kind()),
            $failure->errorCode(),
            $failure->getMessage(),
            extensions: $failure instanceof FailureDetails ? $failure->failureDetails() : [],
        );

        // `Retry-After` va en la cabecera y no solo en el cuerpo: es lo que
        // entienden los clientes HTTP y las bibliotecas de reintento sin que
        // nadie las programe.
        if ($failure instanceof RetryAfter) {
            $response->headers->set('Retry-After', (string) max(0, $failure->retryAfterSeconds()));
        }

        return $response;
    }

    /**
     * @param list<array{field: string, code: string, message: string}> $violations
     */
    public function fromValidation(array $violations): JsonResponse
    {
        return $this->build(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            'VALIDATION_FAILED',
            'Some fields are not valid.',
            $violations,
        );
    }

    /**
     * For an exception we did not expect. The caller is told nothing beyond
     * the fact that it failed; the detail belongs in the log.
     */
    public function unexpected(): JsonResponse
    {
        return $this->build(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::UNEXPECTED,
            'The request could not be completed.',
        );
    }

    public function fromStatus(int $status, string $code, string $detail): JsonResponse
    {
        return $this->build($status, $code, $detail);
    }

    public static function statusFor(FailureKind $kind): int
    {
        return match ($kind) {
            FailureKind::INVALID => Response::HTTP_UNPROCESSABLE_ENTITY,
            FailureKind::UNAUTHENTICATED => Response::HTTP_UNAUTHORIZED,
            FailureKind::NOT_FOUND => Response::HTTP_NOT_FOUND,
            FailureKind::CONFLICT => Response::HTTP_CONFLICT,
            FailureKind::FORBIDDEN => Response::HTTP_FORBIDDEN,
            FailureKind::GONE => Response::HTTP_GONE,
            FailureKind::RATE_LIMITED => Response::HTTP_TOO_MANY_REQUESTS,
            FailureKind::TOO_LARGE => Response::HTTP_REQUEST_ENTITY_TOO_LARGE,
            FailureKind::UPSTREAM_FAILED => Response::HTTP_BAD_GATEWAY,
        };
    }

    /**
     * @param list<array{field: string, code: string, message: string}> $violations
     * @param array<string, scalar>                                     $extensions miembros de extensión
     *                                                                              de RFC 9457. Van al
     *                                                                              nivel superior y
     *                                                                              **nunca** pisan los
     *                                                                              campos del formato
     */
    private function build(int $status, string $code, string $detail, array $violations = [], array $extensions = []): JsonResponse
    {
        $body = [
            'type' => self::TYPE_PREFIX.self::slug($code),
            'title' => self::titleFor($status),
            'status' => $status,
            'code' => $code,
            'detail' => $detail,
        ];

        foreach ($extensions as $member => $value) {
            if (!\array_key_exists($member, $body)) {
                $body[$member] = $value;
            }
        }

        if ([] !== $violations) {
            $body['errors'] = $violations;
        }

        $response = new JsonResponse($body, $status);
        $response->headers->set('Content-Type', 'application/problem+json');

        return $response;
    }

    private static function slug(string $code): string
    {
        return str_replace('_', '-', strtolower($code));
    }

    private static function titleFor(int $status): string
    {
        return Response::$statusTexts[$status] ?? 'Error';
    }
}
