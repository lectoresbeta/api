<?php

declare(strict_types=1);

namespace LectoresBeta\User\Authentication\Infrastructure\Controller;

use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use LectoresBeta\User\Authentication\Application\Command\CompleteOAuth;
use LectoresBeta\User\Authentication\Application\Handler\CompleteOAuthHandler;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * `POST /api/v1/auth/oauth/{provider}/callback` (`FEAT-USR-002`).
 *
 * El cliente manda el código que le devolvió el proveedor. Las versiones
 * legales son opcionales y su ausencia solo es un error cuando la operación
 * implicaría **crear** una cuenta (`RN-4`): quien solo inicia sesión no
 * vuelve a aceptar nada.
 *
 * Limitado por dirección, como el login: aquí no hay contraseña que probar,
 * pero sí un endpoint que dispara llamadas salientes a Google, y eso es algo
 * que conviene que nadie pueda hacer sin freno.
 */
#[AsController]
final readonly class CompleteOAuthController
{
    public function __construct(
        private CompleteOAuthHandler $complete,
        private RateLimiterFactory $byAddress,
    ) {
    }

    public function __invoke(Request $request, string $provider): Response
    {
        $this->ensureWithinLimits($request->getClientIp() ?? 'unknown');

        $body = JsonBody::of($request);
        $legal = $body->nested('acceptedLegalVersions');

        $outcome = ($this->complete)(new CompleteOAuth(
            $provider,
            (string) $body->string('code'),
            $body->string('redirectUri'),
            $legal->string('termsOfUse'),
            $legal->string('privacyPolicy'),
            $request->headers->get('User-Agent'),
            $request->getClientIp(),
        ));

        return new JsonResponse([
            'accessToken' => $outcome->session->accessToken,
            'refreshToken' => $outcome->session->refreshToken,
            'expiresIn' => $outcome->session->expiresIn,
            // Para que el cliente sepa adónde mandar a la persona sin
            // deducirlo del estado del onboarding, que es un detalle que
            // puede cambiar.
            'isNewAccount' => $outcome->isNewAccount,
            'linkedToExistingAccount' => $outcome->linkedToExistingAccount,
        ], $outcome->isNewAccount ? Response::HTTP_CREATED : Response::HTTP_OK);
    }

    private function ensureWithinLimits(string $clientIp): void
    {
        $limit = $this->byAddress->create($clientIp)->consume();

        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException($limit->getRetryAfter()->getTimestamp() - time());
        }
    }
}
