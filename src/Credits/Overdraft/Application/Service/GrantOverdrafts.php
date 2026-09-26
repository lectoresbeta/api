<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Overdraft\Application\Service;

use LectoresBeta\Credits\Overdraft\Domain\Entity\OverdraftGrant;
use LectoresBeta\Credits\Overdraft\Domain\Repository\OverdraftGrantRepository;
use LectoresBeta\Credits\Overdraft\Domain\Repository\ReactivationCandidateRepository;
use LectoresBeta\Credits\Overdraft\Domain\Service\OverdraftPolicy;
use LectoresBeta\Credits\Overdraft\Domain\ValueObject\OverdraftGrantId;
use LectoresBeta\Credits\Overdraft\Domain\ValueObject\ReactivationCandidate;
use LectoresBeta\Credits\Pricing\Application\Service\RefreshCorrectability;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

/**
 * Repartir el cupo de la semana (`FEAT-CRD-019`).
 *
 * **Lo que se concede es elegibilidad, no deuda.** Un capítulo de ese autor
 * pasa a admitir una corrección que su autor no puede pagar; la deuda solo
 * aparece si alguien decide corregirlo, y muchas veces no lo hará. Por eso el
 * cupo es un techo de elegibilidad y no de emisión: el techo de emisión real
 * es cupo × precio máximo, que con 3 y 20 son 60 créditos por semana.
 *
 * **Poner el cupo a cero apaga el mecanismo** (`RN-7`), y no a medias: no se
 * concede nada, así que no hay nada que caduque, ni deuda que aparezca, ni
 * correo que salga. Es lo que se pedía de un interruptor.
 *
 * Nada de esto es un servicio de dominio: decide **a quién** se le concede,
 * que es orquestación sobre una consulta. Las cifras —cupo, ventana de
 * inactividad, caducidad— sí son del dominio, y viven en `OverdraftPolicy`.
 */
final readonly class GrantOverdrafts
{
    public function __construct(
        private ReactivationCandidateRepository $candidates,
        private OverdraftGrantRepository $grants,
        private RefreshCorrectability $correctability,
        private TransactionalSession $session,
        private Clock $clock,
        private int $quota = OverdraftPolicy::DEFAULT_QUOTA,
    ) {
    }

    /**
     * @return list<ReactivationCandidate> a quiénes se les ha concedido
     */
    public function __invoke(bool $dryRun = false): array
    {
        if ($this->quota < 1) {
            return [];
        }

        $now = $this->clock->now();
        $period = OverdraftPolicy::periodOf($now);
        $left = $this->quota - $this->grants->countInPeriod($period);

        if ($left < 1) {
            return [];
        }

        $chosen = $this->candidates->best(
            OverdraftPolicy::idleSince($now),
            OverdraftPolicy::idleUntil($now),
            min($left, 100),
        );

        if ([] === $chosen || $dryRun) {
            return $chosen;
        }

        $this->session->execute(function () use ($chosen, $now): void {
            foreach ($chosen as $candidate) {
                $this->grants->save(new OverdraftGrant(
                    OverdraftGrantId::generate(),
                    $candidate->authorId,
                    $candidate->chapterId,
                    $candidate->price,
                    $now,
                ));
            }
        });

        // Y ahora sus capítulos admiten corrección, que es lo que el gancho
        // necesita para poder existir. Va después de confirmar: publicar que
        // un capítulo se abrió y que la transacción se deshaga sería abrirlo
        // para el resto del mundo y para nadie aquí.
        foreach ($chosen as $candidate) {
            $this->correctability->forAuthor($candidate->authorId);
        }

        return $chosen;
    }
}
