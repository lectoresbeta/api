<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Infrastructure\Http;

use LectoresBeta\Shared\Application\Storage\MediaUrl;
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
     * `counters` va aparte del resto de campos a propósito: los demás son
     * datos de esta persona y estos cuatro son **actividad**, calculada en
     * otros tres contextos. Mezclarlos al mismo nivel haría pensar que se
     * guardan aquí.
     *
     * Un contador en `null` significa **«no se ha podido saber»**, que no es
     * lo mismo que cero (`FEAT-USR-028` `RN-3`). Un cliente que los trate
     * igual enseñará un 0 donde debería enseñar un hueco.
     *
     * @param array{following: ?int, followers: ?int, works: ?int, corrections: ?int} $counters
     *
     * @return array<string, mixed>
     */
    public static function of(EditableProfile $profile, array $counters): array
    {
        return [
            'userId' => $profile->userId,
            'username' => $profile->username,
            'name' => $profile->name,
            'description' => $profile->description,
            'avatarUrl' => MediaUrl::of($profile->avatarUrl),
            // El fondo de la página de autor (`FEAT-USR-016`). Pasa por
            // `MediaUrl` igual que el avatar: lo guardado es la clave, y la
            // dirección se calcula en un solo sitio.
            'coverUrl' => MediaUrl::of($profile->coverUrl),
            'usernameChangeableOn' => $profile->usernameChangeableOn->format(\DATE_ATOM),
            'avatarCrop' => $profile->avatarCrop,
            'counters' => $counters,
        ];
    }
}
