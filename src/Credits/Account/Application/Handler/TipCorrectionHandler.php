<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Account\Application\Handler;

use LectoresBeta\Credits\Account\Application\Command\TipCorrection;
use LectoresBeta\Credits\Account\Application\Service\AnnounceMovement;
use LectoresBeta\Credits\Account\Domain\Entity\CreditAccount;
use LectoresBeta\Credits\Account\Domain\Entity\CreditTransaction;
use LectoresBeta\Credits\Account\Domain\Enum\CreditTransactionReason;
use LectoresBeta\Credits\Account\Domain\Event\CorrectionTipped;
use LectoresBeta\Credits\Account\Domain\Exception\TipRefused;
use LectoresBeta\Credits\Account\Domain\Repository\CreditAccountRepository;
use LectoresBeta\Credits\Account\Domain\Repository\CreditTransactionRepository;
use LectoresBeta\Credits\Account\Domain\Service\TipPolicy;
use LectoresBeta\Credits\Account\Domain\ValueObject\CreditTransactionId;
use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

/**
 * La propina del autor a una corrección que le sirvió (`FEAT-CRD-017`).
 *
 * **Vive en `Credits` y no en `Feedback`, y esa es la decisión del caso.** La
 * ficha pedía comprobar el saldo disponible antes de aceptar; si el endpoint
 * estuviera en `Feedback`, esa comprobación sería una pregunta a `Credits`, y
 * este contexto no publica contratos
 * ([`decision:0002`](../../../../../docs/decisions/0002-credits-as-isolated-bounded-context.md)).
 * La alternativa —aceptar la intención y aplicarla después por un hecho—
 * obligaría a compensar cuando el saldo no llegara, que es mucha maquinaria
 * para un gesto voluntario.
 *
 * Y no hace falta preguntar nada: **este contexto ya sabe de quién es cada
 * corrección**, porque tiene apuntado quién pagó y quién cobró. El autor es
 * el del cargo; el corrector, el del abono. Una corrección por enlace público
 * no tiene abono —quien la escribió no tiene cuenta— y por eso no se puede
 * propinar, sin necesidad de una regla aparte que lo diga.
 *
 * **Masa constante.** Lo que sale del autor entra en el corrector, y esa es
 * la diferencia con la bonificación automática que sustituye: dos cuentas que
 * se valoran mutuamente no ganan nada, porque lo que A da, A lo pierde. El
 * fraude no tiene premio que repartir.
 *
 * **No genera descubierto** (`RN-1`), al revés que el pago de una corrección:
 * el descubierto existe para que un lector nunca trabaje sin cobrar, y
 * endeudarse por ser generoso sería una trampa.
 *
 * **La llave de idempotencia se guarda en el propio movimiento**, no en una
 * tabla de respuestas. Basta porque una corrección admite una sola propina
 * (`RN-4`): el movimiento duplicado no puede existir, y lo único que la llave
 * decide es si repetir la petición se lee como un reintento —y contesta el
 * saldo— o como una segunda propina, que se rechaza.
 */
final readonly class TipCorrectionHandler
{
    public function __construct(
        private CreditAccountRepository $accounts,
        private CreditTransactionRepository $transactions,
        private AnnounceMovement $announce,
        private TransactionalSession $session,
        private EventPublisher $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(TipCorrection $command): int
    {
        $amount = $command->amount ?? 0;

        if (!TipPolicy::admits($amount)) {
            throw TipRefused::outOfRange(TipPolicy::MINIMUM, TipPolicy::MAXIMUM);
        }

        $movements = $this->transactions->ofCorrection($command->correctionId);
        $charge = self::firstWithReason($movements, CreditTransactionReason::CORRECTION_CHARGED);

        // Quien pagó la corrección es su autor. Ni existe, ni es suya, ni se
        // ha cobrado todavía: una sola respuesta, porque distinguirlas diría
        // algo sobre las correcciones de otra persona.
        if (null === $charge || $charge->userId()->value() !== $command->authorId) {
            throw TipRefused::notYours();
        }

        $already = self::firstWithReason($movements, CreditTransactionReason::TIP_SENT);

        if (null !== $already) {
            // Un reintento de red y una segunda propina son la misma
            // petición vistas desde aquí; la llave es lo único que las
            // separa. Sin llave, se rechaza: es la lectura prudente.
            if (null === $command->idempotencyKey || ($already->metadata()['idempotencyKey'] ?? null) !== $command->idempotencyKey) {
                throw TipRefused::alreadyTipped();
            }

            $account = $this->accounts->ofUser($charge->userId());

            return null === $account ? 0 : $account->balance();
        }

        $earning = self::firstWithReason($movements, CreditTransactionReason::CORRECTION_EARNED);

        if (null === $earning) {
            throw TipRefused::nobodyToPay();
        }

        $now = $this->clock->now();
        $authorId = $charge->userId();
        $readerId = $earning->userId();

        $author = $this->accounts->ofUser($authorId) ?? new CreditAccount($authorId, $now);

        if (!$author->canAfford($amount)) {
            throw TipRefused::notAffordable($author->balance());
        }

        $reader = $this->accounts->ofUser($readerId) ?? new CreditAccount($readerId, $now);

        $authorBefore = $author->balance();
        $readerBefore = $reader->balance();

        $metadata = ['correctionId' => $command->correctionId];

        if (null !== $command->idempotencyKey) {
            $metadata['idempotencyKey'] = $command->idempotencyKey;
        }

        $sent = $author->apply(
            CreditTransactionId::generate(),
            -$amount,
            CreditTransactionReason::TIP_SENT,
            $now,
            null,
            $metadata,
        );

        $received = $reader->apply(
            CreditTransactionId::generate(),
            $amount,
            CreditTransactionReason::TIP_RECEIVED,
            $now,
            null,
            $metadata,
        );

        $this->session->execute(function () use ($author, $reader, $sent, $received): void {
            $this->accounts->save($author);
            $this->accounts->save($reader);
            $this->transactions->add($sent);
            $this->transactions->add($received);
        });

        $this->announce->of($sent, $authorBefore, $author->balance());
        $this->announce->of($received, $readerBefore, $reader->balance());

        $this->events->publish(new CorrectionTipped(
            EventId::generate(),
            $command->correctionId,
            $authorId,
            $readerId,
            $amount,
            $now,
        ));

        return $author->balance();
    }

    /**
     * @param list<CreditTransaction> $movements
     */
    private static function firstWithReason(array $movements, CreditTransactionReason $reason): ?CreditTransaction
    {
        foreach ($movements as $movement) {
            if ($reason === $movement->reason()) {
                return $movement;
            }
        }

        return null;
    }
}
