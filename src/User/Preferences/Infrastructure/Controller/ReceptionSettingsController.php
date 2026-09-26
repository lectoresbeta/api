<?php

declare(strict_types=1);

namespace LectoresBeta\User\Preferences\Infrastructure\Controller;

use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use LectoresBeta\User\Preferences\Application\Command\UpdateReceptionSettings;
use LectoresBeta\User\Preferences\Application\DTO\ReceptionSettingsView;
use LectoresBeta\User\Preferences\Application\Handler\GetMyReceptionSettingsHandler;
use LectoresBeta\User\Preferences\Application\Handler\UpdateReceptionSettingsHandler;
use LectoresBeta\User\Preferences\Application\Query\GetMyReceptionSettings;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `GET` y `PUT /api/v1/me/reception-settings` (`FEAT-USR-011`).
 *
 * **No son preferencias de aviso sino de recepción** (`S-19`): no es lo mismo
 * no querer enterarse que no querer recibirlas. Por eso viven aquí y no en
 * `/me/notification-preferences`, donde una invitación silenciada seguiría
 * esperando respuesta en algún sitio.
 *
 * Las dos operaciones comparten controlador porque comparten el recurso: lo
 * que cambia es el verbo.
 */
#[AsController]
final readonly class ReceptionSettingsController
{
    public function __construct(
        private GetMyReceptionSettingsHandler $mine,
        private UpdateReceptionSettingsHandler $update,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $view = $request->isMethod('PUT')
            ? ($this->update)(new UpdateReceptionSettings(
                $user->getUserIdentifier(),
                // Ausente no es `false`: mover un interruptor no pisa el otro.
                JsonBody::of($request)->bool('betaReaderInvitations'),
                JsonBody::of($request)->bool('writingBuddyProposals'),
            ))
            : ($this->mine)(new GetMyReceptionSettings($user->getUserIdentifier()));

        return new JsonResponse(self::body($view));
    }

    /**
     * @return array<string, mixed>
     */
    private static function body(ReceptionSettingsView $view): array
    {
        return [
            'betaReaderInvitations' => $view->betaReaderInvitations,
            'writingBuddyProposals' => $view->writingBuddyProposals,
            'updatedAt' => $view->updatedAt->format(\DATE_ATOM),
        ];
    }
}
