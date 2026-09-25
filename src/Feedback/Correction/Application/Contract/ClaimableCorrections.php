<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Application\Contract;

/**
 * Si una corrección se puede reclamar, y por quién
 * ([`decision:0014`](../../../../../docs/decisions/0014-published-contracts-between-contexts.md)).
 *
 * Existe para `Moderation`, que necesita comprobar dos cosas antes de
 * registrar una reclamación sobre una corrección: que quien reclama es el
 * autor de la obra corregida (`RN-4`) y que la corrección **ya se ha podido
 * leer** (`RN-5`).
 *
 * Lo segundo cierra una vía de abuso sutil: reclamar a ciegas una corrección
 * retenida por descubierto, solo para no pagarla.
 *
 * Pregunta y devuelve datos. No lleva el texto de la corrección, que no hace
 * falta para autorizar y sí sería contenido saliendo de su contexto.
 */
interface ClaimableCorrections
{
    public function ofId(string $correctionId): ?ClaimableCorrection;
}
