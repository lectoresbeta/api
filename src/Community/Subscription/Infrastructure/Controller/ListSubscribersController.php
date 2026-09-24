<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Subscription\Infrastructure\Controller;

use LectoresBeta\Community\Subscription\Application\Handler\ListSubscribersHandler;
use LectoresBeta\Community\Subscription\Application\Query\ListSubscribers;
use LectoresBeta\Community\Subscription\Infrastructure\Http\PeopleBody;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

/**
 * `GET /api/v1/users/{userId}/subscribers` (`FEAT-COM-027`).
 *
 * **Público**, como el perfil al que pertenece: una lista que solo se ve con
 * sesión haría inútil compartir el perfil que la contiene. Quien pasa sin
 * identificarse ve lo que es público, ni más ni menos.
 */
#[AsController]
final readonly class ListSubscribersController
{
    public function __construct(
        private ListSubscribersHandler $subscribers,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request, string $userId): Response
    {
        return new JsonResponse(PeopleBody::of(($this->subscribers)(new ListSubscribers(
            $userId,
            $this->security->getUser()?->getUserIdentifier(),
            PeopleBody::limit($request),
            PeopleBody::cursor($request),
        ))));
    }
}
