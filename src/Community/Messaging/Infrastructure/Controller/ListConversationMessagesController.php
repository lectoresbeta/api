<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Messaging\Infrastructure\Controller;

use LectoresBeta\Community\Messaging\Application\Handler\ListConversationMessagesHandler;
use LectoresBeta\Community\Messaging\Application\Query\ListConversationMessages;
use LectoresBeta\Community\Messaging\Infrastructure\Http\MessagingBody;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `GET /api/v1/conversations/{conversationId}/messages` (`FEAT-COM-012`).
 *
 * Del mensaje más reciente hacia atrás, que es como se lee una conversación:
 * al abrirla hace falta lo último, no lo primero.
 *
 * Una conversación ajena responde `404`, igual que una que no existe
 * (`RN-1`): un `403` confirmaría que esas dos personas hablan.
 */
#[AsController]
final readonly class ListConversationMessagesController
{
    public function __construct(
        private ListConversationMessagesHandler $messages,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request, string $conversationId): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $page = ($this->messages)(new ListConversationMessages(
            $user->getUserIdentifier(),
            $conversationId,
            MessagingBody::cursor($request),
            MessagingBody::limit($request),
        ));

        return new JsonResponse(MessagingBody::messages($page['rows'], $page['nextCursor']));
    }
}
