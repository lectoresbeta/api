<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Application\DTO;

/**
 * Una persona, como una fila de resultados (`FEAT-USR-017`).
 *
 * **Una tarjeta de perfil y nada más.** Ni correo, ni fecha de nacimiento, ni
 * estado de cuenta: quien pregunta está pintando una lista, y cada campo de
 * más sería un dato de alguien cruzando una frontera sin motivo.
 *
 * `name` es anulable porque lo es: quien se registró y no ha terminado el
 * onboarding tiene nombre de usuario y todavía no tiene nombre.
 */
final readonly class AuthorCard
{
    public function __construct(
        public string $userId,
        public string $username,
        public ?string $name,
        public ?string $avatarUrl,
    ) {
    }
}
