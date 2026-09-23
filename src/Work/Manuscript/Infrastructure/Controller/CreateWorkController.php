<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Infrastructure\Controller;

use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use LectoresBeta\Work\Manuscript\Application\Command\CreateWork;
use LectoresBeta\Work\Manuscript\Application\Handler\CreateWorkHandler;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `POST /api/v1/works` (`FEAT-WRK-001`).
 *
 * **El autor es siempre quien llama.** Un `authorId` en el cuerpo se ignora
 * en silencio, porque aceptarlo sería una vía directa a crear obras en nombre
 * de otro.
 *
 * Es además la primera escritura del proyecto **no exenta** de exigir cuenta
 * activada: una cuenta en `PENDING_ACTIVATION` recibe aquí un `403`
 * (`FEAT-USR-025`), y este endpoint es la primera prueba de que esa barrera
 * bloquea y no solo de que deja pasar.
 */
#[AsController]
final readonly class CreateWorkController
{
    public function __construct(
        private CreateWorkHandler $create,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $body = JsonBody::of($request);

        $workId = ($this->create)(new CreateWork(
            $user->getUserIdentifier(),
            (string) $body->string('title'),
            $body->string('synopsis'),
        ));

        return new JsonResponse(['workId' => $workId], Response::HTTP_CREATED);
    }
}
