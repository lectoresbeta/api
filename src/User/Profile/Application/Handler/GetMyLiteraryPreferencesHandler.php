<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Application\Handler;

use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Profile\Application\DTO\GenreView;
use LectoresBeta\User\Profile\Application\Query\GetMyLiteraryPreferences;
use LectoresBeta\User\Profile\Application\Service\MyProfile;
use LectoresBeta\User\Profile\Domain\Entity\Genre;
use LectoresBeta\User\Profile\Domain\Repository\GenreRepository;
use LectoresBeta\User\Profile\Domain\Repository\LiteraryPreferenceRepository;

/**
 * Los géneros que alguien eligió (`FEAT-USR-009`).
 *
 * Devuelve el nombre presentable y no solo el código para que la pantalla no
 * tenga que cruzar dos respuestas para pintar una lista — y, sobre todo,
 * porque un género **retirado** ya no viene en `GET /genres` y ese cruce
 * dejaría sin nombre justo lo que hay que seguir enseñando.
 */
final readonly class GetMyLiteraryPreferencesHandler
{
    public function __construct(
        private MyProfile $profile,
        private LiteraryPreferenceRepository $preferences,
        private GenreRepository $genres,
    ) {
    }

    /**
     * @return list<GenreView>
     */
    public function __invoke(GetMyLiteraryPreferences $query): array
    {
        // Pasa por `MyProfile` para que una sesión cuya cuenta ya no está
        // responda lo mismo aquí que en el resto del perfil.
        $user = $this->profile->of($query->userId);

        return self::asViews($this->genres->ofCodes(
            $this->preferences->codesOf(UserId::fromString($user->id()->value())),
        ));
    }

    /**
     * @param list<Genre> $genres
     *
     * @return list<GenreView>
     */
    public static function asViews(array $genres): array
    {
        return array_map(
            static fn (Genre $genre): GenreView => new GenreView($genre->code(), $genre->name()),
            $genres,
        );
    }
}
