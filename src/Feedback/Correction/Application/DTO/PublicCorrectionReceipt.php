<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Application\DTO;

/**
 * Lo que se le devuelve a quien acaba de corregir sin tener cuenta.
 *
 * `wouldHaveBeenWorth` es el mensaje de captación de `FEAT-FBK-008`: «acabas
 * de escribir una corrección que en Lectores Beta vale 6 créditos». Es
 * **informativa y nada más** —no hay saldo, no hay movimiento, y registrarse
 * después no la abona—, y es el único sitio del sistema donde una cifra de
 * créditos se le enseña a alguien sin cuenta.
 *
 * No abonarla es deliberado. Hacerlo abriría el agujero evidente: publico un
 * texto con el cuestionario más exigente posible, abro un enlace que no me
 * cuesta nada, me corrijo a mí mismo de incógnito y me registro con otra
 * cuenta. Y, más de fondo, convertiría un flujo sin apuestas en uno con
 * dinero, arrastrando hasta aquí todo el control antifraude que hoy no
 * necesita.
 */
final readonly class PublicCorrectionReceipt
{
    public function __construct(
        public string $correctionId,
        public ?int $wouldHaveBeenWorth,
    ) {
    }
}
