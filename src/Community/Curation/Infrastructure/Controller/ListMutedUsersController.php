<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Curation\Infrastructure\Controller;

use LectoresBeta\Community\Curation\Application\Handler\ListMutedUsersHandler;
use LectoresBeta\Community\Curation\Application\Query\ListMutedUsers;
use LectoresBeta\Community\Subscription\Infrastructure\Http\PeopleBody;
use LectoresBeta\User\Account\Application\Contract\DirectoryEntry;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `GET /api/v1/me/muted-users` (`FEAT-COM-033`).
 *
 * **Hace falta**, y aquí más que en la de bloqueados: quien silenció a
 * alguien hace tres meses no recuerda su `@usuario` y, además, ya no le ve
 * pasar por el muro — que es justamente el efecto. Sin esta lista, silenciar
 * sería irreversible en la práctica.
 *
 * Solo la propia. A quién silencia otra persona no es asunto de nadie.
 */
#[AsController]
final readonly class ListMutedUsersController
{
    public function __construct(
        private ListMutedUsersHandler $muted,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $page = ($this->muted)(new ListMutedUsers(
            $user->getUserIdentifier(),
            PeopleBody::cursor($request),
            PeopleBody::limit($request),
        ));

        return new JsonResponse([
            'data' => array_map(
                static function (array $person): array {
                    /** @var DirectoryEntry $profile */
                    $profile = $person['profile'];

                    return [
                        'userId' => $profile->userId,
                        'username' => $profile->username,
                        'name' => $profile->name,
                        'avatarUrl' => $profile->avatarUrl,
                        'mutedAt' => $person['mutedAt']->format(\DATE_ATOM),
                    ];
                },
                $page->people,
            ),
            'pageInfo' => [
                'nextCursor' => $page->nextCursor,
                'hasNextPage' => null !== $page->nextCursor,
            ],
        ]);
    }
}
