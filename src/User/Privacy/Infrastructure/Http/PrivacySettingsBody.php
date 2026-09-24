<?php

declare(strict_types=1);

namespace LectoresBeta\User\Privacy\Infrastructure\Http;

use LectoresBeta\User\Privacy\Application\DTO\PrivacySettingsView;

/**
 * La forma de los ajustes en HTTP, que es la misma al leerlos y al
 * guardarlos: quien acaba de mover un desplegable quiere ver cómo ha quedado
 * todo, no solo lo que tocó.
 */
final readonly class PrivacySettingsBody
{
    /**
     * @return array<string, mixed>
     */
    public static function of(PrivacySettingsView $settings): array
    {
        return [
            'profileVisibility' => $settings->profileVisibility,
            'commentPermission' => $settings->commentPermission,
            'messagePermission' => $settings->messagePermission,
            'updatedAt' => $settings->updatedAt->format(\DATE_ATOM),
        ];
    }
}
