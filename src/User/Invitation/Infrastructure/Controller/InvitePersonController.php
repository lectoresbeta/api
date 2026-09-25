<?php

declare(strict_types=1);

namespace LectoresBeta\User\Invitation\Infrastructure\Controller;

use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use LectoresBeta\User\Invitation\Application\Command\InvitePersonToThePlatform;
use LectoresBeta\User\Invitation\Application\Handler\InvitePersonToThePlatformHandler;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `POST /api/v1/invitations` (`FEAT-USR-018`).
 *
 * **Responde `202` siempre que la petición sea válida**, se mande el correo o
 * no. Invitar a una dirección que ya tiene cuenta no lo dice: eso convertiría
 * este endpoint en un comprobador de quién está en la plataforma, que es
 * justo lo que el alta y la recuperación de contraseña se cuidan de no decir.
 *
 * `202` y no `201` porque es exacto: lo que ha pasado al responder es que se
 * ha aceptado el encargo. El correo sale por la cola, y puede que no salga
 * ninguno.
 */
#[AsController]
final readonly class InvitePersonController
{
    public function __construct(
        private InvitePersonToThePlatformHandler $invite,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        ($this->invite)(new InvitePersonToThePlatform(
            $user->getUserIdentifier(),
            JsonBody::of($request)->string('email'),
        ));

        return new JsonResponse(null, Response::HTTP_ACCEPTED);
    }
}
