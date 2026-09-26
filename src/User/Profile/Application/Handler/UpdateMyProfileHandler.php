<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Application\Handler;

use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Application\Storage\MediaUrl;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\User\Account\Domain\Entity\User;
use LectoresBeta\User\Account\Domain\Event\UserProfileUpdated;
use LectoresBeta\User\Account\Domain\Repository\UserRepository;
use LectoresBeta\User\Account\Domain\ValueObject\Biography;
use LectoresBeta\User\Account\Domain\ValueObject\PersonName;
use LectoresBeta\User\Profile\Application\Command\UpdateMyProfile;
use LectoresBeta\User\Profile\Application\DTO\EditableProfile;
use LectoresBeta\User\Profile\Application\Service\MyProfile;

/**
 * Cambiar lo que enseño de mí (`FEAT-USR-008`).
 *
 * **Un `PATCH`, campo a campo**: el mismo perfil se edita en bloque desde
 * Configuración y en línea desde «Mi perfil», y un `PUT` obligaría a la
 * segunda a reenviar campos que no está tocando — que es como se borra una
 * biografía sin querer.
 *
 * **El nombre de usuario no está aquí**, aunque la pantalla lo enseñe junto a
 * los demás campos, y es la decisión que explica `RN-9`: cambiarlo bloquea
 * 30 días y reserva el anterior como alias. Mezclado en este `PATCH`, una
 * biografía se quedaría sin guardar porque el nombre de usuario que alguien
 * quería está ocupado. Un solo «Guardar» en la interfaz no obliga a una sola
 * llamada.
 *
 * Tampoco la foto, por lo mismo en otro formato: un `multipart` en el que un
 * fallo de subida tirase también el cambio de nombre.
 *
 * **Exige cuenta activada** (`S-29`, resuelta aquí). La tentación era lo
 * contrario —el nombre ya se fija antes de activar, en el onboarding, así que
 * impedir corregir una errata parece incoherente— y decide la biografía: es
 * texto libre que aparece en un perfil público, y dejar publicarlo a una
 * cuenta cuyo correo nadie ha verificado es exactamente lo que
 * [`decision:0003`](../../../../../docs/decisions/0003-write-operations-require-activated-account.md)
 * existe para impedir. El onboarding es la excepción que compra la entrada, y
 * no tiene ningún campo libre.
 */
final readonly class UpdateMyProfileHandler
{
    public function __construct(
        private MyProfile $profile,
        private UserRepository $users,
        private TransactionalSession $session,
        private EventPublisher $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(UpdateMyProfile $command): EditableProfile
    {
        $user = $this->profile->of($command->userId);

        // Se valida todo antes de tocar nada: un nombre válido y una
        // biografía demasiado larga no pueden dejar el perfil a medio
        // guardar.
        $name = self::name($command, $user);
        $description = self::description($command, $user);

        $now = $this->clock->now();

        $this->session->execute(function () use ($user, $name, $description, $now): void {
            $user->updateProfile($name, $description?->value(), $now);
            $this->users->save($user);
        });

        $this->events->publish(new UserProfileUpdated(
            EventId::generate(),
            $user->id(),
            $name?->value(),
            $description?->value(),
            MediaUrl::of($user->avatarUrl()),
            $now,
        ));

        return MyProfile::asView($user, $now);
    }

    /**
     * Enviar el nombre vacío **se rechaza** (`RN-2`): es lo que identifica a
     * la persona en toda la interfaz. No enviarlo lo deja como estaba, que es
     * distinto — y es por lo que una cuenta que aún no ha pasado el
     * onboarding puede editar su biografía sin inventarse un nombre.
     */
    private static function name(UpdateMyProfile $command, User $user): ?PersonName
    {
        if (!$command->nameGiven) {
            return $user->name();
        }

        return PersonName::fromString((string) $command->name);
    }

    private static function description(UpdateMyProfile $command, User $user): ?Biography
    {
        if (!$command->descriptionGiven) {
            return Biography::fromString($user->description());
        }

        return Biography::fromString($command->description);
    }
}
