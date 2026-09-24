<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\AccessInvitation\Infrastructure\Controller;

use LectoresBeta\Reading\AccessInvitation\Application\DTO\InvitableReader;
use LectoresBeta\Reading\AccessInvitation\Application\Handler\SearchInvitableReadersHandler;
use LectoresBeta\Reading\AccessInvitation\Application\Query\SearchInvitableReaders;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `GET /api/v1/works/{workId}/invitable-readers` (`FEAT-RDG-006`).
 *
 * Escritura anticipada para invitar. **Sin paginación y sin total**, y es una
 * excepción justificada a la convención de paginar toda colección: no es una
 * colección que alguien recorre, es una ayuda a escribir un nombre. Con
 * cursor, veinte peticiones devolverían el directorio entero igual, solo que
 * más despacio.
 *
 * Una consulta demasiado corta devuelve lista vacía y **no un error**: el
 * cliente la manda en cada pulsación, y la primera letra de un nombre no es
 * una equivocación de nadie.
 */
#[AsController]
final readonly class SearchInvitableReadersController
{
    public function __construct(
        private SearchInvitableReadersHandler $search,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request, string $workId): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $query = $request->query->get('query');

        $readers = ($this->search)(new SearchInvitableReaders(
            $workId,
            $user->getUserIdentifier(),
            \is_string($query) ? $query : null,
        ));

        return new JsonResponse([
            'readers' => array_map(
                static fn (InvitableReader $reader): array => [
                    'userId' => $reader->userId,
                    'username' => $reader->username,
                    'name' => $reader->name,
                    'avatarUrl' => $reader->avatarUrl,
                ],
                $readers,
            ),
        ]);
    }
}
