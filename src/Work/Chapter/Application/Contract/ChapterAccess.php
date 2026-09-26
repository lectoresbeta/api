<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Chapter\Application\Contract;

/**
 * El veredicto sobre un capítulo concreto y una persona concreta.
 *
 * **Trae la respuesta ya dada, no los datos para darla**, y en eso se
 * diferencia de `CorrectionBrief`, que entrega hechos —modalidad de acceso,
 * si es para adultos— y deja decidir a quien pregunta. La razón es que la
 * regla de lectura es
 * [la más peligrosa del backend](../../Domain/Service/WorkReadPolicy.php) y
 * está escrita una sola vez: reconstruirla en otro contexto es exactamente
 * cómo se filtra una obra inédita.
 *
 * Lleva además `authorId` porque quien pregunta lo necesita a continuación:
 * para aplicar el techo de audiencia del perfil y para saber a quién avisar.
 * No es un dato de más — es lo que evita una segunda llamada.
 *
 * **Ni una palabra del texto.**
 */
final readonly class ChapterAccess
{
    public function __construct(
        public string $chapterId,
        public string $workId,
        public string $authorId,
        public bool $readable,
    ) {
    }
}
