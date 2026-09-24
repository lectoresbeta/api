<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Infrastructure\Http;

use LectoresBeta\User\Profile\Application\DTO\EditableProfile;

/**
 * La forma de «mi perfil» en HTTP, la misma al leerlo y al guardarlo: quien
 * acaba de guardar quiere ver **cómo ha quedado**, que no siempre es lo que
 * escribió — la biografía se guarda sin marcado, y enseñar el resultado es lo
 * que evita descubrirlo más tarde y por sorpresa.
 */
final readonly class EditableProfileBody
{
    /**
     * @return array<string, mixed>
     */
    public static function of(EditableProfile $profile): array
    {
        return [
            'userId' => $profile->userId,
            'username' => $profile->username,
            'name' => $profile->name,
            'description' => $profile->description,
            'avatarUrl' => $profile->avatarUrl,
            'coverUrl' => $profile->coverUrl,
        ];
    }
}
