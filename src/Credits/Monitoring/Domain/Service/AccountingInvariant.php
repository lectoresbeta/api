<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Monitoring\Domain\Service;

use LectoresBeta\Credits\Account\Domain\Repository\CreditAccountRepository;
use LectoresBeta\Credits\Account\Domain\Repository\CreditTransactionRepository;
use LectoresBeta\Credits\Monitoring\Domain\ValueObject\AccountingCheck;

/**
 * La invariante contable de todo el sistema de créditos (`FEAT-CRD-012`).
 *
 * **Es un test, no una aspiración.** Si deja de cumplirse, hay un movimiento
 * que no es una transferencia, y eso significa que alguien tiene créditos que
 * nadie pagó.
 *
 * Vive en el dominio porque es una **regla de negocio** —la más importante
 * del contexto— y no un chequeo técnico. Que se ejecute desde un comando
 * programado o desde un endpoint de administración es cosa de quien la llame.
 *
 * No aparece en `/health`, que es público y lo consultan sondas sin
 * credenciales. Dos razones: una sonda no debe contarle a un desconocido que
 * la economía está rota, y esto suma sobre todo el histórico de movimientos,
 * que no es lo que se pregunta cada diez segundos.
 */
final readonly class AccountingInvariant
{
    public function __construct(
        private CreditTransactionRepository $transactions,
        private CreditAccountRepository $accounts,
    ) {
    }

    public function check(): AccountingCheck
    {
        return new AccountingCheck(
            $this->transactions->totalIssued(),
            $this->transactions->totalMoved(),
            $this->accounts->totalBalance(),
        );
    }
}
