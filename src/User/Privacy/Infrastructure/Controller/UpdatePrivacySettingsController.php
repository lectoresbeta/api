<?php

declare(strict_types=1);

namespace LectoresBeta\User\Privacy\Infrastructure\Controller;

use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use LectoresBeta\User\Privacy\Application\Command\UpdatePrivacySettings;
use LectoresBeta\User\Privacy\Application\Handler\UpdatePrivacySettingsHandler;
use LectoresBeta\User\Privacy\Infrastructure\Http\PrivacySettingsBody;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `PUT /api/v1/me/privacy-settings` (`FEAT-USR-038`).
 *
 * Lo que no viene se queda como estaba: la pantalla manda un desplegable cada
 * vez, y obligar a enviar los tres haría que el cliente reenviase valores que
 * no ha leído — que es como se pierde un ajuste sin que nadie lo toque.
 *
 * No exige cuenta activada, al contrario que el resto de escrituras. Cerrar
 * la puerta es lo último que se le debe dificultar a alguien, y quien no ha
 * activado su cuenta todavía tiene tan poco publicado como para que el
 * trámite sea absurdo.
 */
#[AsController]
final readonly class UpdatePrivacySettingsController
{
    public function __construct(
        private UpdatePrivacySettingsHandler $update,
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

        return new JsonResponse(PrivacySettingsBody::of(($this->update)(new UpdatePrivacySettings(
            $user->getUserIdentifier(),
            $body->string('profileVisibility'),
            $body->string('commentPermission'),
            $body->string('messagePermission'),
        ))));
    }
}
