<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Relationship\Infrastructure\Controller;

use LectoresBeta\Community\Relationship\Application\Handler\ListBlockedUsersHandler;
use LectoresBeta\Community\Relationship\Application\Query\ListBlockedUsers;
use LectoresBeta\Community\Subscription\Infrastructure\Http\PeopleBody;
use LectoresBeta\User\Account\Application\Contract\DirectoryEntry;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `GET /api/v1/me/blocked-users` (`FEAT-COM-034`).
 *
 * **Hace falta**: sin esta lista, un bloqueo sería irreversible en la
 * práctica, porque quien bloqueó a alguien hace tres meses no recuerda su
 * `@usuario` y no puede encontrarlo para deshacerlo.
 *
 * Solo la propia. Quién ha bloqueado otra persona no es asunto de nadie.
 */
#[AsController]
final readonly class ListBlockedUsersController
{
    public function __construct(
        private ListBlockedUsersHandler $blocked,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $page = ($this->blocked)(new ListBlockedUsers(
            $user->getUserIdentifier(),
            PeopleBody::limit($request),
            PeopleBody::cursor($request),
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
                        'blockedAt' => $person['blockedAt']->format(\DATE_ATOM),
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
