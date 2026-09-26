<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\WritingBuddy\Infrastructure\Controller;

use LectoresBeta\Reading\WritingBuddy\Application\DTO\WritingBuddyView;
use LectoresBeta\Reading\WritingBuddy\Application\Handler\ListMyWritingBuddiesHandler;
use LectoresBeta\Reading\WritingBuddy\Application\Query\ListMyWritingBuddies;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `GET /api/v1/me/writing-buddies` (`FEAT-RDG-009`).
 *
 * Los vivos: propuestos y aceptados, en una sola lista porque son una sola
 * pantalla. Lo que cambia por fila es si hay que decidir algo o si toca
 * esperar, y eso lo dicen `status` y `proposedByMe`.
 *
 * Bajo `/me/` porque no existe la versión de otra persona: con quién tiene
 * alguien un vínculo de escritura no es una lista que se pueda pedir.
 */
#[AsController]
final readonly class ListMyWritingBuddiesController
{
    public function __construct(
        private ListMyWritingBuddiesHandler $buddies,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $links = ($this->buddies)(new ListMyWritingBuddies(
            $user->getUserIdentifier(),
            $request->query->getInt('limit', 50),
            $request->query->getInt('offset'),
        ));

        return new JsonResponse([
            'writingBuddies' => array_map(
                static fn (WritingBuddyView $view): array => [
                    'linkId' => $view->linkId,
                    'other' => null === $view->other ? null : [
                        'userId' => $view->other->userId,
                        'username' => $view->other->username,
                        'name' => $view->other->name,
                        'avatarUrl' => $view->other->avatarUrl,
                    ],
                    'status' => $view->status,
                    'proposedByMe' => $view->proposedByMe,
                    'proposedAt' => $view->proposedAt->format(\DATE_ATOM),
                ],
                $links,
            ),
        ]);
    }
}
