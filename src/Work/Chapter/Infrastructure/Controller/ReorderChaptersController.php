<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Chapter\Infrastructure\Controller;

use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use LectoresBeta\Work\Chapter\Application\Command\ReorderChapters;
use LectoresBeta\Work\Chapter\Application\Handler\ReorderChaptersHandler;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `PUT /api/v1/works/{workId}/chapters/order` (`FEAT-WRK-003`).
 *
 * Recibe **la lista completa** de identificadores en el orden deseado. Es
 * idempotente y sobrevive a que dos pestañas hagan lo mismo a la vez, cosa
 * que un «mueve este de la 3 a la 7» no hace.
 */
#[AsController]
final readonly class ReorderChaptersController
{
    public function __construct(
        private ReorderChaptersHandler $reorder,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request, string $workId): Response
    {
        $author = $this->security->getUser();

        if (null === $author) {
            throw new UnauthorizedHttpException('Bearer');
        }

        /** @var list<string> $order */
        $order = array_values(array_filter(
            JsonBody::of($request)->stringList('order'),
            static fn (string $id): bool => '' !== $id,
        ));

        ($this->reorder)(new ReorderChapters($workId, $author->getUserIdentifier(), $order));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
