<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Administration\Application\Handler;

use LectoresBeta\Moderation\Administration\Application\Query\SearchUsers;
use LectoresBeta\Moderation\AuditLog\Application\Service\RecordAuditEntry;
use LectoresBeta\Moderation\Claim\Domain\ValueObject\PartyId;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\User\Account\Application\Contract\AdministrableAccount;
use LectoresBeta\User\Account\Application\Contract\AdministrableAccounts;

/**
 * Buscar usuarios desde el backoffice (`FEAT-MOD-005`).
 *
 * **La búsqueda también se audita** (`RN-1`), y eso sorprende hasta que se
 * piensa: saber quién miró la ficha de un usuario importa tanto como saber
 * quién la cambió. Un backoffice donde consultar es invisible es un
 * backoffice donde se puede curiosear.
 *
 * Se anota el término buscado y no la lista de resultados: lo que hay que
 * poder revisar después es **qué se fue a buscar**, no cuántas filas salieron.
 */
final readonly class SearchUsersHandler
{
    private const MAX = 100;

    public function __construct(
        private AdministrableAccounts $accounts,
        private RecordAuditEntry $audit,
        private TransactionalSession $session,
    ) {
    }

    /**
     * @return list<AdministrableAccount>
     */
    public function __invoke(SearchUsers $query): array
    {
        $limit = max(1, min(self::MAX, $query->limit));
        $found = $this->accounts->search($query->term, $limit, max(0, $query->offset));

        $this->session->execute(function () use ($query, $found): void {
            $this->audit->of(
                PartyId::fromString($query->moderatorId),
                'USERS_SEARCHED',
                'USER_DIRECTORY',
                // No hay un objetivo concreto: lo que se busca es el
                // directorio entero, y el término es lo que lo acota.
                'ALL',
                null,
                ['term' => $query->term, 'results' => \count($found)],
            );
        });

        return $found;
    }
}
