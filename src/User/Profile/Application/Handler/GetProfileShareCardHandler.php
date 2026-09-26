<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Application\Handler;

use LectoresBeta\Shared\Application\Sharing\CanonicalUrl;
use LectoresBeta\Shared\Application\Sharing\ShareCard;
use LectoresBeta\Shared\Application\Storage\MediaUrl;
use LectoresBeta\User\Profile\Application\DTO\PublicProfile;
use LectoresBeta\User\Profile\Application\Query\GetProfileByUserId;

/**
 * La tarjeta con la que se comparte un perfil fuera (`FEAT-USR-032`).
 *
 * La tercera y última, después de la de una obra (`FEAT-WRK-011`) y la de una
 * publicación (`FEAT-COM-020`). **No genera ningún enlace**: la `url` es la
 * dirección canónica del perfil, sin token, sin caducidad y sin abrir nada
 * que no fuera ya público.
 *
 * `ShareCard` lleva escrito que una tarjeta «no lleva a nadie dentro». Aquí
 * no es una contradicción: **el perfil es el contenido**, y el nombre, la
 * foto y la biografía son exactamente lo que su titular publicó para que se
 * viera.
 *
 * La regla, de hecho, es la misma, y por eso **se pide el perfil sin
 * espectador** (`viewerId: null`) en lugar de comprobar la privacidad aquí:
 * es la misma puerta que atiende a un visitante anónimo, y un rastreador no
 * sigue a nadie. Reescribirla habría sido la manera de que un día se quedara
 * corta y un buscador indexara un perfil cerrado.
 */
final readonly class GetProfileShareCardHandler
{
    /** Lo que cabe en una previsualización antes de que la corte el cliente. */
    private const DESCRIPTION = 200;

    public function __construct(
        private GetProfileByUserIdHandler $profiles,
        private string $shareUrlTemplate,
        private string $mediaBaseUrl,
    ) {
    }

    public function __invoke(string $userId): ShareCard
    {
        // Sin espectador a propósito: un perfil `FOLLOWERS`, uno `NOBODY` y
        // una cuenta eliminada responden `404`, igual que uno inexistente.
        $profile = ($this->profiles)(new GetProfileByUserId($userId, null));

        return new ShareCard(
            CanonicalUrl::from($this->shareUrlTemplate, $profile->userId),
            self::name($profile),
            self::preview($profile->description),
            $this->avatar($profile->avatarUrl),
        );
    }

    /**
     * `RN-4`. Quien registró la cuenta y no terminó el onboarding todavía no
     * tiene nombre, y una tarjeta sin título es una previsualización que
     * enseña la URL en crudo.
     */
    private static function name(PublicProfile $profile): string
    {
        return $profile->name ?? '@'.$profile->username;
    }

    private static function preview(?string $description): ?string
    {
        if (null === $description) {
            return null;
        }

        return mb_strlen($description) <= self::DESCRIPTION
            ? $description
            : rtrim(mb_substr($description, 0, self::DESCRIPTION)).'…';
    }

    /**
     * `RN-5`. En el resto de la API el avatar viaja relativo y está bien:
     * quien lo pinta ya sabe contra qué origen habla. Una `og:image` la lee
     * un rastreador que no tiene ese contexto, y una ruta relativa ahí es una
     * imagen rota en lo único que esta ficha produce.
     */
    private function avatar(?string $key): ?string
    {
        $path = MediaUrl::of($key);

        return null === $path ? null : rtrim($this->mediaBaseUrl, '/').$path;
    }
}
