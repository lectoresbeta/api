<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Application\Handler;

use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Profile\Application\Command\UpdateMyLiteraryPreferences;
use LectoresBeta\User\Profile\Application\DTO\GenreView;
use LectoresBeta\User\Profile\Application\Service\GenreSelection;
use LectoresBeta\User\Profile\Application\Service\MyProfile;
use LectoresBeta\User\Profile\Domain\Event\LiteraryPreferencesUpdated;
use LectoresBeta\User\Profile\Domain\Repository\GenreRepository;
use LectoresBeta\User\Profile\Domain\Repository\LiteraryPreferenceRepository;

/**
 * Cambiar los géneros que me interesan (`FEAT-USR-009`).
 *
 * **La selección sustituye a la anterior**, no se acumula: elegir géneros es
 * elegir un conjunto, y quien quita uno espera que desaparezca.
 *
 * Escribe donde escribe el onboarding y publica el mismo hecho, que es la
 * mitad importante de la funcionalidad. `FEAT-USR-023` `RN-6` lo exige: si
 * fueran dos sitios, las recomendaciones acabarían contradiciendo lo que la
 * persona cree haber elegido, y nada lo detectaría.
 *
 * Endpoint propio y no un campo de `PATCH /me/profile` por lo mismo que el
 * nombre de usuario: la pestaña comparte el «Guardar», pero un fallo en un
 * campo no puede llevarse por delante los demás (`settings.md` `S-37`).
 */
final readonly class UpdateMyLiteraryPreferencesHandler
{
    public function __construct(
        private MyProfile $profile,
        private GenreSelection $selection,
        private LiteraryPreferenceRepository $preferences,
        private GenreRepository $genres,
        private TransactionalSession $session,
        private EventPublisher $events,
        private Clock $clock,
    ) {
    }

    /**
     * @return list<GenreView>
     */
    public function __invoke(UpdateMyLiteraryPreferences $command): array
    {
        $user = $this->profile->of($command->userId);
        $userId = UserId::fromString($user->id()->value());

        // Lo que ya tenía, que es lo único que permite conservar un género
        // retirado sin permitir elegirlo (`RN-6`).
        $current = $this->preferences->codesOf($userId);

        $codes = $this->selection->validated($command->genreCodes, $current);

        $now = $this->clock->now();

        $this->session->execute(function () use ($userId, $codes, $now): void {
            $this->preferences->replaceAllOf($userId, $codes, $now);
        });

        $this->events->publish(new LiteraryPreferencesUpdated(
            EventId::generate(),
            $userId,
            $codes,
            $now,
        ));

        return GetMyLiteraryPreferencesHandler::asViews($this->genres->ofCodes($codes));
    }
}
