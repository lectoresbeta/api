<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Overdraft\Application\Service;

use LectoresBeta\Credits\Account\Domain\Entity\CreditTransaction;
use LectoresBeta\Credits\Account\Domain\ValueObject\UserId;
use LectoresBeta\Credits\Overdraft\Domain\Entity\OverdraftGrant;
use LectoresBeta\Credits\Overdraft\Domain\Event\OverdraftCorrectionGranted;
use LectoresBeta\Credits\Overdraft\Domain\Repository\OverdraftGrantRepository;
use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

/**
 * Los dos momentos en que un descubierto concedido cambia de estado
 * (`FEAT-CRD-019`).
 *
 * **Esta funcionalidad no añade mecánica**: provoca a propósito la situación
 * que `FEAT-CRD-018` ya sabe manejar —el corrector cobra, el autor queda en
 * negativo y la corrección llega bloqueada— y le pone un presupuesto. Lo que
 * falta por hacer aquí es apuntar que la elegibilidad se gastó, y publicar el
 * hecho del que vive el gancho.
 *
 * Vive junto al aviso del cruce y no en el cobro porque ahí es donde se sabe
 * que **se ha cruzado**: el cobro conoce el importe, no el antes y el después.
 *
 * La distinción entre concedido y usado es la que sostiene toda la ficha: el
 * cupo limita a cuánta gente se le abre la puerta, no cuánta deuda aparece. Y
 * solo lo usado cuenta en la tasa de recuperación, porque una elegibilidad
 * que nadie aprovechó no emitió un solo crédito.
 */
final readonly class TrackOverdraftUse
{
    public function __construct(
        private OverdraftGrantRepository $grants,
        private TransactionalSession $session,
        private EventPublisher $events,
        private Clock $clock,
    ) {
    }

    /**
     * El saldo acaba de cruzar a negativo. Si era por una corrección que este
     * mecanismo había abierto, el descubierto se ha usado.
     */
    public function crossedIntoDebt(CreditTransaction $movement, int $balanceAfter): void
    {
        $authorId = UserId::fromString($movement->userId()->value());
        $now = $this->clock->now();
        $grant = $this->grants->usableOf($authorId, $now);

        if (null === $grant) {
            // El autor se quedó en negativo por una carrera entre lectores
            // (`FEAT-CRD-009` `RN-5`), que es la otra forma de acabar aquí y
            // no tiene nada que ver con esto.
            return;
        }

        $metadata = $movement->metadata();
        $chapterId = self::textOf($metadata, 'chapterId');

        if ($chapterId !== $grant->chapterId()->value()) {
            // La deuda vino de otro capítulo suyo. La elegibilidad sigue
            // donde estaba: lo que se concedió fue sobre un texto concreto.
            return;
        }

        $grant->markUsed($now);

        $this->session->execute(function () use ($grant): void {
            $this->grants->save($grant);
        });

        $this->events->publish(new OverdraftCorrectionGranted(
            EventId::generate(),
            $authorId->value(),
            self::textOf($metadata, 'readerId') ?? '',
            self::textOf($metadata, 'correctionId') ?? '',
            $chapterId,
            self::textOf($metadata, 'workId') ?? '',
            abs($movement->amount()),
            // Lo que le falta para leerla, que es de lo que vive el gancho:
            // «te faltan 7 créditos» motiva mucho más que «repón saldo»
            // (`RN-4`, `C-31`).
            abs($balanceAfter),
            $movement->occurredAt(),
        ));
    }

    /**
     * El autor ha vuelto a cero o por encima. Lo que debía queda saldado, y
     * esa proporción es la única cifra que dice si esto funciona o está
     * regalando créditos (`FEAT-CRD-012`).
     */
    public function debtCleared(UserId $userId): void
    {
        $pending = array_values(array_filter(
            $this->grants->unsettledOf($userId),
            static fn (OverdraftGrant $grant): bool => $grant->wasUsed(),
        ));

        if ([] === $pending) {
            return;
        }

        $now = $this->clock->now();

        $this->session->execute(function () use ($pending, $now): void {
            foreach ($pending as $grant) {
                $grant->settle($now);
                $this->grants->save($grant);
            }
        });
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private static function textOf(array $metadata, string $key): ?string
    {
        $value = $metadata[$key] ?? null;

        return \is_string($value) && '' !== $value ? $value : null;
    }
}
