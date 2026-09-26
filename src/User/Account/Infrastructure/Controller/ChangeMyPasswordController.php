<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Infrastructure\Controller;

use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use LectoresBeta\User\Account\Application\Command\ChangeMyPassword;
use LectoresBeta\User\Account\Application\Handler\ChangeMyPasswordHandler;
use LectoresBeta\User\Account\Domain\Exception\IncorrectPassword;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * `PUT /api/v1/me/password` (`FEAT-USR-041`).
 *
 * `PUT` porque fija un estado: la cuenta acaba con esa contraseña, se
 * repita o no.
 *
 * **La limitación de frecuencia no es opcional** aquí. Sin ella, este
 * formulario se convierte en un sitio donde probar contraseñas contra una
 * sesión robada, sin las protecciones que sí tiene el login — y con el
 * premio de quedarse con la cuenta, no solo de entrar en ella.
 *
 * Solo se consume el contador cuando el intento **falla**: quien acierta a la
 * primera no debería quedarse sin margen por cambiar de contraseña dos veces
 * en una tarde.
 *
 * La respuesta no devuelve nada del usuario. Una respuesta a un cambio de
 * contraseña puede acabar en un log, y no necesita más superficie de la
 * imprescindible.
 */
#[AsController]
final readonly class ChangeMyPasswordController
{
    public function __construct(
        private ChangeMyPasswordHandler $changePassword,
        private Security $security,
        private RateLimiterFactory $byAccount,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $limiter = $this->byAccount->create($user->getUserIdentifier());

        // `consume(0)` es una consulta y **siempre acepta**, así que lo que
        // decide es cuántos intentos quedan. Preguntar sin consumir es lo que
        // permite cobrar solo los fallos.
        $remaining = $limiter->consume(0);

        if ($remaining->getRemainingTokens() < 1) {
            throw new TooManyRequestsHttpException(max(0, $remaining->getRetryAfter()->getTimestamp() - time()));
        }

        $body = JsonBody::of($request);

        try {
            ($this->changePassword)(new ChangeMyPassword(
                $user->getUserIdentifier(),
                $body->string('currentPassword'),
                (string) $body->string('newPassword'),
            ));
        } catch (IncorrectPassword $failure) {
            $limiter->consume();

            throw $failure;
        }

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
