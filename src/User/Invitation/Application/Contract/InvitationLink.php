<?php

declare(strict_types=1);

namespace LectoresBeta\User\Invitation\Application\Contract;

/**
 * Lo que hace falta para mandarle a alguien una invitación
 * ([`decision:0014`](../../../../../docs/decisions/0014-published-contracts-between-contexts.md)).
 *
 * `token` es el secreto en claro. Está bien aquí y **no lo estaría en un
 * hecho de integración**: este valor se entrega en proceso y muere con la
 * petición, mientras que un mensaje en RabbitMQ se persiste, se reintenta y
 * se aparca en una cola de fallos que la gente lee.
 *
 * `inviterName` viaja porque el correo lo dice —«X te invita»— y sin él
 * `Notification` tendría que preguntarle a `User` quién es, que es justo la
 * llamada que este contrato evita.
 */
final readonly class InvitationLink
{
    public function __construct(
        public string $email,
        public string $token,
        public ?string $inviterName,
        public string $inviterUsername,
    ) {
    }
}
