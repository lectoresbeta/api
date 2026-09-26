<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\BetaReaderAccess\Infrastructure\Controller;

use LectoresBeta\Reading\BetaReaderAccess\Application\Handler\ListWorkBetaReadersHandler;
use LectoresBeta\Reading\BetaReaderAccess\Application\Query\ListWorkBetaReaders;
use LectoresBeta\User\Account\Application\Contract\DirectoryEntry;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `GET /api/v1/works/{workId}/beta-readers` (`FEAT-RDG-010`).
 *
 * Solo el autor de la obra. Quién está leyendo una obra inédita es asunto
 * suyo y de nadie más: una obra ajena responde `404`, no `403`, porque un
 * `403` confirmaría que esa obra existe.
 */
#[AsController]
final readonly class ListWorkBetaReadersController
{
    public function __construct(
        private ListWorkBetaReadersHandler $readers,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request, string $workId): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $page = ($this->readers)(new ListWorkBetaReaders(
            $workId,
            $user->getUserIdentifier(),
            $request->query->has('limit') ? $request->query->getInt('limit') : null,
            \is_string($cursor = $request->query->get('cursor')) && '' !== $cursor ? $cursor : null,
        ));

        return new JsonResponse([
            'data' => array_map(
                static function (array $reader): array {
                    /** @var DirectoryEntry $profile */
                    $profile = $reader['profile'];

                    return [
                        'userId' => $profile->userId,
                        'username' => $profile->username,
                        'name' => $profile->name,
                        'avatarUrl' => $profile->avatarUrl,
                        'source' => $reader['source'],
                        'earned' => $reader['earned'],
                        'grantedAt' => $reader['grantedAt']->format(\DATE_ATOM),
                    ];
                },
                $page->readers,
            ),
            'pageInfo' => [
                'nextCursor' => $page->nextCursor,
                'hasNextPage' => null !== $page->nextCursor,
            ],
        ]);
    }
}
