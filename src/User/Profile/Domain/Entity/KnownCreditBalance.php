<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Domain\Entity;

use LectoresBeta\User\Account\Domain\ValueObject\UserId;

/**
 * El saldo de créditos que `User` conoce, para pintarlo (`FEAT-USR-027`).
 *
 * **Es una copia, y existe porque `Credits` no publica contratos.** Esa regla
 * es dura —[`decision:0002`](../../../../../docs/decisions/0002-credits-as-isolated-bounded-context.md)
 * y `AGENTS.md`— y no admite una excepción para leer: la puerta que se abre
 * para consultar es la misma que mañana alguien usa para algo más. Así que
 * aquí se hace lo que el proyecto ya hace con el grafo de seguidores y con
 * los bloqueos: **escuchar el hecho y quedarse con lo que hace falta**.
 *
 * Lo que hace falta es un número para el menú lateral. Nada más: ni
 * movimientos, ni precios, ni por qué cambió.
 *
 * **Puede ir retrasada**, y la ficha lo asume: el saldo cambia por eventos
 * asíncronos, así que entre una navegación y la siguiente puede quedar
 * vieja. La fuente de verdad es `GET /credits/balance`, y quien necesite el
 * número exacto —para gastar— lo pregunta allí.
 */
class KnownCreditBalance
{
    private string $userId;

    private int $balance = 0;

    private \DateTimeImmutable $knownAt;

    public function __construct(UserId $userId, int $balance, \DateTimeImmutable $now)
    {
        $this->userId = $userId->value();
        $this->balance = $balance;
        $this->knownAt = $now;
    }

    public function userId(): UserId
    {
        return UserId::fromString($this->userId);
    }

    public function balance(): int
    {
        return $this->balance;
    }

    /**
     * Un hecho más viejo que lo que ya se sabe **no retrocede el número**.
     * La cola no promete orden, así que sin esto un `CreditBalanceChanged`
     * reentregado tarde dejaría el menú enseñando un saldo anterior.
     */
    public function record(int $balance, \DateTimeImmutable $at): void
    {
        if ($at < $this->knownAt) {
            return;
        }

        $this->balance = $balance;
        $this->knownAt = $at;
    }
}
