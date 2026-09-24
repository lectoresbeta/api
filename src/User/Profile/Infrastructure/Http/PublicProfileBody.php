<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Infrastructure\Http;

use LectoresBeta\Shared\Application\Storage\MediaUrl;
use LectoresBeta\User\Profile\Application\DTO\PublicProfile;

/**
 * La forma de un perfil ajeno en HTTP, la misma por las dos rutas: piden lo
 * mismo por caminos distintos y devolver dos formas obligaría al cliente a
 * saber por cuál entró.
 *
 * `canonicalUsername` va siempre, no solo al resolver por alias. Así el
 * cliente puede comparar sin preguntarse si el campo aplica, que es como se
 * acaban escribiendo dos ramas donde había una.
 */
final readonly class PublicProfileBody
{
    /**
     * @return array<string, mixed>
     */
    public static function of(PublicProfile $profile): array
    {
        return [
            'userId' => $profile->userId,
            'username' => $profile->username,
            'canonicalUsername' => $profile->canonicalUsername,
            'resolvedVia' => $profile->resolvedVia,
            'name' => $profile->name,
            'description' => $profile->description,
            'avatarUrl' => MediaUrl::of($profile->avatarUrl),
            'coverUrl' => $profile->coverUrl,
        ];
    }
}
