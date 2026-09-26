<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\ContentReview\Application\Port;

use LectoresBeta\Moderation\ContentReview\Domain\ValueObject\ReviewVerdict;

/**
 * Lo que la aplicación necesita de un revisor de contenido (`FEAT-MOD-011`
 * `RN-1`).
 *
 * **Recibe el texto y devuelve un veredicto.** Ese es todo el contrato, y su
 * estrechez es el diseño: hoy hay una implementación que aprueba siempre, y
 * mañana habrá una basada en IA. Cuanto más rico fuera esto, más difícil
 * sería el cambio.
 *
 * Se construye ahora, vacío, porque el coste hoy es casi cero y el ahorro
 * después es grande: el flujo de publicación ya contempla el paso, los
 * estados existen desde el día uno y las pruebas del camino completo están
 * escritas y pasan. Abrir el flujo de publicación más adelante, con obras ya
 * publicadas, cuesta mucho más.
 *
 * **Nunca lanza.** Un revisor que se cae es un `PASSED` con nota (`RN-6`), no
 * una excepción: bloquear una publicación por una caída de infraestructura
 * sería impedir publicar por nada. Cuando exista una IA de verdad conviene
 * revisar esa decisión.
 */
interface ContentReviewer
{
    public function review(string $text): ReviewVerdict;
}
