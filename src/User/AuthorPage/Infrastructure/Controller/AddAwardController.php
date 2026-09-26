<?php

declare(strict_types=1);

namespace LectoresBeta\User\AuthorPage\Infrastructure\Controller;

use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use LectoresBeta\User\AuthorPage\Application\Command\AddAward;
use LectoresBeta\User\AuthorPage\Application\Handler\AddAwardHandler;
use LectoresBeta\User\AuthorPage\Infrastructure\Http\AwardBody;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `POST /api/v1/me/awards` (`FEAT-USR-030`).
 *
 * Solo el título es obligatorio: quien recuerda una mención de hace veinte
 * años no tiene por qué recordar el año ni tener un enlace.
 */
#[AsController]
final readonly class AddAwardController
{
    public function __construct(
        private AddAwardHandler $add,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $body = JsonBody::of($request);

        return new JsonResponse(AwardBody::of(($this->add)(new AddAward(
            $user->getUserIdentifier(),
            $body->string('title') ?? '',
            $body->string('awardedBy'),
            $body->int('year'),
            $body->string('note'),
            $body->string('url'),
        ))), Response::HTTP_CREATED);
    }
}
