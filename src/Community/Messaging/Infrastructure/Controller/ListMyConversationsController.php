<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Messaging\Infrastructure\Controller;

use LectoresBeta\Community\Messaging\Application\Handler\ListMyConversationsHandler;
use LectoresBeta\Community\Messaging\Application\Query\ListMyConversations;
use LectoresBeta\Community\Messaging\Infrastructure\Http\MessagingBody;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `GET /api/v1/me/conversations` (`FEAT-COM-012`).
 *
 * Solo las propias, y por eso cuelga de `/me/`: no existe la versión de otra
 * persona. Con quién habla alguien no es una lista que se pueda pedir.
 */
#[AsController]
final readonly class ListMyConversationsController
{
    public function __construct(
        private ListMyConversationsHandler $conversations,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $page = ($this->conversations)(new ListMyConversations(
            $user->getUserIdentifier(),
            MessagingBody::cursor($request),
            MessagingBody::limit($request),
        ));

        return new JsonResponse(MessagingBody::conversations($page['rows'], $page['nextCursor']));
    }
}
