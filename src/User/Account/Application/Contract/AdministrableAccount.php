<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Application\Contract;

/**
 * Una cuenta, vista desde el backoffice (`FEAT-MOD-005`).
 *
 * **Lleva el correo, y es lo único de este contrato que hay que justificar.**
 * Ninguna otra puerta de `User` lo reparte para una pantalla: `DirectoryEntry`
 * lo excluye a propósito, y `RegisteredUsers::idOfEmail()` dice expresamente
 * que no se use desde un endpoint, porque responder si una dirección tiene
 * cuenta es justo lo que el alta y la recuperación de contraseña se cuidan de
 * no decir.
 *
 * Aquí se abre porque el caso es el contrario: **quien pregunta ya conoce la
 * dirección** —se la ha escrito la persona a la que va a atender— y sin ella
 * no puede saber a qué cuenta se refiere. Lo que contrapesa la apertura es
 * que toda consulta queda auditada (`RN-1`): saber quién miró la ficha de un
 * usuario importa tanto como saber quién la cambió.
 *
 * Lo que **no** lleva: ni fecha de nacimiento, ni contraseña, ni una sola
 * línea de nada que esa persona haya escrito. `RN-5` es una regla de
 * contención, y un backoffice que permite navegar obra inédita es un riesgo
 * mayor que el problema que resuelve.
 */
final readonly class AdministrableAccount
{
    public function __construct(
        public string $userId,
        public string $username,
        public ?string $name,
        public string $email,
        public string $status,
        public string $registeredAt,
        public ?string $activatedAt,
        /**
         * Último acceso (`FEAT-USR-005` `RN-5`). Es lo primero que hay que
         * mirar antes de decidir nada sobre una cuenta: una cuenta que nadie
         * usa desde hace un año no merece el mismo trato que una viva.
         */
        public ?string $lastSignedInAt,
        /** Hasta cuándo no puede escribir, si está restringida (`FEAT-MOD-006`). */
        public ?string $restrictedUntil,
    ) {
    }
}
