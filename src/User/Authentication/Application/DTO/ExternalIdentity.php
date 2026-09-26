<?php

declare(strict_types=1);

namespace LectoresBeta\User\Authentication\Application\DTO;

/**
 * Quién dice el proveedor externo que es esta persona (`FEAT-USR-002`).
 *
 * Tres datos y **ninguna credencial** (`RN-5`). El `access_token` de Google no
 * llega hasta aquí ni se guarda en ningún sitio: se usa para preguntar quién
 * es y se tira. Guardarlo significaría poder actuar en nombre de alguien
 * durante meses, que es una capacidad que esta funcionalidad no necesita.
 *
 * `emailIsVerified` no es un adorno: es lo único que separa «este correo es
 * suyo» de «este correo es el que ha escrito». De ello depende que se pueda
 * enlazar con una cuenta que ya existe (`RN-6`).
 */
final readonly class ExternalIdentity
{
    public function __construct(
        public string $externalId,
        public ?string $email,
        public bool $emailIsVerified,
    ) {
    }
}
