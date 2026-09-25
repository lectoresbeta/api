<?php

declare(strict_types=1);

namespace LectoresBeta\User\Preferences\Infrastructure\Controller;

use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use LectoresBeta\User\Preferences\Application\Command\UpdateNotificationPreferences;
use LectoresBeta\User\Preferences\Application\Handler\GetMyNotificationPreferencesHandler;
use LectoresBeta\User\Preferences\Application\Handler\UpdateNotificationPreferencesHandler;
use LectoresBeta\User\Preferences\Application\Query\GetMyNotificationPreferences;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `GET` y `PUT /api/v1/me/notification-preferences` (`FEAT-USR-039`).
 *
 * La respuesta lleva **el catálogo entero con sus canales**, no solo los
 * valores: si el cliente llevara la lista codificada, cada aviso nuevo
 * exigiría desplegar el frontal.
 *
 * El `PUT` es **parcial**: llega lo que se ha tocado. Una pantalla con veinte
 * avisos manda dos campos al pulsar una casilla, no cuarenta.
 *
 * Las dos operaciones comparten controlador porque comparten el recurso.
 */
#[AsController]
final readonly class NotificationPreferencesController
{
    public function __construct(
        private GetMyNotificationPreferencesHandler $mine,
        private UpdateNotificationPreferencesHandler $update,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        if ($request->isMethod('PUT')) {
            $body = JsonBody::of($request);

            ($this->update)(new UpdateNotificationPreferences(
                $user->getUserIdentifier(),
                $body->has('allMuted') ? true === $body->bool('allMuted') : null,
                self::choices($body),
            ));
        }

        $preferences = ($this->mine)(new GetMyNotificationPreferences($user->getUserIdentifier()));

        return new JsonResponse([
            'allMuted' => $preferences->allMuted,
            'topics' => $preferences->topics,
        ]);
    }

    /**
     * @return list<array{topic: string, channel: string, enabled: bool}>
     */
    private static function choices(JsonBody $body): array
    {
        $choices = [];

        foreach ($body->objectList('preferences') as $choice) {
            $topic = $choice->string('topic');
            $channel = $choice->string('channel');

            // Una fila sin tipo o sin canal no dice nada: se descarta en
            // silencio en vez de tumbar el resto de la pantalla.
            if (null === $topic || null === $channel) {
                continue;
            }

            $choices[] = ['topic' => $topic, 'channel' => $channel, 'enabled' => true === $choice->bool('enabled')];
        }

        return $choices;
    }
}
