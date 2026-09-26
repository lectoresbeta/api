<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Administration\Application\DTO;

use LectoresBeta\User\Account\Application\Contract\AdministrableAccount;

/**
 * La ficha de un usuario en el backoffice (`FEAT-MOD-005`).
 *
 * **El backoffice mira, no posee.** `Moderation` no es dueño de los usuarios
 * —lo es `User`—, así que esto es una vista compuesta: la identidad y el
 * estado vienen de allí por su contrato, los relatos de `Work`, las
 * correcciones de `Feedback`, y las reclamaciones y las sanciones son lo
 * único que este contexto tiene en casa.
 *
 * **El saldo de créditos no está**, y su ausencia es una decisión y no un
 * olvido: `Credits` no publica contratos
 * ([`decision:0002`](../../../../../docs/decisions/0002-credits-as-isolated-bounded-context.md)),
 * así que nadie puede preguntárselo. Lo sirve él mismo, en su propia
 * superficie de administración, y quien pinta la pantalla hace dos llamadas.
 * Componerlo aquí habría exigido abrir la puerta que ese contexto tiene
 * cerrada a propósito.
 *
 * Lo que tampoco lleva, por `RN-5`: ni una línea de obra inédita. El
 * backoffice enseña **la obra reclamada**, no la biblioteca de nadie.
 */
final readonly class AdminUserSheet
{
    /**
     * @param list<AdminClaimRow>    $claimsFiled
     * @param list<AdminClaimRow>    $claimsReceived
     * @param list<AdminSanctionRow> $sanctions
     */
    public function __construct(
        public AdministrableAccount $account,
        public ?int $works,
        public ?int $corrections,
        public array $claimsFiled,
        public array $claimsReceived,
        public array $sanctions,
    ) {
    }
}
