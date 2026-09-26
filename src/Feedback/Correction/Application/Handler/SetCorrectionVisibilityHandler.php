<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Application\Handler;

use LectoresBeta\Feedback\Correction\Application\Command\SetCorrectionVisibility;
use LectoresBeta\Feedback\Correction\Domain\Enum\CorrectionStatus;
use LectoresBeta\Feedback\Correction\Domain\Exception\CorrectionNotFound;
use LectoresBeta\Feedback\Correction\Domain\Repository\CorrectionRepository;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\CorrectionId;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

/**
 * Apartar de la bandeja una corrección recibida, y devolverla
 * (`FEAT-FBK-007`).
 *
 * **Apartar no es borrar, y esa es toda la funcionalidad.** Una corrección
 * entregada no se elimina nunca (`RN-3`): alguien la escribió, cobró por
 * ella y la tiene en «Mis correcciones». Lo que el autor decide es qué sigue
 * viendo él, no qué existe.
 *
 * De ahí las dos reglas que parecen detalles y son el fondo:
 *
 * - **no se le quita a quien la escribió** (`RN-2`). Su lista no cambia, su
 *   contador no baja y lo que cobró no se toca. Si apartar afectara a la otra
 *   parte, sería una forma de castigar una corrección que no gustó;
 * - **se puede deshacer** (`RN-3`), y por eso hay una segunda bandeja. Sin
 *   ella nadie recordaría el identificador de algo apartado hace tres meses,
 *   y «apartar» habría sido «borrar» con otro nombre.
 *
 * Solo el destinatario. Quien la escribió no aparta nada: no es su bandeja.
 */
final readonly class SetCorrectionVisibilityHandler
{
    public function __construct(
        private CorrectionRepository $corrections,
        private TransactionalSession $session,
        private Clock $clock,
    ) {
    }

    public function __invoke(SetCorrectionVisibility $command): string
    {
        try {
            $correction = $this->corrections->ofId(CorrectionId::fromString($command->correctionId));
        } catch (InvalidValue) {
            throw CorrectionNotFound::withId($command->correctionId);
        }

        // No ser el destinatario y no existir responden igual: que exista es
        // información sobre una obra que no es suya.
        if (
            null === $correction
            || CorrectionStatus::SUBMITTED !== $correction->status()
            || $correction->ownerId()->value() !== $command->ownerId
        ) {
            throw CorrectionNotFound::withId($command->correctionId);
        }

        $now = $this->clock->now();

        // Idempotente: apartar lo ya apartado no cambia nada, y la respuesta
        // describe el estado final, no lo que se hizo para llegar a él.
        $this->session->execute(function () use ($correction, $command, $now): void {
            $changed = $command->hidden ? $correction->hide($now) : $correction->show($now);

            if ($changed) {
                $this->corrections->save($correction);
            }
        });

        return $correction->visibility()->value;
    }
}
