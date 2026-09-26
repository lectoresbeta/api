<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Infrastructure\Http;

use LectoresBeta\Shared\Application\Storage\MediaUrl;
use LectoresBeta\User\AuthorPage\Application\DTO\AuthorLinkView;
use LectoresBeta\User\Profile\Application\DTO\PublicProfile;

/**
 * La forma de un perfil ajeno en HTTP, la misma por las dos rutas: piden lo
 * mismo por caminos distintos y devolver dos formas obligaría al cliente a
 * saber por cuál entró.
 *
 * `canonicalUsername` va siempre, no solo al resolver por alias. Así el
 * cliente puede comparar sin preguntarse si el campo aplica, que es como se
 * acaban escribiendo dos ramas donde había una.
 *
 * Los contadores llegan **desde fuera** y no del perfil, por lo mismo que en
 * el perfil propio: los compone la frontera preguntando a tres contextos, y
 * un `null` significa «no se ha podido saber», que no es lo mismo que cero.
 */
final readonly class PublicProfileBody
{
    /**
     * @param array{following: ?int, followers: ?int, works: ?int, corrections: ?int, tips: ?int} $counters
     *
     * @return array<string, mixed>
     */
    public static function of(PublicProfile $profile, array $counters): array
    {
        return [
            'userId' => $profile->userId,
            'username' => $profile->username,
            'canonicalUsername' => $profile->canonicalUsername,
            'resolvedVia' => $profile->resolvedVia,
            'name' => $profile->name,
            'description' => $profile->description,
            'avatarUrl' => MediaUrl::of($profile->avatarUrl),
            // El fondo de la página de autor (`FEAT-USR-016`). Pasa por
            // `MediaUrl` igual que el avatar: lo guardado es la clave, y la
            // dirección se calcula en un solo sitio.
            'coverUrl' => MediaUrl::of($profile->coverUrl),
            // Las referencias de su página de autor (`FEAT-USR-015`): su web,
            // su cuenta en otra red, su blog. Van aquí y no en un endpoint
            // propio porque **la página de autor es este perfil**.
            'links' => array_map(
                static fn (AuthorLinkView $link): array => ['label' => $link->label, 'url' => $link->url],
                $profile->links,
            ),
            // Y su decoración (`FEAT-USR-016`): dos códigos de un catálogo
            // cerrado, nunca CSS ni un hexadecimal.
            'theme' => $profile->theme->value,
            'accentColour' => $profile->accentColour->value,
            'counters' => $counters,
            // Nulos sin sesión: quien los reciba así no tiene ningún botón de
            // relación que pintar, porque no hay nadie de quien hablar.
            'isFollowing' => $profile->isFollowing,
            'isFollowedBy' => $profile->isFollowedBy,
            'isBlocked' => $profile->isBlocked,
        ];
    }
}
