<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Pricing\Domain\Entity;

use LectoresBeta\Credits\Pricing\Domain\ValueObject\WorkId;

/**
 * Si la puerta de una obra está abierta a correcciones, según lo último que
 * `Work` dijo (`FEAT-WRK-016`).
 *
 * **La corregibilidad no es solo dinero.** `CorrectabilityPolicy` respondía
 * con el saldo, el precio y cuántas correcciones hay abiertas, y con eso daba
 * `true` para los capítulos de un borrador que nadie puede corregir todavía.
 * `Feedback` proyecta esa respuesta y abre el panel contra ella, así que la
 * proyección mentía y el rechazo llegaba después, desde `Work`.
 *
 * Una fila por obra, no una columna por capítulo: la puerta es de la obra, y
 * repetirla en cada capítulo sería el mismo dato en cuarenta sitios, con
 * cuarenta oportunidades de que uno se quede atrás. También es lo que hace
 * que un capítulo nuevo de una obra abierta nazca correcto sin heredar nada.
 *
 * **Que no haya fila significa «no lo sé», y no lo sé no bloquea.** Las obras
 * que existían antes de que este contexto escuchara nada siguen
 * comportándose como hasta ahora; a partir de `WorkCreated`, toda obra nueva
 * nace con su fila cerrada.
 *
 * **Y la versión decide, no la llegada**, exactamente como la del
 * cuestionario (`FEAT-WRK-014`). Una cola reentrega y no promete orden: sin
 * versión, las reentregas de «abierta» y «cerrada» se turnan para siempre. La
 * fecha no sirve de desempate porque dos transiciones del mismo segundo son
 * indistinguibles, y eso no es una rareza — publicar y abrir a corrección son
 * dos clics seguidos.
 */
class CorrectionWindow
{
    private string $workId;

    private bool $open;

    private int $version;

    private \DateTimeImmutable $updatedAt;

    private function __construct(WorkId $workId, bool $open, int $version, \DateTimeImmutable $now)
    {
        $this->workId = $workId->value();
        $this->open = $open;
        $this->version = $version;
        $this->updatedAt = $now;
    }

    /**
     * Una obra nace cerrada y en la versión cero, que es la que `Work` le da
     * al crearla.
     */
    public static function closedAtBirth(WorkId $workId, \DateTimeImmutable $now): self
    {
        return new self($workId, false, 0, $now);
    }

    /**
     * Y una obra de la que este contexto nunca oyó hablar **nace por el lado
     * contrario al hecho que llega**, una versión por detrás, para que ese
     * hecho tenga algo que cambiar. Es el caso de las que existían antes.
     */
    public static function unknownBefore(WorkId $workId, bool $open, int $version, \DateTimeImmutable $now): self
    {
        return new self($workId, !$open, $version - 1, $now);
    }

    public function workId(): WorkId
    {
        return WorkId::fromString($this->workId);
    }

    public function isOpen(): bool
    {
        return $this->open;
    }

    /**
     * Responde si la puerta ha cambiado de verdad, que es lo que decide si
     * hace falta recalcular la obra entera. Una versión que no es más nueva
     * que la aplicada responde `false` sin tocar nada: da igual que sea una
     * reentrega o un hecho viejo que llega tarde.
     */
    public function applyVersion(bool $open, int $version, \DateTimeImmutable $now): bool
    {
        if ($version <= $this->version) {
            return false;
        }

        $changed = $open !== $this->open;

        $this->open = $open;
        $this->version = $version;
        $this->updatedAt = $now;

        return $changed;
    }
}
