<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Pricing\Application\Handler;

use LectoresBeta\Credits\Pricing\Application\Event\WorkClosedForCorrection;
use LectoresBeta\Credits\Pricing\Application\Event\WorkCreated;
use LectoresBeta\Credits\Pricing\Application\Event\WorkOpenedForCorrection;
use LectoresBeta\Credits\Pricing\Application\Service\RefreshCorrectability;
use LectoresBeta\Credits\Pricing\Domain\Entity\CorrectionWindow;
use LectoresBeta\Credits\Pricing\Domain\Repository\CorrectionWindowRepository;
use LectoresBeta\Credits\Pricing\Domain\ValueObject\WorkId;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

/**
 * Si la obra admite correcciones, que es la mitad de la corregibilidad que
 * este contexto no miraba (`FEAT-WRK-016`).
 *
 * `WorkOpenedForCorrection` se publicaba desde el principio y **no lo
 * escuchaba nadie**. El efecto no era que faltara un aviso: era que
 * `CorrectabilityPolicy` respondía solo con dinero, así que los capítulos de
 * un borrador salían como corregibles. `Feedback` proyecta esa respuesta y
 * abre el panel contra ella, de modo que la puerta se cerraba **después**, al
 * preguntarle a `Work` — un rechazo que llega cuando el lector ya ha pulsado.
 *
 * **Crear cierra, abrir abre, cerrar cierra.** Las tres, y no solo las dos
 * del medio: sin `WorkCreated` una obra nueva no tendría fila, y la ausencia
 * de fila significa «no lo sé», que deliberadamente no bloquea.
 *
 * Sin registro de duplicados, como `ApplySanction` en `User` y por la misma
 * razón: aquí no hay un efecto que sumar, hay un estado que afirmar.
 * Reprocesar el mismo hecho escribe el mismo booleano, y la entidad responde
 * que no ha cambiado nada para que no se recalcule la obra entera dos veces.
 */
final readonly class TrackCorrectionWindow
{
    public function __construct(
        private CorrectionWindowRepository $windows,
        private RefreshCorrectability $correctability,
        private TransactionalSession $session,
        private Clock $clock,
    ) {
    }

    /**
     * Una obra nace **una sola vez**: si ya hay fila, esto es una reentrega.
     * No hay nada que recalcular — un borrador recién creado no tiene todavía
     * ni un capítulo con precio.
     */
    public function created(WorkCreated $event): void
    {
        $id = $this->parse($event->workId);

        if (null === $id || null !== $this->windows->ofWork($id)) {
            return;
        }

        $window = CorrectionWindow::closedAtBirth($id, $this->clock->now());

        $this->session->execute(function () use ($window): void {
            $this->windows->save($window);
        });
    }

    public function opened(WorkOpenedForCorrection $event): void
    {
        $this->moveDoor($event->workId, $event->version, open: true);
    }

    public function closed(WorkClosedForCorrection $event): void
    {
        $this->moveDoor($event->workId, $event->version, open: false);
    }

    private function moveDoor(string $workId, int $version, bool $open): void
    {
        $id = $this->parse($workId);

        if (null === $id) {
            return;
        }

        $now = $this->clock->now();
        // Sin fila: la obra es anterior a que este contexto escuchara nada, y
        // se le abre una ahora por el lado contrario al hecho que llega, para
        // que ese hecho tenga algo que cambiar.
        $window = $this->windows->ofWork($id) ?? CorrectionWindow::unknownBefore($id, $open, $version, $now);

        $changed = $window->applyVersion($open, $version, $now);

        $this->session->execute(function () use ($window): void {
            $this->windows->save($window);
        });

        if (!$changed) {
            return;
        }

        // Abrir la puerta puede volver corregibles de golpe todos los
        // capítulos de la obra, y cerrarla, ninguno. Es el mismo recálculo
        // que dispara un cambio de precio, por el otro factor.
        $this->correctability->forWork($id);
    }

    private function parse(string $workId): ?WorkId
    {
        try {
            return WorkId::fromString($workId);
        } catch (InvalidValue) {
            // Un hecho con un identificador ilegible se descarta:
            // reintentarlo lo dejaría dando vueltas para siempre.
            return null;
        }
    }
}
