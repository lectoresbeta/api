<?php

declare(strict_types=1);

namespace LectoresBeta\User\Authentication\Infrastructure\Controller;

use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use LectoresBeta\User\Authentication\Application\Command\LogIn;
use LectoresBeta\User\Authentication\Application\Handler\LogInHandler;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * `POST /api/v1/auth/login` (`FEAT-USR-004`).
 *
 * Rate limited **by address and by account** (`RN-13`), and the two limits
 * answer different attacks: by address stops one machine trying thousands of
 * passwords, by account stops a botnet trying a few passwords against one
 * person from a thousand addresses. Only the first is useless on its own.
 *
 * The account limiter is keyed on the submitted email, which means it can be
 * used to lock somebody out by failing repeatedly on purpose. That is why the
 * limit is per attempt and generous, and why the address limit is the tighter
 * of the two.
 */
#[AsController]
final readonly class LogInController
{
    public function __construct(
        private LogInHandler $logIn,
        private RateLimiterFactory $byAddress,
        private RateLimiterFactory $byAccount,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $body = JsonBody::of($request);
        $email = (string) $body->string('email');

        $this->ensureWithinLimits($request->getClientIp() ?? 'unknown', $email);

        $session = ($this->logIn)(new LogIn(
            $email,
            (string) $body->string('password'),
            $request->headers->get('User-Agent'),
        ));

        return new JsonResponse([
            'accessToken' => $session->accessToken,
            'refreshToken' => $session->refreshToken,
            'expiresIn' => $session->expiresIn,
        ]);
    }

    private function ensureWithinLimits(string $clientIp, string $email): void
    {
        $limits = [
            $this->byAddress->create($clientIp)->consume(),
            $this->byAccount->create(strtolower(trim($email)))->consume(),
        ];

        foreach ($limits as $limit) {
            if (!$limit->isAccepted()) {
                throw new TooManyRequestsHttpException($limit->getRetryAfter()->getTimestamp() - time());
            }
        }
    }
}
