<?php

declare(strict_types=1);

namespace LectoresBeta\User\Privacy\Application\Handler;

use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Privacy\Application\Command\UpdatePrivacySettings;
use LectoresBeta\User\Privacy\Application\DTO\PrivacySettingsView;
use LectoresBeta\User\Privacy\Application\Service\StoredPrivacySettings;
use LectoresBeta\User\Privacy\Domain\Enum\PrivacyAudience;
use LectoresBeta\User\Privacy\Domain\Event\PrivacySettingsChanged;
use LectoresBeta\User\Privacy\Domain\Exception\UnknownAudience;
use LectoresBeta\User\Privacy\Domain\Repository\UserPrivacySettingsRepository;

/**
 * Cambiar quién puede llegar hasta mí (`FEAT-USR-038`).
 *
 * **No son preferencias de visualización: son reglas de autorización.** Por
 * eso se guardan aquí y se aplican en el servidor en cada operación, y no
 * ocultando botones — un perfil restringido tiene que responder igual aunque
 * se llame al endpoint directamente.
 *
 * **No reescribe el pasado** (`RN-3`). No borra lo ya publicado, no revoca
 * accesos concedidos y no interrumpe una corrección en curso: quien empezó,
 * entrega y cobra (`S-36`). Lo que cambia es lo que ocurra a partir de ahora.
 *
 * Y **no toca la modalidad de cada obra** (`S-14`). Las dos se guardan como
 * están y la autorización evalúa las dos quedándose con la más restrictiva;
 * reescribir las obras haría imposible volver atrás, porque al relajar el
 * perfil nadie sabría qué modalidad tenía antes cada una.
 */
final readonly class UpdatePrivacySettingsHandler
{
    public function __construct(
        private UserPrivacySettingsRepository $repository,
        private StoredPrivacySettings $settings,
        private TransactionalSession $session,
        private EventPublisher $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(UpdatePrivacySettings $command): PrivacySettingsView
    {
        $userId = UserId::fromString($command->userId);
        $settings = $this->settings->of($userId);

        // Lo que no viene se queda como estaba. Un `PUT` parcial es lo que la
        // pantalla manda al mover un solo desplegable, y obligar a enviar los
        // tres haría que el cliente reenviase valores que no ha leído.
        $profile = self::audience($command->profileVisibility, $settings->profileVisibility());
        $comment = self::audience($command->commentPermission, $settings->commentPermission());
        $message = self::audience($command->messagePermission, $settings->messagePermission());

        $now = $this->clock->now();

        $this->session->execute(function () use ($settings, $profile, $comment, $message, $now): void {
            $settings->change($profile, $comment, $message, $now);
            $this->repository->save($settings);
        });

        $this->events->publish(new PrivacySettingsChanged(
            EventId::generate(),
            $userId,
            $profile,
            $comment,
            $message,
            $now,
        ));

        return StoredPrivacySettings::asView($settings);
    }

    /**
     * Un valor desconocido **se rechaza**, nunca se interpreta. Tomarlo por
     * «todos» abriría una puerta que su dueño cree cerrada, y tomarlo por
     * «nadie» cerraría una que cree abierta; las dos equivocaciones son
     * peores que un error.
     */
    private static function audience(?string $declared, PrivacyAudience $current): PrivacyAudience
    {
        if (null === $declared) {
            return $current;
        }

        return PrivacyAudience::tryFrom(strtoupper($declared)) ?? throw UnknownAudience::create();
    }
}
