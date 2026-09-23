<?php

declare(strict_types=1);

namespace LectoresBeta\Shared\Infrastructure\Http;

use LectoresBeta\Shared\Application\Health\CheckHealth;
use LectoresBeta\Shared\Domain\Health\HealthReport;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

/**
 * Whether the system is up, and whether it can do its job.
 *
 * **Outside `/api/v1` on purpose.** The address of a health probe is wired
 * into orchestrators, load balancers and monitoring, all of which are updated
 * by people who are not reading release notes. It must not move when the API
 * version does.
 *
 * Two routes —declared in `config/routes/shared.yaml`, like every route in
 * this project— because they answer questions with different consequences:
 *
 * - `/health/live` says «this process is alive». It touches nothing.
 * - `/health` says «this process can serve traffic», and for that it checks
 *   PostgreSQL and RabbitMQ.
 *
 * Wiring a liveness probe to the second one is the classic way to turn a
 * database hiccup into a restart of every container at once, which is how a
 * blip becomes an outage. Hence the split.
 *
 * It is public: it has to answer before anybody can authenticate. The price
 * is that it must give away nothing — which is why the body carries the name
 * of each check and its status, and never the reason it failed.
 */
#[AsController]
final readonly class HealthController
{
    public function __construct(private CheckHealth $checkHealth)
    {
    }

    /**
     * Liveness. No I/O: a probe that can fail for a reason other than «this
     * process is stuck» is not a liveness probe.
     *
     * Routed from `config/routes/shared.yaml` as `checkLiveness`. Routes are
     * never declared with attributes in this project
     * (`decision:0010`).
     */
    public function live(): JsonResponse
    {
        return $this->json(['status' => 'up'], Response::HTTP_OK);
    }

    /**
     * Readiness. 200 when everything it depends on answers, 503 when
     * something does not.
     *
     * The failure body is the report itself and not an RFC 9457 `Problem`:
     * the interesting part of a failed health check is **which** dependency
     * broke, and that fits badly in a shape designed to describe one error.
     *
     * Routed from `config/routes/shared.yaml` as `checkReadiness`.
     */
    public function ready(): JsonResponse
    {
        $report = ($this->checkHealth)();

        return $this->json(
            $this->present($report),
            $report->isHealthy() ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function present(HealthReport $report): array
    {
        $checks = [];

        foreach ($report->results as $result) {
            $checks[$result->name] = array_filter(
                [
                    'status' => $result->status->value,
                    'durationMs' => $result->durationMs,
                    'detail' => $result->detail,
                ],
                static fn (mixed $value): bool => null !== $value,
            );
        }

        return [
            'status' => $report->status->value,
            'checkedAt' => $report->checkedAt->format(\DATE_ATOM),
            'checks' => $checks,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function json(array $payload, int $status): JsonResponse
    {
        $response = new JsonResponse($payload, $status);

        // Never cached. A cached health check is worse than no health check:
        // it reports the state of some earlier moment with total confidence.
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
