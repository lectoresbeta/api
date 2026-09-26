<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Overdraft\Infrastructure\Persistence\Doctrine;

use Doctrine\DBAL\Connection;
use LectoresBeta\Credits\Account\Domain\Enum\CreditTransactionReason;
use LectoresBeta\Credits\Account\Domain\ValueObject\UserId;
use LectoresBeta\Credits\Overdraft\Domain\Repository\ReactivationCandidateRepository;
use LectoresBeta\Credits\Overdraft\Domain\ValueObject\ReactivationCandidate;
use LectoresBeta\Credits\Pricing\Domain\Service\CorrectabilityPolicy;
use LectoresBeta\Credits\Pricing\Domain\ValueObject\ChapterId;
use LectoresBeta\Credits\Pricing\Domain\ValueObject\WorkId;

/**
 * La consulta que elige a quién se le tiende el gancho (`FEAT-CRD-019`).
 *
 * Es SQL y vive en `Infrastructure` porque cruza cuatro tablas de este
 * contexto y el resultado es una lista corta: hacerlo en PHP significaría
 * traerse la tabla de movimientos entera para descartarla casi toda.
 *
 * Las tres condiciones obligatorias de la ficha —las que separan esto de un
 * patrón oscuro— están todas aquí, y conviene leerlas como lo que son:
 *
 * - **el texto seguía abierto a corrección**: hay un capítulo suyo con precio
 *   y sin las tres correcciones simultáneas del tope. Lo único que impide
 *   corregirlo es que su autor no puede pagarlo. Sin esto, la corrección
 *   sería mercancía no solicitada; con esto, es el cumplimiento de una
 *   petición que el autor hizo y luego abandonó;
 * - **ha corregido antes**: tiene algún movimiento `CORRECTION_EARNED`. Se
 *   extiende crédito a quien ha demostrado que sabe devolverlo;
 * - **está dormido, no ido**: su último movimiento cae en la ventana de 30 a
 *   180 días.
 *
 * Y tres exclusiones: saldo distinto de cero —quien puede pagar no necesita
 * descubierto—, quien ya recibió uno (`RN-6b`) y quien ha renunciado al
 * mecanismo o a sus avisos (`RN-2d`, `RN-8`).
 */
final readonly class DoctrineReactivationCandidateRepository implements ReactivationCandidateRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function best(\DateTimeImmutable $idleSince, \DateTimeImmutable $idleUntil, int $limit): array
    {
        $sql = <<<'SQL'
            WITH activity AS (
                SELECT user_id,
                       MAX(occurred_at) AS last_active_at,
                       COUNT(*) FILTER (WHERE reason = :earned) AS corrections_given
                  FROM credits_ctx.credit_transaction
              GROUP BY user_id
            )
            SELECT DISTINCT ON (p.author_id)
                   p.author_id,
                   p.chapter_id,
                   p.work_id,
                   p.price,
                   a.corrections_given,
                   a.last_active_at
              FROM credits_ctx.chapter_price p
              JOIN activity a ON a.user_id = p.author_id
              JOIN credits_ctx.credit_account c ON c.user_id = p.author_id
             WHERE c.balance = 0
               AND c.anonymised_at IS NULL
               AND p.price > 0
               AND a.corrections_given > 0
               AND a.last_active_at <= :idle_since
               AND a.last_active_at >= :idle_until
               AND NOT EXISTS (
                     SELECT 1 FROM credits_ctx.overdraft_grant g
                      WHERE g.author_id = p.author_id
                   )
               AND NOT EXISTS (
                     SELECT 1 FROM credits_ctx.overdraft_opt_out o
                      WHERE o.user_id = p.author_id
                   )
               AND (
                     SELECT COUNT(*) FROM credits_ctx.correction_price q
                      WHERE q.chapter_id = p.chapter_id
                   ) < :max_open
            ORDER BY p.author_id,
                   a.corrections_given DESC,
                   p.price ASC
            SQL;

        /** @var list<array{author_id: string, chapter_id: string, work_id: string, price: int|string, corrections_given: int|string, last_active_at: string}> $rows */
        $rows = $this->connection->executeQuery($sql, [
            'earned' => CreditTransactionReason::CORRECTION_EARNED->value,
            'idle_since' => $idleSince->format('Y-m-d H:i:s'),
            'idle_until' => $idleUntil->format('Y-m-d H:i:s'),
            'max_open' => CorrectabilityPolicy::MAX_OPEN_CORRECTIONS,
        ])->fetchAllAssociative();

        $candidates = array_map(
            static fn (array $row): ReactivationCandidate => new ReactivationCandidate(
                UserId::fromString($row['author_id']),
                ChapterId::fromString($row['chapter_id']),
                WorkId::fromString($row['work_id']),
                (int) $row['price'],
                (int) $row['corrections_given'],
                new \DateTimeImmutable($row['last_active_at']),
            ),
            $rows,
        );

        // El orden de la ficha se aplica aquí y no en la consulta porque
        // `DISTINCT ON` obliga a ordenar primero por el autor: lo que sale de
        // la base de datos es un capítulo por autor, no una lista ordenada
        // por mérito. Son unas decenas de filas.
        usort(
            $candidates,
            static fn (ReactivationCandidate $a, ReactivationCandidate $b): int => [$b->correctionsGiven, $b->lastActiveAt]
                <=> [$a->correctionsGiven, $a->lastActiveAt],
        );

        return \array_slice($candidates, 0, $limit);
    }
}
