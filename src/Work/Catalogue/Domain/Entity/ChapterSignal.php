<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Catalogue\Domain\Entity;

use LectoresBeta\Work\Chapter\Domain\ValueObject\ChapterId;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId;

/**
 * Lo que `Credits` ha decidido sobre un capítulo: si admite correcciones,
 * cuánto trabajo produciría enseñarlo y cuánto gana quien lo corrija
 * (`FEAT-WRK-012`, `FEAT-CRD-013`, `decision:0008`).
 *
 * Es una proyección. Existe porque el catálogo no puede hacer un `JOIN` con
 * las tablas de otro contexto acotado, y porque filtrar, ordenar y pintar la
 * insignia exigen tener el dato **aquí**, en la misma consulta que pagina.
 *
 * Las tres cifras las calcula `Credits` y `Work` solo las copia: `RN-1` de
 * `FEAT-CRD-013` es explícita en que la traducción a créditos no es de nadie
 * más. `Work` sigue sin saber **cómo** se calcula ninguna de ellas.
 *
 * Los dos hechos llegan por separado y por eso hay dos fechas: cada uno
 * descarta lo viejo por su cuenta, y una respuesta de corregibilidad
 * retrasada no puede pisar un precio reciente.
 */
class ChapterSignal
{
    private string $chapterId;

    private string $workId;

    private bool $correctable;

    /** Correcciones que el autor puede pagar, ya acotadas por `Credits`. */
    private int $affordableCorrections;

    /**
     * Lo que gana quien corrija este capítulo, que es también lo que paga su
     * autor: una corrección es una transferencia. Cero significa que todavía
     * no ha llegado ningún precio, no que corregirlo sea gratis.
     */
    private int $credits;

    /**
     * Cuándo ocurrió el hecho, no cuándo llegó: la cola no promete orden, y
     * una respuesta vieja pisando a una nueva cerraría un capítulo abierto.
     */
    private \DateTimeImmutable $changedAt;

    private \DateTimeImmutable $pricedAt;

    private function __construct(ChapterId $chapterId, WorkId $workId)
    {
        $this->chapterId = $chapterId->value();
        $this->workId = $workId->value();
        $this->correctable = false;
        $this->affordableCorrections = 0;
        $this->credits = 0;
        $this->changedAt = self::never();
        $this->pricedAt = self::never();
    }

    /**
     * Un capítulo del que todavía no se sabe nada: no corregible y sin
     * precio, que es lo que el catálogo enseña mientras no llegue el primer
     * hecho. Lo crea el primero de los dos que aparezca.
     */
    public static function unknown(ChapterId $chapterId, WorkId $workId): self
    {
        return new self($chapterId, $workId);
    }

    public function chapterId(): ChapterId
    {
        return ChapterId::fromString($this->chapterId);
    }

    public function workId(): WorkId
    {
        return WorkId::fromString($this->workId);
    }

    public function isCorrectable(): bool
    {
        return $this->correctable;
    }

    public function affordableCorrections(): int
    {
        return $this->affordableCorrections;
    }

    public function credits(): int
    {
        return $this->credits;
    }

    public function record(bool $correctable, int $affordableCorrections, \DateTimeImmutable $changedAt): void
    {
        if ($changedAt < $this->changedAt) {
            return;
        }

        $this->correctable = $correctable;
        $this->affordableCorrections = $affordableCorrections;
        $this->changedAt = $changedAt;
    }

    public function price(int $credits, \DateTimeImmutable $pricedAt): void
    {
        if ($pricedAt < $this->pricedAt) {
            return;
        }

        $this->credits = $credits;
        $this->pricedAt = $pricedAt;
    }

    /**
     * Anterior a cualquier hecho real, para que el primero que llegue gane
     * sin tener que tratar la ausencia de fecha como un caso aparte.
     */
    private static function never(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('@0');
    }
}
