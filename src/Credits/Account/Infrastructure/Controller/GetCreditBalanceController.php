<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Account\Infrastructure\Controller;

use LectoresBeta\Credits\Account\Application\Handler\GetCreditBalanceHandler;
use LectoresBeta\Credits\Account\Application\Query\GetCreditBalance;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `GET /api/v1/credits/balance` (`FEAT-CRD-001`).
 *
 * The identifier comes from the token and the route carries none, so there is
 * no ownership check to forget. A `/credits/{userId}/balance` would need one
 * on every request, and that is the kind of check that is missing exactly
 * once.
 *
 * Note what this class touches: `Security` and `UserInterface`, both
 * Symfony. **No class of `User`.** The identity of who is asking is framework
 * territory; the moment `Credits` imported `User`'s aggregate to read it, the
 * isolation would be gone.
 */
#[AsController]
final readonly class GetCreditBalanceController
{
    public function __construct(
        private GetCreditBalanceHandler $balance,
        private Security $security,
    ) {
    }

    public function __invoke(): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $response = new JsonResponse(['balance' => ($this->balance)(new GetCreditBalance($user->getUserIdentifier()))]);

        // The balance changes because of things the caller did not do —
        // somebody delivering a correction on their work — so a cached answer
        // is a wrong answer.
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
